#!/usr/bin/env python3
from __future__ import annotations

import json
import pathlib
import subprocess
import sys

scenes_path = pathlib.Path(sys.argv[1])
audio_root = pathlib.Path(sys.argv[2])
starts_path = pathlib.Path(sys.argv[3])
video_path = pathlib.Path(sys.argv[4])
output_path = pathlib.Path(sys.argv[5])
srt_path = pathlib.Path(sys.argv[6])

scenes = json.loads(scenes_path.read_text(encoding="utf-8"))
starts = json.loads(starts_path.read_text(encoding="utf-8"))

video_duration = float(subprocess.check_output([
    "ffprobe", "-v", "error",
    "-show_entries", "format=duration",
    "-of", "default=noprint_wrappers=1:nokey=1",
    str(video_path),
], text=True).strip())

inputs: list[str] = []
filters: list[str] = []
mix_labels: list[str] = []
srt: list[str] = []
ordinal = 0


def stamp(seconds: float) -> str:
    total = int(round(seconds * 1000))
    hours, total = divmod(total, 3_600_000)
    minutes, total = divmod(total, 60_000)
    secs, millis = divmod(total, 1000)
    return f"{hours:02d}:{minutes:02d}:{secs:02d},{millis:03d}"


for scene in scenes:
    for cue in scene["cues"]:
        ordinal += 1
        key = f"{ordinal:03d}-{scene['id']}-{cue['id']}"
        audio = audio_root / f"{key}.mp3"

        if not audio.is_file():
            raise SystemExit(f"[FAIL] Missing narration audio: {audio}")
        if key not in starts:
            raise SystemExit(f"[FAIL] Missing recorded cue timestamp: {key}")

        start = float(starts[key])
        duration = float(subprocess.check_output([
            "ffprobe", "-v", "error",
            "-show_entries", "format=duration",
            "-of", "default=noprint_wrappers=1:nokey=1",
            str(audio),
        ], text=True).strip())
        end = start + duration
        delay_ms = max(0, int(round(start * 1000)))

        inputs.extend(["-i", str(audio)])
        input_index = ordinal
        label = f"a{ordinal}"
        filters.append(
            f"[{input_index}:a]"
            f"aresample=48000,"
            f"adelay={delay_ms}|{delay_ms}"
            f"[{label}]"
        )
        mix_labels.append(f"[{label}]")
        srt.extend([
            str(ordinal),
            f"{stamp(start)} --> {stamp(end)}",
            cue["narration"],
            "",
        ])

filters.append(
    "".join(mix_labels) +
    f"amix=inputs={len(mix_labels)}:duration=longest:normalize=0,"
    f"apad=whole_dur={video_duration}[mixed]"
)

command = [
    "ffmpeg", "-y", "-hide_banner", "-loglevel", "warning",
    "-f", "lavfi", "-t", str(video_duration),
    "-i", "anullsrc=r=48000:cl=stereo",
    *inputs,
    "-filter_complex", ";".join(filters),
    "-map", "[mixed]",
    "-c:a", "aac",
    "-b:a", "192k",
    "-ar", "48000",
    "-ac", "2",
    str(output_path),
]

subprocess.run(command, check=True)
srt_path.write_text("\n".join(srt), encoding="utf-8")
print(f"[PASS] Narration aligned to {len(mix_labels)} recorded cue timestamps.")
