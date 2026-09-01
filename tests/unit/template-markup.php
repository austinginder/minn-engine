<?php

declare(strict_types=1);

use Minn\Blocks\Attributes;
use Minn\Theme\TemplatePartTheme;

return [
    'a template-part block is told its theme' => static fn () => TemplatePartTheme::apply('<!-- wp:template-part {"slug":"header"} /-->', 'tt5') === '<!-- wp:template-part {"slug":"header","theme":"tt5"} /-->',
    'a block that names a theme is left alone' => static fn () => TemplatePartTheme::apply('<!-- wp:template-part {"slug":"f","theme":"other"} /-->', 'tt5') === '<!-- wp:template-part {"slug":"f","theme":"other"} /-->',
    'a bare block gains only the theme' => static fn () => TemplatePartTheme::apply('<!-- wp:template-part /-->', 'tt5') === '<!-- wp:template-part {"theme":"tt5"} /-->',
    'other blocks and their bytes are untouched' => static function () {
        $in = "<!-- wp:paragraph {\"x\":1} -->\n<p>a</p>\n<!-- /wp:paragraph -->";
        return TemplatePartTheme::apply($in, 'tt5') === $in;
    },
    'the attribute object is read through nested braces and strings' => static function () {
        $markup = '<!-- wp:group {"style":{"a":"}","b":{"c":1}}} -->';
        return Attributes::objectAt($markup, strpos($markup, '{')) === '{"style":{"a":"}","b":{"c":1}}}';
    },
    'no object at the offset is null' => static fn () => Attributes::objectAt('<!-- wp:group -->', 14) === null,
];
