# `Minn\Media\Av`



| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Analyzer`](#analyzer) | final class | 96 | A media file's analysis, keyed as the reference's media reader keys the |
| [`Genres`](#genres) | final class | 15 | The genre names an ID3 tag refers to by number: the ID3v1 list with the |
| [`Id3`](#id3) | final class | 354 | ID3 tags, as the reference's audio reader names what they say (probe |
| [`IsoMedia`](#isomedia) | final class | 361 | MPEG-4 and QuickTime files (MP4, M4A, M4V, MOV) as the reference's media |
| [`Lame`](#lame) | final class | 135 | The LAME tag an encoder writes after the Xing or Info counts, and what |
| [`Mpeg`](#mpeg) | final class | 144 | MPEG audio (MP3, and layers 1 and 2) as the reference's audio reader |
| [`Riff`](#riff) | final class | 289 | RIFF files, WAV audio and AVI video, as the reference's media reader |
| [`Source`](#source) | final class | 70 | A media file read in parts, so a large video is never loaded whole: its |

## Analyzer

`final class Minn\Media\Av\Analyzer` · `public/minn/src/Minn/Media/Av/Analyzer.php`

A media file's analysis, keyed as the reference's media reader keys the
one it hands to the wp_read_audio_metadata and wp_read_video_metadata
filters (probe media-meta): the file's size, format and type, its audio
and video streams, its playing time and bitrate, and its tags. The
format is found by the file's first bytes; a file named .mp3 with none
is still typed as MPEG audio.

- const `ATOMS` = `array (   0 => 'ftyp',   1 => 'moov',   2 => 'mdat',   3 => 'free',   4 => 'wide',   5 => 'skip',   6 => 'pnot', )`

Used by: `Minn\Runtime\AvMetadata`

### static `analyze(string $path): ?array`

The analysis of the file at a path; null when there is no such file.

- `@return array<string, mixed>|null`

### static `playtimeString(int|float $seconds): string`

A playing time as a clock: minutes and seconds, with hours past the first.

Internals: `analyzed()` (private, line 38), `stream()` (private, line 69), `mpeg()` (private, line 92)


## Genres

`final class Minn\Media\Av\Genres` · `public/minn/src/Minn/Media/Av/Genres.php`

The genre names an ID3 tag refers to by number: the ID3v1 list with the
Winamp additions (0 to 191) and a later run from 240. An ID3v2 genre
names an unknown number as an empty string, and 255 as "Unknown"; an
ID3v1 tag names neither (probe media-meta).

- const `LOW` = `'Blues|Classic Rock|Country|Dance|Disco|Funk|Grunge|Hip-Hop|Jazz|Metal|New Age|Oldies|Other|Pop|R&B|Rap|Reggae|Rock|Techno|Industrial|Alternative|Ska|Death Metal|Pranks|Soundtrack|Euro-Techno|Ambient|Trip-Hop|Vocal|Jazz+Funk|Fusion|Trance|Classical|Instrumental|Acid|House|Game|Sound Clip|Gospel|Noise|Alt. Rock|Bass|Soul|Punk|Space|Meditative|Instrumental Pop|Instrumental Rock|Ethnic|Gothic|Darkwave|Techno-Industrial|Electronic|Pop-Folk|Eurodance|Dream|Southern Rock|Comedy|Cult|Gangsta Rap|Top 40|Christian Rap|Pop/Funk|Jungle|Native American|Cabaret|New Wave|Psychedelic|Rave|Showtunes|Trailer|Lo-Fi|Tribal|Acid Punk|Acid Jazz|Polka|Retro|Musical|Rock & Roll|Hard Rock|Folk|Folk/Rock|National Folk|Swing|Fast-Fusion|Bebob|Latin|Revival|Celtic|Bluegrass|Avantgarde|Gothic Rock|Progressive Rock|Psychedelic Rock|Symphonic Rock|Slow Rock|Big Band|Chorus|Easy Listening|Acoustic|Humour|Speech|Chanson|Opera|Chamber Music|Sonata|Symphony|Booty Bass|Primus|Porn Groove|Satire|Slow Jam|Club|Tango|Samba|Folklore|Ballad|Power Ballad|Rhythmic Soul|Freestyle|Duet|Punk Rock|Drum Solo|A Cappella|Euro-House|Dance Hall|Goa|Drum & Bass|Club-House|Hardcore|Terror|Indie|BritPop|Negerpunk|Polsk Punk|Beat|Christian Gangsta Rap|Heavy Metal|Black Metal|Crossover|Contemporary Christian|Christian Rock|Merengue|Salsa|Thrash Metal|Anime|JPop|Synthpop|Abstract|Art Rock|Baroque|Bhangra|Big Beat|Breakbeat|Chillout|Downtempo|Dub|EBM|Eclectic|Electro|Electroclash|Emo|Experimental|Garage|Global|IDM|Illbient|Industro-Goth|Jam Band|Krautrock|Leftfield|Lounge|Math Rock|New Romantic|Nu-Breakz|Post-Punk|Post-Rock|Psytrance|Shoegaze|Space Rock|Trop Rock|World Music|Neoclassical|Audiobook|Audio Theatre|Neue Deutsche Welle|Podcast|Indie-Rock|G-Funk|Dubstep|Garage Rock|Psybient'`
- const `HIGH` = `'Sacred|Northern Europe|Irish & Scottish|Scotland|Ethnic Europe|Enka|Children\'s Song|Japanese Sky|Japanese Heavy Rock|Japanese Doom Rock|Japanese J-POP|Japanese Seiyu|Japanese Ambient Techno|Japanese Moemoe|Japanese Tokusatsu'`

Used by: `Minn\Media\Av\Id3`

### static `name(int $number): ?string`

The name for a number, or null for a number with none.


## Id3

`final class Minn\Media\Av\Id3` · `public/minn/src/Minn/Media/Av/Id3.php`

ID3 tags, as the reference's audio reader names what they say (probe
media-meta): an ID3v2 tag (versions 2.2 to 2.4) at the start of a file
and an ID3v1 tag in its last 128 bytes. Each frame the reader knows
becomes a named list of values in the order the frames come; one it does
not know (a play count, a private frame, a podcast flag) is left out.
ID3v2 text is made UTF-8 whatever its encoding; ID3v1 text is kept as its
bytes.

- const `FRAMES` = `'COM:comment COMM:comment TAL:album TALB:album TBP:bpm TBPM:bpm TCM:composer TCMP:part_of_a_compilation TCO:genre TCOM:composer TCON:genre TCOP:copyright_message TCP:part_of_a_compilation TCR:copyright_message TDA:date TDAT:date TDEN:encoding_time TDLY:playlist_delay TDOR:original_release_time TDRC:recording_time TDRL:release_time TDTG:tagging_time TDY:playlist_delay TEN:encoded_by TENC:encoded_by TEXT:lyricist TFLT:file_type TFT:file_type TIM:time TIME:time TIPL:involved_people_list TIT1:content_group_description TIT2:title TIT3:subtitle TKE:initial_key TKEY:initial_key TLA:language TLAN:language TLE:length TLEN:length TMCL:musician_credits_list TMED:media_type TMOO:mood TMT:media_type TOA:original_artist TOAL:original_album TOF:original_filename TOFN:original_filename TOL:original_lyricist TOLY:original_lyricist TOPE:original_artist TOR:original_year TORY:original_year TOT:original_album TOWN:file_owner TP1:artist TP2:band TP3:conductor TP4:remixer TPA:part_of_a_set TPB:publisher TPE1:artist TPE2:band TPE3:conductor TPE4:remixer TPOS:part_of_a_set TPRO:produced_notice TPUB:publisher TRC:isrc TRCK:track_number TRD:recording_dates TRDA:recording_dates TRK:track_number TRSN:internet_radio_station_name TRSO:internet_radio_station_owner TS2:album_artist_sort_order TSA:album_sort_order TSC:composer_sort_order TSI:size TSIZ:size TSO2:album_artist_sort_order TSOA:album_sort_order TSOC:composer_sort_order TSOP:performer_sort_order TSOT:title_sort_order TSP:performer_sort_order TSRC:isrc TSS:encoder_settings TSSE:encoder_settings TSST:set_subtitle TST:title_sort_order TT1:content_group_description TT2:title TT3:subtitle TXT:lyricist TXX:text TXXX:text TYE:year TYER:year ULT:unsynchronised_lyric USLT:unsynchronised_lyric WAF:url_file WAR:url_artist WAS:url_source WCM:commercial_information WCOM:commercial_information WCOP:copyright WCP:copyright WOAF:url_file WOAR:url_artist WOAS:url_source WORS:url_station WPAY:url_payment WPB:url_publisher WPUB:url_publisher WXX:url_user WXXX:url_user'`
- const `PICTURES` = `array (   0 => 'APIC',   1 => 'PIC', )`
- const `DESCRIBED` = `array (   0 => 'COMM',   1 => 'COM',   2 => 'USLT',   3 => 'ULT', )`
- const `OWN_TEXT` = `array (   0 => 'TXXX',   1 => 'TXX',   2 => 'WXXX',   3 => 'WXX', )`

Used by: `Minn\Media\Av\Analyzer`

### static `v2(Minn\Media\Av\Source $source): ?array`

The ID3v2 tag at the start of a file: where the bytes after it begin,
its version, its named values and its pictures; null with no tag.

- `@return array{end: int, version: int, comments: array<string, list<string>>, pictures: list<array<string, mixed>>}|null`

### static `v1(Minn\Media\Av\Source $source): ?array`

The ID3v1 tag in a file's last 128 bytes: the title, artist, album,
year and comment (kept when empty), the track of a version 1.1 tag and
the named genre; null with no tag.

- `@return array<string, list<string|int>>|null`

Internals: `frames()` (private, line 91), `frameData()` (private, line 145), `values()` (private, line 171), `afterDescription()` (private, line 195), `terminator()` (private, line 202), `decoded()` (private, line 223), `utf16()` (private, line 240), `terminatorLength()` (private, line 250), `attachedPicture()` (private, line 261), `olderPicture()` (private, line 282), `pictureBytes()` (private, line 290), `genres()` (private, line 305), `numbered()` (private, line 338), `syncsafe()` (private, line 347), `names()` (private, line 357)


## IsoMedia

`final class Minn\Media\Av\IsoMedia` · `public/minn/src/Minn/Media/Av/IsoMedia.php`

MPEG-4 and QuickTime files (MP4, M4A, M4V, MOV) as the reference's media
reader measures them (probe media-meta). The movie header gives the
playing time and the time it was made; each track's first sample entry
gives its codec. Only AAC ("mp4a") is read as audio, and it alone makes a
file audio/mp4 (or video/mp4 beside a video track); anything else is
video/quicktime. A video entry with a vendor is read the QuickTime way
(its compressor's name, lossless false); one without, the ISO way (a
named codec, and "mp4" as the format for the codecs it knows). Only H.264
and MPEG-4 video have the bitrate the audio leaves.

- const `VIDEO` = `'avc1=H.264/MPEG-4 AVC|cvid=Cinepak|dvc =DVC-NTSC|dvcp=DVC-PAL|gif =GIF|h263=H263|hvc1=H.265/HEVC|jpeg=JPEG|kpcd=PhotoCD|mjpa=Motion JPEG-A|mjpb=Motion JPEG-B|mp4v=|png =PNG|raw =RAW|rle =|rpza=Video|smc =Graphics|SVQ1=Sorenson Video 3|SVQ3=Sorenson Video 3 (Apple Quicktime 5)|tiff=TIFF|v216=|v308=|v408=|v410=|yuv2=ComponentVideo'` — Video codecs the ISO way names, and makes a file "mp4" for (code => name, empty for none).
- const `DATAFORMATS` = `array (   'mp4v' => 'mpeg4',   '3IV1' => '3ivx',   '3IV2' => '3ivx',   '3ivx' => '3ivx',   'DIVX' => 'divx',   'divx' => 'divx',   'DIV3' => 'divx',   'div3' => 'divx',   'XVID' => 'xvid',   'xvid' => 'xvid', )`
- const `CONTAINERS` = `array (   0 => 'moov',   1 => 'trak',   2 => 'mdia',   3 => 'minf',   4 => 'stbl',   5 => 'udta',   6 => 'ilst',   7 => 'covr', )`
- const `EPOCH` = `2082844800` — Seconds between 1904, where the movie's clock starts, and 1970.
- const `MOOV_LIMIT` = `67108864`

Used by: `Minn\Media\Av\Analyzer`

### static `detects(string $head): bool`

Whether a file's first bytes are an MPEG-4 or QuickTime box.

### static `read(Minn\Media\Av\Source $source): array`

The file's analysis: format, type, playing time and bitrate, the
audio and video streams, the movie header for its creation time and
the cover.

- `@return array<string, mixed>`

Internals: `movie()` (private, line 114), `boxHeader()` (private, line 141), `children()` (private, line 156), `find()` (private, line 178), `movieHeader()` (private, line 203), `track()` (private, line 224), `aac()` (private, line 261), `averageBitrate()` (private, line 282), `afterLength()` (private, line 299), `videoEntry()` (private, line 314), `cover()` (private, line 336), `ordered()` (private, line 359), `videoCodecs()` (private, line 366)


## Lame

`final class Minn\Media\Av\Lame` · `public/minn/src/Minn/Media/Av/Lame.php`

The LAME tag an encoder writes after the Xing or Info counts, and what
the reference's audio reader makes of it (probe media-meta): the bitrate
method names the bitrate mode, and the encoder options are guessed from
the preset number, or failing that from the mode, then the minimum
bitrate, gapless encoding, a lowpass other than the preset's own and a
resampled rate follow.

- const `MODES` = `array (   0 => 'unk',   1 => 'cbr',   2 => 'abr',   3 => 'vbr',   4 => 'vbr',   5 => 'vbr',   6 => 'vbr',   8 => 'cbr',   9 => 'abr',   15 => 'res', )`
- const `UNNAMED` = `array (   0 => 16,   1 => 24,   2 => 40,   3 => 56,   4 => 112,   5 => 128,   6 => 160,   7 => 192,   8 => 256, )` — Preset numbers from 8 to 319 that name no preset, so the mode's guess stands.
- const `NAMED` = `array (   320 => 'insane',   410 => '-V9',   420 => '-V8',   430 => 'radio',   440 => '-V6',   450 => 'portable',   460 => 'medium',   470 => '--r3mix',   480 => 'standard',   490 => '-V1',   500 => 'extreme',   1000 => '--r3mix',   1001 => '--alt-preset standard',   1002 => '--alt-preset extreme',   1003 => '--alt-preset insane',   1004 => '--alt-preset fast standard',   1005 => '--alt-preset fast extreme',   1006 => '--alt-preset medium',   1007 => '--alt-preset fast medium',   1010 => '--preset portable',   1015 => '--preset radio', )`
- const `LOWPASS` = `array (   320 =>    array (     0 => 20500,   ),   450 =>    array (   ),   460 =>    array (     0 => 18000,   ),   470 =>    array (     0 => 18000,     1 => 19500,   ),   480 =>    array (     0 => 19000,   ),   500 =>    array (     0 => 19500,   ),   1000 =>    array (     0 => 18000,     1 => 19500,   ),   1001 =>    array (     0 => 19000,   ),   1002 =>    array (     0 => 19500,   ),   1003 =>    array (     0 => 20500,   ),   1004 =>    array (     0 => 19000,   ),   1005 =>    array (     0 => 19500,   ),   1006 =>    array (     0 => 18000,   ),   1007 =>    array (     0 => 18000,   ),   1010 =>    array (   ), )` — Each preset's own lowpass, which its options leave unsaid; a preset named here may add a different one.

Used by: `Minn\Media\Av\Mpeg`

### static `fields(string $tag): array`

The tag's fields from its 36 bytes: the encoder, the bitrate method,
lowpass, encoding flags, the minimum or average bitrate, the source's
sample rate class and the preset number. An encoder that is not LAME
leaves only its name.

- `@return array<string, mixed>`

### static `mode(int $method): string`

The bitrate mode a LAME tag's method names ("cbr", "abr", "vbr"; "unk", "res" or empty for the rest).

### static `options(?array $info, int $sampleRate, int|float $bitrate, int $headerBitrate): string`

The options a file was most likely encoded with, from its LAME tag
(or "CBR" and the bitrate without an Xing frame, "VBR" with one from
another encoder).

- `@param array<string, mixed>|null $info the Xing or Info frame and its LAME tag`

Internals: `presetName()` (private, line 103), `guess()` (private, line 122), `resampled()` (private, line 140)


## Mpeg

`final class Minn\Media\Av\Mpeg` · `public/minn/src/Minn/Media/Av/Mpeg.php`

MPEG audio (MP3, and layers 1 and 2) as the reference's audio reader
measures it (probe media-meta). The first frame gives the version,
layer, sample rate and channels. An Xing or Info frame (written by LAME
and most encoders) gives the frame and byte counts: a variable bitrate is
the bytes over the frames' duration, and the playing time is the audio's
bytes less one average frame, at that bitrate. Without one the bitrate is
the first frame's and the playing time is every byte at it.

- const `BITRATES` = `array (   '3-1' => '32 64 96 128 160 192 224 256 288 320 352 384 416 448',   '3-2' => '32 48 56 64 80 96 112 128 160 192 224 256 320 384',   '3-3' => '32 40 48 56 64 80 96 112 128 160 192 224 256 320',   '2-1' => '32 48 56 64 80 96 112 128 144 160 176 192 224 256',   '2-2' => '8 16 24 32 40 48 56 64 80 96 112 128 144 160', )`
- const `SAMPLE_RATES` = `array (   3 =>    array (     0 => 44100,     1 => 48000,     2 => 32000,   ),   2 =>    array (     0 => 22050,     1 => 24000,     2 => 16000,   ),   0 =>    array (     0 => 11025,     1 => 12000,     2 => 8000,   ), )`
- const `CHANNEL_MODES` = `array (   0 => 'stereo',   1 => 'joint stereo',   2 => 'dual channel',   3 => 'mono', )`
- const `SCAN` = `131072`

Used by: `Minn\Media\Av\Analyzer`, `Minn\Media\Av\Riff`

### static `read(Minn\Media\Av\Source $source, int $start, int $end): ?array`

The audio from $start (after any ID3v2 tag) to $end (before any
ID3v1 tag): the stream's description, its bitrate and playing time,
and where its frames lie; null when no frame is found.

- `@return array{audio: array<string, mixed>, bitrate: int|float, playtime_seconds: int|float, avdataoffset: int, avdataend: int}|null`

### static `frameHeader(int $word): ?array`

A frame header's fields; null for one that is not valid MPEG audio:
no sync, a reserved version or layer, a free or bad bitrate, or a
reserved sample rate.

- `@return array{version: int, layer: int, bitrate: int, sampleRate: int, padding: int, mode: int, channels: int, samples: int, length: int}|null`

Internals: `firstFrame()` (private, line 80), `infoFrame()` (private, line 130)


## Riff

`final class Minn\Media\Av\Riff` · `public/minn/src/Minn/Media/Av/Riff.php`

RIFF files, WAV audio and AVI video, as the reference's media reader
measures them (probe media-meta). A WAV's format chunk describes its
stream (the codec named by its format tag, lossless only for PCM) and
its data chunk times it. An AVI's headers give the frames and their
rate, each stream's format, and the movie list that holds the data; its
video has the bitrate the audio's leaves. MP3 audio in an AVI (or in a
WAV that states its rate) is described from its first frame.

- const `FORMAT_TAGS` = `'0=Microsoft Unknown Wave Format|1=Pulse Code Modulation (PCM)|2=Microsoft ADPCM|3=IEEE Float|4=Compaq Computer VSELP|5=IBM CVSD|6=Microsoft A-Law|7=Microsoft mu-Law|8=Microsoft DTS|10=OKI ADPCM|11=Intel DVI/IMA ADPCM|12=Videologic MediaSpace ADPCM|13=Sierra Semiconductor ADPCM|14=Antex Electronics G.723 ADPCM|15=DSP Solutions DigiSTD|16=DSP Solutions DigiFIX|17=Dialogic OKI ADPCM|18=MediaVision ADPCM|19=Hewlett-Packard CU|20=Yamaha ADPCM|21=Speech Compression Sonarc|22=DSP Group TrueSpeech|23=Echo Speech EchoSC1|24=Audiofile AF36|25=Audio Processing Technology APTX|26=AudioFile AF10|27=Prosody 1612|28=LRC|30=Dolby AC2|31=Microsoft GSM 6.10|32=MSNAudio|33=Antex Electronics ADPCME|34=Control Resources VQLPC|35=DSP Solutions DigiREAL|36=DSP Solutions DigiADPCM|37=Control Resources CR10|38=Natural MicroSystems VBXADPCM|39=Crystal Semiconductor IMA ADPCM|3a=EchoSC3|3b=Rockwell ADPCM|3c=Rockwell Digit LK|3d=Xebec|40=Antex Electronics G.721 ADPCM|41=G.728 CELP|42=MSG723|50=MPEG Layer-2 or Layer-1|52=RT24|53=PAC|55=MPEG Layer-3|59=Lucent G.723|60=Cirrus|61=ESPCM|62=Voxware|63=Canopus Atrac|64=G.726 ADPCM|65=G.722 ADPCM|66=DSAT|67=DSAT Display|69=Voxware Byte Aligned|70=Voxware AC8|71=Voxware AC10|72=Voxware AC16|73=Voxware AC20|74=Voxware MetaVoice|75=Voxware MetaSound|76=Voxware RT29HW|77=Voxware VR12|78=Voxware VR18|79=Voxware TQ40|80=Softsound|81=Voxware TQ60|82=MSRT24|83=G.729A|84=MVI MV12|85=DF G.726|86=DF GSM610|88=ISIAudio|89=Onlive|91=SBC24|92=Dolby AC3 SPDIF|93=MediaSonic G.723|94=Aculab PLC    Prosody 8kbps|97=ZyXEL ADPCM|98=Philips LPCBB|99=Packed|ff=AAC|100=Rhetorex ADPCM|101=IBM mu-law|102=IBM A-law|103=IBM AVC Adaptive Differential Pulse Code Modulation (ADPCM)|111=Vivo G.723|112=Vivo Siren|123=Digital G.723|125=Sanyo LD ADPCM|130=Sipro Lab Telecom ACELP NET|131=Sipro Lab Telecom ACELP 4800|132=Sipro Lab Telecom ACELP 8V3|133=Sipro Lab Telecom G.729|134=Sipro Lab Telecom G.729A|135=Sipro Lab Telecom Kelvin|140=Windows Media Video V8|150=Qualcomm PureVoice|151=Qualcomm HalfRate|155=Ring Zero Systems TUB GSM|160=Microsoft Audio 1|161=Windows Media Audio V7 / V8 / V9|162=Windows Media Audio Professional V9|163=Windows Media Audio Lossless V9|200=Creative Labs ADPCM|202=Creative Labs Fastspeech8|203=Creative Labs Fastspeech10|210=UHER Informatic GmbH ADPCM|220=Quarterdeck|230=I-link Worldwide VC|240=Aureal RAW Sport|250=Interactive Products HSX|251=Interactive Products RPELP|260=Consistent Software CS2|270=Sony SCX|300=Fujitsu FM Towns Snd|400=BTV Digital|401=Intel Music Coder|450=QDesign Music|680=VME VMPCM|681=AT&T Labs TPC|8ae=ClearJump LiteWave|1000=Olivetti GSM|1001=Olivetti ADPCM|1002=Olivetti CELP|1003=Olivetti SBC|1004=Olivetti OPR|1100=Lernout & Hauspie Codec (0x1100)|1101=Lernout & Hauspie CELP Codec (0x1101)|1102=Lernout & Hauspie SBC Codec (0x1102)|1103=Lernout & Hauspie SBC Codec (0x1103)|1104=Lernout & Hauspie SBC Codec (0x1104)|1400=Norris|1401=AT&T ISIAudio|1500=Soundspace Music Compression|181c=VoxWare RT24 Speech|1fc4=NCT Soft ALF2CD (www.nctsoft.com)|2000=Dolby AC3|2001=Dolby DTS|2002=WAVE_FORMAT_14_4|2003=WAVE_FORMAT_28_8|2004=WAVE_FORMAT_COOK|2005=WAVE_FORMAT_DNET|674f=Ogg Vorbis 1|6750=Ogg Vorbis 2|6751=Ogg Vorbis 3|676f=Ogg Vorbis 1+|6770=Ogg Vorbis 2+|6771=Ogg Vorbis 3+|7a21=GSM-AMR (CBR, no SID)|7a22=GSM-AMR (VBR, including SID)|fffe=WAVE_FORMAT_EXTENSIBLE|ffff=WAVE_FORMAT_DEVELOPMENT'`
- const `VIDEO_CODECS` = `'3IV1=3ivx MPEG-4 v1|3IV2=3ivx MPEG-4 v2|3IVX=3ivx MPEG-4|AASC=Autodesk Animator|ABYR=Kensington ?ABYR?|AEMI=Array Microsystems VideoONE MPEG1-I Capture|AFLC=Autodesk Animator FLC|AFLI=Autodesk Animator FLI|AMPG=Array Microsystems VideoONE MPEG|ANIM=Intel RDX (ANIM)|AP41=AngelPotion Definitive|ASV1=Asus Video v1|ASV2=Asus Video v2|ASVX=Asus Video 2.0 (audio)|AUR2=AuraVision Aura 2 Codec - YUV 4:2:2|AURA=AuraVision Aura 1 Codec - YUV 4:1:1|AVDJ=Independent JPEG Group\\\'s codec (AVDJ)|AVRN=Independent JPEG Group\\\'s codec (AVRN)|AYUV=4:4:4 YUV (AYUV)|AZPR=Quicktime Apple Video (AZPR)|BGR =Raw RGB32|BINK=RAD Game Tools Bink Video|BLZ0=Blizzard DivX MPEG-4|BT20=Conexant Prosumer Video|BTCV=Conexant Composite Video Codec|BW10=Data Translation Broadway MPEG Capture|CC12=Intel YUV12|CDVC=Canopus DV|CFCC=Digital Processing Systems DPS Perception|CGDI=Microsoft Office 97 Camcorder Video|CHAM=Winnov Caviara Champagne|CJPG=Creative WebCam JPEG|CLJR=Cirrus Logic YUV 4:1:1|CMYK=Common Data Format in Printing (Colorgraph)|CPLA=Weitek 4:2:0 YUV Planar|CRAM=Microsoft Video 1 (CRAM)|CVID=Radius Cinepak|CWLT=Microsoft Color WLT DIB|CYUV=Creative Labs YUV|CYUY=ATI YUV|D261=H.261|D263=H.263|DIV3=DivX ;-) MPEG-4 v3.x Low-Motion|DIV4=DivX ;-) MPEG-4 v3.x Fast-Motion|DIV5=DivX MPEG-4 v5.x|DIVX=DivX MPEG-4 v4 (OpenDivX / Project Mayo)|DMB1=Matrox Rainbow Runner hardware MJPEG|DMB2=Paradigm MJPEG|DSVD=?DSVD?|DUCK=Duck TrueMotion 1.0|DV25=Matrox DVCPRO codec|DV50=Matrox DVCPRO50 codec|DVAN=?DVAN?|DVE2=InSoft DVE-2 Videoconferencing|DVHD=IEC Standard DV 1125 lines @ 30fps / 1250 lines @ 25fps|DVSD=IEC 61834 and SMPTE 314M DVC/DV Video|DVSL=IEC Standard DV compressed in SD (SDL)|DVX1=Lucent DVX1000SP Video Decoder|DVX2=Lucent DVX2000S Video Decoder|DVX3=Lucent DVX3000S Video Decoder|DX50=DivX v5|DXT1=Microsoft DirectX Compressed Texture (DXT1)|DXT2=Microsoft DirectX Compressed Texture (DXT2)|DXT3=Microsoft DirectX Compressed Texture (DXT3)|DXT4=Microsoft DirectX Compressed Texture (DXT4)|DXT5=Microsoft DirectX Compressed Texture (DXT5)|DXTC=Microsoft DirectX Compressed Texture (DXTC)|EKQ0=Elsa ?EKQ0?|ELK0=Elsa ?ELK0?|EM2V=Etymonix MPEG-2 I-frame (www.etymonix.com)|ESCP=Eidos Escape|ETV1=eTreppid Video ETV1|ETV2=eTreppid Video ETV2|ETVC=eTreppid Video ETVC|FLJP=D-Vision Field Encoded Motion JPEG|FLV1=Sorenson Spark|FPS1=FRAPS v1|FRWA=SoftLab-Nsk Forward Motion JPEG w/ alpha channel|FRWD=SoftLab-Nsk Forward Motion JPEG|FVF1=Iterated Systems Fractal Video Frame|GLZW=Motion LZW (gabest@freemail.hu)|GPEG=Motion JPEG (gabest@freemail.hu)|GWLT=Microsoft Greyscale WLT DIB|H260=Intel ITU H.260 Videoconferencing|H261=Intel ITU H.261 Videoconferencing|H262=Intel ITU H.262 Videoconferencing|H263=Intel ITU H.263 Videoconferencing|H264=Intel ITU H.264 Videoconferencing|H265=Intel ITU H.265 Videoconferencing|H266=Intel ITU H.266 Videoconferencing|H267=Intel ITU H.267 Videoconferencing|H268=Intel ITU H.268 Videoconferencing|H269=Intel ITU H.269 Videoconferencing|HFYU=Huffman Lossless Codec|HMCR=Rendition Motion Compensation Format (HMCR)|HMRR=Rendition Motion Compensation Format (HMRR)|I263=FFmpeg I263 decoder|I420=Intel Indeo 4|IAN =Intel Indeo 4 (RDX)|ICLB=InSoft CellB Videoconferencing|IGOR=Power DVD|IJPG=Intergraph JPEG|ILVC=Intel Layered Video|ILVR=ITU-T H.263+|IPDV=I-O Data Device Giga AVI DV Codec|IR21=Intel Indeo 2.1|IRAW=Intel YUV Uncompressed|IUYV=Interlaced version of UYVY (www.leadtools.com)|IV30=Intel Indeo 3.0|IV31=Intel Indeo 3.1|IV32=Ligos Indeo 3.2|IV33=Ligos Indeo 3.3|IV34=Ligos Indeo 3.4|IV35=Ligos Indeo 3.5|IV36=Ligos Indeo 3.6|IV37=Ligos Indeo 3.7|IV38=Ligos Indeo 3.8|IV39=Ligos Indeo 3.9|IV40=Ligos Indeo Interactive 4.0|IV41=Ligos Indeo Interactive 4.1|IV42=Ligos Indeo Interactive 4.2|IV43=Ligos Indeo Interactive 4.3|IV44=Ligos Indeo Interactive 4.4|IV45=Ligos Indeo Interactive 4.5|IV46=Ligos Indeo Interactive 4.6|IV47=Ligos Indeo Interactive 4.7|IV48=Ligos Indeo Interactive 4.8|IV49=Ligos Indeo Interactive 4.9|IV50=Ligos Indeo Interactive 5.0|JBYR=Kensington ?JBYR?|JPEG=Still Image JPEG DIB|JPGL=Pegasus Lossless Motion JPEG|KMVC=Team17 Software Karl Morton\\\'s Video Codec|LEAD=LEAD Video Codec|LSVM=Vianet Lighting Strike Vmail (Streaming) (www.vianet.com)|M261=Microsoft H.261|M263=Microsoft H.263|M4S2=Microsoft Fully Compliant MPEG-4 v2 simple profile (M4S2)|MC12=ATI Motion Compensation Format (MC12)|MCAM=ATI Motion Compensation Format (MCAM)|MJ2C=Morgan Multimedia Motion JPEG2000|MJPG=Microsoft Motion JPEG DIB|MMES=Matrox MPEG-2 I-frame|MP42=Microsoft MPEG-4 (low-motion)|MP43=Microsoft MPEG-4 (fast-motion)|MP4S=Microsoft MPEG-4 (MP4S)|MP4V=FFmpeg MPEG-4|MPEG=Chromatic Research MPEG-1 Video I-Frame|MPG4=Microsoft MPEG-4 Video High Speed Compressor|MPGI=Sigma Designs MPEG|MRCA=FAST Multimedia Martin Regen Codec|MRLE=Microsoft Run Length Encoding|MSVC=Microsoft Video 1|MSZH=LCL (Lossless Codec Library) (www.geocities.co.jp/Playtown-Denei/2837/LRC.htm)|MTX1=Matrox ?MTX1?|MTX2=Matrox ?MTX2?|MTX3=Matrox ?MTX3?|MTX4=Matrox ?MTX4?|MTX5=Matrox ?MTX5?|MTX6=Matrox ?MTX6?|MTX7=Matrox ?MTX7?|MTX8=Matrox ?MTX8?|MTX9=Matrox ?MTX9?|MWV1=Aware Motion Wavelets|NTN1=Nogatech Video Compression 1|NVS0=nVidia GeForce Texture (NVS0)|NVS1=nVidia GeForce Texture (NVS1)|NVS2=nVidia GeForce Texture (NVS2)|NVS3=nVidia GeForce Texture (NVS3)|NVS4=nVidia GeForce Texture (NVS4)|NVS5=nVidia GeForce Texture (NVS5)|NVT0=nVidia GeForce Texture (NVT0)|NVT1=nVidia GeForce Texture (NVT1)|NVT2=nVidia GeForce Texture (NVT2)|NVT3=nVidia GeForce Texture (NVT3)|NVT4=nVidia GeForce Texture (NVT4)|NVT5=nVidia GeForce Texture (NVT5)|PDVC=I-O Data Device Digital Video Capture DV codec|PGVV=Radius Video Vision|PHMO=IBM Photomotion|PIM1=MPEG Realtime (Pinnacle Cards)|PIM2=Pegasus Imaging ?PIM2?|PIMJ=Pegasus Imaging Lossless JPEG|PIXL=MiroXL, Pinnacle PCTV|PVEZ=Horizons Technology PowerEZ|PVMM=PacketVideo Corporation MPEG-4|PVW2=Pegasus Imaging Wavelet Compression|QPEG=Q-Team QPEG 1.0|RGBT=Raw RGB w/ Transparency|RLE =Microsoft Run Length Encoder|RLE4=Run Length Encoded (4bpp, 16-color)|RLE8=Run Length Encoded (8bpp, 256-color)|RMP4=REALmagic MPEG-4 (unauthorized XVID copy) (www.sigmadesigns.com)|RPZA=Quicktime Apple Video (RPZA)|RT21=Intel Indeo RealTime Video 2.1|RV20=RealVideo G2|RV30=RealVideo 8|RV40=RealVideo 9|SDCC=Sun Communication Digital Camera Codec|SFMC=CrystalNet Surface Fitting Method|SMSC=Radius SMSC|SMSD=Radius SMSD|SP54=Logitech Sunplus Sp54 Codec for Mustek GSmart Mini 2|SPIG=Radius Spigot|SQZ2=Microsoft VXTreme Video Codec V2|STVA=ST Microelectronics CMOS Imager Data (Bayer)|STVB=ST Microelectronics CMOS Imager Data (Nudged Bayer)|STVC=ST Microelectronics CMOS Imager Data (Bunched)|STVX=ST Microelectronics CMOS Imager Data (Extended CODEC Data Format)|STVY=ST Microelectronics CMOS Imager Data (Extended CODEC Data Format with Correction Data)|SV10=Sorenson Video R1|SVQ1=Sorenson Video|SVQ3=Sorenson Video 3 (Apple Quicktime 5)|TLMS=TeraLogic Motion Intraframe Codec (TLMS)|TLST=TeraLogic Motion Intraframe Codec (TLST)|TM20=Duck TrueMotion 2.0|TM2X=Duck TrueMotion 2X|TMIC=TeraLogic Motion Intraframe Codec (TMIC)|TMOT=Horizons Technology TrueMotion S|TR20=Duck TrueMotion RealTime 2.0|TSCC=TechSmith Screen Capture Codec|TV10=Tecomac Low-Bit Rate Codec|TVJP=Pinnacle/Truevision Targa 2000 board (TVJP)|TVMJ=Pinnacle/Truevision Targa 2000 board (TVMJ)|TY0N=Tecomac Low-Bit Rate Codec (www.tecomac.com)|TY2C=Trident Decompression Driver|TY2N=Trident ?TY2N?|U263=UB Video H.263/H.263+/H.263++ Decoder|UCOD=eMajix.com ClearVideo|ULTI=IBM Ultimotion|V422=Vitec Multimedia 24-bit YUV 4:2:2 Format|V655=Vitec Multimedia 16-bit YUV 4:2:2 Format|VCR1=ATI Video Codec 1|VCR2=ATI Video Codec 2|VCR3=ATI VCR 3.0|VCR4=ATI VCR 4.0|VCR5=ATI VCR 5.0|VCR6=ATI VCR 6.0|VCR7=ATI VCR 7.0|VCR8=ATI VCR 8.0|VCR9=ATI VCR 9.0|VDCT=Vitec Multimedia Video Maker Pro DIB|VDOM=VDOnet VDOWave|VDTZ=Darim Vison VideoTizer YUV|VGPX=Alaris VideoGramPiX|VIDS=Vitec Multimedia YUV 4:2:2 CCIR 601 for V422|VIFP=VFAPI Reader Codec (www.yks.ne.jp/~hori/)|VIVO=Vivo H.263 v2.00|VIXL=Miro/Pinnacle Video XL|VLV1=VideoLogic/PURE Digital Videologic Capture|VP30=On2 VP3.0|VP31=On2 VP3.1|VP50=On2 VP5|VP60=On2 VP6|VP70=On2 VP7|VP80=On2 VP8|VQC2=Vector-quantised codec 2 (research) http://eprints.ecs.soton.ac.uk/archive/00001310/01/VTC97-js.pdf)|VX1K=Lucent VX1000S Video Codec|VX2K=Lucent VX2000S Video Codec|VXSP=Lucent VX1000SP Video Codec|VYU9=ATI YUV (VYU9)|VYUY=ATI YUV (VYUY)|WBVC=Winbond W9960|WHAM=Microsoft Video 1 (WHAM)|WINX=Winnov Software Compression|WJPG=AverMedia Winbond JPEG|WMV1=Windows Media Video V7|WMV2=Windows Media Video V8|WMV3=Windows Media Video V9|WNV1=Winnov Hardware Compression|XLV0=NetXL Video Decoder|XMPG=Xing MPEG (I-Frame only)|XVID=XviD MPEG-4 (www.xvid.org)|XXAN=?XXAN?|Y411=YUV 4:1:1 Packed|Y41P=Brooktree PC1 YUV 4:1:1 Packed|Y8  =Grayscale video|YC12=Intel YUV 12 codec|YUV8=Winnov Caviar YUV8|YUV9=Intel YUV9|YUVP=Extended PAL format YUV palette (www.riff.org)|YUY2=Uncompressed YUV 4:2:2|YUYV=Canopus YUV|YV12=YVU12 Planar|ZLIB=Lossless Codec Library zlib compression (www.geocities.co.jp/Playtown-Denei/2837/LRC.htm)|ZPEG=Metheus Video Zipper|cvid=Radius Cinepak|divx=DivX MPEG-4|dvsd=IEC 61834 and SMPTE 314M DVC/DV Video|i263=Intel ITU H.263 Videoconferencing (i263)|m4s2=Microsoft Fully Compliant MPEG-4 v2 simple profile (m4s2)|mp4s=Microsoft MPEG-4 (mp4s)|qpeq=Q-Team QPEG 1.1|rv20=RealVideo G2|rv30=RealVideo 8|s422=Tekram VideoCap C210 YUV 4:2:2|smsv=WorldConnect Wavelet Video|tmot=Horizons TrueMotion Video Compression|vivo=Vivo H.263|x263=Xirlink H.263'`

Used by: `Minn\Media\Av\Analyzer`

### static `detects(string $head): bool`

Whether a file's first bytes are a RIFF WAVE or AVI file.

### static `read(Minn\Media\Av\Source $source): array`

The file's analysis: format, type, timing and its streams.

- `@return array<string, mixed>`

Internals: `wave()` (private, line 39), `avi()` (private, line 69), `chunks()` (private, line 129), `stream()` (private, line 147), `waveFormat()` (private, line 168), `audio()` (private, line 184), `mpegAudio()` (private, line 213), `firstAudioFrame()` (private, line 233), `video()` (private, line 255), `formatName()` (private, line 278), `videoCodecs()` (private, line 292)


## Source

`final class Minn\Media\Av\Source` · `public/minn/src/Minn/Media/Av/Source.php`

A media file read in parts, so a large video is never loaded whole: its
size, a run of bytes at an offset, and the integers the containers write.
The file's first block is kept, since every reader starts there.

- const `HEAD` = `262144`

Used by: `Minn\Media\Av\Analyzer`, `Minn\Media\Av\Id3`, `Minn\Media\Av\IsoMedia`, `Minn\Media\Av\Lame`, `Minn\Media\Av\Mpeg`, `Minn\Media\Av\Riff`

- readonly `int $size`
- readonly `string $path`

### static `open(string $path): ?self`

The file opened for reading, or null when it cannot be.

### `bytes(int $offset, int $length): string`

Up to $length bytes from $offset (fewer at the end of the file).

### `bigEndian(int $offset, int $width): int`

A big-endian unsigned integer of $width bytes (1 to 8) at $offset; 0 past the end.

### `littleEndian(int $offset, int $width): int`

A little-endian unsigned integer of $width bytes (1 to 8) at $offset; 0 past the end.

### static `bigEndianValue(string $bytes): int`

The unsigned integer a run of bytes spells, most significant first.

### static `littleEndianValue(string $bytes): int`

The unsigned integer a run of bytes spells, least significant first.

### `close(): void`

The file let go.

