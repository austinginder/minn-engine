<?php

declare(strict_types=1);

namespace Minn\Media\Av;

/**
 * RIFF files, WAV audio and AVI video, as the reference's media reader
 * measures them (probe media-meta). A WAV's format chunk describes its
 * stream (the codec named by its format tag, lossless only for PCM) and
 * its data chunk times it. An AVI's headers give the frames and their
 * rate, each stream's format, and the movie list that holds the data; its
 * video has the bitrate the audio's leaves. MP3 audio in an AVI (or in a
 * WAV that states its rate) is described from its first frame.
 */
final class Riff
{
    private const FORMAT_TAGS = '0=Microsoft Unknown Wave Format|1=Pulse Code Modulation (PCM)|2=Microsoft ADPCM|3=IEEE Float|4=Compaq Computer VSELP|5=IBM CVSD|6=Microsoft A-Law|7=Microsoft mu-Law|8=Microsoft DTS|10=OKI ADPCM|11=Intel DVI/IMA ADPCM|12=Videologic MediaSpace ADPCM|13=Sierra Semiconductor ADPCM|14=Antex Electronics G.723 ADPCM|15=DSP Solutions DigiSTD|16=DSP Solutions DigiFIX|17=Dialogic OKI ADPCM|18=MediaVision ADPCM|19=Hewlett-Packard CU|20=Yamaha ADPCM|21=Speech Compression Sonarc|22=DSP Group TrueSpeech|23=Echo Speech EchoSC1|24=Audiofile AF36|25=Audio Processing Technology APTX|26=AudioFile AF10|27=Prosody 1612|28=LRC|30=Dolby AC2|31=Microsoft GSM 6.10|32=MSNAudio|33=Antex Electronics ADPCME|34=Control Resources VQLPC|35=DSP Solutions DigiREAL|36=DSP Solutions DigiADPCM|37=Control Resources CR10|38=Natural MicroSystems VBXADPCM|39=Crystal Semiconductor IMA ADPCM|3a=EchoSC3|3b=Rockwell ADPCM|3c=Rockwell Digit LK|3d=Xebec|40=Antex Electronics G.721 ADPCM|41=G.728 CELP|42=MSG723|50=MPEG Layer-2 or Layer-1|52=RT24|53=PAC|55=MPEG Layer-3|59=Lucent G.723|60=Cirrus|61=ESPCM|62=Voxware|63=Canopus Atrac|64=G.726 ADPCM|65=G.722 ADPCM|66=DSAT|67=DSAT Display|69=Voxware Byte Aligned|70=Voxware AC8|71=Voxware AC10|72=Voxware AC16|73=Voxware AC20|74=Voxware MetaVoice|75=Voxware MetaSound|76=Voxware RT29HW|77=Voxware VR12|78=Voxware VR18|79=Voxware TQ40|80=Softsound|81=Voxware TQ60|82=MSRT24|83=G.729A|84=MVI MV12|85=DF G.726|86=DF GSM610|88=ISIAudio|89=Onlive|91=SBC24|92=Dolby AC3 SPDIF|93=MediaSonic G.723|94=Aculab PLC    Prosody 8kbps|97=ZyXEL ADPCM|98=Philips LPCBB|99=Packed|ff=AAC|100=Rhetorex ADPCM|101=IBM mu-law|102=IBM A-law|103=IBM AVC Adaptive Differential Pulse Code Modulation (ADPCM)|111=Vivo G.723|112=Vivo Siren|123=Digital G.723|125=Sanyo LD ADPCM|130=Sipro Lab Telecom ACELP NET|131=Sipro Lab Telecom ACELP 4800|132=Sipro Lab Telecom ACELP 8V3|133=Sipro Lab Telecom G.729|134=Sipro Lab Telecom G.729A|135=Sipro Lab Telecom Kelvin|140=Windows Media Video V8|150=Qualcomm PureVoice|151=Qualcomm HalfRate|155=Ring Zero Systems TUB GSM|160=Microsoft Audio 1|161=Windows Media Audio V7 / V8 / V9|162=Windows Media Audio Professional V9|163=Windows Media Audio Lossless V9|200=Creative Labs ADPCM|202=Creative Labs Fastspeech8|203=Creative Labs Fastspeech10|210=UHER Informatic GmbH ADPCM|220=Quarterdeck|230=I-link Worldwide VC|240=Aureal RAW Sport|250=Interactive Products HSX|251=Interactive Products RPELP|260=Consistent Software CS2|270=Sony SCX|300=Fujitsu FM Towns Snd|400=BTV Digital|401=Intel Music Coder|450=QDesign Music|680=VME VMPCM|681=AT&T Labs TPC|8ae=ClearJump LiteWave|1000=Olivetti GSM|1001=Olivetti ADPCM|1002=Olivetti CELP|1003=Olivetti SBC|1004=Olivetti OPR|1100=Lernout & Hauspie Codec (0x1100)|1101=Lernout & Hauspie CELP Codec (0x1101)|1102=Lernout & Hauspie SBC Codec (0x1102)|1103=Lernout & Hauspie SBC Codec (0x1103)|1104=Lernout & Hauspie SBC Codec (0x1104)|1400=Norris|1401=AT&T ISIAudio|1500=Soundspace Music Compression|181c=VoxWare RT24 Speech|1fc4=NCT Soft ALF2CD (www.nctsoft.com)|2000=Dolby AC3|2001=Dolby DTS|2002=WAVE_FORMAT_14_4|2003=WAVE_FORMAT_28_8|2004=WAVE_FORMAT_COOK|2005=WAVE_FORMAT_DNET|674f=Ogg Vorbis 1|6750=Ogg Vorbis 2|6751=Ogg Vorbis 3|676f=Ogg Vorbis 1+|6770=Ogg Vorbis 2+|6771=Ogg Vorbis 3+|7a21=GSM-AMR (CBR, no SID)|7a22=GSM-AMR (VBR, including SID)|fffe=WAVE_FORMAT_EXTENSIBLE|ffff=WAVE_FORMAT_DEVELOPMENT';

