<?php

use Minn\Auth\PortableHash;

/**
 * phpass, as plugin code drives it (probe identity-internals), over the
 * engine's own implementation of the published algorithm
 * (Minn\Auth\PortableHash). A count outside 4 to 31 becomes 8. A portable
 * hasher makes "$P$" hashes (2^(count+5) rounds); one that is not makes
 * bcrypt ("$2a$") and falls back to portable. Checking reads either kind.
 * A password over 4096 bytes is refused: "*" as a hash, false as a check.
 */
class PasswordHash
{
    public $itoa64;
    public $iteration_count_log2;
    public $portable_hashes;
    public $random_state;

    public function __construct($iteration_count_log2, $portable_hashes)
    {
        $this->itoa64 = PortableHash::ALPHABET;
        $this->iteration_count_log2 = $iteration_count_log2 < 4 || $iteration_count_log2 > 31 ? 8 : $iteration_count_log2;
        $this->portable_hashes = $portable_hashes;
        $this->random_state = microtime();
    }

    public function PasswordHash($iteration_count_log2, $portable_hashes)
    {
        self::__construct($iteration_count_log2, $portable_hashes);
    }

    public function get_random_bytes($count)
    {
        return random_bytes(max(1, (int) $count));
    }

    public function encode64($input, $count)
    {
        return PortableHash::encode((string) $input, (int) $count);
    }

    public function gensalt_private($input)
    {
        return PortableHash::salt((string) $input, min((int) $this->iteration_count_log2 + 5, 30));
    }

    public function crypt_private($password, $setting)
    {
        return PortableHash::crypt((string) $password, (string) $setting);
    }

    public function gensalt_blowfish($input)
    {
        return PortableHash::blowfishSalt((string) $input, (int) $this->iteration_count_log2);
    }

    public function HashPassword($password)
    {
        if (strlen((string) $password) > 4096) {
            return '*';
        }
        if (!$this->portable_hashes) {
            $hash = crypt((string) $password, $this->gensalt_blowfish($this->get_random_bytes(16)));
            if (strlen($hash) === 60) {
                return $hash;
            }
        }
        $hash = $this->crypt_private($password, $this->gensalt_private($this->get_random_bytes(6)));
        return strlen($hash) === 34 ? $hash : '*';
    }

    public function CheckPassword($password, $stored_hash)
    {
        if (strlen((string) $password) > 4096) {
            return false;
        }
        $hash = $this->crypt_private($password, $stored_hash);
        if ($hash[0] === '*') {
            $hash = crypt((string) $password, (string) $stored_hash);
        }
        return hash_equals((string) $stored_hash, $hash);
    }
}
