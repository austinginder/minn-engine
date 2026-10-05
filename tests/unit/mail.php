<?php

declare(strict_types=1);

use Minn\Mail\Composer;
use Minn\Mail\DataLines;
use Minn\Mail\Dkim;
use Minn\Mail\Draft;
use Minn\Mail\HeaderWords;
use Minn\Mail\HostEntry;
use Minn\Mail\HtmlMessage;
use Minn\Mail\MailHeaders;
use Minn\Mail\PathParts;
use Minn\Mail\SmtpSession;
use Minn\Mail\TextWrap;
use Minn\Mail\Transfer;

/** The mail building blocks; whole messages and SMTP sessions are pinned against the reference by tests/mail.test.php. */
$fixtures = dirname(__DIR__) . '/fixtures/mail';
$draft = static fn (array $over = []): Draft => new Draft(...array_values(array_replace([
    'mailer' => 'smtp', 'eol' => "\r\n", 'charset' => 'utf-8', 'contentType' => 'text/plain', 'encoding' => '8bit',
    'subject' => 'S', 'body' => 'B', 'altBody' => '', 'ical' => '', 'from' => 'a@example.test', 'fromName' => '',
    'to' => [['b@example.test', '']], 'cc' => [], 'bcc' => [], 'replyTo' => [], 'messageId' => '<id@example.test>',
    'messageDate' => 'Mon, 05 Oct 2026 12:00:00 +0000', 'priority' => null, 'xMailer' => '', 'confirmReadingTo' => '',
    'customHeaders' => [], 'attachments' => [], 'toHeader' => 'list',
], $over)));