    private const VIDEO_CODECS = '3IV1=3ivx MPEG-4 v1|3IV2=3ivx MPEG-4 v2|3IVX=3ivx MPEG-4|AASC=Autodesk Animator|ABYR=Kensington ?ABYR?|AEMI=Array Microsystems VideoONE MPEG1-I Capture|AFLC=Autodesk Animator FLC|AFLI=Autodesk Animator FLI|AMPG=Array Microsystems VideoONE MPEG|ANIM=Intel RDX (ANIM)|AP41=AngelPotion Definitive|ASV1=Asus Video v1|ASV2=Asus Video v2|ASVX=Asus Video 2.0 (audio)|AUR2=AuraVision Aura 2 Codec - YUV 4:2:2|AURA=AuraVision Aura 1 Codec - YUV 4:1:1|AVDJ=Independent JPEG Group\\\'s codec (AVDJ)|AVRN=Independent JPEG Group\\\'s codec (AVRN)|AYUV=4:4:4 YUV (AYUV)|AZPR=Quicktime Apple Video (AZPR)|BGR =Raw RGB32|BINK=RAD Game Tools Bink Video|BLZ0=Blizzard DivX MPEG-4|BT20=Conexant Prosumer Video|BTCV=Conexant Composite Video Codec|BW10=Data Translation Broadway MPEG Capture|CC12=Intel YUV12|CDVC=Canopus DV|CFCC=Digital Processing Systems DPS Perception|CGDI=Microsoft Office 97 Camcorder Video|CHAM=Winnov Caviara Champagne|CJPG=Creative WebCam JPEG|CLJR=Cirrus Logic YUV 4:1:1|CMYK=Common Data Format in Printing (Colorgraph)|CPLA=Weitek 4:2:0 YUV Planar|CRAM=Microsoft Video 1 (CRAM)|CVID=Radius Cinepak|CWLT=Microsoft Color WLT DIB|CYUV=Creative Labs YUV|CYUY=ATI YUV|D261=H.261|D263=H.263|DIV3=DivX ;-) MPEG-4 v3.x Low-Motion|DIV4=DivX ;-) MPEG-4 v3.x Fast-Motion|DIV5=DivX MPEG-4 v5.x|DIVX=DivX MPEG-4 v4 (OpenDivX / Project Mayo)|DMB1=Matrox Rainbow Runner hardware MJPEG|DMB2=Paradigm MJPEG|DSVD=?DSVD?|DUCK=Duck TrueMotion 1.0|DV25=Matrox DVCPRO codec|DV50=Matrox DVCPRO50 codec|DVAN=?DVAN?|DVE2=InSoft DVE-2 Videoconferencing|DVHD=IEC Standard DV 1125 lines @ 30fps / 1250 lines @ 25fps|DVSD=IEC 61834 and SMPTE 314M DVC/DV Video|DVSL=IEC Standard DV compressed in SD (SDL)|DVX1=Lucent DVX1000SP Video Decoder|DVX2=Lucent DVX2000S Video Decoder|DVX3=Lucent DVX3000S Video Decoder|DX50=DivX v5|DXT1=Microsoft DirectX Compressed Texture (DXT1)|DXT2=Microsoft DirectX Compressed Texture (DXT2)|DXT3=Microsoft DirectX Compressed Texture (DXT3)|DXT4=Microsoft DirectX Compressed Texture (DXT4)|DXT5=Microsoft DirectX Compressed Texture (DXT5)|DXTC=Microsoft DirectX Compressed Texture (DXTC)|EKQ0=Elsa ?EKQ0?|ELK0=Elsa ?ELK0?|EM2V=Etymonix MPEG-2 I-frame (www.etymonix.com)|ESCP=Eidos Escape|ETV1=eTreppid Video ETV1|ETV2=eTreppid Video ETV2|ETVC=eTreppid Video ETVC|FLJP=D-Vision Field Encoded Motion JPEG|FLV1=Sorenson Spark|FPS1=FRAPS v1|FRWA=SoftLab-Nsk Forward Motion JPEG w/ alpha channel|FRWD=SoftLab-Nsk Forward Motion JPEG|FVF1=Iterated Systems Fractal Video Frame|GLZW=Motion LZW (gabest@freemail.hu)|GPEG=Motion JPEG (gabest@freemail.hu)|GWLT=Microsoft Greyscale WLT DIB|H260=Intel ITU H.260 Videoconferencing|H261=Intel ITU H.261 Videoconferencing|H262=Intel ITU H.262 Videoconferencing|H263=Intel ITU H.263 Videoconferencing|H264=Intel ITU H.264 Videoconferencing|H265=Intel ITU H.265 Videoconferencing|H266=Intel ITU H.266 Videoconferencing|H267=Intel ITU H.267 Videoconferencing|H268=Intel ITU H.268 Videoconferencing|H269=Intel ITU H.269 Videoconferencing|HFYU=Huffman Lossless Codec|HMCR=Rendition Motion Compensation Format (HMCR)|HMRR=Rendition Motion Compensation Format (HMRR)|I263=FFmpeg I263 decoder|I420=Intel Indeo 4|IAN =Intel Indeo 4 (RDX)|ICLB=InSoft CellB Videoconferencing|IGOR=Power DVD|IJPG=Intergraph JPEG|ILVC=Intel Layered Video|ILVR=ITU-T H.263+|IPDV=I-O Data Device Giga AVI DV Codec|IR21=Intel Indeo 2.1|IRAW=Intel YUV Uncompressed|IUYV=Interlaced version of UYVY (www.leadtools.com)|IV30=Intel Indeo 3.0|IV31=Intel Indeo 3.1|IV32=Ligos Indeo 3.2|IV33=Ligos Indeo 3.3|IV34=Ligos Indeo 3.4|IV35=Ligos Indeo 3.5|IV36=Ligos Indeo 3.6|IV37=Ligos Indeo 3.7|IV38=Ligos Indeo 3.8|IV39=Ligos Indeo 3.9|IV40=Ligos Indeo Interactive 4.0|IV41=Ligos Indeo Interactive 4.1|IV42=Ligos Indeo Interactive 4.2|IV43=Ligos Indeo Interactive 4.3|IV44=Ligos Indeo Interactive 4.4|IV45=Ligos Indeo Interactive 4.5|IV46=Ligos Indeo Interactive 4.6|IV47=Ligos Indeo Interactive 4.7|IV48=Ligos Indeo Interactive 4.8|IV49=Ligos Indeo Interactive 4.9|IV50=Ligos Indeo Interactive 5.0|JBYR=Kensington ?JBYR?|JPEG=Still Image JPEG DIB|JPGL=Pegasus Lossless Motion JPEG|KMVC=Team17 Software Karl Morton\\\'s Video Codec|LEAD=LEAD Video Codec|LSVM=Vianet Lighting Strike Vmail (Streaming) (www.vianet.com)|M261=Microsoft H.261|M263=Microsoft H.263|M4S2=Microsoft Fully Compliant MPEG-4 v2 simple profile (M4S2)|MC12=ATI Motion Compensation Format (MC12)|MCAM=ATI Motion Compensation Format (MCAM)|MJ2C=Morgan Multimedia Motion JPEG2000|MJPG=Microsoft Motion JPEG DIB|MMES=Matrox MPEG-2 I-frame|MP42=Microsoft MPEG-4 (low-motion)|MP43=Microsoft MPEG-4 (fast-motion)|MP4S=Microsoft MPEG-4 (MP4S)|MP4V=FFmpeg MPEG-4|MPEG=Chromatic Research MPEG-1 Video I-Frame|MPG4=Microsoft MPEG-4 Video High Speed Compressor|MPGI=Sigma Designs MPEG|MRCA=FAST Multimedia Martin Regen Codec|MRLE=Microsoft Run Length Encoding|MSVC=Microsoft Video 1|MSZH=LCL (Lossless Codec Library) (www.geocities.co.jp/Playtown-Denei/2837/LRC.htm)|MTX1=Matrox ?MTX1?|MTX2=Matrox ?MTX2?|MTX3=Matrox ?MTX3?|MTX4=Matrox ?MTX4?|MTX5=Matrox ?MTX5?|MTX6=Matrox ?MTX6?|MTX7=Matrox ?MTX7?|MTX8=Matrox ?MTX8?|MTX9=Matrox ?MTX9?|MWV1=Aware Motion Wavelets|NTN1=Nogatech Video Compression 1|NVS0=nVidia GeForce Texture (NVS0)|NVS1=nVidia GeForce Texture (NVS1)|NVS2=nVidia GeForce Texture (NVS2)|NVS3=nVidia GeForce Texture (NVS3)|NVS4=nVidia GeForce Texture (NVS4)|NVS5=nVidia GeForce Texture (NVS5)|NVT0=nVidia GeForce Texture (NVT0)|NVT1=nVidia GeForce Texture (NVT1)|NVT2=nVidia GeForce Texture (NVT2)|NVT3=nVidia GeForce Texture (NVT3)|NVT4=nVidia GeForce Texture (NVT4)|NVT5=nVidia GeForce Texture (NVT5)|PDVC=I-O Data Device Digital Video Capture DV codec|PGVV=Radius Video Vision|PHMO=IBM Photomotion|PIM1=MPEG Realtime (Pinnacle Cards)|PIM2=Pegasus Imaging ?PIM2?|PIMJ=Pegasus Imaging Lossless JPEG|PIXL=MiroXL, Pinnacle PCTV|PVEZ=Horizons Technology PowerEZ|PVMM=PacketVideo Corporation MPEG-4|PVW2=Pegasus Imaging Wavelet Compression|QPEG=Q-Team QPEG 1.0|RGBT=Raw RGB w/ Transparency|RLE =Microsoft Run Length Encoder|RLE4=Run Length Encoded (4bpp, 16-color)|RLE8=Run Length Encoded (8bpp, 256-color)|RMP4=REALmagic MPEG-4 (unauthorized XVID copy) (www.sigmadesigns.com)|RPZA=Quicktime Apple Video (RPZA)|RT21=Intel Indeo RealTime Video 2.1|RV20=RealVideo G2|RV30=RealVideo 8|RV40=RealVideo 9|SDCC=Sun Communication Digital Camera Codec|SFMC=CrystalNet Surface Fitting Method|SMSC=Radius SMSC|SMSD=Radius SMSD|SP54=Logitech Sunplus Sp54 Codec for Mustek GSmart Mini 2|SPIG=Radius Spigot|SQZ2=Microsoft VXTreme Video Codec V2|STVA=ST Microelectronics CMOS Imager Data (Bayer)|STVB=ST Microelectronics CMOS Imager Data (Nudged Bayer)|STVC=ST Microelectronics CMOS Imager Data (Bunched)|STVX=ST Microelectronics CMOS Imager Data (Extended CODEC Data Format)|STVY=ST Microelectronics CMOS Imager Data (Extended CODEC Data Format with Correction Data)|SV10=Sorenson Video R1|SVQ1=Sorenson Video|SVQ3=Sorenson Video 3 (Apple Quicktime 5)|TLMS=TeraLogic Motion Intraframe Codec (TLMS)|TLST=TeraLogic Motion Intraframe Codec (TLST)|TM20=Duck TrueMotion 2.0|TM2X=Duck TrueMotion 2X|TMIC=TeraLogic Motion Intraframe Codec (TMIC)|TMOT=Horizons Technology TrueMotion S|TR20=Duck TrueMotion RealTime 2.0|TSCC=TechSmith Screen Capture Codec|TV10=Tecomac Low-Bit Rate Codec|TVJP=Pinnacle/Truevision Targa 2000 board (TVJP)|TVMJ=Pinnacle/Truevision Targa 2000 board (TVMJ)|TY0N=Tecomac Low-Bit Rate Codec (www.tecomac.com)|TY2C=Trident Decompression Driver|TY2N=Trident ?TY2N?|U263=UB Video H.263/H.263+/H.263++ Decoder|UCOD=eMajix.com ClearVideo|ULTI=IBM Ultimotion|V422=Vitec Multimedia 24-bit YUV 4:2:2 Format|V655=Vitec Multimedia 16-bit YUV 4:2:2 Format|VCR1=ATI Video Codec 1|VCR2=ATI Video Codec 2|VCR3=ATI VCR 3.0|VCR4=ATI VCR 4.0|VCR5=ATI VCR 5.0|VCR6=ATI VCR 6.0|VCR7=ATI VCR 7.0|VCR8=ATI VCR 8.0|VCR9=ATI VCR 9.0|VDCT=Vitec Multimedia Video Maker Pro DIB|VDOM=VDOnet VDOWave|VDTZ=Darim Vison VideoTizer YUV|VGPX=Alaris VideoGramPiX|VIDS=Vitec Multimedia YUV 4:2:2 CCIR 601 for V422|VIFP=VFAPI Reader Codec (www.yks.ne.jp/~hori/)|VIVO=Vivo H.263 v2.00|VIXL=Miro/Pinnacle Video XL|VLV1=VideoLogic/PURE Digital Videologic Capture|VP30=On2 VP3.0|VP31=On2 VP3.1|VP50=On2 VP5|VP60=On2 VP6|VP70=On2 VP7|VP80=On2 VP8|VQC2=Vector-quantised codec 2 (research) http://eprints.ecs.soton.ac.uk/archive/00001310/01/VTC97-js.pdf)|VX1K=Lucent VX1000S Video Codec|VX2K=Lucent VX2000S Video Codec|VXSP=Lucent VX1000SP Video Codec|VYU9=ATI YUV (VYU9)|VYUY=ATI YUV (VYUY)|WBVC=Winbond W9960|WHAM=Microsoft Video 1 (WHAM)|WINX=Winnov Software Compression|WJPG=AverMedia Winbond JPEG|WMV1=Windows Media Video V7|WMV2=Windows Media Video V8|WMV3=Windows Media Video V9|WNV1=Winnov Hardware Compression|XLV0=NetXL Video Decoder|XMPG=Xing MPEG (I-Frame only)|XVID=XviD MPEG-4 (www.xvid.org)|XXAN=?XXAN?|Y411=YUV 4:1:1 Packed|Y41P=Brooktree PC1 YUV 4:1:1 Packed|Y8  =Grayscale video|YC12=Intel YUV 12 codec|YUV8=Winnov Caviar YUV8|YUV9=Intel YUV9|YUVP=Extended PAL format YUV palette (www.riff.org)|YUY2=Uncompressed YUV 4:2:2|YUYV=Canopus YUV|YV12=YVU12 Planar|ZLIB=Lossless Codec Library zlib compression (www.geocities.co.jp/Playtown-Denei/2837/LRC.htm)|ZPEG=Metheus Video Zipper|cvid=Radius Cinepak|divx=DivX MPEG-4|dvsd=IEC 61834 and SMPTE 314M DVC/DV Video|i263=Intel ITU H.263 Videoconferencing (i263)|m4s2=Microsoft Fully Compliant MPEG-4 v2 simple profile (m4s2)|mp4s=Microsoft MPEG-4 (mp4s)|qpeq=Q-Team QPEG 1.1|rv20=RealVideo G2|rv30=RealVideo 8|s422=Tekram VideoCap C210 YUV 4:2:2|smsv=WorldConnect Wavelet Video|tmot=Horizons TrueMotion Video Compression|vivo=Vivo H.263|x263=Xirlink H.263';

