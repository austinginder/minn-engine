<?php
/**
 * PHPMailer's internationalized addresses (probe mail-eai): the default
 * validator ("php") becomes "eai" for the rest of the process once an
 * address whose local part has 8-bit characters is added as a recipient or
 * reply-to (whether or not it then passes); setFrom, validateAddress, a
 * domain alone with 8-bit characters, a display name, and any other
 * validator leave it as it is. "eai" is html5's check with letters, marks
 * and numbers of any script. Nothing is sent and nothing is stored; the
 * validator is put back to "php" at the start and the end.
 */

use PHPMailer\PHPMailer\PHPMailer;

if (!class_exists(PHPMailer::class)) {
    require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
    require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
}

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$name = static fn ($validator): string => is_string($validator) ? $validator : 'callable';
$char = static fn (string $hex): string => mb_chr((int) hexdec($hex), 'UTF-8');

PHPMailer::$validator = 'php';
$addresses = [
    'plain@example.com', 'josé@example.com', 'ß@example.com', 'a..b@example.com', '.a@example.com', 'x@y', 'user@localhost',
    'user@exämple.com', '"quoted"@example.com', 'user@[127.0.0.1]', 'user@[IPv6:::1]', 'ü' . str_repeat('a', 63) . '@example.com',
    $char('1f600') . '@example.com', 'e' . $char('301') . '@example.com', $char('661') . $char('662') . '@example.com',
    'a' . $char('200d') . 'b@example.com', $char('216b') . '@example.com', $char('b2') . '@example.com', 'αβγ@δε.gr',
    'ü!#$%&\'*+/=?^_`{|}~-@example.com', 'user@exa' . $char('661') . 'mple.com', 'user@ü-.com', 'user@-ü.com',
    'user@' . str_repeat('ü', 63) . '.com', 'user@' . str_repeat('ü', 64) . '.com', 'user@ex_ample.com', 'us er@example.com',
    'user@exämple.c', 'ü@a', 'user@example.com.', $char('3000') . '@example.com', 'ʰ@example.com', 'ǅ@example.com',
    "bad\xC3@example.com", "a\tb@example.com", 'user@exämple.com ', 'a@b@c.com',
];
foreach ($addresses as $address) {
    $say('fresh default and eai: ' . json_encode($address, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), [PHPMailer::validateAddress($address), PHPMailer::validateAddress($address, 'eai'), PHPMailer::validateAddress($address, 'php')]);
}

foreach (['addAddress', 'addCC', 'addBCC', 'addReplyTo', 'setFrom'] as $method) {
    foreach (['php', 'html5', 'pcre8', 'noregex', 'callable'] as $start) {
        PHPMailer::$validator = $start === 'callable' ? static fn ($email): bool => true : $start;
        $mailer = new PHPMailer(false);
        $added = $method === 'setFrom' ? $mailer->setFrom('josé@example.com') : $mailer->$method('josé@example.com');
        $say("{$method} josé from {$start}", [$added, $name(PHPMailer::$validator)]);
    }
}
foreach (['user@exämple.com' => '', 'plain@example.com' => 'José', 'jo sé@example.com' => '', 'nobody' => '', 'jösé' => ''] as $address => $display) {
    PHPMailer::$validator = 'php';
    $mailer = new PHPMailer(false);
    $say('addAddress ' . json_encode([$address, $display], JSON_UNESCAPED_UNICODE) . ' from php', [$mailer->addAddress($address, $display), $name(PHPMailer::$validator)]);
}

// Once switched, the default answers as eai for everyone after, an explicit "php" as before.
PHPMailer::$validator = 'php';
(new PHPMailer(false))->addAddress('josé@example.com');
foreach (['a..b@example.com', '"quoted"@example.com', 'user@[127.0.0.1]', 'ñ@ñ.es'] as $address) {
    $later = new PHPMailer(false);
    $say('after the switch: ' . json_encode($address, JSON_UNESCAPED_UNICODE), [PHPMailer::validateAddress($address), PHPMailer::validateAddress($address, 'php'), $later->addAddress($address), $later->ErrorInfo]);
}
PHPMailer::$validator = 'php';

echo json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
