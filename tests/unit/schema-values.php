<?php

declare(strict_types=1);

use Minn\Rest\SchemaValues;

return [
    'booleans read the way the reference reads them' => static fn () => SchemaValues::isBoolean(true) && SchemaValues::isBoolean('false') && !SchemaValues::isBoolean('maybe') && SchemaValues::toBoolean('0') === false,
    'integers are whole numbers, numeric strings included' => static fn () => SchemaValues::isInteger('3') && SchemaValues::isInteger(4.0) && !SchemaValues::isInteger('3.5'),
    'a comma or space separated string is a list' => static fn () => SchemaValues::toArray('a, b c') === ['a', 'b', 'c'] && SchemaValues::isArray('a,b'),
    'hex colours and uuids' => static fn () => SchemaValues::parseHexColor('#fff') === '#fff' && SchemaValues::parseHexColor('red') === false
        && SchemaValues::isUuid('123e4567-e89b-42d3-a456-426614174000') && !SchemaValues::isUuid('nope'),
];