return [
    'header text: plain stays, 8-bit goes B when mostly 8-bit and Q otherwise, folded at the transport line' => static fn () => HeaderWords::encode('Plain', 'text', 'utf-8', 998, "\r\n") === 'Plain'
        && HeaderWords::encode('Ü', 'text', 'utf-8', 998, "\r\n") === '=?utf-8?B?w5w=?='
        && HeaderWords::encode('Café menu', 'text', 'utf-8', 998, "\r\n") === '=?utf-8?Q?Caf=C3=A9_menu?='
        && substr_count(HeaderWords::encode(str_repeat('a', 80), 'text', 'utf-8', HeaderWords::MAIL_LINE, "\r\n"), "\r\n ") === 1,
    'a phrase is quoted when it holds a special, escaped inside' => static fn () => HeaderWords::encode('Name, With "Quotes"', 'phrase', 'utf-8', 998, "\r\n") === '"Name, With \\"Quotes\\""' && HeaderWords::encode("O'Brien", 'phrase', 'utf-8', 998, "\r\n") === "O'Brien",
    'encoded words decode, adjacent ones joined, into the asked charset' => static fn () => HeaderWords::decode('=?utf-8?B?w5xuw68=?= =?utf-8?B?IHRleHQ=?=', 'utf-8') === 'Ünï text' && HeaderWords::decode('a =?iso-8859-1?Q?=FC?= b', 'utf-8') === 'a ü b' && HeaderWords::decode('=?utf-8?Q?J=C3=BCrgen?=', 'iso-8859-1') === "J\xFCrgen",
    'plain wrap keeps long words whole; quoted-printable wrap cuts them with soft breaks' => static fn () => TextWrap::plain(str_repeat('word ', 12), 20, "\n") === "word word word word\nword word word word\nword word word word \n"
        && str_contains(TextWrap::quotedPrintable(str_repeat('x', 50), 20, 'us-ascii', "\n"), "=\n"),
    'a quoted-printable UTF-8 cut never splits an encoded character' => static fn () => array_map(static fn ($n) => TextWrap::utf8Boundary('ab=C3=BCcd=E2=82=ACef', $n), [1, 3, 4, 6, 9, 12]) === [1, 2, 2, 2, 9, 10],
    'transfer encodings: 7bit and 8bit end in a break, binary is untouched, unknown ones throw' => static function (): bool {
        try {
            Transfer::encode('x', 'nonsense', "\r\n");
            return false;
        } catch (InvalidArgumentException) {
            return Transfer::encode("a\nb", '8bit', "\r\n") === "a\r\nb\r\n" && Transfer::encode("a\nb", 'binary', "\r\n") === "a\nb" && Transfer::encode('abc', 'base64', "\r\n") === "YWJj\r\n";
        }
    },
    'a line longer than 998 characters forces quoted-printable unless the body is base64' => static fn () => Composer::body($draft(['body' => str_repeat('y', 1000)]), 'X')['encoding'] === 'quoted-printable' && Composer::body($draft(['body' => str_repeat('y', 1000), 'encoding' => 'base64']), 'X')['encoding'] === 'base64',
    'a 7-bit body declared 8bit is sent as 7bit with no transfer-encoding header' => static fn () => !str_contains(Composer::headers($draft(), 'X', 'host.test', Composer::body($draft(), 'X')['encoding']), 'Content-Transfer-Encoding'),
    'a past date is kept and reformatted; a future one becomes now' => static function () use ($draft): bool {
        $head = static fn (string $date) => preg_match('/^Date: (.*)$/m', Composer::headers($draft(['messageDate' => $date]), 'X', 'h', '8bit'), $m) ? trim($m[1]) : '';
        return $head('Mon, 05 Oct 2026 12:00:00 +0200') === 'Mon, 5 Oct 2026 12:00:00 +0200' && $head('1990-01-01') === 'Mon, 1 Jan 1990 00:00:00 +0000' && abs((int) strtotime($head('9999-12-31')) - time()) < 5;
    },
    'To is undisclosed with neither To nor Cc, absent with only Cc, and omitted one-per-recipient' => static fn () => str_contains(Composer::headers($draft(['to' => [], 'bcc' => [['x@y.z', '']]]), 'X', 'h', '8bit'), "To: undisclosed-recipients:;\r\n")
        && !str_contains(Composer::headers($draft(['to' => [], 'cc' => [['x@y.z', '']]]), 'X', 'h', '8bit'), 'To:')
        && !str_contains(Composer::headers($draft(['toHeader' => 'omit']), 'X', 'h', '8bit'), 'To:'),
    'Bcc is written only for the transports that read it from the headers' => static fn () => str_contains(Composer::headers($draft(['mailer' => 'sendmail', 'bcc' => [['h@x.y', '']]]), 'X', 'h', '8bit'), 'Bcc: h@x.y') && !str_contains(Composer::headers($draft(['bcc' => [['h@x.y', '']]]), 'X', 'h', '8bit'), 'Bcc:'),
    'a malformed message id is replaced by one from the unique id and host' => static fn () => Composer::messageId('<a@b>', 'U', 'h') === '<a@b>' && Composer::messageId('<a b@c>', 'U', 'h') === '<U@h>' && Composer::messageId('<x@[127.0.0.1]>', 'U', 'h') === '<x@[127.0.0.1]>',
    'an alternative message nests its parts in the captured order' => static function () use ($draft): bool {
        $body = Composer::body($draft(['altBody' => 'alt', 'attachments' => [['data', 'a.txt', 'a.txt', 'base64', 'text/plain', true, 'attachment', 0]]]), 'U')['body'];
        return strpos($body, '--b1=_U') < strpos($body, 'boundary="b2=_U"') && strpos($body, 'text/plain; charset=us-ascii') < strpos($body, 'text/html') && str_ends_with($body, "--b1=_U--\r\n");
    },
    'an unreadable attachment fails the body with its path' => static function () use ($draft): bool {
        try {
            Composer::body($draft(['attachments' => [['/no/such/file', 'f', 'f', 'base64', 'text/plain', false, 'attachment', 'f']]]), 'U');
            return false;
        } catch (RuntimeException $e) {
            return $e->getMessage() === '/no/such/file';
        }
    },
    'DATA lines: cut at the last space or at 997, header continuations tabbed' => static function (): bool {
        $body = DataLines::split("S: x\r\n\r\n" . str_repeat('word ', 250));
        $head = DataLines::split('X-Long: ' . str_repeat('h', 2100) . "\r\n\r\nbody");
        return strlen($body[2]) === 994 && $head[0] === 'X-Long:' && $head[1] === "\t" . str_repeat('h', 996) && strlen($head[3]) === 109 && DataLines::split('') === [''];
    },
    'an SMTP reply splits into code, enhanced code and detail' => static fn () => SmtpSession::parseReply("550 5.1.1 Recipient rejected\r\n") === [550, '5.1.1', "Recipient rejected\r\n"] && SmtpSession::parseReply("250-a\r\n250 b\r\n") === [250, '', "a\r\nb\r\n"],
    'a session refuses commands before it connects, naming them' => static function (): bool {
        $session = new SmtpSession(static function (string $text, int $level): void {
        });
        return !$session->mail('a@b.c', '') && $session->error()['error'] === 'Called MAIL FROM without being connected' && !$session->hello('x') && $session->error()['error'] === 'Called HELO without being connected' && $session->transactionId() === null;
    },
    'host entries: ssl:// and tls:// prefixes, a port, anything else part of the host' => static function (): bool {
        $entries = HostEntry::parseAll('ssl://a.example:465; b.example ;tcp://c.example:25;');
        return $entries[0][1]->prefix === 'ssl' && $entries[0][1]->port === 465 && $entries[1][1]->host === 'b.example' && $entries[1][1]->port === null
            && $entries[2][1]->host === 'tcp://c.example' && !HostEntry::validHost($entries[2][1]->host) && $entries[3][1] === null;
    },
    'host names: labels, IPv4 dotted quads, bracketed IPv6 only' => static fn () => array_map([HostEntry::class, 'validHost'], ['a.b.', '999.1.1.1', '[::1]', '[::ffff:1.2.3.4]', 'ex_ample.com', 5]) === [true, false, true, false, false, false],
    'DKIM canonicalization and quoting as the reference writes them' => static fn () => Dkim::quotedPrintable("a=b; c\r\nd\te") === 'a=3Db=3B=20c=0D=0Ad=09e'
        && Dkim::headers("Subject:  Some   Thing \r\nFrom: A <a@b>\r\n") === "subject:Some Thing\r\nfrom:A <a@b>\r\n" && Dkim::body("x  \r\n\r\n\r\n") === "x  \r\n" && Dkim::body('') === "\r\n",
    'DKIM signatures reproduce the reference byte for byte at the same time' => static function () use ($fixtures): bool {
        $ref = json_decode((string) file_get_contents("{$fixtures}/dkim-reference.json"), true);
        $key = (string) file_get_contents("{$fixtures}/dkim-test.key");
        $time = static fn (string $header): int => preg_match('/t=(\d+);/', $header, $m) ? (int) $m[1] : 0;
        $copied = (new Dkim('example.test', 'sel', $key, '', ''))->copyingHeaders()->signatureHeader($ref['message']['headers'], 'Hello', $ref['message']['body'], $time($ref['copied']), [], "\r\n");
        $plain = (new Dkim('example.test', 'sel', $key, '', 'sender@example.test'))->signatureHeader($ref['identity, not copied']['headers'], 'Hello', $ref['message']['body'], $time($ref['identity, not copied']['signature']), [], "\r\n");
        return (new Dkim('example.test', 'sel', $key, '', ''))->sign($ref['sign']['text']) === $ref['sign']['signature'] && $copied === $ref['copied'] && $plain === $ref['identity, not copied']['signature'];
    },
    'HTML images: local relative paths and raster data URIs embed; schemes, roots, ../ and SVG do not' => static fn () => HtmlMessage::localPath('img/a.png') === 'img/a.png' && HtmlMessage::localPath('../a.png') === null && HtmlMessage::localPath('/a.png') === null && HtmlMessage::localPath('//cdn/a.png') === null && HtmlMessage::localPath('cid:x') === null
        && HtmlMessage::dataImage('data:image/png,%89PNG') === ["\x89PNG", 'image/png'] && HtmlMessage::dataImage('data:image/svg+xml;base64,PHN2Zy8+') === null && HtmlMessage::dataImage('data:image/gif;base64,R0lG')[0] === 'GIF',
    'HTML to text drops head, style and script, strips tags and decodes entities into the charset' => static fn () => HtmlMessage::text('<head><title>T</title></head><p>A &amp; <b>B</b></p><script>x()</script>', 'utf-8') === 'A & B' && HtmlMessage::cid('pixel.gif') === '82e44a85dcbc44811df451792c30b5a0@phpmailer.0',
    'path parts: no "." directory, a leading dot is all extension' => static fn () => PathParts::of('fïle.txt')['dirname'] === '' && PathParts::of('.hidden') === ['dirname' => '', 'basename' => '.hidden', 'extension' => 'hidden', 'filename' => ''] && PathParts::of('C:\\dir\\file.txt')['dirname'] === 'C:\\dir',
    'wp_mail headers: From name unquoted, lists split on every comma, a boundary empties the charset, the last custom header wins' => static function (): bool {
        $h = MailHeaders::parse("From: \"Q, N\" <q@x.y>\ncc: a@x.y, \"B, C\" <b@x.y>\nContent-Type: multipart/alternative; boundary=\"bnd\"\nX-M: 1\nX-M: 2\nno colon");
        return $h->fromEmail === 'q@x.y' && $h->fromName === 'Q, N' && $h->cc === ['a@x.y', ' "B', ' C" <b@x.y>'] && $h->contentType === 'multipart/alternative' && $h->charset === '' && $h->boundary === 'bnd' && $h->custom === ['X-M' => '2']
            && MailHeaders::recipient(' "Q" <q@x.y>') === ['q@x.y', '"Q"'] && MailHeaders::parse(['Content-Type: text/plain; charset="iso-8859-1"'])->charset === 'iso-8859-1';
    },
];
