<?php

declare(strict_types=1);

namespace Minn\Ops;

use Minn\Admin\SubmitButton;
use Minn\I18n\Gettext;
use Minn\Runtime\Handoff;
use Minn\Runtime\OptionWrites;
use Minn\Runtime\Runtime;
use Minn\Support\Escape;
use Minn\Support\Slashes;
use Minn\Support\Url;

/**
 * How the site reaches its own files, and the connection it asks for when
 * it cannot write them itself (probes filesystem-credentials and
 * filesystem-credentials-constants).
 *
 * The method (get_filesystem_method) is FS_METHOD when set; otherwise
 * direct when a file made in the folder (the content folder by default)
 * belongs to the owner of the running script, or when ownership is relaxed
 * and the file could be made at all; otherwise SSH2, the FTP extension or
 * FTP over sockets, whichever this PHP has; filesystem_method has the last
 * word.
 *
 * The connection (request_filesystem_credentials): request_filesystem_
 * credentials may answer first; a direct method needs none. The stored
 * connection (ftp_credentials) is the start, each field then taken from its
 * FTP_* constant, else from the form posted with a good _fs_nonce when it
 * is not empty, else as stored; a host's ":port" is split off (kept only
 * when it is a number); the type is ssh under FTP_SSH, ftps under FTP_SSL,
 * else as posted, stored, or ftp. With no error to show, a host, a user and
 * a password (or, over SSH, both key paths) make it complete: it is stored
 * (not autoloaded) without the password, the keys or the port (the port
 * back on the host)
 * and returned. Otherwise the answer is false and the form is printed, as
 * the reference prints it, after the error's notice (the facade's
 * wp_admin_notice).
 */
final class FilesystemCredentials
{
    private const NONCE = 'filesystem-credentials';
    private const CONSTANTS = ['hostname' => 'FTP_HOST', 'username' => 'FTP_USER', 'password' => 'FTP_PASS', 'public_key' => 'FTP_PUBKEY', 'private_key' => 'FTP_PRIKEY'];

    /**
     * The method the site's files are reached by (get_filesystem_method).
     *
     * @param array{0?: mixed, 1?: mixed, 2?: mixed} $asked the arguments as given: the method's own, the folder, whether ownership is relaxed
     */
    public static function method(array $asked): mixed
    {
        [$args, $context, $relaxed] = [(array) ($asked[0] ?? []), $asked[1] ?? '', (bool) ($asked[2] ?? false)];
        $context = Url::withTrailingSlash(is_string($context) && $context !== '' ? $context : Runtime::current()->contentDir());
        $method = defined('FS_METHOD') ? constant('FS_METHOD') : false;
        if (!$method) {
            $own = self::ownsNewFiles($context);
            $method = $own || $relaxed && $own !== null ? 'direct' : self::transport();
        }
        return Runtime::hooks()->filter('filesystem_method', [$method, $args, $context, $relaxed]);
    }

    /**
     * The connection, or false with the form to print
     * (request_filesystem_credentials); an error's notice is the caller's.
     *
     * @param array{0: mixed, 1?: mixed, 2?: mixed, 3?: mixed, 4?: mixed, 5?: mixed} $asked the arguments as given: the form's address, the type, the error, the folder, the extra fields, whether ownership is relaxed
     * @param array<string, mixed> $posted the request's form
     * @return array{0: mixed, 1: string} the answer, then what is printed
     */
    public static function request(array $asked, array $posted): array
    {
        $asked += [1 => '', 2 => false, 3 => '', 4 => null, 5 => false];
        [$formPost, $type, $error, $context, $extraFields, $relaxed] = [(string) $asked[0], (string) $asked[1], $asked[2], $asked[3], $asked[4], $asked[5]];
        $answer = Runtime::hooks()->filter('request_filesystem_credentials', ['', $asked[0], $asked[1], $error, $context, $extraFields, $relaxed]);
        if ($answer !== '') {
            return [$answer, ''];
        }
        if ($type === '') {
            $type = self::method([[], $context, $relaxed]);
        }
        if ($type === 'direct') {
            return [true, ''];
        }
        $submitted = Handoff::verifyNonce((string) ($posted['_fs_nonce'] ?? ''), self::NONCE) ? (array) Slashes::strip($posted) : [];
        $credentials = self::credentials($submitted);
        $ssh = $credentials['connection_type'] === 'ssh';
        $complete = !empty($credentials['hostname']) && !empty($credentials['username'])
            && (!empty($credentials['password']) || $ssh && !empty($credentials['public_key']) && !empty($credentials['private_key']));
        if (!$error && $complete) {
            $stored = array_diff_key($credentials, ['password' => true, 'port' => true, 'private_key' => true, 'public_key' => true]);
            $stored['hostname'] = self::host($credentials);
            OptionWrites::update('ftp_credentials', $stored, false);
            return [$credentials, ''];
        }
        $types = (array) Runtime::hooks()->filter('fs_ftp_connection_types', [self::types(), $credentials, $type, $error, $context]);
        return [false, self::form($formPost, $credentials, $types, is_array($extraFields) ? $extraFields : [], $posted)];
    }

