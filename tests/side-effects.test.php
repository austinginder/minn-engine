<?php

declare(strict_types=1);

/**
 * Side effects: what each api probe writes to the database, compared with
 * the reference. tests/tools/side-effects.php runs every probe on both
 * stacks with triggers on the shared database and compares each row's
 * history; a probe that differs where contracts/fixtures/effects/
 * divergences.json does not say so fails (the snapshot only shrinks, as the
 * hook battery's does). Take its findings with --show; --snapshot drops what
 * agrees now.
 */

passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/tools/side-effects.php'), $code);
exit($code);
