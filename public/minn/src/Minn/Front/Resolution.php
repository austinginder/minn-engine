<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\UserRecord;
use Minn\Content\PostRecord;

/**
 * The outcome of resolving a public URL: which kind of thing it names,
 * the record behind it, and the page number for paginated views. Redirects
 * carry their target instead.
 */
final readonly class Resolution
{
    /**
     * @param array<string, mixed>|PostRecord|UserRecord|null $record the post, term, or user row
     * @param array{0: int, 1: ?int, 2: ?int}|null $date year, month, day
     */
    private function __construct(
        public Kind $kind,
        public array|PostRecord|UserRecord|null $record = null,
        public int $paged = 1,
        public ?string $location = null,
        public int $status = 200,
        public ?string $search = null,
        public ?array $date = null,
        public ?string $authorName = null,
        /** the static front page (show_on_front = page), rendered as a page that is also home */
        public bool $front = false,
        /** a preview: the reader's autosave replaces the stored content */
        public bool $preview = false,
        /** the posts page (page_for_posts): the record is the page, the listing is the blog */
        public bool $postsPage = false,
    ) {
    }

    public function asPreview(): self
    {
        return new self($this->kind, $this->record, $this->paged, $this->location, $this->status, $this->search, $this->date, $this->authorName, $this->front, true, $this->postsPage);
    }

    public static function home(int $paged = 1): self
    {
        return new self(Kind::Home, paged: $paged);
    }

    public static function single(PostRecord $post, int $paged = 1): self
    {
        return new self($post->isPage() ? Kind::Page : Kind::Single, $post, $paged);
    }

    public static function frontPage(PostRecord $page, int $paged = 1): self
    {
        return new self(Kind::Page, $page, $paged, front: true);
    }

    /** The page that stands for the blog: a home listing whose record is the page. */
    public static function postsPage(PostRecord $page, int $paged = 1): self
    {
        return new self(Kind::Home, $page, $paged, postsPage: true);
    }

    public static function term(string $taxonomy, array $term, int $paged = 1): self
    {
        return new self($taxonomy === 'category' ? Kind::Category : Kind::Tag, $term, $paged);
    }

    /** A plugin taxonomy's term archive; the record is the term row (with its taxonomy). */
    public static function taxonomy(array $term, int $paged = 1): self
    {
        return new self(Kind::Taxonomy, $term, $paged);
    }

    /** A plugin post type's archive; the record is the registered type (name, label, ...). */
    public static function postTypeArchive(array $type, int $paged = 1): self
    {
        return new self(Kind::PostTypeArchive, $type, $paged);
    }

    public static function author(string $name, ?UserRecord $user, int $paged = 1): self
    {
        return new self(Kind::Author, $user, $paged, authorName: $name);
    }

    public static function date(int $year, ?int $month, ?int $day, int $paged = 1): self
    {
        return new self(Kind::Date, paged: $paged, date: [$year, $month, $day]);
    }

    public static function search(string $term, int $paged = 1): self
    {
        return new self(Kind::Search, paged: $paged, search: $term);
    }

    public static function notFound(): self
    {
        return new self(Kind::NotFound, status: 404);
    }

    public static function redirect(string $location, int $status = 301): self
    {
        return new self(Kind::Redirect, location: $location, status: $status);
    }

    public function id(): int
    {
        return (int) ($this->record['ID'] ?? $this->record['term_id'] ?? 0);
    }
}