    /** Whether a file's first bytes are a RIFF WAVE or AVI file. */
    public static function detects(string $head): bool
    {
        return str_starts_with($head, 'RIFF') && in_array(substr($head, 8, 4), ['WAVE', 'AVI '], true);
    }

    /**
     * The file's analysis: format, type, timing and its streams.
     *
     * @return array<string, mixed>
     */
    public static function read(Source $source): array
    {
        return $source->bytes(8, 4) === 'WAVE' ? self::wave($source) : self::avi($source);
    }

    /** @return array<string, mixed> */
    private static function wave(Source $source): array
    {
        $format = null;
        $data = null;
        foreach (self::chunks($source, 12, $source->size) as $chunk) {
            if ($chunk['id'] === 'fmt ' && $format === null) {
                $format = self::waveFormat($source->bytes($chunk['start'], min($chunk['size'], 40)));
            } elseif ($chunk['id'] === 'data' && $data === null) {
                $data = $chunk;
            }
        }
        $out = ['fileformat' => 'wav', 'mime_type' => 'audio/wav'];
        if ($format === null) {
            return $out;
        }
        $start = $data['start'] ?? null;
        $end = $data === null ? null : min($data['start'] + $data['size'], $source->size);
        if ($start !== null) {
            $out = ['avdataoffset' => $start, 'avdataend' => $end] + $out;
        }
        $scan = $format['tag'] === 0x55 && $start !== null ? Mpeg::scan($source, $start, $end) : null;
        $ac3 = $start !== null && in_array($format['tag'], [0x2000, 0xFFFE], true) ? Ac3::frame($source->bytes($start, 8)) : null;
        $out['audio'] = match (true) {
            $scan !== null => self::scannedAudio($format, $scan),
            $ac3 !== null => self::ac3Audio($format, $ac3),
            default => self::audio($format, ['ac3' => 0x2000, 'litewave' => 0x08AE]),
        };
        if ($start !== null && $format['average'] > 0) {
            $out['playtime_seconds'] = ($end - $start) / $format['average'];
            $out['bitrate'] = $format['average'] * 8;
        } elseif ($scan !== null) {
            // No stated rate: the frames' mean measures the data.
            $out['playtime_seconds'] = ($end - $start) * 8 / $scan['bitrate'];
            $out['bitrate'] = $scan['bitrate'];
        }
        return $out;
    }

