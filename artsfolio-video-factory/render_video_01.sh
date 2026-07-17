#!/bin/bash
set -euo pipefail

FACTORY_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VIDEO_ROOT="$FACTORY_ROOT/video-01"
WORK_ROOT="$FACTORY_ROOT/work/video-01"
RAW_ROOT="$WORK_ROOT/raw"
AUDIO_ROOT="$WORK_ROOT/audio"
OUTPUT_ROOT="$FACTORY_ROOT/output/video-01"
ENV_FILE="$FACTORY_ROOT/.env.video.local"

fail() { printf '[FAIL] %s\n' "$*" >&2; exit 1; }

for command in node python3 ffmpeg ffprobe; do
  command -v "$command" >/dev/null 2>&1 || fail "Missing command: $command"
done

[[ -f "$ENV_FILE" ]] || fail "Missing $ENV_FILE"
set -a
. "$ENV_FILE"
set +a

mkdir -p "$RAW_ROOT" "$AUDIO_ROOT" "$OUTPUT_ROOT"
rm -f "$RAW_ROOT"/* "$AUDIO_ROOT"/* 2>/dev/null || true

SCENES="$VIDEO_ROOT/scenes.json"
DURATIONS="$WORK_ROOT/cue-durations.json"
SRT="$OUTPUT_ROOT/01_admin_orientation_captions.srt"
CONCAT="$AUDIO_ROOT/concat.txt"
VOICE_ONLY="$AUDIO_ROOT/video01-voice-only.m4a"
NARRATION="$AUDIO_ROOT/video01-narration-with-cards.m4a"
RAW="$RAW_ROOT/video01-browser.webm"
FINAL="$OUTPUT_ROOT/ArtsFolio_Training_01_Admin_Orientation.mp4"

printf '[RUN] Generating ElevenLabs narration\n'
node "$VIDEO_ROOT/generate_narration.mjs" "$SCENES" "$AUDIO_ROOT"

python3 - "$SCENES" "$AUDIO_ROOT" "$DURATIONS" "$SRT" "$CONCAT" <<'PY'
import json, pathlib, subprocess, sys

scenes = json.loads(pathlib.Path(sys.argv[1]).read_text())
audio_root = pathlib.Path(sys.argv[2])
duration_path = pathlib.Path(sys.argv[3])
srt_path = pathlib.Path(sys.argv[4])
concat_path = pathlib.Path(sys.argv[5])

durations = {}
srt = []
concat = []
start = 10.0
ordinal = 0

def stamp(seconds):
    total = int(round(seconds * 1000))
    h, total = divmod(total, 3600000)
    m, total = divmod(total, 60000)
    s, ms = divmod(total, 1000)
    return f"{h:02d}:{m:02d}:{s:02d},{ms:03d}"

for scene in scenes:
    for cue in scene["cues"]:
        ordinal += 1
        key = f"{ordinal:03d}-{scene['id']}-{cue['id']}"
        audio = audio_root / f"{key}.mp3"
        if not audio.is_file():
            raise SystemExit(f"[FAIL] Missing {audio}")
        raw_duration = float(subprocess.check_output([
            "ffprobe","-v","error","-show_entries","format=duration",
            "-of","default=noprint_wrappers=1:nokey=1",str(audio)
        ], text=True).strip())
        duration = raw_duration + 0.35
        durations[key] = duration
        end = start + duration
        srt.extend([str(ordinal), f"{stamp(start)} --> {stamp(end)}", cue["narration"], ""])
        concat.append(f"file '{audio}'")
        start = end

duration_path.write_text(json.dumps(durations, indent=2) + "\n")
srt_path.write_text("\n".join(srt))
concat_path.write_text("\n".join(concat) + "\n")
PY

ffmpeg -y -hide_banner -loglevel warning \
  -f concat -safe 0 -i "$CONCAT" \
  -c:a aac -b:a 192k -ar 48000 -ac 2 "$VOICE_ONLY"

ffmpeg -y -hide_banner -loglevel warning \
  -f lavfi -t 10 -i anullsrc=r=48000:cl=stereo \
  -i "$VOICE_ONLY" \
  -f lavfi -t 6 -i anullsrc=r=48000:cl=stereo \
  -filter_complex "[0:a][1:a][2:a]concat=n=3:v=0:a=1[a]" \
  -map "[a]" -c:a aac -b:a 192k "$NARRATION"

printf '[RUN] Recording browser tour\n'
node "$VIDEO_ROOT/record_video_01.mjs"

[[ -s "$RAW" ]] || fail "Browser recording was not created."

printf '[RUN] Rendering QuickTime-compatible MP4\n'
ffmpeg -y -hide_banner -loglevel warning \
  -i "$RAW" -i "$NARRATION" \
  -map 0:v:0 -map 1:a:0 \
  -vf "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=30,format=yuv420p" \
  -c:v libx264 -profile:v high -level:v 4.1 -preset medium -crf 20 \
  -c:a aac -b:a 192k -ar 48000 -ac 2 \
  -movflags +faststart -shortest "$FINAL"

ffmpeg -v error -i "$FINAL" -f null - >/dev/null
ffprobe -v error -show_entries format=duration,size \
  -of default=noprint_wrappers=1 "$FINAL"

printf '[PASS] Video 01 rendered with a ten-second branded opening, page-first narration choreography, whole-sidebar highlighting, and a branded closing card.\n'
printf '[OUTPUT] %s\n' "$FINAL"
