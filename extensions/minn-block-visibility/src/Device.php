<?php

declare(strict_types=1);

namespace Minn\Ext\BlockVisibility;

/** The device class the plugin's browserDevice control sees: phones and tablets are "mobile", the rest "other". */
final class Device
{
    public static function fromUserAgent(string $agent): string
    {
        return preg_match('/iPhone|iPad|iPod|Android|Mobile|Windows Phone|BlackBerry|Opera Mini|Silk|Kindle|webOS|Tablet/i', $agent) ? 'mobile' : 'other';
    }
}
