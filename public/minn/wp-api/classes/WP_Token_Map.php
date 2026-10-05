<?php
/** A word map read longest-first, as the HTML API reads character reference names; the work is Minn\Html\TokenMap's. */

use Minn\Html\TokenMap;

class WP_Token_Map
{
    const STORAGE_VERSION = '6.6.0-trunk';
    const MAX_LENGTH = 256;

    private TokenMap $minnMap;

    private function __construct(TokenMap $map)
    {
        $this->minnMap = $map;
    }

    public static function from_array($mappings, $key_length = 2)
    {
        foreach ((array) $mappings as $word => $replacement) {
            if (strlen((string) $word) > self::MAX_LENGTH || strlen((string) $replacement) > self::MAX_LENGTH) {
                return null;
            }
        }
        return new self(new TokenMap((array) $mappings, (int) $key_length));
    }

    public static function from_precomputed_table($state)
    {
        $state = (array) $state;
        return new self(new TokenMap((array) ($state['words'] ?? []), (int) ($state['key_length'] ?? 2)));
    }

    public function contains($word, $case_sensitivity = 'case-sensitive')
    {
        return $this->minnMap->contains((string) $word, (string) $case_sensitivity);
    }

    public function read_token($text, $offset = 0, &$matched_token_byte_length = null, $case_sensitivity = 'case-sensitive')
    {
        $found = $this->minnMap->read((string) $text, (int) $offset, (string) $case_sensitivity);
        if ($found === null) {
            return null;
        }
        $matched_token_byte_length = $found[1];
        return $found[0];
    }

    public function to_array()
    {
        return $this->minnMap->toArray();
    }

    public function precomputed_php_source_table($indent = "\t")
    {
        $state = ['storage_version' => self::STORAGE_VERSION, 'key_length' => $this->minnMap->keyLength(), 'words' => $this->minnMap->toArray()];
        return 'WP_Token_Map::from_precomputed_table(' . "\n" . $indent . str_replace("\n", "\n" . $indent, var_export($state, true)) . "\n);\n";
    }
}
