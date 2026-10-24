<?php

declare(strict_types=1);

namespace Minn\Extension;

use Minn\Runtime\Handoff;
use Minn\Runtime\Runtime;

/**
 * Where the engine looks for the seams an extension registered. The
 * request's runtime holds them, so nothing here keeps state of its own
 * and a request cannot inherit the extensions of the one before it.
 * Before the front or a REST request has loaded any (the command line
 * never does) there are none.
 */
final class Extensions
{
    /** Installs the seams for this request. */
    public static function set(Seams $seams): void
    {
        self::install(new SeamRunner($seams, $seams->registrations()));
    }

    /**
     * Installs only the extensions' blocks, for a REST request: its answers
     * (content.rendered, the editor's previews) draw them, while the filters
     * and the rest stay with the themed front.
     */
    public static function setBlocks(Seams $seams): void
    {
        self::install(new SeamRunner($seams, $seams->registrations()->onlyBlocks()));
    }

    /**
     * The runner, and each extension block registered as a dynamic block
     * type, so the block-type registry (wp/v2/block-types, Minn Admin's
     * inspector and inserter) lists it and the renderer draws it. The
     * callback asks the runner of the request it renders in, so a type
     * registered once never holds an earlier request's extension.
     */
    private static function install(SeamRunner $runner): void
    {
        Runtime::current()->useSeams($runner);
        if (!Handoff::bound('blockTypes')) {
            return;
        }
        $registry = Handoff::blockTypes();
        foreach ($runner->blockTypes() as $name => $metadata) {
            if ($registry->is_registered($name)) {
                continue;
            }
            $render = static fn (array $attributes, string $content = ''): string => self::runner()?->renderBlock($name, $attributes, $content) ?? '';
            $registry->register($name, self::typeArgs($metadata) + ['render_callback' => $render]);
        }
    }

    /**
     * A block.json's keys as a block type's properties: the camelCase keys
     * become the snake_case properties they fill, and the keys that only
     * name files for a build step (scripts, styles, render) are left out.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private static function typeArgs(array $metadata): array
    {
        $renamed = ['apiVersion' => 'api_version', 'allowedBlocks' => 'allowed_blocks', 'usesContext' => 'uses_context', 'providesContext' => 'provides_context'];
        $kept = ['title', 'category', 'parent', 'ancestor', 'icon', 'description', 'keywords', 'textdomain', 'styles', 'variations', 'selectors', 'supports', 'example', 'attributes'];
        $args = [];
        foreach ($metadata as $key => $value) {
            if (isset($renamed[$key])) {
                $args[$renamed[$key]] = $value;
            } elseif (in_array($key, $kept, true)) {
                $args[$key] = $value;
            }
        }
        return $args;
    }

    /** The engine side of the seams, if any. */
    public static function runner(): ?SeamRunner
    {
        return Runtime::booted() ? Runtime::current()->seams() : null;
    }
}
