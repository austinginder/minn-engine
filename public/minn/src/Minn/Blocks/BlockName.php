<?php

declare(strict_types=1);

namespace Minn\Blocks;

use Minn\Runtime\Refusal;

/** The block name rules: a string, lower-case, `namespace/name`. */
final class BlockName
{
    public static function refuse(mixed $name): ?Refusal
    {
        if (!is_string($name)) {
            return new Refusal('block_name_type', 'Block type names must be strings.');
        }
        if (preg_match('/[A-Z]+/', $name)) {
            return new Refusal('block_name_case', 'Block type names must not contain uppercase characters.');
        }
        if (!preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/', $name)) {
            return new Refusal('block_name_namespace', 'Block type names must contain a namespace prefix. Example: my-plugin/my-custom-block-type');
        }
        return null;
    }
}
