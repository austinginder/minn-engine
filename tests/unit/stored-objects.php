<?php

declare(strict_types=1);

use Minn\Runtime\StoredObjects;
use Minn\Support\Serialized;

/**
 * Object records in a serialized blob: the reader never instantiates, the
 * registry decides what a known class becomes, a plain data class comes back
 * as itself, and everything else stays a stdClass of its properties (the
 * reference would hand back an incomplete class for a missing one, which
 * nothing can use, and revive one that runs code, which the engine will not).
 */
$post = 'O:7:"WP_Post":2:{s:2:"ID";i:5;s:9:"post_name";s:5:"hello";}';
$private = 'O:3:"Box":2:{s:10:"' . "\0Box\0" . 'inner";s:1:"x";s:7:"' . "\0*\0" . 'prot";i:2;}';
StoredObjects::reset();
StoredObjects::register('WP_Post', static fn (stdClass $properties): ArrayObject => new ArrayObject((array) $properties));

// Plain data classes a plugin keeps settings in, and ones that run code as they are revived or let go.
if (!class_exists('ZzUnitPlainBase', false)) {
    class ZzUnitPlainBase
    {
        private string $secret = 'base';
        public function baseSecret(): string
        {
            return $this->secret;
        }
    }
    final class ZzUnitPlain extends ZzUnitPlainBase
    {
        public string $name = '';
        public int $count = 0;
        public array $list = [];
        public ?ZzUnitPlain $child = null;
        protected string $guarded = '';
        private string $secret = '';
        public readonly string $fixed;
        public function __construct()
        {
            throw new LogicException('a stored object is revived without its constructor');
        }
        public function parts(): array
        {
            return [$this->name, $this->count, $this->list, $this->child?->name, $this->guarded, $this->secret, $this->fixed, $this->baseSecret()];
        }
    }
    final class ZzUnitWakes
    {
        public string $name = '';
        public function __wakeup(): void
        {
        }
    }
    final class ZzUnitUnserializes
    {
        public string $name = '';
        public function __unserialize(array $data): void
        {
        }
    }
    final class ZzUnitDestructs
    {
        public string $name = '';
        public function __destruct()
        {
        }
    }
}
$plainBlob = static function (array $props, string $class = 'ZzUnitPlain'): string {
    $out = '';
    foreach ($props as $key => $value) {
        $out .= serialize($key) . serialize($value);
    }
    return 'O:' . strlen($class) . ':"' . $class . '":' . count($props) . ':{' . $out . '}';
};

