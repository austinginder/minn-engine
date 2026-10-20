#!/usr/bin/env bash
# Builds tests/fixtures/media/: short synthetic tones and test patterns in the
# audio and video formats a site accepts, with and without tags and cover art,
# for probe media-meta (wp_read_audio_metadata, wp_read_video_metadata). The
# files are committed; this script records how they were made (ffmpeg, lame,
# sox). Rebuilding changes their bytes, so recapture the fixture after.
set -euo pipefail
OUT="$(cd "$(dirname "$0")/../fixtures/media" && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
cd "$WORK"
F="ffmpeg -hide_banner -loglevel error -y"

# Cover art: a small JPEG and a small PNG.
$F -f lavfi -i "color=c=0xC04030:s=32x32:d=1" -frames:v 1 cover.jpg
$F -f lavfi -i "testsrc2=s=24x20:d=1" -frames:v 1 cover.png

tone() { sox -n -r "$1" -c "$2" -b 16 "$3" synth "$4" sine 440 sine 660 gain -6; }

# MP3: constant and variable bitrate, mono, untagged, long.
tone 44100 2 t1.wav 2.6
lame --quiet -b 128 -m s --tt "Tone One" --ta "Minn Test Band" --tl "Probe Album" --ty 2021 --tg "Electronic" --tn 2/9 --tc "a comment" --ti cover.jpg t1.wav "$OUT/a01-cbr-tags.mp3"
tone 44100 2 t2.wav 3.2
lame --quiet -V 2 -m j --id3v2-only --tt "Café Ñandú" --ta "Ünïcödé Ärtist" --tl "Ålbum" --ty 1999 --tg "Jazz" --tn 3/12 t2.wav "$OUT/a02-vbr-v2.mp3"
tone 22050 1 t3.wav 1.4
lame --quiet -b 64 -m m --id3v1-only --tt "Mono V1" --ta "Old Tagger" --tl "V1 Album" --ty 1987 --tg "Rock" --tn 7 t3.wav "$OUT/a03-mono-v1.mp3"
tone 48000 2 t4.wav 2.0
$F -i t4.wav -c:a libmp3lame -b:a 192k -write_xing 0 -id3v2_version 0 -map_metadata -1 "$OUT/a04-plain.mp3"
tone 8000 1 t5.wav 61.5
lame --quiet -b 8 -m m --tt "Long Tone" t5.wav "$OUT/a05-long.mp3"

# MPEG-4 audio: AAC with tags and cover, Apple Lossless.
tone 44100 2 t6.wav 2.3
$F -i t6.wav -i cover.png -map 0 -map 1 -c:a aac -b:a 96k -c:v png -disposition:v attached_pic \
  -metadata title="AAC Tone" -metadata artist="M4A Artist" -metadata album="M4A Album" -metadata date=2018 \
  -metadata genre="Ambient" -metadata track=4/10 -metadata comment="m4a comment" "$OUT/a06-aac.m4a"
tone 44100 1 t7.wav 1.2
$F -i t7.wav -c:a alac "$OUT/a07-alac.m4a"

# WAV: 16-bit stereo; 24-bit mono with INFO tags.
tone 44100 2 "$OUT/a08-pcm16.wav" 0.6
tone 48000 1 t9.wav 0.4
$F -i t9.wav -c:a pcm_s24le -metadata title="Wave Title" -metadata artist="Wave Artist" -metadata genre="Field" "$OUT/a09-pcm24-info.wav"

# Ogg: Vorbis (sox) and Opus; FLAC with tags and cover; raw AAC.
tone 44100 2 t10.wav 2.2
sox t10.wav --comment "TITLE=Vorbis Tone" "$OUT/a10-vorbis.ogg"
tone 48000 2 t11.wav 1.8
$F -i t11.wav -c:a libopus -b:a 64k -metadata title="Opus Tone" -metadata artist="Opus Artist" -metadata album="Opus Album" "$OUT/a11-opus.ogg"
tone 44100 2 t12.wav 1.6
$F -i t12.wav -i cover.jpg -map 0 -map 1 -c:a flac -c:v mjpeg -disposition:v attached_pic \
  -metadata title="Flac Tone" -metadata artist="Flac Artist" -metadata album="Flac Album" -metadata date=2020 -metadata genre="Classical" -metadata track=1 "$OUT/a12-flac.flac"
tone 44100 2 t13.wav 1.3
$F -i t13.wav -c:a aac -b:a 128k -f adts "$OUT/a13-adts.aac"

# Video.
V="testsrc2=r=25"
$F -f lavfi -i "$V:s=160x90:d=2.4" -f lavfi -i "sine=f=440:d=2.4" -c:v libx264 -preset veryfast -pix_fmt yuv420p -c:a aac -b:a 64k \
  -metadata title="Video Title" -metadata artist="Video Artist" -metadata creation_time=2024-05-06T07:08:09Z "$OUT/v01-h264-aac.mp4"
