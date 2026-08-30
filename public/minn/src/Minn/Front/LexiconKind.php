<?php

declare(strict_types=1);

namespace Minn\Front;

enum LexiconKind
{
    case Heading;
    case Paragraph;
    case List;
    case Table;
    case Rule;
}
