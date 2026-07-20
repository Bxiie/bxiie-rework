#!/bin/bash
set -euo pipefail

F="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
V="$F/video-05"
W="$F/work/video-05"
R="$W/raw"
A="$W/audio"
O="$F/output/video-05"

fail() {
  printf '[FAIL] %s\n' "$*" >&2
  exit 1
}

set -a
. "$F/.env.video.local"
set +a

mkdir -p "$R" "$A" "$O"
rm -f "$R"/* "$A"/* "$W/cue-starts.json" 2>/dev/null || true

echo "[RUN] Complete browser choreography preflight"
echo "[INFO] No video recording and no ElevenLabs calls occur in this stage."

AF_VIDEO_PREFLIGHT_ONLY=true \
node "$V/record_video_05.mjs" \
  || fail "Browser preflight reported issues. ElevenLabs was not called."

echo "[PASS] Preflight passed. Starting ElevenLabs narration."

node "$V/generate_narration.mjs" "$V/scenes.json" "$A"

python3 - "$V/scenes.json" "$A" "$W/cue-durations.json" <<'PY'
import json
import pathlib
import subprocess
import sys

scenes = json.loads(pathlib.Path(sys.argv[1]).read_text())
audio_root = pathlib.Path(sys.argv[2])
output = pathlib.Path(sys.argv[3])
durations = {}
ordinal = 0

for scene in scenes:
    for cue in scene["cues"]:
        ordinal += 1
        key = f"{ordinal:03d}-{scene['id']}-{cue['id']}"
        audio = audio_root / f"{key}.mp3"
        seconds = float(subprocess.check_output([
            "ffprobe", "-v", "error",
            "-show_entries", "format=duration",
            "-of", "default=noprint_wrappers=1:nokey=1",
            str(audio),
        ], text=True).strip())
        durations[key] = seconds + 0.35

output.write_text(json.dumps(durations, indent=2) + "\n")
PY

AF_VIDEO_PREFLIGHT_ONLY=false \
node "$V/record_video_05.mjs"

python3 "$V/build_aligned_audio.py" \
  "$V/scenes.json" \
  "$A" \
  "$W/cue-starts.json" \
  "$R/video05-browser.webm" \
  "$A/video05.m4a" \
  "$O/05_messages_email_signups_captions.srt"

ffmpeg -y -hide_banner -loglevel warning \
  -i "$R/video05-browser.webm" \
  -i "$A/video05.m4a" \
  -map 0:v:0 -map 1:a:0 \
  -vf "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=30,format=yuv420p" \
  -c:v libx264 -crf 20 \
  -c:a aac -b:a 192k \
  -movflags +faststart -shortest \
  "$O/ArtsFolio_Training_05_Messages_and_Email_Signups.mp4"

echo "[PASS] Video 05 rendered."
