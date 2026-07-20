#!/bin/bash
set -euo pipefail
FACTORY_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VIDEO_ROOT="$FACTORY_ROOT/video-02"; WORK_ROOT="$FACTORY_ROOT/work/video-02"; RAW_ROOT="$WORK_ROOT/raw"; AUDIO_ROOT="$WORK_ROOT/audio"; OUTPUT_ROOT="$FACTORY_ROOT/output/video-02"; ENV_FILE="$FACTORY_ROOT/.env.video.local"
fail(){ printf '[FAIL] %s\n' "$*" >&2; exit 1; }
for c in node python3 ffmpeg ffprobe; do command -v "$c" >/dev/null 2>&1 || fail "Missing command: $c"; done
set -a; . "$ENV_FILE"; set +a
mkdir -p "$RAW_ROOT" "$AUDIO_ROOT" "$OUTPUT_ROOT"
rm -f "$RAW_ROOT"/* "$AUDIO_ROOT"/* "$WORK_ROOT/cue-starts.json" "$WORK_ROOT/video02-recording-error.txt" 2>/dev/null || true
SCENES="$VIDEO_ROOT/scenes.json"; DURATIONS="$WORK_ROOT/cue-durations.json"; STARTS="$WORK_ROOT/cue-starts.json"; SRT="$OUTPUT_ROOT/02_branding_content_captions.srt"; AUDIO="$AUDIO_ROOT/video02-aligned-narration.m4a"; RAW="$RAW_ROOT/video02-browser.webm"; FINAL="$OUTPUT_ROOT/ArtsFolio_Training_02_Site_Identity_Branding_and_Content.mp4"
node "$VIDEO_ROOT/generate_narration.mjs" "$SCENES" "$AUDIO_ROOT"
python3 - "$SCENES" "$AUDIO_ROOT" "$DURATIONS" <<'PY'
import json,pathlib,subprocess,sys
scenes=json.loads(pathlib.Path(sys.argv[1]).read_text()); root=pathlib.Path(sys.argv[2]); out=pathlib.Path(sys.argv[3]); d={}; n=0
for scene in scenes:
  for cue in scene["cues"]:
    n+=1; key=f"{n:03d}-{scene['id']}-{cue['id']}"; audio=root/f"{key}.mp3"
    duration=float(subprocess.check_output(["ffprobe","-v","error","-show_entries","format=duration","-of","default=noprint_wrappers=1:nokey=1",str(audio)],text=True).strip())
    d[key]=duration+0.35
out.write_text(json.dumps(d,indent=2)+"\n")
PY
if ! node "$VIDEO_ROOT/record_video_02.mjs"; then [[ -f "$WORK_ROOT/video02-recording-error.txt" ]] && cat "$WORK_ROOT/video02-recording-error.txt" >&2; fail "Video 02 recording aborted."; fi
python3 "$VIDEO_ROOT/build_aligned_audio.py" "$SCENES" "$AUDIO_ROOT" "$STARTS" "$RAW" "$AUDIO" "$SRT"
ffmpeg -y -hide_banner -loglevel warning -i "$RAW" -i "$AUDIO" -map 0:v:0 -map 1:a:0 -vf "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=30,format=yuv420p" -c:v libx264 -profile:v high -level:v 4.1 -preset medium -crf 20 -c:a aac -b:a 192k -ar 48000 -ac 2 -movflags +faststart -shortest "$FINAL"
ffmpeg -v error -i "$FINAL" -f null - >/dev/null
printf '[PASS] Video 02 rendered.\n[OUTPUT] %s\n' "$FINAL"
