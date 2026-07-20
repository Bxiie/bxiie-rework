#!/bin/bash
set -euo pipefail
F="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; V="$F/video-04"; W="$F/work/video-04"; R="$W/raw"; A="$W/audio"; O="$F/output/video-04"; set -a; . "$F/.env.video.local"; set +a
mkdir -p "$R" "$A" "$O"; rm -f "$R"/* "$A"/* "$W/cue-starts.json" 2>/dev/null || true
fail(){ echo "[FAIL] $*" >&2; exit 1; }
echo "[RUN] Complete browser choreography preflight"; echo "[INFO] No video recording and no ElevenLabs calls occur in this stage."
AF_VIDEO_PREFLIGHT_ONLY=true node "$V/record_video_04.mjs" || fail "Browser preflight reported issues. ElevenLabs was not called."
echo "[PASS] Preflight passed. Starting ElevenLabs narration."
node "$V/generate_narration.mjs" "$V/scenes.json" "$A"
python3 - "$V/scenes.json" "$A" "$W/cue-durations.json" <<'PY'
import json,pathlib,subprocess,sys
s=json.loads(pathlib.Path(sys.argv[1]).read_text());r=pathlib.Path(sys.argv[2]);o=pathlib.Path(sys.argv[3]);d={};n=0
for x in s:
 for c in x["cues"]:
  n+=1;k=f"{n:03d}-{x['id']}-{c['id']}";p=r/f"{k}.mp3";q=float(subprocess.check_output(["ffprobe","-v","error","-show_entries","format=duration","-of","default=noprint_wrappers=1:nokey=1",str(p)],text=True));d[k]=q+.35
o.write_text(json.dumps(d,indent=2)+"\n")
PY
AF_VIDEO_PREFLIGHT_ONLY=false node "$V/record_video_04.mjs"
python3 "$V/build_aligned_audio.py" "$V/scenes.json" "$A" "$W/cue-starts.json" "$R/video04-browser.webm" "$A/video04.m4a" "$O/04_events_public_history_captions.srt"
ffmpeg -y -hide_banner -loglevel warning -i "$R/video04-browser.webm" -i "$A/video04.m4a" -map 0:v:0 -map 1:a:0 -vf "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=30,format=yuv420p" -c:v libx264 -crf 20 -c:a aac -b:a 192k -movflags +faststart -shortest "$O/ArtsFolio_Training_04_Events_and_Public_History.mp4"
echo "[PASS] Video 04 rendered."
