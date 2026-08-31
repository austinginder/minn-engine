<?php
/**
 * The PHPMailer interface plugin code drives (Gravity SMTP configures one
 * per send). Original implementation: state recording here, the MIME
 * composition in Minn\Mail\Mime, delivery through Minn\Mail\Smtp when the
 * caller asked for SMTP, PHP's mail() otherwise. The wp-includes/PHPMailer
 * skeleton files are empty placeholders; these classes are the engine's.
 */

namespace PHPMailer\PHPMailer;

class Exception extends \Exception
{
}

class SMTP
{
    public const DEBUG_OFF = 0;
    public const DEBUG_CLIENT = 1;
    public const DEBUG_SERVER = 2;
    public const DEBUG_CONNECTION = 3;
    public const DEBUG_LOWLEVEL = 4;
}

class PHPMailer
{
    /** @var string|callable the address validator callers may swap in */
    public static $validator = 'php';

    public $Host = 'localhost';
    public $Port = 25;
    public $Username = '';
    public $Password = '';
    public $SMTPSecure = '';
    public $SMTPAuth = false;
    public $SMTPAutoTLS = true;
    public $SMTPDebug = 0;
    public $Timeout = 300;
    public $CharSet = 'iso-8859-1';
    public $ContentType = 'text/plain';
    public $Encoding = '8bit';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $From = 'root@localhost';
    public $FromName = 'Root User';
    public $Sender = '';
    public $ErrorInfo = '';
    public $Mailer = 'mail';

    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $replyTo = [];
    protected $attachments = [];
    protected $customHeaders = [];
    protected $exceptions = false;
    protected $sentMime = '';

    public function __construct($exceptions = null)
    {
        $this->exceptions = (bool) $exceptions;
    }

    public function isSMTP()
    {
        $this->Mailer = 'smtp';
    }

    public function isMail()
    {
        $this->Mailer = 'mail';
    }

    public function isHTML($isHtml = true)
    {
        $this->ContentType = $isHtml ? 'text/html' : 'text/plain';
    }

    public function setFrom($address, $name = '', $auto = true)
    {
        $this->From = (string) $address;
        $this->FromName = (string) $name;
        return true;
    }

    public function addAddress($address, $name = '')
    {
        $this->to[] = [(string) $address, (string) $name];
        return true;
    }

    public function addCC($address, $name = '')
    {
        $this->cc[] = [(string) $address, (string) $name];
        return true;
    }

    public function addBCC($address, $name = '')
    {
        $this->bcc[] = [(string) $address, (string) $name];
        return true;
    }

    public function addReplyTo($address, $name = '')
    {
        $this->replyTo[] = [(string) $address, (string) $name];
        return true;
    }

    public function addAttachment($path, $name = '', $encoding = 'base64', $type = '', $disposition = 'attachment')
    {
        if (!@is_file($path)) {
            return $this->fail('Could not access file: ' . $path);
        }
        $this->attachments[] = [(string) $path, (string) $name];
        return true;
    }

    public function addCustomHeader($name, $value = null)
    {
        if ($value === null && str_contains((string) $name, ':')) {
            [$name, $value] = explode(':', (string) $name, 2);
        }
        $this->customHeaders[] = [(string) $name, (string) $value];
        return true;
    }

    public function clearAddresses()
    {
        $this->to = [];
    }

    public function clearCCs()
    {
        $this->cc = [];
    }

    public function clearBCCs()
    {
        $this->bcc = [];
    }

    public function clearReplyTos()
    {
        $this->replyTo = [];
    }

    public function clearAllRecipients()
    {
        $this->to = [];
        $this->cc = [];
        $this->bcc = [];
    }

    public function clearAttachments()
    {
        $this->attachments = [];
    }

    public function clearCustomHeaders()
    {
        $this->customHeaders = [];
    }

    public function getToAddresses()
    {
        return $this->to;
    }

    public function preSend()
    {
        if ($this->to === [] && $this->cc === [] && $this->bcc === []) {
            return $this->fail('You must provide at least one recipient email address.');
        }
        $this->sentMime = \Minn\Mail\Mime::compose(
            $this->From,
            $this->FromName,
            $this->to,
            $this->cc,
            $this->bcc,
            $this->replyTo,
            $this->Subject,
            $this->Body,
            $this->ContentType,
            $this->CharSet,
            $this->customHeaders,
            $this->attachments,
            $this->Sender,
        );
        return true;
    }

    public function getSentMIMEMessage()
    {
        return $this->sentMime;
    }

    public function send()
    {
        if (!$this->preSend()) {
            return false;
        }
        return $this->postSend();
    }

    public function postSend()
    {
        $recipients = array_map(static fn (array $entry) => $entry[0], array_merge($this->to, $this->cc, $this->bcc));
        try {
            if ($this->Mailer === 'smtp') {
                return $this->smtp()->sendRaw($this->Sender !== '' ? $this->Sender : $this->From, $recipients, $this->sentMime);
            }
            [$headers, $body] = explode("\r\n\r\n", $this->sentMime, 2);
            $headers = (string) preg_replace('/^(To|Subject): [^\r\n]*\r\n/mi', '', $headers . "\r\n");
            return mail(implode(', ', array_map(static fn (array $e) => $e[0], $this->to)), \Minn\Mail\Mailer::encodeHeader($this->Subject), $body, trim($headers));
        } catch (\Throwable $error) {
            return $this->fail($error->getMessage());
        }
    }

    public function smtpConnect($options = null)
    {
        $socket = @stream_socket_client(($this->SMTPSecure === 'ssl' ? 'ssl://' : 'tcp://') . $this->Host . ':' . (int) $this->Port, $errno, $error, 10);
        if ($socket === false) {
            return $this->fail("SMTP connect to {$this->Host}:{$this->Port} failed: {$error}");
        }
        fclose($socket);
        return true;
    }

    /** @return \Minn\Mail\Smtp a client over this mailer's own connection settings */
    protected function smtp()
    {
        $encryption = $this->SMTPSecure === 'ssl' ? 'ssl' : ($this->SMTPSecure === 'tls' || $this->SMTPAutoTLS ? 'tls' : 'none');
        $settings = new \Minn\Mail\MailSettings('smtp', (string) $this->Host, (int) $this->Port, $encryption, $this->SMTPAuth ? (string) $this->Username : '', (string) $this->Password, $this->From, $this->FromName);
        return new \Minn\Mail\Smtp($settings);
    }

    /** @return false */
    protected function fail($message)
    {
        $this->ErrorInfo = (string) $message;
        if ($this->exceptions) {
            throw new Exception((string) $message);
        }
        return false;
    }
}
