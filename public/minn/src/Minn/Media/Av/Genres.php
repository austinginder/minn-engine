<?php

declare(strict_types=1);

namespace Minn\Media\Av;

/**
 * The genre names an ID3 tag refers to by number: the ID3v1 list with the
 * Winamp additions (0 to 191) and a later run from 240. An ID3v2 genre
 * names an unknown number as an empty string, and 255 as "Unknown"; an
 * ID3v1 tag names neither (probe media-meta).
 */
final class Genres
{
    private const LOW = 'Blues|Classic Rock|Country|Dance|Disco|Funk|Grunge|Hip-Hop|Jazz|Metal|New Age|Oldies|Other|Pop|R&B|Rap|Reggae|Rock|Techno|Industrial|Alternative|Ska|Death Metal|Pranks|Soundtrack|Euro-Techno|Ambient|Trip-Hop|Vocal|Jazz+Funk|Fusion|Trance|Classical|Instrumental|Acid|House|Game|Sound Clip|Gospel|Noise|Alt. Rock|Bass|Soul|Punk|Space|Meditative|Instrumental Pop|Instrumental Rock|Ethnic|Gothic|Darkwave|Techno-Industrial|Electronic|Pop-Folk|Eurodance|Dream|Southern Rock|Comedy|Cult|Gangsta Rap|Top 40|Christian Rap|Pop/Funk|Jungle|Native American|Cabaret|New Wave|Psychedelic|Rave|Showtunes|Trailer|Lo-Fi|Tribal|Acid Punk|Acid Jazz|Polka|Retro|Musical|Rock & Roll|Hard Rock|Folk|Folk/Rock|National Folk|Swing|Fast-Fusion|Bebob|Latin|Revival|Celtic|Bluegrass|Avantgarde|Gothic Rock|Progressive Rock|Psychedelic Rock|Symphonic Rock|Slow Rock|Big Band|Chorus|Easy Listening|Acoustic|Humour|Speech|Chanson|Opera|Chamber Music|Sonata|Symphony|Booty Bass|Primus|Porn Groove|Satire|Slow Jam|Club|Tango|Samba|Folklore|Ballad|Power Ballad|Rhythmic Soul|Freestyle|Duet|Punk Rock|Drum Solo|A Cappella|Euro-House|Dance Hall|Goa|Drum & Bass|Club-House|Hardcore|Terror|Indie|BritPop|Negerpunk|Polsk Punk|Beat|Christian Gangsta Rap|Heavy Metal|Black Metal|Crossover|Contemporary Christian|Christian Rock|Merengue|Salsa|Thrash Metal|Anime|JPop|Synthpop|Abstract|Art Rock|Baroque|Bhangra|Big Beat|Breakbeat|Chillout|Downtempo|Dub|EBM|Eclectic|Electro|Electroclash|Emo|Experimental|Garage|Global|IDM|Illbient|Industro-Goth|Jam Band|Krautrock|Leftfield|Lounge|Math Rock|New Romantic|Nu-Breakz|Post-Punk|Post-Rock|Psytrance|Shoegaze|Space Rock|Trop Rock|World Music|Neoclassical|Audiobook|Audio Theatre|Neue Deutsche Welle|Podcast|Indie-Rock|G-Funk|Dubstep|Garage Rock|Psybient';

    private const HIGH = 'Sacred|Northern Europe|Irish & Scottish|Scotland|Ethnic Europe|Enka|Children\'s Song|Japanese Sky|Japanese Heavy Rock|Japanese Doom Rock|Japanese J-POP|Japanese Seiyu|Japanese Ambient Techno|Japanese Moemoe|Japanese Tokusatsu';

    /** The name for a number, or null for a number with none. */
    public static function name(int $number): ?string
    {
        static $low = null, $high = null;
        $low ??= explode('|', self::LOW);
        $high ??= explode('|', self::HIGH);
        return $low[$number] ?? $high[$number - 240] ?? null;
    }
}