return [
    'a plain data object comes back as its class, every property set, its constructor not run' => static function () use ($plainBlob): bool|string {
        $child = $plainBlob(['name' => 'kid']);
        $blob = $plainBlob(['name' => 'a', 'count' => 3, 'list' => [1, 'x'], "\0*\0guarded" => 'g', "\0ZzUnitPlain\0secret" => 's', 'fixed' => 'f', "\0ZzUnitPlainBase\0secret" => 'b']);
        $blob = substr($blob, 0, -1) . serialize('child') . $child . '}';
        $blob = (string) preg_replace('/^O:11:"ZzUnitPlain":7:/', 'O:11:"ZzUnitPlain":8:', $blob);
        $value = Serialized::decode($blob, StoredObjects::reviver());
        return $value instanceof ZzUnitPlain && $value->parts() === ['a', 3, [1, 'x'], 'kid', 'g', 's', 'f', 'b'] ? true : var_export($value, true);
    },
    'a revived plain object writes back byte for byte' => static function (): bool|string {
        $object = (new ReflectionClass(ZzUnitPlain::class))->newInstanceWithoutConstructor();
        $object->name = 'n';
        $blob = serialize($object);
        return Serialized::encode(Serialized::decode($blob, StoredObjects::reviver())) === $blob ? true : 'differs';
    },
    'a class that runs code as it is revived or let go stays a stdClass' => static function () use ($plainBlob): bool|string {
        foreach (['ZzUnitWakes', 'ZzUnitUnserializes', 'ZzUnitDestructs'] as $class) {
            $value = Serialized::decode($plainBlob(['name' => 'n'], $class), StoredObjects::reviver());
            if (!$value instanceof stdClass || $value->name !== 'n') {
                return $class;
            }
        }
        return true;
    },
    "the engine's own classes, PHP's own, and a name no class may have stay records" => static function () use ($plainBlob): bool|string {
        foreach (['Minn\\Content\\PostRecord', 'DateTimeZone', 'Zz Unit Bad', 'ZzUnitMissingClass'] as $class) {
            $value = Serialized::decode($plainBlob(['name' => 'n'], $class), StoredObjects::reviver());
            if (!$value instanceof stdClass) {
                return $class;
            }
        }
        return true;
    },
    'a value that does not fit its typed property, or a private name of a class it does not extend, keeps it a stdClass' => static function () use ($plainBlob): bool|string {
        $typed = Serialized::decode($plainBlob(['count' => 'many']), StoredObjects::reviver());
        $foreign = Serialized::decode($plainBlob(["\0ZzUnitWakes\0name" => 'n']), StoredObjects::reviver());
        return $typed instanceof stdClass && $foreign instanceof stdClass ? true : var_export([$typed, $foreign], true);
    },
    'without a reviver an object record is a stdClass of its properties' => static function () use ($post): bool {
        $value = Serialized::decode($post);
        return $value instanceof stdClass && $value->ID === 5 && $value->post_name === 'hello';
    },
    'a registered class comes back through its factory' => static function () use ($post): bool {
        $value = Serialized::decode($post, StoredObjects::reviver());
        return $value instanceof ArrayObject && $value['ID'] === 5;
    },
    'a record nested in an array revives too' => static function () use ($post): bool {
        $value = Serialized::decode('a:1:{s:1:"p";' . $post . '}', StoredObjects::reviver());
        return is_array($value) && $value['p'] instanceof ArrayObject && $value['p']['post_name'] === 'hello';
    },
    'the class name is matched without regard to case' => static function () use ($post): bool {
        $value = Serialized::decode(str_replace('"WP_Post"', '"wp_post"', $post), StoredObjects::reviver());
        return $value instanceof ArrayObject;
    },
    'an unregistered class stays a stdClass' => static function (): bool {
        $value = Serialized::decode('O:3:"Foo":1:{s:1:"k";s:1:"v";}', StoredObjects::reviver());
        return $value instanceof stdClass && $value->k === 'v';
    },
    'private and protected property names lose their markers' => static function () use ($private): bool {
        $value = Serialized::decode($private);
        return $value instanceof stdClass && $value->inner === 'x' && $value->prot === 2;
    },
    "PHP's array wrappers come back as themselves and write back byte for byte" => static function (): bool {
        $blobs = [
            'O:11:"ArrayObject":4:{i:0;i:0;i:1;a:1:{s:1:"a";i:1;}i:2;a:0:{}i:3;N;}',
            'O:11:"ArrayObject":4:{i:0;i:2;i:1;a:1:{s:1:"x";i:1;}i:2;a:0:{}i:3;N;}',
            'O:13:"ArrayIterator":4:{i:0;i:0;i:1;a:2:{i:0;i:1;i:1;i:2;}i:2;a:0:{}i:3;N;}',
            'a:1:{s:4:"vars";O:11:"ArrayObject":4:{i:0;i:0;i:1;a:1:{s:6:"apikey";s:3:"abc";}i:2;a:0:{}i:3;N;}}',
        ];
        foreach ($blobs as $blob) {
            if (Serialized::encode(Serialized::decode($blob, StoredObjects::reviver())) !== $blob) {
                return false;
            }
        }
        $value = Serialized::decode($blobs[0], StoredObjects::reviver());
        return $value instanceof ArrayObject && $value['a'] === 1;
    },
    'a wrapper carrying member properties stays a record of its numbered parts' => static function (): bool {
        $value = Serialized::decode('O:11:"ArrayObject":4:{i:0;i:0;i:1;a:0:{}i:2;a:1:{s:1:"m";i:5;}i:3;N;}', StoredObjects::reviver());
        return $value instanceof stdClass && $value->{'2'} === ['m' => 5];
    },
    "a record of a class nobody revives keeps its class and its property names when written back" => static function (): bool {
        $box = 'O:3:"Box":3:{s:1:"a";i:1;s:4:"' . "\0*\0" . 'b";i:2;s:6:"' . "\0Box\0" . 'c";i:3;}';
        $child = 'O:5:"Child":4:{s:1:"a";i:1;s:4:"' . "\0*\0" . 'b";i:2;s:6:"' . "\0Box\0" . 'c";i:3;s:8:"' . "\0Child\0" . 'c";i:4;}';
        $plan = 'O:14:"FS_Plugin_Plan":2:{s:2:"id";s:3:"123";s:4:"name";s:4:"free";}';
        foreach ([$box, $child, $plan, 'a:2:{s:4:"plan";' . $plan . 's:3:"obj";O:8:"stdClass":1:{s:4:"plan";' . $plan . '}}'] as $blob) {
            if (Serialized::encode(Serialized::decode($blob, StoredObjects::reviver())) !== $blob) {
                return false;
            }
        }
        $value = Serialized::decode($plan, StoredObjects::reviver());
        return $value instanceof stdClass && Serialized::storedClass($value) === 'FS_Plugin_Plan' && $value->name === 'free';
    },
    'a record a plugin changed is still written as its class' => static function (): bool {
        $value = Serialized::decode('O:3:"Box":2:{s:1:"a";i:1;s:4:"' . "\0*\0" . 'b";i:2;}');
        $value->a = 5;
        $value->z = 'new';
        return Serialized::encode($value) === 'O:3:"Box":3:{s:1:"a";i:5;s:4:"' . "\0*\0" . 'b";i:2;s:1:"z";s:3:"new";}';
    },
    "a stdClass is written as PHP writes it, numbered names as strings" => static function (): bool {
        $o = new stdClass();
        $o->{'0'} = 'a';
        $o->b = 1.5;
        $o->c = null;
        $o->d = [1, (object) ['x' => true]];
        return Serialized::encode($o) === serialize($o) && Serialized::encode((object) ['0' => 'a', '1' => 'b']) === serialize((object) ['0' => 'a', '1' => 'b']);
    },
    'a record inside an ArrayObject keeps its class' => static function (): bool {
        $blob = 'O:11:"ArrayObject":4:{i:0;i:0;i:1;a:1:{s:1:"k";O:3:"Foo":1:{s:1:"x";i:1;}}i:2;a:0:{}i:3;N;}';
        $wrapper = new ArrayObject([], 0, 'RecursiveArrayIterator');
        return Serialized::encode(Serialized::decode($blob, StoredObjects::reviver())) === $blob && Serialized::encode($wrapper) === serialize($wrapper);
    },
    'the registry says what it knows' => static fn (): bool => StoredObjects::knows('wp_post') && !StoredObjects::knows('WP_User'),
    'an object is written as its class, nested or not' => static fn (): bool => Serialized::encode(['p' => (object) ['a' => 1]]) === 'a:1:{s:1:"p";O:8:"stdClass":1:{s:1:"a";i:1;}}' && Serialized::encode((object) ['a' => 1]) === 'O:8:"stdClass":1:{s:1:"a";i:1;}',
];
