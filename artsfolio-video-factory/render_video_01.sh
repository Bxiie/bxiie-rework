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
rm -f "$RAW_ROOT"/* "$AUDIO_ROOT"/* \
  "$WORK_ROOT/cue-starts.json" \
  "$WORK_ROOT/video01-recording-error.txt" 2>/dev/null || true

SCENES="$VIDEO_ROOT/scenes.json"
DURATIONS="$WORK_ROOT/cue-durations.json"
STARTS="$WORK_ROOT/cue-starts.json"
SRT="$OUTPUT_ROOT/01_admin_orientation_captions.srt"
ALIGNED_AUDIO="$AUDIO_ROOT/video01-aligned-narration.m4a"
RAW="$RAW_ROOT/video01-browser.webm"
FINAL="$OUTPUT_ROOT/ArtsFolio_Training_01_Admin_Orientation.mp4"

printf '[RUN] Generating ElevenLabs narration at speed %s\n' \
  "${ELEVENLABS_SPEED:-0.88}"
node "$VIDEO_ROOT/generate_narration.mjs" "$SCENES" "$AUDIO_ROOT"

python3 - "$SCENES" "$AUDIO_ROOT" "$DURATIONS" <<'PY'
import json
import pathlib
import subprocess
import sys

scenes = json.loads(pathlib.Path(sys.argv[1]).read_text(encoding="utf-8"))
audio_root = pathlib.Path(sys.argv[2])
duration_path = pathlib.Path(sys.argv[3])
durations = {}
ordinal = 0

for scene in scenes:
    for cue in scene["cues"]:
        ordinal += 1
        key = f"{ordinal:03d}-{scene['id']}-{cue['id']}"
        audio = audio_root / f"{key}.mp3"
        if not audio.is_file():
            raise SystemExit(f"[FAIL] Missing narration audio: {audio}")
        duration = float(subprocess.check_output([
            "ffprobe", "-v", "error",
            "-show_entries", "format=duration",
            "-of", "default=noprint_wrappers=1:nokey=1",
            str(audio),
        ], text=True).strip())
        durations[key] = duration + 0.35

duration_path.write_text(json.dumps(durations, indent=2) + "\n")
PY

printf '[RUN] Recording browser actions and actual cue timestamps\n'
if ! node "$VIDEO_ROOT/record_video_01.mjs"; then
  if [[ -f "$WORK_ROOT/video01-recording-error.txt" ]]; then
    cat "$WORK_ROOT/video01-recording-error.txt" >&2
  fi
  fail "Browser recording aborted. No MP4 was produced."
fi

[[ -s "$RAW" ]] || fail "Browser recording was not created."
[[ -s "$STARTS" ]] || fail "Cue timestamp file was not created."

printf '[RUN] Aligning narration to recorded page-ready timestamps\n'
python3 "$VIDEO_ROOT/build_aligned_audio.py" \
  "$SCENES" "$AUDIO_ROOT" "$STARTS" "$RAW" "$ALIGNED_AUDIO" "$SRT"

printf '[RUN] Rendering QuickTime-compatible MP4\n'
ffmpeg -y -hide_banner -loglevel warning \
  -i "$RAW" -i "$ALIGNED_AUDIO" \
  -map 0:v:0 -map 1:a:0 \
  -vf "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=30,format=yuv420p" \
  -c:v libx264 -profile:v high -level:v 4.1 -preset medium -crf 20 \
  -c:a aac -b:a 192k -ar 48000 -ac 2 \
  -movflags +faststart -shortest "$FINAL"

ffmpeg -v error -i "$FINAL" -f null - >/dev/null
ffprobe -v error -show_entries format=duration,size \
  -of default=noprint_wrappers=1 "$FINAL"

printf '[PASS] Video 01 rendered from actual browser cue timestamps.\n'
printf '[OUTPUT] %s\n' "$FINAL"
