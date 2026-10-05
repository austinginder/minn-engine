<?php

declare(strict_types=1);

use Minn\Runtime\StoredObjects;
use Minn\Support\Serialized;

/**
 * Object records in a serialized blob: the reader never instantiates, the
 * registry decides what a known class becomes, and everything else stays a
 * stdClass of its properties (the reference would hand back an incomplete
 * class there, which nothing can use).
 */
$post = 'O:7:"WP_Post":2:{s:2:"ID";i:5;s:9:"post_name";s:5:"hello";}';
$private = 'O:3:"Box":2:{s:10:"' . "\0Box\0" . 'inner";s:1:"x";s:7:"' . "\0*\0" . 'prot";i:2;}';
StoredObjects::reset();
StoredObjects::register('WP_Post', static fn (stdClass $properties): ArrayObject => new ArrayObject((array) $properties));

return [
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
    'the registry says what it knows' => static fn (): bool => StoredObjects::knows('wp_post') && !StoredObjects::knows('WP_User'),
    'an object is written as its class, nested or not' => static fn (): bool => Serialized::encode(['p' => (object) ['a' => 1]]) === 'a:1:{s:1:"p";O:8:"stdClass":1:{s:1:"a";i:1;}}' && Serialized::encode((object) ['a' => 1]) === 'O:8:"stdClass":1:{s:1:"a";i:1;}',
];
