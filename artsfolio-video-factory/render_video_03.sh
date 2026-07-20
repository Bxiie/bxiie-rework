#!/bin/bash
set -euo pipefail
FACTORY_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VIDEO_ROOT="$FACTORY_ROOT/video-03"; WORK_ROOT="$FACTORY_ROOT/work/video-03"; RAW_ROOT="$WORK_ROOT/raw"; AUDIO_ROOT="$WORK_ROOT/audio"; OUTPUT_ROOT="$FACTORY_ROOT/output/video-03"; ENV_FILE="$FACTORY_ROOT/.env.video.local"
fail(){ printf '[FAIL] %s\n' "$*" >&2; exit 1; }
for c in node python3 ffmpeg ffprobe; do command -v "$c" >/dev/null 2>&1 || fail "Missing command: $c"; done
set -a; . "$ENV_FILE"; set +a
mkdir -p "$RAW_ROOT" "$AUDIO_ROOT" "$OUTPUT_ROOT"
rm -f "$RAW_ROOT"/* "$AUDIO_ROOT"/* "$WORK_ROOT/cue-starts.json" "$WORK_ROOT/video03-recording-error.txt" 2>/dev/null || true
SCENES="$VIDEO_ROOT/scenes.json"; DURATIONS="$WORK_ROOT/cue-durations.json"; STARTS="$WORK_ROOT/cue-starts.json"; SRT="$OUTPUT_ROOT/03_artwork_portfolio_captions.srt"; AUDIO="$AUDIO_ROOT/video03-aligned-narration.m4a"; RAW="$RAW_ROOT/video03-browser.webm"; FINAL="$OUTPUT_ROOT/ArtsFolio_Training_03_Artwork_and_Portfolio_Management.mp4"
printf '[RUN] Complete browser choreography preflight\n'
printf '[INFO] No video recording and no ElevenLabs calls occur in this stage.\n'

if ! AF_VIDEO_PREFLIGHT_ONLY=true \
  AF_VIDEO_STRICT_TARGETS=true \
  node "$VIDEO_ROOT/record_video_03.mjs"; then
  fail "Browser preflight reported issues. ElevenLabs was not called."
fi

printf '[PASS] Full browser choreography passed. Starting ElevenLabs narration.\n'
node "$VIDEO_ROOT/generate_narration.mjs" "$SCENES" "$AUDIO_ROOT"
python3 - "$SCENES" "$AUDIO_ROOT" "$DURATIONS" <<'PY'
import json,pathlib,subprocess,sys
scenes=json.loads(pathlib.Path(sys.argv[1]).read_text());root=pathlib.Path(sys.argv[2]);out=pathlib.Path(sys.argv[3]);d={};n=0
for scene in scenes:
  for cue in scene["cues"]:
    n+=1;key=f"{n:03d}-{scene['id']}-{cue['id']}";audio=root/f"{key}.mp3"
    sec=float(subprocess.check_output(["ffprobe","-v","error","-show_entries","format=duration","-of","default=noprint_wrappers=1:nokey=1",str(audio)],text=True).strip());d[key]=sec+0.35
out.write_text(json.dumps(d,indent=2)+"\n")
PY
if ! AF_VIDEO_PREFLIGHT_ONLY=false \
  AF_VIDEO_STRICT_TARGETS=true \
  node "$VIDEO_ROOT/record_video_03.mjs"; then [[ -f "$WORK_ROOT/video03-recording-error.txt" ]] && cat "$WORK_ROOT/video03-recording-error.txt" >&2; fail "Video 03 recording aborted."; fi
python3 "$VIDEO_ROOT/build_aligned_audio.py" "$SCENES" "$AUDIO_ROOT" "$STARTS" "$RAW" "$AUDIO" "$SRT"
ffmpeg -y -hide_banner -loglevel warning -i "$RAW" -i "$AUDIO" -map 0:v:0 -map 1:a:0 -vf "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=30,format=yuv420p" -c:v libx264 -profile:v high -level:v 4.1 -preset medium -crf 20 -c:a aac -b:a 192k -ar 48000 -ac 2 -movflags +faststart -shortest "$FINAL"
ffmpeg -v error -i "$FINAL" -f null - >/dev/null
printf '[PASS] Video 03 rendered.\n[OUTPUT] %s\n' "$FINAL"
