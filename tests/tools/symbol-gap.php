<?php
/**
 * Writes the part of the reference's interface the runtime does not answer, so
 * the catalogue scan can judge plugin source on a machine with no engine.
 * php tests/tools/symbol-gap.php [out.json]
 */
require __DIR__ . '/engine-runtime.php';
$out = $argv[1] ?? __DIR__ . '/../../contracts/api/symbol-gap.json';
$gap = Minn\Runtime\SymbolGap::ofLoadedFacade(MINN_ENGINE_DIR);
file_put_contents($out, $gap->json() . "\n");
printf("%s: %d functions, %d classes absent\n", basename($out), count($gap->functions), count($gap->classes));
