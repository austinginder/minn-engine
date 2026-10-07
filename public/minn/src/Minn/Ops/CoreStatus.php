<?php

declare(strict_types=1);

namespace Minn\Ops;

use Minn\RestError;
use RuntimeException;

/**
 * The core the app's update banner and chip speak of, which on Minn is
 * Minn: the running engine's version, a newer release on offer
 * (Ops\Releases, asked of the update service once a day), and installing it
 * (Ops\EngineUpdate). WordPress's own offer (the update_core transient a
 * parked copy may write) is not Minn's to act on and is not shown.
 * dbUpgrade is false: the database is WordPress's, and a Minn release
 * never migrates it.
 */
final readonly class CoreStatus
{
    public function __construct(
        private Releases $releases,
        private EngineUpdate $engine,
        private string $version,
    ) {
    }

    /** Minn's version, any offer, and when the update service was last asked. */
    public function data(): array
    {
        $offer = $this->releases->offer();
        return [
            'product' => 'minn',
            'version' => $this->version,
            'dbUpgrade' => false,
            'checked' => $this->releases->stored()['checked'],
            'update' => $offer === null ? null : ['version' => $offer->version, 'url' => $offer->url, 'published' => $offer->published],
        ];
    }

    /** Whether the update service was last asked a day ago or more. */
    public function due(): bool
    {
        return $this->releases->due();
    }

    /** Asks the update service now. */
    public function refresh(): void
    {
        $this->releases->refresh();
    }

    /** Installs the release on offer; the version now in place. */
    public function update(): string
    {
        $offer = $this->releases->offer();
        if ($offer === null) {
            throw new RestError('minn_current', "This Minn ({$this->version}) is the latest release.", 400);
        }
        try {
            return $this->engine->apply($offer);
        } catch (RuntimeException $e) {
            throw new RestError('minn_update_failed', $e->getMessage(), 500);
        }
    }
}
