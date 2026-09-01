<?php

declare(strict_types=1);

use Minn\Content\Texturize;

/** Facts the oracle settled (contracts/blocks.md, contracts/front/theme.md). */
return [
    'a digit-apostrophe stays a right quote, a digit-double-quote becomes a prime' => static fn () => Texturize::html('<p>6\'2"</p>') === '<p>6&#8217;2&#8243;</p>',
    'straight quotes curl' => static fn () => Texturize::html('<p>"hi"</p>') === '<p>&#8220;hi&#8221;</p>',
    'code is left alone' => static fn () => Texturize::html('<code>"x" -- y</code>') === '<code>"x" -- y</code>',
    'three dots become an ellipsis' => static fn () => Texturize::html('<p>wait...</p>') === '<p>wait&#8230;</p>',
];
