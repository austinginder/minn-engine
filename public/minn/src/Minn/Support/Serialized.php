<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * Tolerant readers for the serialized-PHP blobs WordPress stores. Nothing
 * here executes the blob; each reader scans for the one shape it needs.
 */
final class Serialized
{
    /** @var \WeakMap<object, array{class: string, keys: array<string, int|string>, hidden: list<array{0: int|string, 1: mixed}>}>|null records read back as a stdClass, by object */
    private static ?\WeakMap $records = null;
    /** The objects being encoded right now, outermost first, so a loop is seen. */
    private static ?\SplObjectStorage $open = null;

    /** The string values of a serialized string list. */
    public static function stringList(?string $blob): array
    {
        if ($blob === null || !str_starts_with($blob, 'a:')) {
            return [];
        }
        preg_match_all('/;s:\d+:"([^"]*)";/', $blob, $m);
        return $m[1];
    }

    /** A flat string list in the stored form. */
    public static function serializeStringList(array $values): string
    {
        $out = 'a:' . count($values) . ':{';
        $index = 0;
        foreach ($values as $value) {
            $out .= 'i:' . $index++ . ';s:' . strlen($value) . ':"' . $value . '";';
        }
        return $out . '}';
    }

    /** The first "field";value pair inside a blob: string, int, or float value as a string. */
    public static function field(?string $blob, string $field): ?string
    {
        if ($blob === null) {
            return null;
        }
        $quoted = preg_quote($field, '/');
        if (preg_match('/s:' . strlen($field) . ':"' . $quoted . '";(?:s:\d+:"([^"]*)"|i:(-?\d+)|d:([0-9.Ee+-]+))/', $blob, $m)) {
            return ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? $m[3] ?? '');
        }
        return null;
    }

    /** Returned by decode() when the blob is not a serialized value the reader accepts. */
    public const INVALID = "\0minn:invalid\0";

    /**
     * A serialized scalar or array as PHP data, parsed by this reader:
     * strings, integers, floats, booleans, null, and arrays of those. An
     * object record becomes a stdClass of its properties, or whatever the
     * reviver makes of the class name and those properties; nothing here
     * instantiates anything itself. A blob with trailing bytes or a
     * malformed shape is INVALID.
     *
     * @param ?\Closure(string, \stdClass): object $revive
     */
    public static function decode(string $blob, ?\Closure $revive = null): mixed
    {
        if ($blob === '' || !preg_match('/^[sidbNaO]:|^N;/', $blob)) {
            return self::INVALID;
        }
        $offset = 0;
        try {
            $value = self::read($blob, $offset, $revive);
        } catch (\ValueError) {
            return self::INVALID;
        }
        return $offset === strlen($blob) ? $value : self::INVALID;
    }

    private static function read(string $blob, int &$offset, ?\Closure $revive): mixed
    {
        $type = $blob[$offset] ?? '';
        $offset++;
        switch ($type) {
            case 'N':
                self::expect($blob, $offset, ';');
                return null;
            case 'b':
                self::expect($blob, $offset, ':');
                $digit = self::until($blob, $offset, ';');
                return $digit === '1';
            case 'i':
                self::expect($blob, $offset, ':');
                return (int) self::until($blob, $offset, ';');
            case 'd':
                self::expect($blob, $offset, ':');
                return (float) self::until($blob, $offset, ';');
            case 's':
                self::expect($blob, $offset, ':');
                $length = (int) self::until($blob, $offset, ':');
                self::expect($blob, $offset, '"');
                $string = substr($blob, $offset, $length);
                $offset += $length;
                self::expect($blob, $offset, '"');
                self::expect($blob, $offset, ';');
                return $string;
            case 'O':
                return self::readObject($blob, $offset, $revive);
            case 'a':
                self::expect($blob, $offset, ':');
                $count = (int) self::until($blob, $offset, ':');
                self::expect($blob, $offset, '{');
                $array = [];
                for ($i = 0; $i < $count; $i++) {
                    $key = self::read($blob, $offset, $revive);
                    if (!is_int($key) && !is_string($key)) {
                        throw new \ValueError('key');
                    }
                    $array[$key] = self::read($blob, $offset, $revive);
                }
                self::expect($blob, $offset, '}');
                return $array;
            default:
                throw new \ValueError('type');
        }
    }

    /**
     * An object record. The properties land on a plain stdClass; only the
     * reviver may turn that into something else, and only for a class it
     * knows. A record it leaves alone keeps its class name and its stored
     * property names (visibility markers, numbered parts, a private name a
     * parent class shares) on the side, so encode() writes it back as it
     * was read instead of as a stdClass.
     */
    private static function readObject(string $blob, int &$offset, ?\Closure $revive): object
    {
        self::expect($blob, $offset, ':');
        $nameLength = (int) self::until($blob, $offset, ':');
        self::expect($blob, $offset, '"');
        $class = substr($blob, $offset, $nameLength);
        $offset += $nameLength;
        self::expect($blob, $offset, '"');
        self::expect($blob, $offset, ':');
        $count = (int) self::until($blob, $offset, ':');
        self::expect($blob, $offset, '{');
        $object = new \stdClass();
        $keys = [];
        $hidden = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = self::read($blob, $offset, $revive);
            if (!is_string($raw) && !is_int($raw)) {
                throw new \ValueError('key');
            }
            // A class with its own serialization (ArrayObject) numbers its parts: i:0 the flags, i:1 the storage.
            $name = is_int($raw) ? (string) $raw : (str_starts_with($raw, "\0") ? (string) substr($raw, (int) strrpos($raw, "\0") + 1) : $raw);
            $value = self::read($blob, $offset, $revive);
            if (property_exists($object, $name)) {
                $hidden[] = [$raw, $value];
                continue;
            }
            $object->{$name} = $value;
            if ($raw !== $name) {
                $keys[$name] = $raw;
            }
        }
        self::expect($blob, $offset, '}');
        $result = $revive === null ? $object : $revive($class, $object);
        if ($result === $object && $class !== 'stdClass') {
            self::$records ??= new \WeakMap();
            self::$records[$object] = ['class' => $class, 'keys' => $keys, 'hidden' => $hidden];
        }
        return $result;
    }

    /** The class an object was stored as, when it came back as a stdClass of its properties. */
    public static function storedClass(object $object): ?string
    {
        return self::$records !== null && isset(self::$records[$object]) ? self::$records[$object]['class'] : null;
    }

    /**
     * A stdClass, or a record read back as one, in PHP's own form: the
     * stored class name and property names where there are some, property
     * names as strings otherwise, each value through encode() so a record
     * nested inside keeps its class too.
     *
     * @param array{class: string, keys: array<string, int|string>, hidden: list<array{0: int|string, 1: mixed}>} $record
     */
    private static function encodeRecord(object $object, array $record): string
    {
        $body = '';
        $count = 0;
        foreach (get_object_vars($object) as $name => $item) {
            $key = $record['keys'][(string) $name] ?? (string) $name;
            $body .= self::encode($key) . self::encode($item);
            $count++;
        }
        foreach ($record['hidden'] as [$key, $item]) {
            $body .= self::encode($key) . self::encode($item);
            $count++;
        }
        return 'O:' . strlen($record['class']) . ':"' . $record['class'] . '":' . $count . ':{' . $body . '}';
    }

    /** PHP's array wrappers in their own form (flags, storage, members, iterator class), the storage through encode(). */
    private static function encodeWrapper(\ArrayObject|\ArrayIterator $wrapper): string
    {
        $iterator = $wrapper instanceof \ArrayObject && $wrapper->getIteratorClass() !== \ArrayIterator::class ? self::encode($wrapper->getIteratorClass()) : 'N;';
        $class = get_class($wrapper);
        return 'O:' . strlen($class) . ':"' . $class . '":4:{i:0;i:' . $wrapper->getFlags() . ';i:1;' . self::encode($wrapper->getArrayCopy()) . 'i:2;a:0:{}i:3;' . $iterator . '}';
    }

    private static function expect(string $blob, int &$offset, string $char): void
    {
        if (($blob[$offset] ?? '') !== $char) {
            throw new \ValueError('shape');
        }
        $offset++;
    }

    private static function until(string $blob, int &$offset, string $char): string
    {
        $end = strpos($blob, $char, $offset);
        if ($end === false) {
            throw new \ValueError('shape');
        }
        $value = substr($blob, $offset, $end - $offset);
        $offset = $end + 1;
        return $value;
    }

    /**
     * PHP's serialize() for the values decode() accepts: null, bool, int,
     * float, string, arrays of those, and objects written as their class.
     */
    public static function encode(mixed $value): string
    {
        if (self::$open !== null) {
            return self::encodeValue($value);
        }
        // A graph that loops back on itself (an object holding itself, or an
        // ancestor) has no form but PHP's own, which writes the loop as a
        // back-reference; the whole value goes through it then.
        self::$open = new \SplObjectStorage();
        try {
            return self::encodeValue($value);
        } catch (\UnexpectedValueException) {
            return serialize($value);
        } finally {
            self::$open = null;
        }
    }

    private static function encodeValue(mixed $value): string
    {
        if ($value === null) {
            return 'N;';
        }
        if (is_bool($value)) {
            return $value ? 'b:1;' : 'b:0;';
        }
        if (is_int($value)) {
            return 'i:' . $value . ';';
        }
        if (is_float($value)) {
            // PHP's own serializer writes the shortest round-tripping form (serialize_precision -1);
            // a string cast would round to "precision" digits and change a stored value on rewrite.
            return serialize($value);
        }
        if (is_string($value)) {
            return 's:' . strlen($value) . ':"' . $value . '";';
        }
        if (is_array($value)) {
            $body = '';
            foreach ($value as $key => $item) {
                $body .= self::encode(is_int($key) ? $key : (string) $key) . self::encode($item);
            }
            return 'a:' . count($value) . ':{' . $body . '}';
        }
        if (is_object($value)) {
            return self::encodeObject($value);
        }
        throw new \ValueError('encode');
    }

    /**
     * Plugin code stores objects (maybe_serialize's contract). A record read
     * back as a stdClass is written under the class it was stored as; a
     * stdClass and PHP's array wrappers are written here so a record inside
     * them keeps its class too; any other object goes through PHP's own
     * serializer, which runs nothing and matches the reference byte for
     * byte. Only reading is the hazard, and reads stay on decode().
     */
    private static function encodeObject(object $value): string
    {
        $class = get_class($value);
        $record = self::$records !== null && isset(self::$records[$value]) ? self::$records[$value] : null;
        $plainWrapper = in_array($class, [\ArrayObject::class, \ArrayIterator::class, \RecursiveArrayIterator::class], true) && get_object_vars($value) === [];
        if ($record === null && $class !== \stdClass::class && !$plainWrapper) {
            return serialize($value);
        }
        if (self::$open !== null && self::$open->contains($value)) {
            throw new \UnexpectedValueException('cycle');
        }
        self::$open?->attach($value);
        try {
            return $plainWrapper ? self::encodeWrapper($value) : self::encodeRecord($value, $record ?? ['class' => $class, 'keys' => [], 'hidden' => []]);
        } finally {
            self::$open?->detach($value);
        }
    }

    /** The integer values of a serialized list such as sticky_posts. */
    public static function intList(?string $blob): array
    {
        if ($blob === null || $blob === '' || !preg_match_all('/i:\d+;i:(\d+);/', $blob, $m)) {
            return [];
        }
        return array_map(intval(...), $m[1]);
    }
}