    /** @return array<string, mixed> */
    private static function avi(Source $source): array
    {
        $header = null;
        $streams = [];
        $movie = null;
        foreach (self::chunks($source, 12, $source->size) as $chunk) {
            $list = $chunk['id'] === 'LIST' ? $source->bytes($chunk['start'], 4) : '';
            if ($list === 'hdrl') {
                // A stream list ends with the header list holding it, whatever size it claims.
                $end = min($chunk['start'] + $chunk['size'], $source->size);
                foreach (self::chunks($source, $chunk['start'] + 4, $end) as $part) {
                    if ($part['id'] === 'avih') {
                        $header = $source->bytes($part['start'], 40);
                    } elseif ($part['id'] === 'LIST' && $source->bytes($part['start'], 4) === 'strl') {
                        $streams[] = self::stream($source, ['size' => min($part['size'], $end - $part['start'])] + $part);
                    }
                }
            } elseif ($list === 'movi' && $movie === null) {
                $movie = $chunk;
            }
        }
        $out = ['fileformat' => 'avi', 'mime_type' => 'video/avi'];
        if ($movie !== null) {
            $out = ['avdataoffset' => $movie['start'] + 8, 'avdataend' => min($movie['start'] + $movie['size'], $source->size)] + $out;
        }
        $video = null;
        $audio = null;
        $audioFormat = null;
        foreach ($streams as $index => $stream) {
            if ($stream['type'] === 'vids' && $video === null) {
                $video = $stream;
            } elseif ($stream['type'] === 'auds' && $audioFormat === null && $stream['format'] !== null) {
                $audioFormat = $stream['format'];
                $mpeg = $audioFormat['tag'] === 0x55 ? self::firstAudioFrame($source, $movie, $index) : null;
                $audio = $mpeg !== null ? self::mpegAudio($audioFormat, $mpeg) : self::audio($audioFormat, ['ac3' => 0x2000, 'litewave' => 0x08AE, 'mp2' => 0x50]);
            }
        }
        $frames = $header === null ? 0 : Source::littleEndianValue(substr($header, 16, 4));
        $rate = $video !== null && $video['scale'] > 0 ? $video['rate'] / $video['scale'] : 0;
        $playtime = $rate > 0 ? $frames / $rate : null;
        $overall = $playtime && isset($out['avdataoffset']) ? ($out['avdataend'] - $out['avdataoffset']) * 8 / $playtime : null;
        if ($audio !== null) {
            $out['audio'] = $audio;
        }
        if ($video !== null) {
            $out['video'] = self::video($video, $frames, $rate, $overall === null ? null : $overall - ($audioFormat !== null && $audioFormat['average'] > 0 ? $audioFormat['average'] * 8 : 0));
        }
        if ($playtime !== null) {
            $out['playtime_seconds'] = $playtime;
        }
        if ($overall !== null) {
            $out['bitrate'] = $overall;
        }
        return $out;
    }

