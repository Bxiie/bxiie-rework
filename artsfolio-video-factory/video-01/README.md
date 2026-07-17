# ArtsFolio Training Video 01 Factory

Run:

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio/artsfolio-video-factory
./render_video_01.sh
```

Outputs are written to:

```text
output/video-01/
```

The login credentials are stored in `.env.video.local`, which is mode `600` and
listed in `.gitignore`. Change or remove that file after production.

To watch the browser while recording:

```bash
AF_VIDEO_HEADLESS=false ./render_video_01.sh
```

To choose another macOS voice:

```bash
AF_VIDEO_VOICE=Alex ./render_video_01.sh
```

Environment values placed before the command override the local env file only
when exported after loading; for routine changes, edit `.env.video.local`.

The main MP4 contains a selectable English subtitle track. When the installed
FFmpeg build supports the `subtitles` filter, the factory also creates a version
with captions burned into the picture.
