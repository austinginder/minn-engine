<?php

declare(strict_types=1);

namespace Minn\Extension;

/**
 * What a Minn extension is: a class the engine constructs once per request
 * and asks to register what it provides. Everything it can touch is on the
 * Seams object; there is no global state to reach for.
 */
interface Extension
{
    /** Registers the extension's seams. */
    public function register(Seams $minn): void;
}