    /**
     * The chunks between two offsets: each one's id, where its body starts
     * and its size (bodies are padded to an even length).
     *
     * @return list<array{id: string, start: int, size: int}>
     */
    private static function chunks(Source $source, int $from, int $to): array
    {
        $chunks = [];
        for ($at = $from; $at + 8 <= $to && count($chunks) < 4096; ) {
            $size = $source->littleEndian($at + 4, 4);
            $chunks[] = ['id' => $source->bytes($at, 4), 'start' => $at + 8, 'size' => $size];
            $at += 8 + $size + ($size & 1);
        }
        return $chunks;
    }

    /**
     * A stream list's header and format: its type, codec code, rate and
     * scale, and its bitmap or wave format.
     *
     * @param array{id: string, start: int, size: int} $list
     * @return array<string, mixed>
     */
    private static function stream(Source $source, array $list): array
    {
        $stream = ['type' => '', 'handler' => '', 'rate' => 0, 'scale' => 0, 'format' => null, 'bitmap' => null];
        foreach (self::chunks($source, $list['start'] + 4, $list['start'] + $list['size']) as $chunk) {
            if ($chunk['id'] === 'strh') {
                $head = $source->bytes($chunk['start'], 32);
                $stream = ['type' => substr($head, 0, 4), 'handler' => substr($head, 4, 4), 'scale' => Source::littleEndianValue(substr($head, 20, 4)), 'rate' => Source::littleEndianValue(substr($head, 24, 4))] + $stream;
            } elseif ($chunk['id'] === 'strf') {
                $body = $source->bytes($chunk['start'], min($chunk['size'], 40));
                $stream[$stream['type'] === 'auds' ? 'format' : 'bitmap'] = $stream['type'] === 'auds' ? self::waveFormat($body) : $body;
            }
        }
        return $stream;
    }