    /**
     * The connection as stored, its fields from the constants, the form or
     * what was stored, its port split from the host and its type settled.
     *
     * @param array<string, mixed> $submitted
     * @return array<string, mixed>
     */
    private static function credentials(array $submitted): array
    {
        $stored = Runtime::options()->filtered('ftp_credentials', ['hostname' => '', 'username' => '']);
        // The reference fails outright on a stored value that is not a list; here it counts as none.
        $credentials = is_array($stored) ? $stored : [];
        foreach (self::CONSTANTS as $field => $constant) {
            $credentials[$field] = defined($constant) ? constant($constant) : (!empty($submitted[$field]) ? $submitted[$field] : ($credentials[$field] ?? ''));
        }
        unset($credentials['port']);
        $host = is_scalar($credentials['hostname']) ? (string) $credentials['hostname'] : '';
        if (str_contains($host, ':')) {
            [$credentials['hostname'], $port] = explode(':', $host, 2);
            if (is_numeric($port)) {
                $credentials['port'] = $port;
            }
        }
        $credentials['connection_type'] = match (true) {
            defined('FTP_SSH') && constant('FTP_SSH') => 'ssh',
            defined('FTP_SSL') && constant('FTP_SSL') => 'ftps',
            !empty($submitted['connection_type']) => $submitted['connection_type'],
            !isset($credentials['connection_type']) => 'ftp',
            default => $credentials['connection_type'],
        };
        return $credentials;
    }

    /** The host with its port, as stored and shown. @param array<string, mixed> $credentials */
    private static function host(array $credentials): string
    {
        return (string) $credentials['hostname'] . (empty($credentials['port']) ? '' : ':' . $credentials['port']);
    }

    /** The connection types this PHP can offer. @return array<string, string> */
    private static function types(): array
    {
        $sockets = extension_loaded('sockets') || function_exists('fsockopen');
        return array_merge(
            extension_loaded('ftp') || $sockets ? ['ftp' => Gettext::text('FTP')] : [],
            extension_loaded('ftp') && function_exists('ftp_ssl_connect') ? ['ftps' => Gettext::text('FTPS (SSL)')] : [],
            extension_loaded('ssh2') ? ['ssh' => Gettext::text('SSH2')] : [],
        );
    }

    /** Whether a file made in the folder belongs to the running script's owner; null when none can be made there. */
    private static function ownsNewFiles(string $context): ?bool
    {
        $file = $context . 'temp-write-test-' . str_replace('.', '-', uniqid('', true));
        $handle = is_dir($context) && is_writable($context) ? fopen($file, 'w') : false;
        if ($handle === false) {
            return null;
        }
        $own = fileowner($file) === self::scriptOwner();
        fclose($handle);
        unlink($file);
        return $own;
    }

    /**
     * The running script's owner, as getmyuid() reports it. Where a host
     * disables getmyuid() it is the owner of the engine's own code: the first
     * included file can be the host's auto_prepend_file (root-owned on
     * Kinsta), which made every update ask for an FTP connection.
     */
    private static function scriptOwner(): int|false
    {
        return function_exists('getmyuid') ? getmyuid() : fileowner(__FILE__);
    }

    /** The first remote transport this PHP has, or false. */
    private static function transport(): string|false
    {
        return match (true) {
            extension_loaded('ssh2') => 'ssh2',
            extension_loaded('ftp') => 'ftpext',
            extension_loaded('sockets') || function_exists('fsockopen') => 'ftpsockets',
            default => false,
        };
    }

