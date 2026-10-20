<?php

declare(strict_types=1);

namespace Minn\Theme;

/**
 * What a post's full text does with the part before its more tag
 * (the_content's $strip_teaser): kept, or left out so the text starts
 * where the more tag stood.
 */
enum Teaser
{
    case Kept;
    case Stripped;
}
