<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The block pattern, pattern category, and block style registries as data.
 * Entries registered after init are remembered separately, because the
 * editor asks for those on their own.
 */
final class Patterns
{
    /** @var array<string, array<string, mixed>> */
    private array $patterns = [];
    /** @var array<string, array<string, mixed>> */
    private array $patternsAfterInit = [];
    /** @var array<string, array<string, mixed>> */
    private array $categories = [];
    /** @var array<string, array<string, mixed>> */
    private array $categoriesAfterInit = [];
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $styles = [];

    /** Registers a block pattern, or the refusal. */
    public function registerPattern(mixed $name, mixed $properties): ?Refusal
    {
        $afterInit = Runtime::hooks()->actionsDone('init') > 0;
        if (!is_string($name)) {
            return new Refusal('pattern_name', 'Pattern name must be a string.');
        }
        if (!is_array($properties) || !is_string($properties['title'] ?? null)) {
            return new Refusal('pattern_title', 'Pattern title must be a string.');
        }
        if (!isset($properties['filePath']) && !is_string($properties['content'] ?? null)) {
            return new Refusal('pattern_content', 'Pattern content must be a string.');
        }
        $pattern = array_merge($properties, ['name' => $name]);
        $this->patterns[$name] = $pattern;
        if ($afterInit) {
            $this->patternsAfterInit[$name] = $pattern;
        }
        return null;
    }

    /** Forgets a pattern. */
    public function unregisterPattern(string $name): bool
    {
        if (!isset($this->patterns[$name])) {
            return false;
        }
        unset($this->patterns[$name], $this->patternsAfterInit[$name]);
        return true;
    }

    /** One pattern, or null. */
    public function pattern(string $name): ?array
    {
        return $this->patterns[$name] ?? null;
    }

    /**
     * Every pattern.
     *
     * @return list<array<string, mixed>>
     */
    public function patterns(): array
    {
        return array_values($this->patterns);
    }

    /** The patterns registered after init, which the editor lists separately. */
    public function patternsAfterInit(): array
    {
        return array_values($this->patternsAfterInit);
    }

    /** Registers a pattern category, or the refusal. */
    public function registerCategory(mixed $name, mixed $properties): ?Refusal
    {
        $afterInit = Runtime::hooks()->actionsDone('init') > 0;
        if (!is_string($name)) {
            return new Refusal('category_name', 'Block pattern category name must be a string.');
        }
        $category = array_merge(['name' => $name], (array) $properties);
        $this->categories[$name] = $category;
        if ($afterInit) {
            $this->categoriesAfterInit[$name] = $category;
        }
        return null;
    }

    /** Forgets a category. */
    public function unregisterCategory(string $name): bool
    {
        if (!isset($this->categories[$name])) {
            return false;
        }
        unset($this->categories[$name], $this->categoriesAfterInit[$name]);
        return true;
    }

    /** One category, or null. */
    public function category(string $name): ?array
    {
        return $this->categories[$name] ?? null;
    }

    /**
     * Every category.
     *
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        return array_values($this->categories);
    }

    /** The categories registered after init. */
    public function categoriesAfterInit(): array
    {
        return array_values($this->categoriesAfterInit);
    }

    /**
     * Registers a block style, or the refusal.
     *
     * @param string|list<string> $blocks
     */
    public function registerStyle(mixed $blocks, mixed $properties): ?Refusal
    {
        if (!is_string($blocks) && !is_array($blocks)) {
            return new Refusal('style_block', 'Block name must be a string or array.');
        }
        if (!is_array($properties) || !is_string($properties['name'] ?? null)) {
            return new Refusal('style_name', 'Block style name must be a string.');
        }
        if (str_contains($properties['name'], ' ')) {
            return new Refusal('style_name_spaces', 'Block style name must not contain any spaces.');
        }
        foreach ((array) $blocks as $block) {
            $this->styles[$block][$properties['name']] = $properties;
        }
        return null;
    }

    /** Forgets a block style. */
    public function unregisterStyle(string $block, string $style): bool
    {
        if (!isset($this->styles[$block][$style])) {
            return false;
        }
        unset($this->styles[$block][$style]);
        return true;
    }

    /** One block style, or null. */
    public function style(string $block, string $style): ?array
    {
        return $this->styles[$block][$style] ?? null;
    }

    /**
     * Every block style, or one block's.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function styles(?string $block = null): array
    {
        return $block === null ? $this->styles : ($this->styles[$block] ?? []);
    }
}
