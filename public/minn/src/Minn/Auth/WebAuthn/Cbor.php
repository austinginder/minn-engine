<?php

declare(strict_types=1);

namespace Minn\Auth\WebAuthn;

/**
 * A CBOR reader (RFC 8949) for what authenticators send: integers, byte
 * strings (as CborBytes), text, arrays, maps keyed by integers or text,
 * tags (read through), true, false and null. Everything else an
 * authenticator has no reason to send is refused: lengths of unknown size,
 * floats, byte-string map keys, a key given twice, nesting past sixteen
 * levels, and any length longer than the bytes left. Lengths are checked
 * against what remains before anything is read, so a short message cannot
 * ask for a large allocation.
 */
final class Cbor
{
    private const MAX_DEPTH = 16;

    private function __construct(private readonly string $bytes, private int $offset)
    {
    }

    /** The one item the bytes hold; bytes left after it are refused. */
    public static function decode(string $bytes): mixed
    {
        $reader = new self($bytes, 0);
        $value = $reader->item(0);
        if ($reader->offset !== strlen($bytes)) {
            throw new Refused('cbor', 'The passkey sent bytes after its data.');
        }
        return $value;
    }

    /**
     * The item that starts at an offset, and the offset just past it: a key
     * inside authenticator data, which more bytes may follow.
     *
     * @return array{0: mixed, 1: int}
     */
    public static function decodeAt(string $bytes, int $offset): array
    {
        $reader = new self($bytes, $offset);
        $value = $reader->item(0);
        return [$value, $reader->offset];
    }

    private function item(int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw new Refused('cbor', 'The passkey data is nested too deeply.');
        }
        $initial = ord($this->take(1));
        $major = $initial >> 5;
        $info = $initial & 0x1f;
        if ($major === 7) {
            return $this->simple($info);
        }
        $argument = $this->argument($info);
        return match ($major) {
            0 => $argument,
            1 => -1 - $argument,
            2 => new CborBytes($this->take($argument)),
            3 => $this->text($argument),
            4 => $this->list($argument, $depth),
            5 => $this->map($argument, $depth),
            default => $this->item($depth + 1),
        };
    }

    private function argument(int $info): int
    {
        if ($info < 24) {
            return $info;
        }
        $size = match ($info) {
            24 => 1,
            25 => 2,
            26 => 4,
            27 => 8,
            default => throw new Refused('cbor', 'The passkey data uses a length CBOR allows but passkeys never send.'),
        };
        $value = 0;
        foreach (str_split($this->take($size)) as $byte) {
            if ($value > (PHP_INT_MAX >> 8)) {
                throw new Refused('cbor', 'A number in the passkey data is too large.');
            }
            $value = ($value << 8) | ord($byte);
        }
        return $value;
    }

    private function simple(int $info): ?bool
    {
        return match ($info) {
            20 => false,
            21 => true,
            22, 23 => null,
            default => throw new Refused('cbor', 'The passkey data holds a value passkeys never send.'),
        };
    }

    private function text(int $length): string
    {
        $text = $this->take($length);
        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new Refused('cbor', 'Text in the passkey data is not UTF-8.');
        }
        return $text;
    }

    /** @return list<mixed> */
    private function list(int $count, int $depth): array
    {
        $this->room($count);
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = $this->item($depth + 1);
        }
        return $items;
    }

    /** @return array<int|string, mixed> */
    private function map(int $count, int $depth): array
    {
        $this->room($count * 2);
        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            $key = $this->item($depth + 1);
            if (!is_int($key) && !is_string($key)) {
                throw new Refused('cbor', 'A key in the passkey data is neither a number nor text.');
            }
            if (array_key_exists($key, $entries)) {
                throw new Refused('cbor', 'A key appears twice in the passkey data.');
            }
            $entries[$key] = $this->item($depth + 1);
        }
        return $entries;
    }

    /** Every item takes at least a byte, so a count past the bytes left cannot be honest. */
    private function room(int $items): void
    {
        if ($items > strlen($this->bytes) - $this->offset) {
            throw new Refused('cbor', 'The passkey data is shorter than it says.');
        }
    }

    private function take(int $length): string
    {
        if ($length > strlen($this->bytes) - $this->offset) {
            throw new Refused('cbor', 'The passkey data is shorter than it says.');
        }
        $bytes = substr($this->bytes, $this->offset, $length);
        $this->offset += $length;
        return $bytes;
    }
}
