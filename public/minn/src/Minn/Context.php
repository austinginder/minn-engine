<?php

declare(strict_types=1);

namespace Minn;

use Minn\Auth\Capabilities;
use Minn\Content\Reader;
use Minn\Content\Site;
use Minn\Http\Request;

/**
 * One request, as a value: the database door, who is asking, what they
 * may do, and where the site's files are. It is built once at the front
 * door and handed down, so nothing below has to reach for a global to
 * learn what request it is answering.
 *
 * The reader is the one part that differs by surface (REST resolves it
 * from the nonce-bound caller, the front from the session cookie), so a
 * context is made with the reader its surface resolved.
 */
final readonly class Context
{
    public function __construct(
        public Db $db,
        public Site $site,
        public ?Request $request,
        public Reader $reader,
        public Capabilities $capabilities,
        /** the minn/ folder: the engine's own files */
        public string $engineDir,
        /** the site root with a trailing slash, as ABSPATH holds it */
        public string $absPath,
        /** the WordPress release whose contracts the runtime speaks */
        public string $version,
    ) {
    }

    /** The same context with another reader, for a surface that resolves one later. */
    public function withReader(Reader $reader): self
    {
        return new self($this->db, $this->site, $this->request, $reader, $this->capabilities, $this->engineDir, $this->absPath, $this->version);
    }

    /** wp-content under the site root. */
    public function contentDir(): string
    {
        return rtrim($this->absPath, '/') . '/wp-content';
    }

    /** The themes folder under wp-content. */
    public function themesDir(): string
    {
        return $this->contentDir() . '/themes';
    }

    /** Whether the request is over HTTPS. */
    public function isSecure(): bool
    {
        return $this->request?->secure ?? false;
    }
}