$F -f lavfi -i "$V:s=96x64:d=1" -c:v libx264 -preset veryfast -pix_fmt yuv420p -movflags +faststart -map_metadata -1 "$OUT/v02-h264-silent.mp4"
$F -f lavfi -i "$V:s=128x72:d=1.6" -f lavfi -i "sine=f=330:d=1.6" -c:v libx264 -preset veryfast -pix_fmt yuv420p -c:a aac -b:a 48k \
  -metadata creation_time=2023-01-02T03:04:05Z "$OUT/v03-quicktime.mov"
$F -f lavfi -i "$V:s=120x80:d=1.5" -f lavfi -i "sine=f=550:d=1.5" -c:v libvpx-vp9 -b:v 100k -c:a libopus -b:a 48k -metadata title="WebM Title" "$OUT/v04-vp9-opus.webm"
$F -f lavfi -i "$V:s=64x48:d=1.2" -c:v libvpx -b:v 50k "$OUT/v05-vp8-silent.webm"
$F -f lavfi -i "$V:s=112x64:d=1.1" -f lavfi -i "sine=f=220:d=1.1" -c:v libx264 -preset veryfast -pix_fmt yuv420p -c:a aac -b:a 48k -f ipod "$OUT/v06-ipod.m4v"
$F -f lavfi -i "$V:s=80x60:d=1.2" -f lavfi -i "sine=f=440:d=1.2" -c:v mpeg4 -c:a libmp3lame -b:a 64k "$OUT/v07-mpeg4.avi"
$F -f lavfi -i "$V:s=80x60:d=1.0" -f lavfi -i "sine=f=440:d=1.0:sample_rate=44100" -c:v flv -c:a libmp3lame -b:a 64k "$OUT/v08-flash.flv"
$F -f lavfi -i "$V:s=64x64:d=1.3" -f lavfi -i "sine=f=440:d=1.3" -c:v libx264 -preset veryfast -pix_fmt yuv420p -c:a flac -metadata title="MKV Title" "$OUT/v09-matroska.mkv"
$F -f lavfi -i "$V:s=96x48:d=1.0" -c:v libx264 -preset veryfast -pix_fmt yuv420p -metadata:s:v:0 rotate=90 "$OUT/v10-rotated.mp4"
$F -f lavfi -i "$V:s=80x64:d=1.0" -f lavfi -i "sine=f=440:d=1.0" -c:v wmv2 -c:a wmav2 -b:a 64k -metadata title="WMV Title" "$OUT/v11-windows.wmv"

# More codecs in their containers: float WAV, AC-3 5.1 and MP3 in Matroska, PCM in AVI.
tone 44100 1 t14.wav 0.3
$F -i t14.wav -c:a pcm_f32le "$OUT/a14-float.wav"
$F -f lavfi -i "$V:s=64x48:d=1.0" -f lavfi -i "sine=f=440:d=1.0" -af "aformat=channel_layouts=5.1" -c:v libx264 -preset veryfast -pix_fmt yuv420p -c:a ac3 "$OUT/v12-ac3.mkv"
$F -f lavfi -i "$V:s=64x48:d=1.0" -f lavfi -i "sine=f=440:d=1.0" -c:v libx264 -preset veryfast -pix_fmt yuv420p -c:a libmp3lame -b:a 64k "$OUT/v13-mp3.mkv"
$F -f lavfi -i "$V:s=80x60:d=1.0" -f lavfi -i "sine=f=440:d=1.0" -c:v mjpeg -c:a pcm_s16le "$OUT/v14-mjpeg-pcm.avi"

# Codecs inside other containers' readers: MP3 (constant and variable) and AC-3 (stereo, and 5.1 as an
# extensible format) in WAV; MP2 and Vorbis as Matroska audio; H.264 in Flash video at a High and a
# Baseline profile. v17-h264-ep.flv is a 160x90 Baseline FLV with its parameter set replaced by one holding an
# emulation-prevention byte (built by hand once, kept as committed).
tone 44100 2 t15.wav 2.0
$F -i t15.wav -c:a libmp3lame -b:a 128k -f wav "$OUT/a15-mp3-cbr.wav"
$F -i t15.wav -c:a libmp3lame -q:a 2 -f wav "$OUT/a16-mp3-vbr.wav"
tone 48000 2 t17.wav 1.7
$F -i t17.wav -c:a ac3 -b:a 192k -f wav "$OUT/a17-ac3.wav"
$F -f lavfi -i "sine=f=440:d=2" -ac 6 -c:a ac3 -b:a 384k -f wav "$OUT/a18-ac3-51.wav"
$F -f lavfi -i "sine=f=440:d=1.6" -c:a mp2 -b:a 192k "$OUT/a19-mp2.mka"
$F -f lavfi -i "sine=f=440:d=1.6" -ac 2 -c:a vorbis -strict -2 "$OUT/a20-vorbis.mka"
$F -f lavfi -i "$V:s=160x90:d=1.6" -f lavfi -i "sine=f=440:d=1.6" -c:v libx264 -profile:v high -preset veryfast -pix_fmt yuv420p -c:a aac -b:a 64k "$OUT/v15-h264-high.flv"
$F -f lavfi -i "$V:s=100x50:d=0.6" -c:v libx264 -profile:v baseline -preset veryfast -pix_fmt yuv420p -an "$OUT/v16-h264-baseline.flv"
ls -la "$OUT"
