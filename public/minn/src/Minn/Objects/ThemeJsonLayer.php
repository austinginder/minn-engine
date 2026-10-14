<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * One origin's theme.json data as the WordPress layer hands it to the
 * wp_theme_json_data_ filters (WP_Theme_JSON_Data), which may change it.
 * The engine knows these objects by this mark and makes them through
 * Runtime\Handoff, so it never names the WordPress layer's class.
 */
interface ThemeJsonLayer
{
}
