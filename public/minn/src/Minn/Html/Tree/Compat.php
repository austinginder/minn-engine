<?php

declare(strict_types=1);

namespace Minn\Html\Tree;

/**
 * The compatibility mode a doctype indicates, by the identifier lists in
 * the HTML standard: quirks for legacy and missing doctypes, limited
 * quirks for the XHTML 1.0 transitional and frameset ones (and HTML 4.01's
 * with a system identifier), no quirks otherwise.
 */
final class Compat
{
    private const QUIRKS_PUBLIC = ['-//w3o//dtd w3 html strict 3.0//en//', '-/w3c/dtd html 4.0 transitional/en', 'html'];
    private const QUIRKS_PREFIXES = [
        '+//silmaril//dtd html pro v0r11 19970101//', '-//as//dtd html 3.0 aswedit + extensions//', '-//advasoft ltd//dtd html 3.0 aswedit + extensions//',
        '-//ietf//dtd html 2.0 level 1//', '-//ietf//dtd html 2.0 level 2//', '-//ietf//dtd html 2.0 strict level 1//', '-//ietf//dtd html 2.0 strict level 2//',
        '-//ietf//dtd html 2.0 strict//', '-//ietf//dtd html 2.0//', '-//ietf//dtd html 2.1e//', '-//ietf//dtd html 3.0//', '-//ietf//dtd html 3.2 final//',
        '-//ietf//dtd html 3.2//', '-//ietf//dtd html 3//', '-//ietf//dtd html level 0//', '-//ietf//dtd html level 1//', '-//ietf//dtd html level 2//',
        '-//ietf//dtd html level 3//', '-//ietf//dtd html strict level 0//', '-//ietf//dtd html strict level 1//', '-//ietf//dtd html strict level 2//',
        '-//ietf//dtd html strict level 3//', '-//ietf//dtd html strict//', '-//ietf//dtd html//', '-//metrius//dtd metrius presentational//',
        '-//microsoft//dtd internet explorer 2.0 html strict//', '-//microsoft//dtd internet explorer 2.0 html//', '-//microsoft//dtd internet explorer 2.0 tables//',
        '-//microsoft//dtd internet explorer 3.0 html strict//', '-//microsoft//dtd internet explorer 3.0 html//', '-//microsoft//dtd internet explorer 3.0 tables//',
        '-//netscape comm. corp.//dtd html//', '-//netscape comm. corp.//dtd strict html//', "-//o'reilly and associates//dtd html 2.0//",
        "-//o'reilly and associates//dtd html extended 1.0//", "-//o'reilly and associates//dtd html extended relaxed 1.0//",
        '-//sq//dtd html 2.0 hotmetal + extensions//', '-//softquad software//dtd hotmetal pro 6.0::19990601::extensions to html 4.0//',
        '-//softquad//dtd hotmetal pro 4.0::19970916::extensions to html 4.0//', '-//spyglass//dtd html 2.0 extended//', '-//sun microsystems corp.//dtd hotjava html//',
        '-//sun microsystems corp.//dtd hotjava strict html//', '-//w3c//dtd html 3 1995-03-24//', '-//w3c//dtd html 3.2 draft//', '-//w3c//dtd html 3.2 final//',
        '-//w3c//dtd html 3.2//', '-//w3c//dtd html 3.2s draft//', '-//w3c//dtd html 4.0 frameset//', '-//w3c//dtd html 4.0 transitional//',
        '-//w3c//dtd html experimental 19960712//', '-//w3c//dtd html experimental 970421//', '-//w3c//dtd w3 html//', '-//w3o//dtd w3 html 3.0//',
        '-//webtechs//dtd mozilla html 2.0//', '-//webtechs//dtd mozilla html//',
    ];
    private const HTML401 = ['-//w3c//dtd html 4.01 frameset//', '-//w3c//dtd html 4.01 transitional//'];
    private const XHTML10 = ['-//w3c//dtd xhtml 1.0 frameset//', '-//w3c//dtd xhtml 1.0 transitional//'];

    /**
     * The mode ("no-quirks", "limited-quirks", "quirks") for a doctype's pieces.
     *
     * @param array{name: ?string, public: ?string, system: ?string} $doctype
     */
    public static function of(array $doctype): string
    {
        if (strtolower((string) $doctype['name']) !== 'html') {
            return 'quirks';
        }
        $public = strtolower((string) $doctype['public']);
        $system = $doctype['system'];
        if (in_array($public, self::QUIRKS_PUBLIC, true) || strtolower((string) $system) === 'http://www.ibm.com/data/dtd/v11/ibmxhtml1-transitional.dtd') {
            return 'quirks';
        }
        if (self::startsWithAny($public, self::QUIRKS_PREFIXES) || ($system === null && self::startsWithAny($public, self::HTML401))) {
            return 'quirks';
        }
        if (self::startsWithAny($public, self::XHTML10) || ($system !== null && self::startsWithAny($public, self::HTML401))) {
            return 'limited-quirks';
        }
        return 'no-quirks';
    }

    /**
     * A whole doctype token read: its name (lower-cased), identifiers and
     * mode; a malformed one is quirks. Null when the text is not one doctype.
     *
     * @return array{name: ?string, public: ?string, system: ?string, mode: string}|null
     */
    public static function read(string $html): ?array
    {
        if (preg_match('/^<!DOCTYPE([^>]*)>$/i', $html, $m) !== 1) {
            return null;
        }
        $quoted = '("[^"]*"|\'[^\']*\')';
        $wellFormed = preg_match('/^\s*(\S+)?\s*(?:PUBLIC\s*' . $quoted . '(?:\s*' . $quoted . ')?|SYSTEM\s*' . $quoted . ')?\s*$/i', $m[1], $parts) === 1;
        $name = isset($parts[1]) && $parts[1] !== '' ? strtolower($parts[1]) : (preg_match('/^\s*([^\s>]+)/', $m[1], $n) === 1 ? strtolower($n[1]) : null);
        $public = isset($parts[2]) && $parts[2] !== '' ? substr($parts[2], 1, -1) : null;
        $system = isset($parts[3]) && $parts[3] !== '' ? substr($parts[3], 1, -1) : (isset($parts[4]) && $parts[4] !== '' ? substr($parts[4], 1, -1) : null);
        $mode = $wellFormed ? self::of(['name' => $name, 'public' => $public, 'system' => $system]) : 'quirks';
        return ['name' => $name, 'public' => $wellFormed ? $public : null, 'system' => $wellFormed ? $system : null, 'mode' => $mode];
    }

    /** @param list<string> $prefixes */
    private static function startsWithAny(string $text, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($text, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
