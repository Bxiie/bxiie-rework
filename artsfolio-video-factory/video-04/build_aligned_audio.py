#!/usr/bin/env python3
import json,pathlib,subprocess,sys
scenes=json.loads(pathlib.Path(sys.argv[1]).read_text())
audio_root=pathlib.Path(sys.argv[2]); starts=json.loads(pathlib.Path(sys.argv[3]).read_text())
video=pathlib.Path(sys.argv[4]); output=pathlib.Path(sys.argv[5]); srt_path=pathlib.Path(sys.argv[6])
video_duration=float(subprocess.check_output(["ffprobe","-v","error","-show_entries","format=duration","-of","default=noprint_wrappers=1:nokey=1",str(video)],text=True).strip())
inputs=[]; filters=[]; labels=[]; srt=[]; ordinal=0
def stamp(sec):
  total=int(round(sec*1000)); h,total=divmod(total,3600000); m,total=divmod(total,60000); s,ms=divmod(total,1000)
  return f"{h:02d}:{m:02d}:{s:02d},{ms:03d}"
for scene in scenes:
  for cue in scene["cues"]:
    ordinal+=1; key=f"{ordinal:03d}-{scene['id']}-{cue['id']}"; audio=audio_root/f"{key}.mp3"
    start=float(starts[key]); duration=float(subprocess.check_output(["ffprobe","-v","error","-show_entries","format=duration","-of","default=noprint_wrappers=1:nokey=1",str(audio)],text=True).strip()); end=start+duration
    inputs += ["-i",str(audio)]; label=f"a{ordinal}"; delay=max(0,int(round(start*1000)))
    filters.append(f"[{ordinal}:a]aresample=48000,adelay={delay}|{delay}[{label}]"); labels.append(f"[{label}]")
    srt += [str(ordinal),f"{stamp(start)} --> {stamp(end)}",cue["narration"],""]
filters.append("".join(labels)+f"amix=inputs={len(labels)}:duration=longest:normalize=0,apad=whole_dur={video_duration}[mixed]")
subprocess.run(["ffmpeg","-y","-hide_banner","-loglevel","warning","-f","lavfi","-t",str(video_duration),"-i","anullsrc=r=48000:cl=stereo",*inputs,"-filter_complex",";".join(filters),"-map","[mixed]","-c:a","aac","-b:a","192k","-ar","48000","-ac","2",str(output)],check=True)
srt_path.write_text("\n".join(srt))
