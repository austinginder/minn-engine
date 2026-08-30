<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;

/**
 * block.json to the settings a block type registers with: the property
 * renames, the script and style handles (registered through the closures,
 * one per entry), the view script modules, the block hooks positions, and
 * the render template as a callback. Behaviour pinned by contracts/fixtures/api/blocks.json.
 */
final class BlockMetadata
{
    private const PROPERTIES = ['apiVersion' => 'api_version', 'name' => 'name', 'title' => 'title', 'category' => 'category', 'parent' => 'parent', 'ancestor' => 'ancestor', 'icon' => 'icon', 'description' => 'description', 'keywords' => 'keywords', 'attributes' => 'attributes', 'providesContext' => 'provides_context', 'usesContext' => 'uses_context', 'selectors' => 'selectors', 'supports' => 'supports', 'styles' => 'styles', 'variations' => 'variations', 'example' => 'example', 'allowedBlocks' => 'allowed_blocks'];
    private const SCRIPTS = ['editorScript' => 'editor_script_handles', 'script' => 'script_handles', 'viewScript' => 'view_script_handles'];
    private const STYLES = ['editorStyle' => 'editor_style_handles', 'style' => 'style_handles', 'viewStyle' => 'view_style_handles'];
    private const POSITIONS = ['before' => 'before', 'after' => 'after', 'firstChild' => 'first_child', 'lastChild' => 'last_child'];

    /**
     * @param Closure(array, string, int): (string|false) $scriptHandle registers one script entry, answering its handle
     * @param Closure(array, string, int): (string|false) $styleHandle registers one style entry
     * @param Closure(array, string, int): (string|false) $moduleId registers one view script module entry
     * @param Closure(string): ?Closure $render the render callback for a template path, or null when the file is missing
     * @return array<string, mixed>
     */
    public static function settings(array $metadata, Closure $scriptHandle, Closure $styleHandle, Closure $moduleId, Closure $render): array
    {
        $settings = [];
        foreach (self::PROPERTIES as $key => $mapped) {
            if (isset($metadata[$key])) {
                $settings[$mapped] = $metadata[$key];
            }
        }
        foreach (self::SCRIPTS as $field => $target) {
            $settings[$target] = self::handles($metadata, $field, $scriptHandle);
        }
        foreach (self::STYLES as $field => $target) {
            $settings[$target] = self::handles($metadata, $field, $styleHandle);
        }
        foreach (array_merge(self::SCRIPTS, self::STYLES) as $target) {
            if ($settings[$target] === null) {
                unset($settings[$target]);
            }
        }
        if (!empty($metadata['viewScriptModule'])) {
            $ids = [];
            foreach (array_values((array) $metadata['viewScriptModule']) as $index => $value) {
                $id = $moduleId($metadata + ['viewScriptModule' => $value], 'viewScriptModule', $index);
                if ($id !== false) {
                    $ids[] = $id;
                }
            }
            $settings['view_script_module_ids'] = $ids;
        }
        if (!empty($metadata['blockHooks'])) {
            $settings['block_hooks'] = [];
            foreach ($metadata['blockHooks'] as $anchor => $position) {
                if (isset(self::POSITIONS[$position])) {
                    $settings['block_hooks'][$anchor] = self::POSITIONS[$position];
                }
            }
        }
        if (!empty($metadata['render'])) {
            $callback = $render((string) $metadata['render']);
            if ($callback !== null) {
                $settings['render_callback'] = $callback;
            }
        }
        return $settings;
    }

    /** The registered handles for one asset field: every entry when the field lists several; null when the field is absent. @return list<string>|null */
    private static function handles(array $metadata, string $field, Closure $register): ?array
    {
        if (empty($metadata[$field])) {
            return null;
        }
        $entries = is_array($metadata[$field]) ? array_values($metadata[$field]) : [$metadata[$field]];
        $handles = [];
        foreach ($entries as $index => $entry) {
            $result = $register($metadata, $field, is_array($metadata[$field]) ? $index : 0);
            if ($result) {
                $handles[] = $result;
            }
        }
        return $handles;
    }

    private const FIELD_HANDLES = ['editorScript' => 'editor-script', 'editorStyle' => 'editor-style', 'script' => 'script', 'style' => 'style', 'viewScript' => 'view-script', 'viewScriptModule' => 'view-script-module', 'viewStyle' => 'view-style'];

    /** The script or style handle a block.json field registers under; core blocks keep the `wp-block-` spelling. */
    public static function assetHandle(string $block, string $field, int $index = 0): string
    {
        if (str_starts_with($block, 'core/')) {
            $handle = str_replace('core/', 'wp-block-', $block)
                . (str_starts_with($field, 'editor') ? '-editor' : '')
                . (str_starts_with($field, 'view') ? '-view' : '')
                . (str_ends_with(strtolower($field), 'scriptmodule') ? '-script-module' : '');
        } else {
            $handle = str_replace('/', '-', $block) . '-' . (self::FIELD_HANDLES[$field] ?? $field);
        }
        return $index > 0 ? $handle . '-' . ($index + 1) : $handle;
    }
}
