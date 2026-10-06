# `Minn\Mail`

sending mail and the notices the engine sends

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`AddressRules`](#addressrules) | final class | 111 | Email addresses: the checks a mailer can apply (PHP's filter, the |
| [`Composer`](#composer) | final class | 253 | A message as the reference's mailer writes it: the header block (Date, |
| [`DataLines`](#datalines) | final class | 28 | A message cut into the lines SMTP DATA sends. Any line break (CRLF, CR |
| [`DebugOutput`](#debugoutput) | final class | 38 | Where a mailer's debug text goes, as its Debugoutput setting names it: |
| [`Dkim`](#dkim) | final class | 110 | DKIM signatures (RFC 6376) as the reference's mailer writes them: |
| [`Draft`](#draft) | final readonly class | 66 | Everything a message is composed from: the transport it goes out on |
| [`HeaderWords`](#headerwords) | final class | 112 | Header text as mail carries it (RFC 2047): left alone when it is plain |
| [`HostEntry`](#hostentry) | final readonly class | 36 | One entry of a mailer's Host setting ("host", "host:port", |
| [`HtmlMessage`](#htmlmessage) | final class | 55 | What a mailer reads out of an HTML message: the images it can carry |
| [`MailHeaders`](#mailheaders) | final readonly class | 83 | The headers argument of wp_mail() read the way the reference reads it: |
| [`MailLog`](#maillog) | final class | 25 | The engine's development transport: each message as one JSON line |
| [`MailSettings`](#mailsettings) | final readonly class | 71 | How mail leaves the site, from the minn_mail option (JSON or a serialized |
| [`Mailer`](#mailer) | final readonly class | 88 | Sends a Message through the configured transport. Failures are logged |
| [`MailerStrings`](#mailerstrings) | final class | 34 | The mailer's English messages, keyed as plugins look them up |
| [`Message`](#message) | final readonly class | 18 | One outgoing plain-text email. |
| [`MimeTypes`](#mimetypes) | final class | 43 | The MIME type a mailer gives an attachment by its file extension (the |
| [`Notices`](#notices) | final readonly class | 65 | The messages the engine itself sends, worded as the reference words them |
| [`PathParts`](#pathparts) | final class | 22 | A path's parts the way a mailer names attachments, safe for multibyte |
| [`Smime`](#smime) | final class | 27 | S/MIME signing of a composed MIME entity with a certificate and key on |
| [`Smtp`](#smtp) | final readonly class | 33 | Delivery over SMTP for the engine's own mail when WordPress's mail |
| [`SmtpSession`](#smtpsession) | final class | 461 | One SMTP conversation as the reference's client holds it: the socket, |
| [`TextWrap`](#textwrap) | final class | 118 | Word wrapping for mail text. Lines break at spaces once they would pass |
| [`Transfer`](#transfer) | final class | 61 | Body text in a content transfer encoding, and the line-ending helpers |

## AddressRules

`final class Minn\Mail\AddressRules` · `public/minn/src/Minn/Mail/AddressRules.php`

Email addresses: the checks a mailer can apply (PHP's filter, the
RFC 5322 grammar with comments, quoted local parts and domain literals,
the HTML5 form rule), parsing an address list, and the domain of an
address in ASCII.

- const `HTML5` = `'/^[a-zA-Z0-9.!#$%&\'*+\\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/sD'`

Used by: `Minn\Mail\Composer`

### static `valid(string $address, string $pattern): bool`

Whether the address passes the named check ("php", "pcre", "pcre8", "html5", "noregex").

### static `parseList(string $list, string $charset): array`

The addresses in a comma-separated list, each with its display name
(encoded words decoded into $charset); entries that are not valid
addresses are left out.

- `@return list<array{name: string, address: string}>`

### static `asciiDomain(string $address, string $charset): string`

The address with its domain in ASCII (IDNA), the domain read from $charset first; unchanged when it cannot be.

### static `quoted(string $text): string`

The text as an RFC 822 quoted string when it holds a character that needs one.

Internals: `rfc5322()` (private, line 81), `splitList()` (private, line 104)


## Composer

`final class Minn\Mail\Composer` · `public/minn/src/Minn/Mail/Composer.php`

A message as the reference's mailer writes it: the header block (Date,
the address lines, Subject, Message-ID, X-Mailer, custom headers,
MIME-Version), the MIME header for its type, and the body, multipart
when it has an alternative, inline parts or attachments. Boundaries are
"b1=_{id}" to "b3=_{id}"; a 7-bit-clean body declared 8bit goes out as
7bit us-ascii, and a body with a line past 998 characters as
quoted-printable.

- const `ICAL_METHODS` = `array (   0 => 'REQUEST',   1 => 'PUBLISH',   2 => 'REPLY',   3 => 'ADD',   4 => 'CANCEL',   5 => 'REFRESH',   6 => 'COUNTER',   7 => 'DECLINECOUNTER', )`

Used by: `Minn\Mail\Mailer`

### static `body(Minn\Mail\Draft $d, string $id): array`

The body and the encoding a single-part message declares.

- `@return array{body: string, encoding: string}`

### static `headers(Minn\Mail\Draft $d, string $id, string $hostname, string $encoding): string`

The header block, MIME-Version and the MIME header included.

### static `mimeHeaders(Minn\Mail\Draft $d, string $id, string $encoding): string`

The MIME header for the message's type: the content type (with its boundary) and the transfer encoding.

### static `mailOnlyHeaders(Minn\Mail\Draft $d): string`

The To and Subject lines PHP's mail() takes as arguments, written after the headers in the sent copy.

### static `addressLine(Minn\Mail\Draft $d, string $name, array $list): string`

"Name: a, b" for a list of [address, name] pairs.

- `@param list<array{0: string, 1: string}> $list`

### static `address(Minn\Mail\Draft $d, array $pair): string`

One address as a header writes it: the address alone, or the name (as a phrase) and the address in angle brackets.

- `@param array{0: string, 1: string} $pair`

### static `secure(string $text): string`

Header text with any line break taken out.

### static `messageId(string $given, string $id, string $hostname): string`

The message id given when it is well formed, else one made from the id and the host name.

Internals: `encodingFor()` (private, line 133), `partHead()` (private, line 145), `related()` (private, line 155), `alternative()` (private, line 161), `end()` (private, line 167), `calendar()` (private, line 176), `date()` (private, line 186), `attachAll()` (private, line 193), `attachmentHead()` (private, line 214), `attachmentData()` (private, line 232), `extraLines()` (private, line 251)


## DataLines

`final class Minn\Mail\DataLines` · `public/minn/src/Minn/Mail/DataLines.php`

A message cut into the lines SMTP DATA sends. Any line break (CRLF, CR
or LF) ends a line. A line longer than 998 characters is cut at its last
space within the first 998 (the space dropped), or at 997 when there is
none; in the header block (when the first line reads "Name: ..." with no
space in the name, up to the first empty line) each continuation starts
with a tab. Dot stuffing is the sender's job, after the cut.

Used by: `Minn\Mail\SmtpSession`

### static `split(string $message): array`

The lines to send, without their line endings.

- `@return list<string>`


## DebugOutput

`final class Minn\Mail\DebugOutput` · `public/minn/src/Minn/Mail/DebugOutput.php`

Where a mailer's debug text goes, as its Debugoutput setting names it:
"echo" (a timestamp and a tab, continuation lines indented under the
text), "html" (entity-escaped, line breaks dropped, ending in <br>),
"error_log", a PSR-3 logger's debug(), or any other callable, which gets
the text and its level. The two printed forms come back as text for the
caller to print; the others are delivered here and return null.

- const `INDENT` = `'                    	                  '`

### static `smtp(mixed $how, string $text, int $level): ?string`

Debug text from the SMTP client: the text to print, or null once delivered; its html form carries a timestamp.

### static `mailer(mixed $how, string $text, int $level): ?string`

Debug text from the mailer itself: the text to print, or null once delivered; its html form has no timestamp.

Internals: `write()` (private, line 31), `html()` (private, line 48)


## Dkim

`final class Minn\Mail\Dkim` · `public/minn/src/Minn/Mail/Dkim.php`

DKIM signatures (RFC 6376) as the reference's mailer writes them:
rsa-sha256, relaxed header and simple body canonicalization, the
standard headers signed in the order they appear (plus any extra ones
the caller names), an optional identity, the signed headers copied into
z= when asked, and the signature folded in 73-character pieces.

- const `SIGNED` = `array (   0 => 'from',   1 => 'to',   2 => 'cc',   3 => 'date',   4 => 'subject',   5 => 'reply-to',   6 => 'message-id',   7 => 'content-type',   8 => 'mime-version',   9 => 'x-mailer', )`

```php
__construct(string $domain, string $selector, string $key, string $passphrase, string $identity)
```


### `copyingHeaders(): self`

The same signer, copying the signed headers into z= as well.

### static `quotedPrintable(string $text): string`

Text with everything outside the DKIM-safe printable set written as =XX.

### static `headers(string $text): string`

Relaxed header canonicalization: unfolded, names lower-cased, runs of space collapsed, values trimmed.

### static `body(string $body): string`

Simple body canonicalization: CRLF line endings and exactly one at the end.

### `sign(string $text): ?string`

The base64 RSA-SHA256 signature of the text, or null when the key cannot be read.

### `signatureHeader(string $headerBlock, string $subject, string $body, int $time, array $extra, string $eol): ?string`

The DKIM-Signature header for a message, signed at $time.

- `@param list<string> $extra lower-case names of further headers to sign`

Internals: `signedHeaders()` (private, line 106)


## Draft

`final readonly class Minn\Mail\Draft` · `public/minn/src/Minn/Mail/Draft.php`

Everything a message is composed from: the transport it goes out on
(which decides the line ending, the header line length, and where To,
Subject and Bcc are written), the envelope, the content, the headers,
and the attachments as [path or data, file name, name, encoding, type,
is data, disposition, content id]. $toHeader is "list" (To, or
undisclosed-recipients when there is neither To nor Cc) or "omit" (one
message per recipient, the address given to each send instead).

- const `VERSION` = `'7.1.1'`

Used by: `Minn\Mail\Composer`, `Minn\Mail\Mailer`

```php
__construct(string $mailer, string $eol, string $charset, string $contentType, string $encoding, string $subject, string $body, string $altBody, string $ical, string $from, string $fromName, array $to, array $cc, array $bcc, array $replyTo, string $messageId, string $messageDate, ?int $priority, string $xMailer, string $confirmReadingTo, array $customHeaders, array $attachments, string $toHeader = 'list')
```
- `@param list<array{0: string, 1: string}> $to address and name pairs, as are $cc, $bcc and $replyTo`
- `@param list<array{0: string, 1: string}> $customHeaders name and value pairs`
- `@param list<array<int, mixed>> $attachments`

- readonly `string $mailer`
- readonly `string $eol`
- readonly `string $charset`
- readonly `string $contentType`
- readonly `string $encoding`
- readonly `string $subject`
- readonly `string $body`
- readonly `string $altBody`
- readonly `string $ical`
- readonly `string $from`
- readonly `string $fromName`
- readonly `array $to`
- readonly `array $cc`
- readonly `array $bcc`
- readonly `array $replyTo`
- readonly `string $messageId`
- readonly `string $messageDate`
- readonly `?int $priority`
- readonly `string $xMailer`
- readonly `string $confirmReadingTo`
- readonly `array $customHeaders`
- readonly `array $attachments`
- readonly `string $toHeader`

### `lineLength(): int`

The longest header line the transport takes before it must fold.

### `encodeHeader(string $text, string $position = 'text'): string`

Header text encoded for this message's charset and transport.

### `messageType(): string`

Which parts the message has: plain, or "alt", "inline", "attach" joined by "_".


## HeaderWords

`final class Minn\Mail\HeaderWords` · `public/minn/src/Minn/Mail/HeaderWords.php`

Header text as mail carries it (RFC 2047): left alone when it is plain
and short enough, quoted when a phrase needs it, otherwise encoded words
in Q (mostly ASCII) or B (mostly not), folded to the line length the
transport allows: 63 for PHP's mail(), 998 otherwise. Plain text only
becomes an encoded word to be folded when it is too long for one line.

- const `MAIL_LINE` = `63`
- const `SMTP_LINE` = `998`
- const `ATEXT_PHRASE` = `'/[^A-Za-z0-9!#$%&\'*+\\/=?^_`{|}~ -]/'`

Used by: `Minn\Mail\Draft`

### static `encode(string $text, string $position, string $charset, int $lineLength, string $eol): string`

The header text encoded for its position ("text", "phrase" or "comment").

### static `q(string $text, string $position = 'text'): string`

RFC 2047 "Q" encoding for a position: spaces as "_", and the characters the position cannot carry as =XX.

### static `base64Lines(string $text, string $charset): array`

Base64 lines of at most 63 characters cut at whole characters, so a
multibyte character is never split across encoded words.

- `@return list<string>`

### static `decode(string $value, string $charset): string`

Encoded words decoded (adjacent ones joined) and converted into $charset; other text passes through.

Internals: `specials()` (private, line 100), `bLines()` (private, line 110), `qLines()` (private, line 120)


## HostEntry

`final readonly class Minn\Mail\HostEntry` · `public/minn/src/Minn/Mail/HostEntry.php`

One entry of a mailer's Host setting ("host", "host:port",
"ssl://host:465", "tls://host"; entries are separated by semicolons):
the security prefix, the host and the port. Only ssl:// and tls:// are
prefixes; anything else stays part of the host and fails the host check.

```php
__construct(string $prefix, string $host, ?int $port)
```

- readonly `string $prefix`
- readonly `string $host`
- readonly `?int $port`

### static `parseAll(string $hosts): array`

The entries of a Host setting, null where one cannot be read.

- `@return list<array{0: string, 1: self|null}> the trimmed entry text and its reading`

### static `validHost(mixed $host): bool`

Whether a host name, IPv4 address or bracketed IPv6 address is well formed.


## HtmlMessage

`final class Minn\Mail\HtmlMessage` · `public/minn/src/Minn/Mail/HtmlMessage.php`

What a mailer reads out of an HTML message: the images it can carry
inline (a src or background naming a file under the base directory, or
a data: URI of a raster image), the content id each one gets (the first
32 hex digits of a SHA-256 of the URL, or of a data URI's bytes, at
"phpmailer.0"), and the plain-text version (head, style and script
dropped, tags stripped, entities decoded into the charset).

- const `NO_TEXT` = `'This is an HTML-only message. To view it, activate HTML in your email application.'`

### static `images(string $html): array`

Every quoted src or background value, in document order.

- `@return list<array{attribute: string, url: string}>`

### static `dataImage(string $url): ?array`

A data: URI's bytes and type when it is a raster image, else null.

- `@return array{0: string, 1: string}|null`

### static `localPath(string $url): ?string`

A relative path with no scheme and no step up out of the base directory, or null.

### static `cid(string $source): string`

The content id the mailer gives an image.

### static `text(string $html, string $charset): string`

The plain-text version of an HTML message.


## MailHeaders

`final readonly class Minn\Mail\MailHeaders` · `public/minn/src/Minn/Mail/MailHeaders.php`

The headers argument of wp_mail() read the way the reference reads it:
a string split into lines (CRLF or LF) or a list of lines; a line
without a colon is skipped. From gives an address and a name (quotes
dropped); Cc, Bcc and Reply-To add comma-separated entries (a comma
inside quotes still splits); Content-Type gives a type and either a
charset or a boundary (a boundary leaves the charset empty); every
other header is kept by name, the last of a name winning.

```php
__construct(?string $fromEmail, ?string $fromName, array $cc, array $bcc, array $replyTo, ?string $contentType, ?string $charset, string $boundary, array $custom)
```
- `@param list<string> $cc`
- `@param list<string> $bcc`
- `@param list<string> $replyTo`
- `@param array<string, string> $custom`

- readonly `?string $fromEmail`
- readonly `?string $fromName`
- readonly `array $cc`
- readonly `array $bcc`
- readonly `array $replyTo`
- readonly `?string $contentType`
- readonly `?string $charset`
- readonly `string $boundary`
- readonly `array $custom`

### static `parse(mixed $headers): self`

The headers argument, as a string of lines or a list of them.

### static `recipient(string $entry): array`

One recipient entry as [address, name]: "Name <address>" (the name as written) or a bare address.

- `@return array{0: string, 1: string}`

Internals: `from()` (private, line 71), `contentType()` (private, line 84)


## MailLog

`final class Minn\Mail\MailLog` · `public/minn/src/Minn/Mail/MailLog.php`

The engine's development transport: each message as one JSON line
(time, from, to, subject, body) appended to wp-content/minn-mail.log,
nothing sent.

Used by: `Minn\Mail\Mailer`

### static `forSite(): string`

The site's log file.

### static `write(string $file, string $from, string $fromName, array $to, string $subject, string $body): bool`

Appends one message; false when the file cannot be written.

- `@param list<string> $to`


## MailSettings

`final readonly class Minn\Mail\MailSettings` · `public/minn/src/Minn/Mail/MailSettings.php`

How mail leaves the site, from the minn_mail option (JSON or a serialized
array): the PHP mail
function by default, SMTP when configured, or a log file for development.
The sender defaults to the site name at a no-reply address on the home host.

- const `OPTION` = `'minn_mail'`

Used by: `Minn\Cli\MinnCommand`, `Minn\Mail\Mailer`, `Minn\Mail\Smtp`, `Minn\Ops\Diagnostics`

```php
__construct(string $transport = 'mail', string $host = '', int $port = 587, string $encryption = 'tls', string $username = '', string $password = '', string $fromEmail = '', string $fromName = '')
```

- readonly `string $transport`
- readonly `string $host`
- readonly `int $port`
- readonly `string $encryption`
- readonly `string $username`
- readonly `string $password`
- readonly `string $fromEmail`
- readonly `string $fromName`

### static `stored(Minn\Content\Site $site): array`

What the minn_mail option holds, without defaults: empty when the site
has not configured mail.

- `@return array<string, mixed>`

### static `fromSite(Minn\Content\Site $site): self`

The site's mail settings from its minn_mail option, or the defaults.

### `toArray(): array`

The option's JSON, secrets included, for the settings surface.


## Mailer

`final readonly class Minn\Mail\Mailer` · `public/minn/src/Minn/Mail/Mailer.php`

Sends a Message through the configured transport. Failures are logged
and reported as false; nothing here throws into a request.

Used by: `Minn\Cli\MinnCommand`, `Minn\Cli\UserCommand`, `Minn\Engine`, `Minn\Front\CommentPostController`, `Minn\Login\LoginController`, `Minn\Mail\Composer`

```php
__construct(Minn\Mail\MailSettings $settings, string $logFile)
```


### static `forSite(Minn\Content\Site $site): self`

A mailer using the site's own settings.

### static `mail(array|string $to, string $subject, string $body): bool`

The one-line send: a recipient, a subject, a body, through the site's
transport and sender, with nothing to construct at the call site.

### static `noticesFor(Minn\Content\Site $site): Minn\Mail\Notices`

The engine's own notices, worded once, from this site's name and address.

### `send(Minn\Mail\Message $message): bool`

Sends one message; false on failure. With WordPress's mail function
loaded the message goes through wp_mail(), so its filters and the
phpmailer_init hook (an SMTP plugin's way in) apply as they do on the
reference; otherwise through the configured transport directly.

Internals: `viaSmtp()` (private, line 77), `viaMail()` (private, line 87), `draft()` (private, line 98)


## MailerStrings

`final class Minn\Mail\MailerStrings` · `public/minn/src/Minn/Mail/MailerStrings.php`

The mailer's English messages, keyed as plugins look them up
("authenticate", "provide_address", ...): the base set, and the set
WordPress's own mailer subclass loads, which words four of them
differently. Captured from the reference into data/mailer-strings.json.


### static `base(): array`

The base mailer's messages.

- `@return array<string, string>`

### static `wordpress(): array`

The messages WordPress's mailer subclass loads.

- `@return array<string, string>`

Internals: `set()` (private, line 39)


## Message

`final readonly class Minn\Mail\Message` · `public/minn/src/Minn/Mail/Message.php`

One outgoing plain-text email.

Used by: `Minn\Cli\MinnCommand`, `Minn\Mail\Mailer`, `Minn\Mail\Notices`

```php
__construct(array $to, string $subject, string $body, string $fromEmail = '', string $fromName = '')
```
- `@param list<string> $to`

- readonly `array $to`
- readonly `string $subject`
- readonly `string $body`
- readonly `string $fromEmail`
- readonly `string $fromName`

### static `to(array|string $to, string $subject, string $body): self`

One recipient or several, and the two things every mail has.


## MimeTypes

`final class Minn\Mail\MimeTypes` · `public/minn/src/Minn/Mail/MimeTypes.php`

The MIME type a mailer gives an attachment by its file extension (the
table the reference's mailer answers with); anything else is
application/octet-stream.

- const `TYPES` = `array (   'ai' => 'application/postscript',   'aif' => 'audio/x-aiff',   'aifc' => 'audio/x-aiff',   'aiff' => 'audio/x-aiff',   'avi' => 'video/x-msvideo',   'avif' => 'image/avif',   'bin' => 'application/macbinary',   'bmp' => 'image/bmp',   'css' => 'text/css',   'csv' => 'text/csv',   'dcr' => 'application/x-director',   'dir' => 'application/x-director',   'doc' => 'application/msword',   'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',   'dvi' => 'application/x-dvi',   'dxr' => 'application/x-director',   'eml' => 'message/rfc822',   'eps' => 'application/postscript',   'gif' => 'image/gif',   'gtar' => 'application/x-gtar',   'heic' => 'image/heic',   'heics' => 'image/heic-sequence',   'heif' => 'image/heif',   'heifs' => 'image/heif-sequence',   'htm' => 'text/html',   'html' => 'text/html',   'ics' => 'text/calendar',   'jpe' => 'image/jpeg',   'jpeg' => 'image/jpeg',   'jpg' => 'image/jpeg',   'js' => 'application/javascript',   'log' => 'text/plain',   'm4a' => 'audio/mp4',   'm4v' => 'video/mp4',   'mid' => 'audio/midi',   'midi' => 'audio/midi',   'mif' => 'application/vnd.mif',   'mka' => 'audio/x-matroska',   'mkv' => 'video/x-matroska',   'mov' => 'video/quicktime',   'movie' => 'video/x-sgi-movie',   'mp2' => 'audio/mpeg',   'mp3' => 'audio/mpeg',   'mp4' => 'video/mp4',   'mpe' => 'video/mpeg',   'mpeg' => 'video/mpeg',   'mpg' => 'video/mpeg',   'mpga' => 'audio/mpeg',   'oda' => 'application/oda',   'pdf' => 'application/pdf',   'php' => 'application/x-httpd-php',   'php3' => 'application/x-httpd-php',   'php4' => 'application/x-httpd-php',   'phps' => 'application/x-httpd-php-source',   'phtml' => 'application/x-httpd-php',   'png' => 'image/png',   'ppt' => 'application/vnd.ms-powerpoint',   'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',   'ps' => 'application/postscript',   'qt' => 'video/quicktime',   'ra' => 'audio/x-realaudio',   'ram' => 'audio/x-pn-realaudio',   'rm' => 'audio/x-pn-realaudio',   'rpm' => 'audio/x-pn-realaudio-plugin',   'rtf' => 'text/rtf',   'rtx' => 'text/richtext',   'rv' => 'video/vnd.rn-realvideo',   'shtml' => 'text/html',   'sit' => 'application/x-stuffit',   'smi' => 'application/smil',   'smil' => 'application/smil',   'swf' => 'application/x-shockwave-flash',   'tar' => 'application/x-tar',   'text' => 'text/plain',   'tgz' => 'application/x-tar',   'tif' => 'image/tiff',   'tiff' => 'image/tiff',   'txt' => 'text/plain',   'vcard' => 'text/vcard',   'vcf' => 'text/vcard',   'wav' => 'audio/x-wav',   'wbxml' => 'application/vnd.wap.wbxml',   'webm' => 'video/webm',   'webp' => 'image/webp',   'wmlc' => 'application/vnd.wap.wmlc',   'wmv' => 'video/x-ms-wmv',   'xht' => 'application/xhtml+xml',   'xhtml' => 'application/xhtml+xml',   'xl' => 'application/excel',   'xls' => 'application/vnd.ms-excel',   'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',   'xml' => 'text/xml',   'xsl' => 'text/xml',   'zip' => 'application/zip', )`

### static `forExtension(string $extension): string`

The type for an extension, in any case.

### static `forFilename(string $filename): string`

The type for a file name or path: its last extension (a query string ignored).


## Notices

`final readonly class Minn\Mail\Notices` · `public/minn/src/Minn/Mail/Notices.php`

The messages the engine itself sends, worded as the reference words them
and built in one place, so a controller and a CLI verb that announce the
same thing say the same thing.

Used by: `Minn\Mail\Mailer`

```php
__construct(string $siteName, string $home)
```


### `loginDetails(string $login, string $email, string $resetLink): Minn\Mail\Message`

A new account's login details, with a link to choose a password.

### `passwordReset(string $login, string $email, string $resetLink, string $requestIp): Minn\Mail\Message`

The password reset link, with the requester's address as the reference prints it.

### `moderation(string $adminEmail, string $postTitle, string $author, string $comment): Minn\Mail\Message`

A comment waiting in the queue, announced to the site's address.

Internals: `subject()` (private, line 72)


## PathParts

`final class Minn\Mail\PathParts` · `public/minn/src/Minn/Mail/PathParts.php`

A path's parts the way a mailer names attachments, safe for multibyte
names: the directory ('' when there is none), the last segment, its
extension (after the last dot, so ".hidden" is all extension) and the
name before it. Either slash separates; trailing ones are ignored.

### static `of(string $path): array`

The path's four parts.

- `@return array{dirname: string, basename: string, extension: string, filename: string}`


## Smime

`final class Minn\Mail\Smime` · `public/minn/src/Minn/Mail/Smime.php`

S/MIME signing of a composed MIME entity with a certificate and key on
disk: the entity goes through OpenSSL as a detached signature and comes
back as new MIME headers (multipart/signed) and the signed body.

### static `sign(string $entity, string $cert, string $key, string $passphrase, string $extraCerts): ?array`

The signed entity's MIME headers and body, or null when signing fails.

- `@return array{0: string, 1: string}|null`


## Smtp

`final readonly class Minn\Mail\Smtp` · `public/minn/src/Minn/Mail/Smtp.php`

Delivery over SMTP for the engine's own mail when WordPress's mail
function is not loaded: the site's server, SSL or STARTTLS, a sign-in
when a user name is set, one message per connection, through the same
SmtpSession the PHPMailer SMTP class speaks with.

Used by: `Minn\Mail\Mailer`

```php
__construct(Minn\Mail\MailSettings $settings)
```


### `sendRaw(string $from, array $recipients, string $data): bool`

Sends one composed message (headers, a blank line, the body) to the
envelope recipients; false on any refusal.

- `@param list<string> $recipients`


## SmtpSession

`final class Minn\Mail\SmtpSession` · `public/minn/src/Minn/Mail/SmtpSession.php`

One SMTP conversation as the reference's client holds it: the socket,
the last reply, the server's extensions from EHLO, and the last error as
{error, detail, smtp_code, smtp_code_ex}. Every command names itself in
its error ("RCPT TO command failed"); a command sent before connecting
fails as "Called X without being connected". Debug text goes to the
given closure with its level: 1 client lines, 2 server replies, 3
connection events, 4 raw inbound lines. Credentials never reach it.

- const `MAX_LINE` = `998`
- const `CREDENTIALS` = `array (   0 => 'User & Password',   1 => 'Username',   2 => 'Password', )`
- const `AUTH_ORDER` = `array (   0 => 'CRAM-MD5',   1 => 'LOGIN',   2 => 'PLAIN',   3 => 'XOAUTH2', )`
- const `TRANSACTION_IDS` = `array (   0 => '/queued as ([^\\s]+)/i',   1 => '/\\bOK id=([^\\s]+)/i',   2 => '/^\\d{3} 2\\.0\\.0 ([^\\s]+) Message accepted for delivery/m',   3 => '/^\\d{3} 2\\.\\d\\.0 <?([^\\s@>]+)@[^\\s]* Queued mail for delivery/m',   4 => '/Message Queued \\(([^)]+)\\)/i',   5 => '/^\\d{3} Ok ([^\\s]+)/m', )`

Used by: `Minn\Mail\DataLines`, `Minn\Mail\Smtp`

```php
__construct(Closure $debug)
```
- `@param Closure(string, int): void $debug`


### `limits(int $timeout, int $timelimit): void`

How long one read may wait and how long a whole reply may take, in seconds.

### `connect(string $host, ?int $port, int $timeout, array $options): bool`

Opens the connection and reads the greeting.

- `@param array<string, mixed> $options stream context options`

### `startTls(): bool`

Asks for TLS and turns it on.

### `authenticate(string $user, string $password, string $type, ?string $oauth): bool`

Signs in with the named mechanism, or the strongest the server offers
when it is empty or not offered.

### `connected(): bool`

Whether the connection is open; a socket closed at the far end is closed here too.

### `close(): void`

Closes the socket and forgets the server's greeting and extensions; the last error stays.

### `data(string $message): bool`

Sends a message: DATA, the lines (a line past 998 characters split at
its last space, or at 997 without one; header continuations indented;
leading dots doubled), then the closing dot.

### `hello(string $host): bool`

EHLO, falling back to HELO; the extensions come from the EHLO reply.

### `mail(string $from, string $parameters): bool`

MAIL FROM, with the given parameters (" XVERP", " SMTPUTF8") after the address.

### `quit(string $onError = 'close'): bool`

QUIT; the connection closes when it succeeds, or anyway unless told to keep it.

### `recipient(string $address, string $dsn): bool`

RCPT TO, with the delivery notifications asked for (NEVER, SUCCESS, FAILURE, DELAY).

### `xclient(array $vars, array $allowed): bool`

XCLIENT with the named attributes that are in $allowed; others are dropped.

- `@param array<string, string> $vars`
- `@param list<string> $allowed`

### `turn(): bool`

TURN is refused here, as the reference refuses it.

### `command(string $name, string $line, array $expect): bool`

Sends one command and reads the reply; a reply outside $expect fails
with "{name} command failed" and the reply's detail and codes.

- `@param list<int> $expect`

### `send(string $data, string $command): int|false`

Writes raw text to the server; credential commands show as hidden in the debug text.

### `error(): array`

The last error; every field is empty after a command that succeeded.

- `@return array{error: string, detail: string, smtp_code: int|string, smtp_code_ex: string}`

### `extensions(): ?array`

The extensions from the last EHLO (or the HELO greeting), null before one.

- `@return array<string, mixed>|null`

### `extension(string $name): mixed`

One extension's value: true for a bare one, a string or list for one with arguments, false when absent, null before EHLO.

### `lastReply(): string`

The server's last reply, every line.

### `transactionId(): string|false|null`

The queue id the server gave the last message: null before one was sent, false when none was recognised.

### static `parseReply(string $reply): array`

The reply's code, enhanced code (when present) and the text after them.

- `@return array{0: int, 1: string, 2: string}`

Internals: `greet()` (private, line 351), `extensionsFrom()` (private, line 360), `mechanism()` (private, line 382), `unsupported()` (private, line 405), `open()` (private, line 415), `lines()` (private, line 433), `failed()` (private, line 463), `setError()` (private, line 469), `say()` (private, line 474)


## TextWrap

`final class Minn\Mail\TextWrap` · `public/minn/src/Minn/Mail/TextWrap.php`

Word wrapping for mail text. Lines break at spaces once they would pass
the length; existing line breaks stay; a word longer than the line stays
whole in plain text, and in quoted-printable text is cut into pieces
ending in a soft break ("="), never inside an =XX escape or (for UTF-8)
inside an encoded character. A line broken at a space keeps the space
and, in quoted-printable text, a soft break after it.

Used by: `Minn\Mail\HeaderWords`

### static `plain(string $text, int $length, string $eol): string`

Plain text wrapped to the length, every line ending in $eol.

### static `quotedPrintable(string $text, int $length, string $charset, string $eol): string`

Quoted-printable text wrapped to the length with soft breaks, every line ending in $eol.

### static `utf8Boundary(string $encoded, int $max): int`

Where to cut quoted-printable UTF-8 text at or before $max without
splitting an encoded character.

Internals: `wrapWith()` (private, line 48), `line()` (private, line 61), `closeLine()` (private, line 90), `pieces()` (private, line 105), `cut()` (private, line 122)


## Transfer

`final class Minn\Mail\Transfer` · `public/minn/src/Minn/Mail/Transfer.php`

Body text in a content transfer encoding, and the line-ending helpers
mail composition leans on.

- const `MAX_LINE` = `998`

Used by: `Minn\Mail\AddressRules`, `Minn\Mail\Composer`, `Minn\Mail\Dkim`, `Minn\Mail\HeaderWords`, `Minn\Mail\Smime`, `Minn\Mail\TextWrap`

### static `encode(string $text, string $encoding, string $eol): string`

The text in an encoding: 7bit, 8bit and binary keep the text (the first
two with normalized line breaks and a final one), base64 in 76-column
lines, quoted-printable by PHP's encoder.

### static `quotedPrintable(string $text, string $eol): string`

Quoted-printable text with the encoder's line breaks normalized.

### static `normalizeBreaks(string $text, string $eol = ' '): string`

Every CRLF, CR or LF as $eol.

### static `stripTrailingSpace(string $text): string`

The text without trailing spaces, tabs and line breaks.

### static `stripTrailingBreaks(string $text): string`

The text without trailing line breaks.

### static `has8bit(string $text): bool`

Whether any byte is outside 7-bit ASCII.

### static `hasLongLine(string $text): bool`

Whether any line runs past the 998 characters SMTP allows.

