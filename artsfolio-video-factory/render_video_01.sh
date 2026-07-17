#!/bin/bash
set -euo pipefail

FACTORY_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VIDEO_ROOT="${FACTORY_ROOT}/video-01"
WORK_ROOT="${FACTORY_ROOT}/work/video-01"
RAW_ROOT="${WORK_ROOT}/raw"
AUDIO_ROOT="${WORK_ROOT}/audio"
OUTPUT_ROOT="${FACTORY_ROOT}/output/video-01"
ENV_FILE="${FACTORY_ROOT}/.env.video.local"

fail() {
    printf '[FAIL] %s\n' "$*" >&2
    exit 1
}

for command in node ffmpeg ffprobe; do
    command -v "$command" >/dev/null 2>&1 || fail "Missing required command: $command"
done

[[ -f "$ENV_FILE" ]] || fail "Missing $ENV_FILE"
[[ -f "${VIDEO_ROOT}/scenes.json" ]] || fail "Missing scenes.json"
[[ -f "${VIDEO_ROOT}/record_video_01.mjs" ]] || fail "Missing recorder"
[[ -f "${VIDEO_ROOT}/generate_narration.mjs" ]] || fail "Missing ElevenLabs helper"

set -a
. "$ENV_FILE"
set +a

mkdir -p "$RAW_ROOT" "$AUDIO_ROOT" "$OUTPUT_ROOT"
rm -f "$RAW_ROOT"/* "$AUDIO_ROOT"/* 2>/dev/null || true

SCENE_JSON="${VIDEO_ROOT}/scenes.json"
DURATION_JSON="${WORK_ROOT}/scene-durations.json"
SRT_PATH="${OUTPUT_ROOT}/01_admin_orientation_captions.srt"
NARRATION_M4A="${AUDIO_ROOT}/video01-narration.m4a"
RAW_VIDEO="${RAW_ROOT}/video01-browser.webm"
FINAL_MP4="${OUTPUT_ROOT}/ArtsFolio_Training_01_Admin_Orientation.mp4"
COMPAT_MP4="${OUTPUT_ROOT}/ArtsFolio_Training_01_Admin_Orientation_compatible.mp4"

TTS_PROVIDER="${AF_VIDEO_TTS_PROVIDER:-elevenlabs}"

if [[ "$TTS_PROVIDER" != "elevenlabs" ]]; then
    fail "This renderer currently expects AF_VIDEO_TTS_PROVIDER=elevenlabs"
fi

printf '[RUN] Generating ElevenLabs narration\n'
node "${VIDEO_ROOT}/generate_narration.mjs" "$SCENE_JSON" "$AUDIO_ROOT"

python3 - "$SCENE_JSON" "$AUDIO_ROOT" "$DURATION_JSON" "$SRT_PATH" <<'PY'
import json
import pathlib
import subprocess
import sys

scenes = json.loads(pathlib.Path(sys.argv[1]).read_text(encoding="utf-8"))
audio_root = pathlib.Path(sys.argv[2])
duration_path = pathlib.Path(sys.argv[3])
srt_path = pathlib.Path(sys.argv[4])

durations = {}
start = 0.0
srt = []

def stamp(seconds):
    milliseconds = int(round(seconds * 1000))
    hours, milliseconds = divmod(milliseconds, 3600000)
    minutes, milliseconds = divmod(milliseconds, 60000)
    secs, milliseconds = divmod(milliseconds, 1000)
    return f"{hours:02d}:{minutes:02d}:{secs:02d},{milliseconds:03d}"

for index, scene in enumerate(scenes, start=1):
    audio_file = audio_root / f"{index:02d}-{scene['id']}.mp3"
    if not audio_file.is_file():
        raise SystemExit(f"[FAIL] Missing narration audio: {audio_file}")

    duration = float(subprocess.check_output([
        "ffprobe", "-v", "error",
        "-show_entries", "format=duration",
        "-of", "default=noprint_wrappers=1:nokey=1",
        str(audio_file),
    ], text=True).strip()) + 0.45

    durations[scene["id"]] = duration
    end = start + duration
    srt.extend([
        str(index),
        f"{stamp(start)} --> {stamp(end)}",
        scene["narration"],
        "",
    ])
    start = end

duration_path.write_text(json.dumps(durations, indent=2) + "\n", encoding="utf-8")
srt_path.write_text("\n".join(srt), encoding="utf-8")
PY

CONCAT_AUDIO="${AUDIO_ROOT}/concat.txt"
: > "$CONCAT_AUDIO"

find "$AUDIO_ROOT" -maxdepth 1 -name '*.mp3' -print | sort | while IFS= read -r file; do
    printf "file '%s'\n" "$file" >> "$CONCAT_AUDIO"
done

ffmpeg -y -hide_banner -loglevel warning \
    -f concat -safe 0 -i "$CONCAT_AUDIO" \
    -c:a aac -b:a 192k -ar 48000 -ac 2 \
    "$NARRATION_M4A"

printf '[RUN] Recording browser tour\n'
node "${VIDEO_ROOT}/record_video_01.mjs"

[[ -s "$RAW_VIDEO" ]] || fail "Missing raw browser recording: $RAW_VIDEO"

printf '[RUN] Rendering QuickTime-compatible MP4\n'
ffmpeg -y -hide_banner -loglevel warning \
    -i "$RAW_VIDEO" \
    -i "$NARRATION_M4A" \
    -map 0:v:0 \
    -map 1:a:0 \
    -vf "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=30,format=yuv420p" \
    -c:v libx264 \
    -profile:v high \
    -level:v 4.1 \
    -preset medium \
    -crf 20 \
    -c:a aac \
    -b:a 192k \
    -ar 48000 \
    -ac 2 \
    -movflags +faststart \
    -shortest \
    "$COMPAT_MP4"

cp -f "$COMPAT_MP4" "$FINAL_MP4"

printf '[RUN] Verifying final MP4\n'
ffprobe -v error \
    -show_entries format=duration,size \
    -of default=noprint_wrappers=1 \
    "$FINAL_MP4"

ffmpeg -v error -i "$FINAL_MP4" -f null - >/dev/null

printf '[PASS] Video 01 rendered with ElevenLabs narration.\n'
printf '[OUTPUT] %s\n' "$FINAL_MP4"
printf '[OUTPUT] %s\n' "$SRT_PATH"