    /**
     * A wave format's fields: its tag, channels, sample rate, average bytes
     * a second and bits a sample.
     *
     * @return array{tag: int, channels: int, sampleRate: int, average: int, bits: int}|null
     */
    private static function waveFormat(string $body): ?array
    {
        if (strlen($body) < 14) {
            return null;
        }
        $field = static fn (int $offset, int $width): int => Source::littleEndianValue(substr($body, $offset, $width));
        return ['tag' => $field(0, 2), 'channels' => $field(2, 2), 'sampleRate' => $field(4, 4), 'average' => $field(8, 4), 'bits' => strlen($body) >= 16 ? $field(14, 2) : 0];
    }

    /**
     * A stream described by its wave format alone.
     *
     * @param array{tag: int, channels: int, sampleRate: int, average: int, bits: int} $format
     * @param array<string, int> $dataformats the tags with a format name of their own
     * @return array<string, mixed>
     */
    private static function audio(array $format, array $dataformats): array
    {
        $audio = ['dataformat' => array_search($format['tag'], $dataformats, true) ?: 'wav', 'bitrate_mode' => 'cbr', 'wformattag' => $format['tag'], 'codec' => self::formatName($format['tag']), 'channels' => $format['channels'], 'sample_rate' => $format['sampleRate']];
        $bitrate = $format['average'] * 8;
        if ($bitrate > 0) {
            $audio['bitrate'] = $bitrate;
        }
        if ($format['bits'] > 0) {
            $audio['bits_per_sample'] = $format['bits'];
        }
        $audio['lossless'] = $format['tag'] === 1;
        if ($format['channels'] === 1 || $format['channels'] === 2) {
            $audio['channelmode'] = $format['channels'] === 1 ? 'mono' : 'stereo';
        }
        if ($bitrate > 0 && $format['channels'] > 0 && $format['sampleRate'] > 0) {
            $audio['compression_ratio'] = $bitrate / ($format['channels'] * $format['sampleRate'] * ($format['bits'] ?: 16));
        }
        return $audio;
    }

