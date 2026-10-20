<?php

declare(strict_types=1);

namespace Minn\Media\Av;

/**
 * A bit reader over a byte string for the exponential-Golomb codes of a
 * video parameter set: single bits, unsigned and signed codes. Reading
 * past the end reads zeros, and a code longer than 32 bits stops at 32.
 */
final class ExpGolomb
{
    private int $at = 0;

    public function __construct(private readonly string $bytes)
    {
    }

    /** The next bit. */
    public function bit(): int
    {
        $byte = $this->at >> 3 < strlen($this->bytes) ? ord($this->bytes[$this->at >> 3]) : 0;
        $bit = ($byte >> (7 - ($this->at & 7))) & 1;
        $this->at++;
        return $bit;
    }

    /** Passes over a number of bits. */
    public function skip(int $count): void
    {
        $this->at += $count;
    }

    /** The next unsigned exponential-Golomb code. */
    public function unsigned(): int
    {
        $zeros = 0;
        while ($this->bit() === 0 && $zeros < 32) {
            $zeros++;
        }
        $value = 0;
        for ($i = 0; $i < $zeros; $i++) {
            $value = ($value << 1) | $this->bit();
        }
        return (1 << $zeros) - 1 + $value;
    }

    /** The next signed exponential-Golomb code. */
    public function signed(): int
    {
        $code = $this->unsigned();
        return $code % 2 === 1 ? intdiv($code + 1, 2) : -intdiv($code, 2);
    }
}
