<?php
/** The phpass interface plugin code drives, over the engine's own portable-hash implementation. */
class PasswordHash
{
    private $iteration_count_log2;

    public function __construct($iteration_count_log2, $portable_hashes)
    {
        $this->iteration_count_log2 = min(31, max(4, (int) $iteration_count_log2));
    }

    public function HashPassword($password)
    {
        return \Minn\Auth\PortableHash::hash((string) $password, $this->iteration_count_log2);
    }

    public function CheckPassword($password, $stored_hash)
    {
        return \Minn\Auth\PortableHash::verify((string) $password, (string) $stored_hash);
    }

    public function get_random_bytes($count)
    {
        return random_bytes(max(1, (int) $count));
    }
}
