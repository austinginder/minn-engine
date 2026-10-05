<?php
/**
 * The parts of wp_mail() (pluggable.php) a plugin never replaces: the
 * shared WP_PHPMailer in the global $phpmailer, reset between sends; the
 * sender, recipients, content type, charset, custom headers and files set
 * from the arguments in the reference's order, its filters included; the
 * engine's own mail settings (the minn_mail option) as the transport when
 * the site has configured one; and the failure action.
 */

use Minn\Mail\MailHeaders;
use Minn\Mail\MailSettings;
use Minn\Runtime\Runtime;

/** The global $phpmailer, created as a throwing WP_PHPMailer when it is not a mailer, and cleared of the last message. */
function _minn_wp_phpmailer()
{
    global $phpmailer;
    if (!($phpmailer instanceof PHPMailer\PHPMailer\PHPMailer)) {
        $phpmailer = new WP_PHPMailer(true);
        $phpmailer::$validator = static fn ($email) => (bool) is_email($email);
    }
    $phpmailer->clearAllRecipients();
    $phpmailer->clearAttachments();
    $phpmailer->clearCustomHeaders();
    $phpmailer->clearReplyTos();
    $phpmailer->Body = '';
    $phpmailer->AltBody = '';
    return $phpmailer;
}

/**
 * Sets the message on the mailer: sender (wp_mail_from, wp_mail_from_name),
 * recipients, subject and body, transport, content type (wp_mail_content_type,
 * asked once before the sender and once with any boundary), charset
 * (wp_mail_charset), custom headers and files. A refused sender throws.
 */
function _minn_wp_mail_prepare($phpmailer, MailHeaders $parsed, array $args)
{
    $stored = MailSettings::stored(Runtime::current()->site);
    $type = apply_filters('wp_mail_content_type', $parsed->contentType ?? 'text/plain');
    $fromEmail = apply_filters('wp_mail_from', $parsed->fromEmail ?? _minn_wp_mail_default_from($stored));
    $fromName = apply_filters('wp_mail_from_name', (string) $parsed->fromName !== '' ? $parsed->fromName : ((string) ($stored['from_name'] ?? '') ?: 'WordPress'));
    $phpmailer->setFrom($fromEmail, $fromName, false);
    _minn_wp_mail_recipients($phpmailer, (array) $args['to'], $parsed);
    $phpmailer->Subject = $args['subject'];
    $phpmailer->Body = $args['message'];
    $phpmailer->isMail();
    _minn_wp_mail_transport($phpmailer);
    $phpmailer->ContentType = apply_filters('wp_mail_content_type', $type . ($parsed->boundary !== '' ? '; boundary="' . $parsed->boundary . '"' : ''));
    if ($phpmailer->ContentType === 'text/html') {
        $phpmailer->isHTML(true);
    }
    $phpmailer->CharSet = apply_filters('wp_mail_charset', $parsed->charset ?? get_bloginfo('charset'));
    foreach ($parsed->custom as $name => $value) {
        if (!in_array(strtolower((string) $name), ['mime-version', 'x-mailer'], true)) {
            $phpmailer->addCustomHeader(sprintf('%1$s: %2$s', $name, $value));
        }
    }
    _minn_wp_mail_files($phpmailer, (array) $args['attachments'], (array) $args['embeds']);
}

/** The default sender: the configured address, else wordpress@ the site's host (without www.). */
function _minn_wp_mail_default_from(array $stored)
{
    if ((string) ($stored['from_email'] ?? '') !== '') {
        return (string) $stored['from_email'];
    }
    $host = strtolower((string) wp_parse_url(network_home_url(), PHP_URL_HOST));
    return 'wordpress@' . (str_starts_with($host, 'www.') ? substr($host, 4) : $host);
}

/** To, Cc, Bcc and Reply-To entries; one the mailer refuses is skipped. */
function _minn_wp_mail_recipients($phpmailer, array $to, MailHeaders $parsed)
{
    foreach (['addAddress' => $to, 'addCC' => $parsed->cc, 'addBCC' => $parsed->bcc, 'addReplyTo' => $parsed->replyTo] as $method => $entries) {
        foreach ($entries as $entry) {
            [$address, $name] = MailHeaders::recipient((string) $entry);
            try {
                $phpmailer->{$method}($address, $name);
            } catch (PHPMailer\PHPMailer\Exception $e) {
                continue;
            }
        }
    }
}

/** Attachments (a string key names the file) and inline images (the key is the content id); a file that cannot be read is skipped. */
function _minn_wp_mail_files($phpmailer, array $attachments, array $embeds)
{
    foreach ($attachments as $key => $path) {
        try {
            $phpmailer->addAttachment($path, is_string($key) ? $key : '');
        } catch (PHPMailer\PHPMailer\Exception $e) {
            continue;
        }
    }
    foreach ($embeds as $key => $path) {
        try {
            $phpmailer->addEmbeddedImage($path, (string) $key);
        } catch (PHPMailer\PHPMailer\Exception $e) {
            continue;
        }
    }
}

/** The engine's configured transport, when the site set one: its SMTP server, or the development log. */
function _minn_wp_mail_transport($phpmailer)
{
    if (MailSettings::stored(Runtime::current()->site) === []) {
        return;
    }
    $settings = MailSettings::fromSite(Runtime::current()->site);
    if ($settings->transport === 'log') {
        $phpmailer->Mailer = 'minnlog';
    } elseif ($settings->transport === 'smtp') {
        $phpmailer->isSMTP();
        [$phpmailer->Host, $phpmailer->Port, $phpmailer->Username, $phpmailer->Password] = [$settings->host, $settings->port, $settings->username, $settings->password];
        $phpmailer->SMTPSecure = $settings->encryption === 'none' ? '' : $settings->encryption;
        $phpmailer->SMTPAutoTLS = $settings->encryption !== 'none';
        $phpmailer->SMTPAuth = $settings->username !== '';
    }
}

/** Reports a failed send through wp_mail_failed and answers false. */
function _minn_wp_mail_failed($exception, array $data)
{
    $data['phpmailer_exception_code'] = $exception->getCode();
    do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $exception->getMessage(), $data));
    return false;
}
