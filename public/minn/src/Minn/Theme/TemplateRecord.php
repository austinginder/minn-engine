<?php

declare(strict_types=1);

namespace Minn\Theme;

/**
 * One block template or template part, whatever it came from: a theme
 * file, a row the site editor saved, or a template a plugin registered.
 * The id every caller uses is "<theme>//<slug>" in all three cases.
 */
final readonly class TemplateRecord
{
    public function __construct(
        public string $theme,
        public string $slug,
        public string $type,
        public string $content,
        public string $title,
        public string $description,
        public string $source,
        public ?string $origin,
        public bool $hasThemeFile,
        public int $author,
        public ?string $modified,
        public ?string $date,
        public int $wpId,
        public string $status = 'publish',
        public ?string $area = null,
        public ?string $plugin = null,
    ) {
    }

    public function id(): string
    {
        return "{$this->theme}//{$this->slug}";
    }

    public function isPart(): bool
    {
        return $this->type === 'wp_template_part';
    }
}