    /**
     * MP3 audio in a wave file, described from all its frames: the first
     * frame's channels and rate, the frames' mean bitrate (variable when any
     * frame's differs), the tag; the codec, the encoder's options and the
     * lossless flag only when the wave format states a rate.
     *
     * @param array{tag: int, channels: int, sampleRate: int, average: int, bits: int} $format
     * @param array{first: array<string, int>, bitrate: float, variable: bool} $scan
     * @return array<string, mixed>
     */
    private static function scannedAudio(array $format, array $scan): array
    {
        $first = $scan['first'];
        $bitrate = $scan['variable'] ? $scan['bitrate'] : $first['bitrate'];
        $mode = ['channelmode' => ['stereo', 'joint stereo', 'dual channel', 'mono'][$first['mode']]];
        $rate = ['bitrate_mode' => $scan['variable'] ? 'vbr' : 'cbr'];
        $audio = ['dataformat' => 'mp3', 'channels' => $first['channels'], 'sample_rate' => $first['sampleRate'], 'bitrate' => $bitrate] + ($scan['variable'] ? $rate + $mode : $mode + $rate);
        $audio['wformattag'] = $format['tag'];
        if ($format['average'] > 0) {
            $audio += ['codec' => self::formatName($format['tag']), 'lossless' => false, 'encoder_options' => $scan['variable'] ? 'VBR' : 'CBR' . intdiv($first['bitrate'], 1000)];
        }
        $audio['compression_ratio'] = $bitrate / ($first['channels'] * $first['sampleRate'] * 16);
        return $audio;
    }

    /**
     * AC-3 audio in a wave file, described from its first frame: the frame's
     * rate, bitrate, channel mode and channels (a low frequency channel
     * counted as ".1", which the ratio counts too), then the tag and codec;
     * the sample size only for an extensible format.
     *
     * @param array{tag: int, channels: int, sampleRate: int, average: int, bits: int} $format
     * @param array{sampleRate: int, bitrate: int, channels: int|string, mode: string} $frame
     * @return array<string, mixed>
     */
    private static function ac3Audio(array $format, array $frame): array
    {
        $audio = ['dataformat' => 'ac3', 'bitrate_mode' => 'cbr', 'lossless' => false, 'sample_rate' => $frame['sampleRate'], 'bitrate' => $frame['bitrate'], 'channelmode' => $frame['mode'], 'channels' => $frame['channels'], 'wformattag' => $format['tag'], 'codec' => self::formatName($format['tag'])];
        if ($format['tag'] === 0xFFFE && $format['bits'] > 0) {
            $audio['bits_per_sample'] = $format['bits'];
        }
        $audio['compression_ratio'] = $frame['bitrate'] / ((float) $frame['channels'] * $frame['sampleRate'] * 16);
        return $audio;
    }

