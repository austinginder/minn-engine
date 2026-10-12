<?php
/**
 * PHPMailer's address helpers as plugins call them (probe mail-addresses):
 * an address judged by each check validateAddress offers (php, pcre,
 * pcre8, html5, noregex, a callable), address lists parsed into names and
 * addresses (encoded words, groups, quotes, comments), punycode for an
 * address's domain, and the encoded words a header carries. Nothing is
 * sent and nothing is stored.
 */

use PHPMailer\PHPMailer\PHPMailer;

// The reference loads its mailer only when it sends; the engine's comes with the facade.
if (!class_exists(PHPMailer::class)) {
    require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
    require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
}

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};

$addresses = [
    'user@example.com', 'first.last@example.co.uk', 'user+tag@example.org', 'user@localhost', 'user@[192.168.0.1]',
    'user@[IPv6:2001:db8::1]', '"quoted user"@example.com', '"a\\"b"@example.com', 'user.@example.com', '.user@example.com',
    'us..er@example.com', 'user@-example.com', 'user@example-.com', 'user@example', 'user@ex_ample.com', 'üser@example.com',
    'user@exämple.com', 'user name@example.com', 'user@example.com.', '', '@example.com', 'user@', 'a@b.c',
    str_repeat('a', 64) . '@example.com', str_repeat('a', 65) . '@example.com', 'user@' . str_repeat('a', 63) . '.com',
    'user@' . str_repeat('a', 64) . '.com', 'user(comment)@example.com', "user\n@example.com", 'Name <user@example.com>',
    "user@example.com\r\nBcc: x@example.com", 'user@xn--exmple-cua.com', 'USER@EXAMPLE.COM', 'user@123.123.123.123',
];
$checks = ['php', 'pcre', 'pcre8', 'html5', 'noregex'];
foreach ($addresses as $address) {
    $row = [];
    foreach ($checks as $check) {
        $row[$check] = PHPMailer::validateAddress($address, $check);
    }
    $row['default'] = PHPMailer::validateAddress($address);
    $say('valid: ' . json_encode($address), $row);
}
$say('valid: a callable decides', [PHPMailer::validateAddress('anything', static fn ($a) => $a === 'anything'), PHPMailer::validateAddress('other', static fn ($a) => $a === 'anything')]);

$lists = [
    'user@example.com',
    'User Name <user@example.com>, other@example.org',
    '"Last, First" <first@example.com>, =?UTF-8?B?w4lsaXNl?= <elise@example.com>',
    '=?ISO-8859-1?Q?Andr=E9?= <andre@example.com>',
    'Group: a@example.com, b@example.com;, c@example.com',
    'not an address, <also@not>, ok@example.com',
    '(comment) user@example.com',
    '',
    'a@example.com,,b@example.com',
    '"quoted \\"name\\"" <q@example.com>',
];
// Each parse is asked with notices caught, which the simple parser raises.
$caught = static function (callable $call): array {
    $notices = [];
    set_error_handler(static function (int $level, string $message) use (&$notices): bool {
        $notices[] = $message;
        return true;
    });
    try {
        return ['result' => $call(), 'notices' => $notices];
    } finally {
        restore_error_handler();
    }
};
foreach ($lists as $list) {
    $say('parse: ' . json_encode($list), [$caught(static fn () => PHPMailer::parseAddresses($list, false, 'utf-8')), $caught(static fn () => PHPMailer::parseAddresses($list, false))]);
}

$mailer = new PHPMailer();
$mailer->CharSet = 'utf-8';
foreach (['user@exämple.com', 'user@example.com', 'üser@exämple.com', 'user@', 'user@xn--exmple-cua.com'] as $address) {
    $say('puny: ' . json_encode($address), $mailer->punyencodeAddress($address));
}
foreach (['plain', 'Andrée', 'a "quoted" word', str_repeat('long words ', 12), "tab\there", 'ascii=equals?'] as $text) {
    $say('encode header: ' . json_encode($text), [$mailer->encodeHeader($text), $mailer->encodeHeader($text, 'phrase'), $mailer->encodeHeader($text, 'comment')]);
}
foreach (['=?UTF-8?B?w4lsaXNl?=', '=?ISO-8859-1?Q?Andr=E9?= rest', 'no words', '=?UTF-8?Q?a_b?= =?UTF-8?Q?c?='] as $value) {
    $say('decode header: ' . json_encode($value), [PHPMailer::decodeHeader($value, 'utf-8')]);
}

// A list parsed into ISO-8859-1 names carries bytes that are not UTF-8: each shows as the replacement character.
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
