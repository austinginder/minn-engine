<?php
/** The value classes a stored blob revives as: the reference reads them back typed, so the engine does too. */

use Minn\Runtime\StoredObjects;

StoredObjects::register('WP_Post', static fn (stdClass $properties): WP_Post => new WP_Post($properties));
StoredObjects::register('WP_Term', static fn (stdClass $properties): WP_Term => new WP_Term($properties));
StoredObjects::register('WP_Comment', static fn (stdClass $properties): WP_Comment => new WP_Comment($properties));