    /**
     * MP3 audio described from its first frame, the wave format's tag and
     * codec beside: a rate the format states comes early, one read from the
     * frame late.
     *
     * @param array{tag: int, channels: int, sampleRate: int, average: int, bits: int} $format
     * @param array<string, int> $frame
     * @return array<string, mixed>
     */
    private static function mpegAudio(array $format, array $frame): array
    {
        $audio = ['dataformat' => 'mp3', 'channels' => $frame['channels'], 'sample_rate' => $frame['sampleRate']];
        if ($format['average'] > 0) {
            $audio['bitrate'] = $frame['bitrate'];
        }
        $audio += ['channelmode' => ['stereo', 'joint stereo', 'dual channel', 'mono'][$frame['mode']], 'bitrate_mode' => 'cbr', 'wformattag' => $format['tag'], 'codec' => self::formatName($format['tag']), 'lossless' => false];
        $audio['bitrate'] ??= $frame['bitrate'];
        $audio['encoder_options'] = 'CBR' . intdiv($frame['bitrate'], 1000);
        $audio['compression_ratio'] = $frame['bitrate'] / ($frame['channels'] * $frame['sampleRate'] * 16);
        return $audio;
    }

    /**
     * The first MPEG frame header of an AVI's audio stream, from its first
     * chunk in the movie list.
     *
     * @param array{id: string, start: int, size: int}|null $movie
     * @return array<string, int>|null
     */
    private static function firstAudioFrame(Source $source, ?array $movie, int $index): ?array
    {
        if ($movie === null) {
            return null;
        }
        $id = sprintf('%02dwb', $index);
        foreach (self::chunks($source, $movie['start'] + 4, min($movie['start'] + $movie['size'], $movie['start'] + 4 + 1048576)) as $chunk) {
            if ($chunk['id'] === $id) {
                return Mpeg::frameHeader($source->bigEndian($chunk['start'], 4));
            }
        }
        return null;
    }

    /**
     * An AVI's video stream: its size and depth from its bitmap header, the
     * frames and their rate, its codec named by the stream's handler (or
     * failing that the bitmap's compression), and the bitrate it has.
     *
     * @param array<string, mixed> $stream
     * @return array<string, mixed>
     */
    private static function video(array $stream, int $frames, int|float $rate, int|float|null $bitrate): array
    {
        $bitmap = (string) $stream['bitmap'];
        $field = static fn (int $offset, int $width): int => Source::littleEndianValue(substr($bitmap, $offset, $width));
        $width = $field(4, 4);
        $height = $field(8, 4);
        $height = $height >= 0x80000000 ? 0x100000000 - $height : $height;
        $depth = $field(14, 2);
        $codecs = self::videoCodecs();
        $video = [
            'dataformat' => 'avi', 'bitrate_mode' => 'vbr', 'resolution_x' => $width, 'resolution_y' => $height, 'total_frames' => $frames, 'frame_rate' => $rate,
            'fourcc' => $stream['handler'], 'codec' => $codecs[$stream['handler']] ?? $codecs[substr($bitmap, 16, 4)] ?? '', 'pixel_aspect_ratio' => 1.0, 'lossless' => false, 'bits_per_sample' => $depth,
        ];
        if ($bitrate !== null) {
            $video['bitrate'] = $bitrate;
            if ($width > 0 && $height > 0 && $depth > 0 && $rate > 0) {
                $video['compression_ratio'] = $bitrate / ($width * $height * $depth * $rate);
            }
        }
        return $video;
    }

    /** The codec a wave format tag names; empty for one it does not know. */
    private static function formatName(int $tag): string
    {
        static $names = null;
        if ($names === null) {
            $names = [];
            foreach (explode('|', self::FORMAT_TAGS) as $pair) {
                [$code, $name] = explode('=', $pair, 2);
                $names[hexdec($code)] = $name;
            }
        }
        return $names[$tag] ?? '';
    }

    /** The name an AVI video codec code has; empty for one it does not know. */
    public static function videoCodec(string $code): string
    {
        return self::videoCodecs()[$code] ?? '';
    }

    /** @return array<string, string> code => name */
    private static function videoCodecs(): array
    {
        static $codecs = null;
        if ($codecs === null) {
            $codecs = [];
            foreach (explode('|', self::VIDEO_CODECS) as $pair) {
                [$code, $name] = explode('=', $pair, 2);
                $codecs[$code] = $name;
            }
        }
        return $codecs;
    }
}