    /**
     * The form asking for the connection, character for character as the
     * reference prints it.
     *
     * @param array<string, mixed> $credentials
     * @param array<mixed> $types
     * @param array<mixed> $extraFields
     * @param array<string, mixed> $posted
     */
    private static function form(string $formPost, array $credentials, array $types, array $extraFields, array $posted): string
    {
        $disabled = static fn (bool $on): string => $on ? " disabled='disabled'" : '';
        $ftp = isset($types['ftp']) || isset($types['ftps']);
        $ssh = isset($types['ssh']);
        [$user, $pass, $ask] = match (true) {
            $ftp && $ssh => ['FTP/SSH Username', 'FTP/SSH Password', 'Please enter your FTP or SSH credentials to proceed.'],
            $ftp => ['FTP Username', 'FTP Password', 'Please enter your FTP credentials to proceed.'],
            default => ['Username', 'Password', ''],
        };
        $intro = Gettext::text('To perform the requested action, WordPress needs to access your web server.')
            . ($ask === '' ? '' : ' ' . Gettext::text($ask))
            . ' ' . Gettext::text('If you do not remember your credentials, you should contact your web host.');
        $html = '<form action="' . Escape::url($formPost) . "\" method=\"post\">\n"
            . "<div id=\"request-filesystem-credentials-form\" class=\"request-filesystem-credentials-form\">\n"
            . "\t<h2 id='request-filesystem-credentials-title'>" . Gettext::text('Connection Information') . "</h2><p id=\"request-filesystem-credentials-desc\">\n"
            . "\t" . $intro . "</p>\n"
            . "<label for=\"hostname\">\n"
            . "\t<span class=\"field-title\">" . Gettext::text('Hostname') . "</span>\n"
            . "\t<input name=\"hostname\" type=\"text\" id=\"hostname\" aria-describedby=\"request-filesystem-credentials-desc\" class=\"code\" placeholder=\"" . Escape::attr(Gettext::text('example: www.wordpress.org')) . '" value="' . Escape::attr(self::host($credentials)) . '"' . $disabled(defined('FTP_HOST')) . " />\n"
            . "</label>\n"
            . "<div class=\"ftp-username\">\n"
            . "\t<label for=\"username\">\n"
            . "\t\t<span class=\"field-title\">" . Gettext::text($user) . "</span>\n"
            . "\t\t<input name=\"username\" type=\"text\" id=\"username\" value=\"" . Escape::attr($credentials['username']) . '"' . $disabled(defined('FTP_USER')) . " />\n"
            . "\t</label>\n"
            . "</div>\n"
            . "<div class=\"ftp-password\">\n"
            . "\t<label for=\"password\">\n"
            . "\t\t<span class=\"field-title\">" . Gettext::text($pass) . "</span>\n"
            . "\t\t<input name=\"password\" type=\"password\" id=\"password\" value=\"" . (defined('FTP_PASS') ? '*****' : '') . '"' . $disabled(defined('FTP_PASS')) . " spellcheck=\"false\" />\n"
            . "\t\t" . (defined('FTP_PASS') ? '' : Gettext::text('This password will not be stored on the server.')) . "\t</label>\n"
            . "</div>\n"
            . "<fieldset>\n"
            . '<legend>' . Gettext::text('Connection Type') . "</legend>\n";
        $locked = defined('FTP_SSL') || defined('FTP_SSH');
        foreach ($types as $name => $text) {
            $name = Escape::attr($name);
            $html .= "\t\t<label for=\"{$name}\">\n"
                . "\t\t<input type=\"radio\" name=\"connection_type\" id=\"{$name}\" value=\"{$name}\" " . ((string) $name === (string) $credentials['connection_type'] ? " checked='checked'" : '') . ' ' . $disabled($locked) . " />\n"
                . "\t\t" . Escape::html($text) . "\t</label>\n\t";
        }
        $html .= "\t</fieldset>\n";
        if ($ssh) {
            $html .= "\t<fieldset id=\"ssh-keys\"" . ($credentials['connection_type'] === 'ssh' ? '' : ' class="hidden"') . ">\n"
                . '<legend>' . Gettext::text('Authentication Keys') . "</legend>\n"
                . "<label for=\"public_key\">\n"
                . "\t<span class=\"field-title\">" . Gettext::text('Public Key:') . "</span>\n"
                . "\t<input name=\"public_key\" type=\"text\" id=\"public_key\" aria-describedby=\"auth-keys-desc\" value=\"" . Escape::attr($credentials['public_key']) . '"' . $disabled(defined('FTP_PUBKEY')) . " />\n"
                . "</label>\n"
                . "<label for=\"private_key\">\n"
                . "\t<span class=\"field-title\">" . Gettext::text('Private Key:') . "</span>\n"
                . "\t<input name=\"private_key\" type=\"text\" id=\"private_key\" value=\"" . Escape::attr($credentials['private_key']) . '"' . $disabled(defined('FTP_PRIKEY')) . " />\n"
                . "</label>\n"
                . '<p id="auth-keys-desc">' . Gettext::text('Enter the location on the server where the public and private keys are located. If a passphrase is needed, enter that in the password field above.') . "</p>\n"
                . "</fieldset>\n\t";
        }
        $html .= "\t";
        foreach ($extraFields as $field) {
            if (is_scalar($field) && isset($posted[$field])) {
                $value = Slashes::strip($posted[$field]);
                $html .= '<input type="hidden" name="' . Escape::attr($field) . '" value="' . Escape::attr(is_array($value) ? 'Array' : $value) . '" />';
            }
        }
        return $html . "\t<p class=\"request-filesystem-credentials-action-buttons\">\n"
            . "\t\t<input type=\"hidden\" id=\"_fs_nonce\" name=\"_fs_nonce\" value=\"" . Escape::attr(Handoff::createNonce(self::NONCE)) . "\" />\t\t<button class=\"button cancel-button\" data-js-action=\"close\" type=\"button\">" . Gettext::text('Cancel') . "</button>\n"
            . "\t\t" . SubmitButton::input(Gettext::text('Proceed'), ['primary'], 'upgrade', '') . "\t</p>\n"
            . "</div>\n"
            . "</form>\n\t";
    }
}
