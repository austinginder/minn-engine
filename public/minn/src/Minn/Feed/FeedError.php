<?php

declare(strict_types=1);

namespace Minn\Feed;

use RuntimeException;

/** A feed that could not be read: the message is the one the reference reports. */
final class FeedError extends RuntimeException
{
}
