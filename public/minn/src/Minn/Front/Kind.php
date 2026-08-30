<?php

declare(strict_types=1);

namespace Minn\Front;

/** What a public URL resolved to. */
enum Kind
{
    case Home;
    case Single;
    case Page;
    case Category;
    case Tag;
    case Author;
    case Date;
    case Search;
    /** a term archive of a plugin's taxonomy */
    case Taxonomy;
    /** the archive of a plugin's post type */
    case PostTypeArchive;
    case NotFound;
    case Redirect;
}
