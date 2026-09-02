# `Minn\Mail`

sending mail and the notices the engine sends

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`MailSettings`](#mailsettings) | final readonly class | 60 | How mail leaves the site, from the minn_mail option (JSON or a serialized |
| [`Mailer`](#mailer) | final readonly class | 87 | Sends a Message through the configured transport. Failures are logged |
| [`Message`](#message) | final readonly class | 18 | One outgoing plain-text email. |
| [`Mime`](#mime) | final readonly class | 90 | A full MIME message from recorded mailer state, for the PHPMailer facade: |
| [`Notices`](#notices) | final readonly class | 65 | The messages the engine itself sends, worded as the reference words them |
| [`Smtp`](#smtp) | final readonly class | 86 | A small SMTP client: SSL or STARTTLS, AUTH LOGIN or PLAIN, one message per connection. |

## MailSettings

`final readonly class Minn\Mail\MailSettings` · `public/minn/src/Minn/Mail/MailSettings.php`

How mail leaves the site, from the minn_mail option (JSON or a serialized
array): the PHP mail
function by default, SMTP when configured, or a log file for development.
The sender defaults to the site name at a no-reply address on the home host.

- const `OPTION` = `'minn_mail'`

Used by: `Minn\Admin\Diagnostics`, `Minn\Cli\MinnCommand`, `Minn\Mail\Mailer`, `Minn\Mail\Smtp`

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

### static `fromSite(Minn\Content\Site $site): self`

The site's mail settings from its minn_mail option, or the defaults.

### `toArray(): array`

The option's JSON, secrets included, for the settings surface.


## Mailer

`final readonly class Minn\Mail\Mailer` · `public/minn/src/Minn/Mail/Mailer.php`

Sends a Message through the configured transport. Failures are logged
and reported as false; nothing here throws into a request.

Used by: `Minn\Cli\MinnCommand`, `Minn\Cli\UserCommand`, `Minn\Engine`, `Minn\Front\CommentPostController`, `Minn\Login\LoginController`, `Minn\Mail\Mime`, `Minn\Mail\Smtp`, `Minn\Rest\CommentsController`, `Minn\Rest\UsersController`

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

Sends one message over the configured transport; false on failure.

### static `address(string $email, string $name): string`

A mail address with an optional display name, header-encoded.

### static `encodeHeader(string $value): string`

A header value: no line breaks, encoded when not plain ASCII.

Internals: `viaMail()` (private, line 67), `log()` (private, line 76)


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


## Mime

`final readonly class Minn\Mail\Mime` · `public/minn/src/Minn/Mail/Mime.php`

A full MIME message from recorded mailer state, for the PHPMailer facade:
address headers, custom headers, the single-part body, and a
multipart/mixed wrap with base64 attachments when files ride along.

### static `compose(string $from, string $fromName, array $to, array $cc, array $bcc, array $replyTo, string $subject, string $body, string $contentType, string $charset, array $customHeaders, array $attachments, string $sender = ''): string`

A whole MIME message, headers and body, from its parts.

- `@param list<array{0: string, 1: string}> $to [address, name]`
- `@param list<array{0: string, 1: string}> $cc`
- `@param list<array{0: string, 1: string}> $bcc`
- `@param list<array{0: string, 1: string}> $replyTo`
- `@param list<array{0: string, 1: string}> $customHeaders [name, value]`
- `@param list<array{0: string, 1: string}> $attachments [path, name]`

Internals: `line()` (private, line 62), `payload()` (private, line 71), `addressList()` (private, line 97)


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


## Smtp

`final readonly class Minn\Mail\Smtp` · `public/minn/src/Minn/Mail/Smtp.php`

A small SMTP client: SSL or STARTTLS, AUTH LOGIN or PLAIN, one message per connection.

Used by: `Minn\Mail\Mailer`

```php
__construct(Minn\Mail\MailSettings $settings)
```


### `send(string $from, string $fromName, array $to, string $subject, string $body): bool`

Sends one message over SMTP; false on failure.

- `@param list<string> $to`

### `sendRaw(string $from, array $recipients, string $data): bool`

One raw MIME message (headers and body) to the listed envelope recipients. @param list<string> $recipients

- `@param list<string> $recipients`

Internals: `command()` (private, line 73), `expect()` (private, line 80)

