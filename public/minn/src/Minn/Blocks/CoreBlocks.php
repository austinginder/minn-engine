<?php

declare(strict_types=1);

namespace Minn\Blocks;

/**
 * The core block types' metadata as the reference registers them
 * (data/blocks.json): their supports and selectors, read once.
 */
final class CoreBlocks
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $metadata = null;

    /**
     * A core block type's registered metadata; empty for a name core does not register.
     *
     * @return array<string, mixed>
     */
    public static function metadata(string $name): array
    {
        self::$metadata ??= (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/blocks.json'), true);
        return (array) (self::$metadata[$name] ?? []);
    }

    /**
     * A core block type's supports.
     *
     * @return array<string, mixed>
     */
    public static function supports(string $name): array
    {
        return (array) (self::metadata($name)['supports'] ?? []);
    }
}
