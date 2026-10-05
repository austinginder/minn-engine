<?php
/**
 * PHPMailer as plugins drive it: the PHPMailer class (addresses, content,
 * attachments, headers, the composed message, sending through PHP's mail(),
 * sendmail or SMTP, DKIM), its SMTP client and its Exception. An original
 * implementation over Minn\Mail (Composer writes the message, SmtpSession
 * speaks SMTP, Dkim signs), matched to the reference by captured messages
 * and SMTP transcripts. The wp-includes/PHPMailer files are empty
 * placeholders so the reference's require_once lines still resolve.
 */

namespace PHPMailer\PHPMailer;

use Minn\Mail\AddressRules;
use Minn\Mail\Composer;
use Minn\Mail\DebugOutput;
use Minn\Mail\Dkim;
use Minn\Mail\Draft;
use Minn\Mail\HeaderWords;
use Minn\Mail\HostEntry;
use Minn\Mail\HtmlMessage;
use Minn\Mail\MailerStrings;
use Minn\Mail\MailLog;
use Minn\Mail\MimeTypes;
use Minn\Mail\SmtpSession;
use Minn\Mail\TextWrap;
use Minn\Mail\Transfer;

class Exception extends \Exception
{
    public function errorMessage()
    {
        return '<strong>' . htmlspecialchars($this->getMessage(), ENT_COMPAT | ENT_HTML401) . "</strong><br />\n";
    }
}

class SMTP
{
    const VERSION = '7.1.1';
    const LE = "\r\n";
    const DEFAULT_PORT = 25;
    const DEFAULT_SECURE_PORT = 465;
    const MAX_LINE_LENGTH = 998;
    const MAX_REPLY_LENGTH = 512;
    const DEBUG_OFF = 0;
    const DEBUG_CLIENT = 1;
    const DEBUG_SERVER = 2;
    const DEBUG_CONNECTION = 3;
    const DEBUG_LOWLEVEL = 4;

    public $do_debug = self::DEBUG_OFF;
    public $Debugoutput = 'echo';
    public $do_verp = false;
    public $do_smtputf8 = false;
    public $Timeout = 300;
    public $Timelimit = 300;
    public static $xclient_allowed_attributes = ['NAME', 'ADDR', 'PORT', 'PROTO', 'HELO', 'LOGIN', 'DESTADDR', 'DESTPORT'];

    /** @var SmtpSession|null */
    private $minnSession;

    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        return $this->minn()->connect((string) $host, $port === null || $port === '' ? null : (int) $port, (int) $timeout, (array) $options);
    }

    public function startTLS()
    {
        return $this->minn()->startTls();
    }

    public function authenticate($username, $password, $authtype = null, $OAuth = null)
    {
        $token = is_object($OAuth) && method_exists($OAuth, 'getOauth64') ? (string) $OAuth->getOauth64() : null;
        return $this->minn()->authenticate((string) $username, (string) $password, (string) $authtype, $token);
    }

    public function connected()
    {
        return $this->minn()->connected();
    }

    public function close()
    {
        $this->minn()->close();
    }

    public function data($msg_data)
    {
        return $this->minn()->data((string) $msg_data);
    }

    public function hello($host = '')
    {
        return $this->minn()->hello((string) $host);
    }

    public function mail($from)
    {
        return $this->minn()->mail((string) $from, ($this->do_verp ? ' XVERP' : '') . ($this->do_smtputf8 ? ' SMTPUTF8' : ''));
    }

    public function quit($close_on_error = true)
    {
        return $this->minn()->quit($close_on_error ? 'close' : 'keep');
    }

    public function recipient($address, $dsn = '')
    {
        return $this->minn()->recipient((string) $address, (string) $dsn);
    }

    public function xclient($vars)
    {
        return $this->minn()->xclient((array) $vars, static::$xclient_allowed_attributes);
    }

    public function reset()
    {
        return $this->sendCommand('RSET', 'RSET', 250);
    }

    public function sendAndMail($from)
    {
        return $this->sendCommand('SAML', "SAML FROM:{$from}", 250);
    }

    public function verify($name)
    {
        return $this->sendCommand('VRFY', "VRFY {$name}", [250, 251]);
    }

    public function noop()
    {
        return $this->sendCommand('NOOP', 'NOOP', 250);
    }

    public function turn()
    {
        return $this->minn()->turn();
    }

    public function client_send($data, $command = '')
    {
        return $this->minn()->send((string) $data, (string) $command);
    }

    public function getError()
    {
        return $this->minn()->error();
    }

    public function getServerExtList()
    {
        return $this->minn()->extensions();
    }

    public function getServerExt($name)
    {
        return $this->minn()->extension((string) $name);
    }

    public function getLastReply()
    {
        return $this->minn()->lastReply();
    }

    public function setVerp($enabled = false)
    {
        $this->do_verp = $enabled;
    }

    public function getVerp()
    {
        return $this->do_verp;
    }

    public function setSMTPUTF8($enabled = false)
    {
        $this->do_smtputf8 = $enabled;
    }

    public function getSMTPUTF8()
    {
        return $this->do_smtputf8;
    }

    public function setDebugOutput($method = 'echo')
    {
        $this->Debugoutput = $method;
    }

    public function getDebugOutput()
    {
        return $this->Debugoutput;
    }

    public function setDebugLevel($level = 0)
    {
        $this->do_debug = $level;
    }

    public function getDebugLevel()
    {
        return $this->do_debug;
    }

    public function setTimeout($timeout = 0)
    {
        $this->Timeout = $timeout;
    }

    public function getTimeout()
    {
        return $this->Timeout;
    }

    public function getLastTransactionID()
    {
        return $this->minn()->transactionId();
    }

    protected function sendCommand($command, $commandstring, $expect)
    {
        return $this->minn()->command((string) $command, (string) $commandstring, array_map('intval', (array) $expect));
    }

    protected function edebug($str, $level = 0)
    {
        if ($level <= $this->do_debug) {
            echo DebugOutput::smtp($this->Debugoutput, (string) $str, (int) $level) ?? '';
        }
    }

    private function minn()
    {
        $this->minnSession ??= new SmtpSession(fn (string $text, int $level) => $this->edebug($text, $level));
        $this->minnSession->limits((int) $this->Timeout, (int) $this->Timelimit);
        return $this->minnSession;
    }
}

class PHPMailer
{
    const CHARSET_ASCII = 'us-ascii';
    const CHARSET_ISO88591 = 'iso-8859-1';
    const CHARSET_UTF8 = 'utf-8';
    const CONTENT_TYPE_PLAINTEXT = 'text/plain';
    const CONTENT_TYPE_TEXT_CALENDAR = 'text/calendar';
    const CONTENT_TYPE_TEXT_HTML = 'text/html';
    const CONTENT_TYPE_MULTIPART_ALTERNATIVE = 'multipart/alternative';
    const CONTENT_TYPE_MULTIPART_MIXED = 'multipart/mixed';
    const CONTENT_TYPE_MULTIPART_RELATED = 'multipart/related';
    const ENCODING_7BIT = '7bit';
    const ENCODING_8BIT = '8bit';
    const ENCODING_BASE64 = 'base64';
    const ENCODING_BINARY = 'binary';
    const ENCODING_QUOTED_PRINTABLE = 'quoted-printable';
    const ENCRYPTION_STARTTLS = 'tls';
    const ENCRYPTION_SMTPS = 'ssl';
    const ICAL_METHOD_REQUEST = 'REQUEST';
    const ICAL_METHOD_PUBLISH = 'PUBLISH';
    const ICAL_METHOD_REPLY = 'REPLY';
    const ICAL_METHOD_ADD = 'ADD';
    const ICAL_METHOD_CANCEL = 'CANCEL';
    const ICAL_METHOD_REFRESH = 'REFRESH';
    const ICAL_METHOD_COUNTER = 'COUNTER';
    const ICAL_METHOD_DECLINECOUNTER = 'DECLINECOUNTER';
    const RFC822_DATE_FORMAT = 'D, j M Y H:i:s O';
    const VERSION = '7.1.1';
    const STOP_MESSAGE = 0;
    const STOP_CONTINUE = 1;
    const STOP_CRITICAL = 2;
    const CRLF = "\r\n";
    const FWS = ' ';
    const MAIL_MAX_LINE_LENGTH = 63;
    const MAX_LINE_LENGTH = 998;
    const STD_LINE_LENGTH = 76;

    public $Priority;
    public $CharSet = self::CHARSET_ISO88591;
    public $ContentType = self::CONTENT_TYPE_PLAINTEXT;
    public $Encoding = self::ENCODING_8BIT;
    public $ErrorInfo = '';
    public $From = '';
    public $FromName = '';
    public $Sender = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $Ical = '';
    protected static $IcalMethods = [self::ICAL_METHOD_REQUEST, self::ICAL_METHOD_PUBLISH, self::ICAL_METHOD_REPLY, self::ICAL_METHOD_ADD, self::ICAL_METHOD_CANCEL, self::ICAL_METHOD_REFRESH, self::ICAL_METHOD_COUNTER, self::ICAL_METHOD_DECLINECOUNTER];
    protected $MIMEBody = '';
    protected $MIMEHeader = '';
    protected $mailHeader = '';
    public $WordWrap = 0;
    public $Mailer = 'mail';
    public $Sendmail = '/usr/sbin/sendmail';
    public $UseSendmailOptions = true;
    public $ConfirmReadingTo = '';
    public $Hostname = '';
    public $MessageID = '';
    public $MessageDate = '';
    public $Host = 'localhost';
    public $Port = 25;
    public $Helo = '';
    public $SMTPSecure = '';
    public $SMTPAutoTLS = true;
    public $SMTPAuth = false;
    public $SMTPOptions = [];
    public $Username = '';
    public $Password = '';
    public $AuthType = '';
    protected $oauth;
    public $Timeout = 300;
    public $dsn = '';
    public $SMTPDebug = 0;
    public $Debugoutput = 'echo';
    public $SMTPKeepAlive = false;
    public $SingleTo = false;
    protected $SingleToArray = [];
    public $do_verp = false;
    public $AllowEmpty = false;
    public $DKIM_selector = '';
    public $DKIM_identity = '';
    public $DKIM_passphrase = '';
    public $DKIM_domain = '';
    public $DKIM_copyHeaderFields = true;
    public $DKIM_extraHeaders = [];
    public $DKIM_private = '';
    public $DKIM_private_string = '';
    public $action_function = '';
    public $XMailer = '';
    public static $validator = 'php';
    public $UseSMTPUTF8 = false;
    protected $smtp;
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $ReplyTo = [];
    protected $all_recipients = [];
    protected $RecipientsQueue = [];
    protected $ReplyToQueue = [];
    protected $attachment = [];
    protected $CustomHeader = [];
    protected $lastMessageID = '';
    protected $message_type = '';
    protected $boundary = [];
    protected static $language = [];
    protected $error_count = 0;
    protected $sign_cert_file = '';
    protected $sign_key_file = '';
    protected $sign_extracerts_file = '';
    protected $sign_key_pass = '';
    protected $exceptions = false;
    protected $uniqueid = '';
    protected static $LE = self::CRLF;
    protected $SMTPXClient = [];
    /** @var string the transfer encoding the last composed single-part body declares */
    protected $minnEncoding = '';

    public function __construct($exceptions = null)
    {
        if ($exceptions !== null) {
            $this->exceptions = (bool) $exceptions;
        }
    }

    public function __destruct()
    {
        $this->smtpClose();
    }

    public function isHTML($isHtml = true)
    {
        $this->ContentType = $isHtml ? static::CONTENT_TYPE_TEXT_HTML : static::CONTENT_TYPE_PLAINTEXT;
    }

    public function isSMTP()
    {
        $this->Mailer = 'smtp';
    }

    public function isMail()
    {
        $this->Mailer = 'mail';
    }

    public function isSendmail()
    {
        $path = (string) ini_get('sendmail_path');
        $this->Sendmail = stripos($path, 'sendmail') === false ? '/usr/sbin/sendmail' : $path;
        $this->Mailer = 'sendmail';
    }

    public function isQmail()
    {
        $path = (string) ini_get('sendmail_path');
        $this->Sendmail = stripos($path, 'qmail') === false ? '/var/qmail/bin/qmail-inject' : $path;
        $this->Mailer = 'qmail';
    }

    public function addAddress($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('to', $address, $name);
    }

    public function addCC($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('cc', $address, $name);
    }

    public function addBCC($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('bcc', $address, $name);
    }

    public function addReplyTo($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('Reply-To', $address, $name);
    }

    protected function addOrEnqueueAnAddress($kind, $address, $name)
    {
        $address = trim((string) $address);
        $name = trim((string) preg_replace('/[\r\n]+/', '', (string) $name));
        $at = strrpos($address, '@');
        if ($at === false) {
            return $this->minnRefuse($this->lang('invalid_address') . " ({$kind}): {$address}");
        }
        if (static::has8bitChars(substr($address, $at + 1)) && static::idnSupported()) {
            $queue = $kind === 'Reply-To' ? 'ReplyToQueue' : 'RecipientsQueue';
            if (array_key_exists($address, $this->{$queue})) {
                return false;
            }
            $this->{$queue}[$address] = [$kind, $address, $name];
            return true;
        }
        return $this->addAnAddress($kind, $address, $name);
    }

    protected function addAnAddress($kind, $address, $name = '')
    {
        if (!in_array($kind, ['to', 'cc', 'bcc', 'Reply-To'], true)) {
            return $this->minnRefuse('Invalid recipient kind: ' . $kind);
        }
        if (!static::validateAddress($address)) {
            return $this->minnRefuse($this->lang('invalid_address') . " ({$kind}): {$address}");
        }
        if ($kind === 'Reply-To') {
            return $this->minnAddReplyTo($address, $name);
        }
        if (array_key_exists(strtolower($address), $this->all_recipients)) {
            return false;
        }
        $this->{$kind}[] = [$address, $name];
        $this->all_recipients[strtolower($address)] = true;
        return true;
    }

    private function minnAddReplyTo($address, $name)
    {
        if (in_array(strtolower($address), array_map(static fn ($pair) => strtolower($pair[0]), $this->ReplyTo), true)) {
            return false;
        }
        $this->ReplyTo[] = [$address, $name];
        return true;
    }

    public function setBoundaries()
    {
        $this->uniqueid = $this->generateId();
        $this->boundary = [1 => 'b1=_' . $this->uniqueid, 2 => 'b2=_' . $this->uniqueid, 3 => 'b3=_' . $this->uniqueid];
    }

    public static function parseAddresses($addrstr, $useimap = null, $charset = self::CHARSET_ISO88591)
    {
        return AddressRules::parseList((string) $addrstr, (string) $charset);
    }

    public function setFrom($address, $name = '', $auto = true)
    {
        $address = trim((string) $address);
        $name = trim((string) preg_replace('/[\r\n]+/', '', (string) $name));
        $at = strrpos($address, '@');
        $idn = $at !== false && static::has8bitChars(substr($address, $at + 1)) && static::idnSupported();
        if ($at === false || (!$idn && !static::validateAddress($address))) {
            return $this->minnRefuse($this->lang('invalid_address') . " (From): {$address}");
        }
        $this->From = $address;
        $this->FromName = $name;
        if ($auto && empty($this->Sender)) {
            $this->Sender = $address;
        }
        return true;
    }

    public function getLastMessageID()
    {
        return $this->lastMessageID;
    }

    public static function validateAddress($address, $patternselect = null)
    {
        $patternselect ??= static::$validator;
        if (is_callable($patternselect) && !is_string($patternselect)) {
            return (bool) call_user_func($patternselect, $address);
        }
        if (!is_string($address)) {
            return false;
        }
        return AddressRules::valid($address, is_string($patternselect) ? $patternselect : 'php');
    }

    public static function idnSupported()
    {
        return function_exists('idn_to_ascii') && function_exists('mb_convert_encoding');
    }

    public function punyencodeAddress($address)
    {
        if (empty($this->CharSet) || strrpos((string) $address, '@') === false || !static::idnSupported()) {
            return $address;
        }
        return AddressRules::asciiDomain((string) $address, (string) $this->CharSet);
    }

    public function send()
    {
        try {
            if (!$this->preSend()) {
                return false;
            }
            return $this->postSend();
        } catch (Exception $exc) {
            $this->mailHeader = '';
            $this->setError($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
    }

    public function preSend()
    {
        static::setLE(in_array($this->Mailer, ['smtp', 'mail'], true) ? self::CRLF : PHP_EOL);
        try {
            $this->minnPrepare();
            return true;
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
    }

    private function minnPrepare()
    {
        $this->error_count = 0;
        $this->mailHeader = '';
        $this->minnDequeue();
        if (count($this->to) + count($this->cc) + count($this->bcc) < 1) {
            throw new Exception($this->lang('provide_address'), self::STOP_CRITICAL);
        }
        $this->minnCheckSenders();
        $this->setMessageType();
        if (!$this->AllowEmpty && empty($this->Body)) {
            throw new Exception($this->lang('empty_message'), self::STOP_CRITICAL);
        }
        $this->Subject = trim((string) $this->Subject);
        $this->minnCompose();
    }

    public function postSend()
    {
        try {
            return $this->{$this->minnSendMethod()}($this->MIMEHeader, $this->MIMEBody);
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            $this->edebug($exc->getMessage());
            if ($this->Mailer === 'smtp' && $this->SMTPKeepAlive && $this->smtp !== null && $this->smtp->connected()) {
                $this->smtp->reset();
            }
            if ($this->exceptions) {
                throw $exc;
            }
        }
        return false;
    }

    /** The send method for the Mailer setting: a {Mailer}Send method when the class has one, else PHP's mail(). */
    private function minnSendMethod()
    {
        return match ($this->Mailer) {
            'sendmail', 'qmail' => 'sendmailSend',
            'smtp' => 'smtpSend',
            'mail' => 'mailSend',
            default => method_exists($this, $this->Mailer . 'Send') ? $this->Mailer . 'Send' : 'mailSend',
        };
    }

    protected function sendmailSend($header, $body)
    {
        $header = Transfer::stripTrailingSpace((string) $header) . static::$LE . static::$LE;
        $command = $this->minnSendmailCommand();
        $targets = $this->SingleTo ? array_map(fn ($pair) => [$pair], $this->to) : [$this->to];
        foreach ($targets as $to) {
            $pipe = @popen($command, 'w');
            if ($pipe === false) {
                throw new Exception($this->lang('execute') . $this->Sendmail, self::STOP_CRITICAL);
            }
            fwrite($pipe, ($this->SingleTo ? 'To: ' . $to[0][0] . "\n" : '') . $header . $body);
            $result = pclose($pipe);
            $this->doCallback($result === 0, $to, $this->SingleTo ? [] : $this->cc, $this->SingleTo ? [] : $this->bcc, $this->Subject, $body, $this->From, []);
            if ($result !== 0) {
                throw new Exception($this->lang('execute') . $this->Sendmail, self::STOP_CRITICAL);
            }
        }
        return true;
    }

    protected static function isShellSafe($string)
    {
        $string = (string) $string;
        return $string !== '' && escapeshellcmd($string) === $string && escapeshellarg($string) === "'{$string}'" && (bool) preg_match('/^[\w@.+=\-]+$/u', $string);
    }

    protected static function isPermittedPath($path)
    {
        return !preg_match('#^[a-z][a-z\d+.-]*://#i', (string) $path);
    }

    protected static function fileIsAccessible($path)
    {
        return static::isPermittedPath($path) && @is_file($path) && is_readable($path);
    }

    protected function mailSend($header, $body)
    {
        $header = Transfer::stripTrailingSpace((string) $header) . static::$LE . static::$LE;
        $list = array_map(fn ($pair) => $this->addrFormat($pair), $this->to);
        $to = trim(implode(', ', $list)) ?: 'undisclosed-recipients:;';
        $params = $this->Sender !== '' && static::validateAddress($this->Sender) && static::isShellSafe($this->Sender) ? '-f' . $this->Sender : null;
        $previous = $params !== null ? ini_get('sendmail_from') : false;
        if ($params !== null) {
            ini_set('sendmail_from', $this->Sender);
        }
        $result = $this->minnMailEach($this->SingleTo && count($list) > 1 ? $list : [$to], $body, rtrim($header), $params);
        if ($previous !== false) {
            ini_set('sendmail_from', $previous);
        }
        if (!$result) {
            throw new Exception($this->lang('instantiate'), self::STOP_CRITICAL);
        }
        return true;
    }

    private function minnMailEach(array $targets, $body, $header, $params)
    {
        $result = false;
        foreach ($targets as $to) {
            $result = $this->mailPassthru($to, $this->Subject, $body, $header, $params);
            $this->doCallback($result, $this->SingleTo ? [[$to, '']] : $this->to, $this->cc, $this->bcc, $this->Subject, $body, $this->From, []);
        }
        return $result;
    }

    public function getSMTPInstance()
    {
        if (!is_object($this->smtp)) {
            $this->smtp = new SMTP();
        }
        return $this->smtp;
    }

    public function setSMTPInstance(SMTP $smtp)
    {
        $this->smtp = $smtp;
        return $this->smtp;
    }

    public function setSMTPXclientAttribute($name, $value)
    {
        if (!in_array($name, SMTP::$xclient_allowed_attributes, true)) {
            return false;
        }
        if ($value === null) {
            unset($this->SMTPXClient[$name]);
        } else {
            $this->SMTPXClient[$name] = $value;
        }
        return true;
    }

    public function getSMTPXclientAttributes()
    {
        return $this->SMTPXClient;
    }

    protected function smtpSend($header, $body)
    {
        $header = Transfer::stripTrailingSpace((string) $header) . static::$LE . static::$LE;
        if (!$this->smtpConnect($this->SMTPOptions)) {
            throw new Exception($this->lang('smtp_connect_failed') . ' https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting', self::STOP_CRITICAL);
        }
        $from = $this->Sender !== '' ? $this->Sender : $this->From;
        if (!$this->smtp->mail($from)) {
            $this->setError($this->lang('from_failed') . $from . ' : ' . implode(',', $this->smtp->getError()));
            throw new Exception($this->ErrorInfo, self::STOP_CRITICAL);
        }
        [$sent, $bad] = $this->minnRecipients();
        if (count($this->all_recipients) > count($bad) && !$this->smtp->data($header . $body)) {
            throw new Exception($this->lang('data_not_accepted'), self::STOP_CRITICAL);
        }
        $this->minnSmtpDone($sent, $body);
        if ($bad !== []) {
            throw new Exception($this->lang('recipients_failed') . implode('', array_map(static fn ($b) => $b['to'] . ': ' . $b['error'], $bad)), self::STOP_CONTINUE);
        }
        return true;
    }

    /** @return array{0: list<array{0: bool, 1: string, 2: string}>, 1: list<array{to: string, error: string}>} */
    private function minnRecipients()
    {
        $sent = $bad = [];
        foreach ([$this->to, $this->cc, $this->bcc] as $group) {
            foreach ($group as $pair) {
                $ok = $this->smtp->recipient($pair[0], $this->dsn);
                if (!$ok) {
                    $bad[] = ['to' => $pair[0], 'error' => $this->smtp->getError()['detail']];
                }
                $sent[] = [$ok, $pair[0], $pair[1]];
            }
        }
        return [$sent, $bad];
    }

    private function minnSmtpDone(array $sent, $body)
    {
        $id = $this->smtp->getLastTransactionID();
        if ($this->SMTPKeepAlive) {
            $this->smtp->reset();
        } else {
            $this->smtp->quit();
            $this->smtp->close();
        }
        foreach ($sent as [$ok, $address, $name]) {
            $this->doCallback($ok, [[$address, $name]], [], [], $this->Subject, $body, $this->From, ['smtp_transaction_id' => $id]);
        }
    }

    public function smtpConnect($options = null)
    {
        $this->smtp ??= $this->getSMTPInstance();
        if ($this->smtp->connected()) {
            return true;
        }
        $this->smtp->setTimeout($this->Timeout);
        $this->smtp->setDebugLevel($this->SMTPDebug);
        $this->smtp->setDebugOutput($this->Debugoutput);
        $this->smtp->setVerp($this->do_verp);
        $last = null;
        foreach (HostEntry::parseAll((string) $this->Host) as [$text, $entry]) {
            $result = $this->minnTryHost($text, $entry, (array) ($options ?? $this->SMTPOptions));
            if ($result === true) {
                return true;
            }
            $last = $result ?? $last;
        }
        $this->smtp->close();
        if ($this->exceptions) {
            throw $last ?? new Exception($this->getSmtpErrorMessage('connect_host'));
        }
        return false;
    }

    /** @return true|Exception|null connected, failed after connecting, or not tried */
    private function minnTryHost($text, $entry, array $options)
    {
        if ($entry === null) {
            $this->edebug($this->lang('invalid_hostentry') . ' ' . $text);
            return null;
        }
        [$prefix, $secure, $tls] = $this->minnSecurity($entry->prefix);
        if (!static::isValidHost($entry->host)) {
            $this->edebug($this->lang('invalid_host') . ' ' . $entry->host);
            return null;
        }
        if (!$this->smtp->connect($prefix . $entry->host, $entry->port ?? (int) $this->Port, $this->Timeout, $options)) {
            return null;
        }
        return $this->minnOpenSession($secure, $tls);
    }

    /** @return array{0: string, 1: string, 2: bool} the connect prefix, the security asked for, and whether STARTTLS is required */
    private function minnSecurity($prefix)
    {
        $secure = (string) $this->SMTPSecure;
        $result = $prefix === 'ssl' || ($prefix === '' && $secure === 'ssl')
            ? ['ssl://', 'ssl', false]
            : ['', $prefix === 'tls' ? 'tls' : $secure, $prefix === 'tls' || $secure === 'tls'];
        if (in_array($result[1], ['tls', 'ssl'], true) && !defined('OPENSSL_ALGO_SHA256')) {
            throw new Exception($this->lang('extension_missing') . 'openssl', self::STOP_CRITICAL);
        }
        return $result;
    }

    private function minnOpenSession($secure, $tls)
    {
        try {
            $this->minnGreet($secure, $tls);
            if ($this->SMTPAuth && !$this->smtp->authenticate($this->Username, $this->Password, $this->AuthType, $this->oauth)) {
                throw new Exception($this->lang('authenticate'));
            }
            return true;
        } catch (Exception $exc) {
            $this->edebug($exc->getMessage());
            $this->smtp->quit();
            return $exc;
        }
    }

    private function minnGreet($secure, $tls)
    {
        $hello = $this->Helo !== '' ? $this->Helo : $this->serverHostname();
        $this->smtp->hello($hello);
        if ($this->SMTPAutoTLS && $secure !== 'ssl' && $this->smtp->getServerExt('STARTTLS')) {
            $tls = true;
        }
        if (!$tls) {
            return;
        }
        if (!$this->smtp->startTLS()) {
            throw new Exception($this->getSmtpErrorMessage('connect_host'));
        }
        $this->smtp->hello($hello);
    }

    public function smtpClose()
    {
        if ($this->smtp !== null && $this->smtp->connected()) {
            $this->smtp->quit();
            $this->smtp->close();
        }
    }

    public static function setLanguage($langcode = 'en', $lang_path = '')
    {
        static::$language = MailerStrings::base();
        return $langcode === 'en';
    }

    public function getTranslations()
    {
        if (empty(static::$language)) {
            static::setLanguage();
        }
        return static::$language;
    }

    public function addrAppend($type, $addr)
    {
        return $type . ': ' . implode(', ', array_map(fn ($pair) => $this->addrFormat($pair), (array) $addr)) . static::$LE;
    }

    public function addrFormat($addr)
    {
        if (!isset($addr[1]) || trim((string) $addr[1]) === '') {
            return $this->secureHeader($addr[0]);
        }
        return $this->encodeHeader($this->secureHeader($addr[1]), 'phrase') . ' <' . $this->secureHeader($addr[0]) . '>';
    }

    public function wrapText($message, $length, $qp_mode = false)
    {
        return $qp_mode
            ? TextWrap::quotedPrintable((string) $message, (int) $length, (string) $this->CharSet, static::$LE)
            : TextWrap::plain((string) $message, (int) $length, static::$LE);
    }

    public function utf8CharBoundary($encodedText, $maxLength)
    {
        return TextWrap::utf8Boundary((string) $encodedText, (int) $maxLength);
    }

    public function setWordWrap()
    {
        if ($this->WordWrap < 1) {
            return;
        }
        if (str_starts_with((string) $this->message_type, 'alt')) {
            $this->AltBody = $this->wrapText($this->AltBody, $this->WordWrap);
        } else {
            $this->Body = $this->wrapText($this->Body, $this->WordWrap);
        }
    }

    public function createHeader()
    {
        $draft = $this->minnDraft();
        if ($this->Mailer === 'mail') {
            $this->mailHeader .= Composer::mailOnlyHeaders($draft);
        }
        return Composer::headers($draft, $this->uniqueid, $this->serverHostname(), $this->minnEncoding !== '' ? $this->minnEncoding : (string) $this->Encoding);
    }

    public function getMailMIME()
    {
        return Composer::mimeHeaders($this->minnDraft(), $this->uniqueid, $this->minnEncoding !== '' ? $this->minnEncoding : (string) $this->Encoding);
    }

    public function getSentMIMEMessage()
    {
        return rtrim($this->MIMEHeader . $this->mailHeader, "\n\r") . static::$LE . static::$LE . $this->MIMEBody;
    }

    protected function generateId()
    {
        return str_replace(['=', '+', '/'], '', base64_encode(random_bytes(32)));
    }

    public function createBody()
    {
        $this->setWordWrap();
        try {
            ['body' => $body, 'encoding' => $this->minnEncoding] = Composer::body($this->minnDraft(), $this->uniqueid);
        } catch (\InvalidArgumentException $e) {
            throw new Exception($this->lang('encoding') . $e->getMessage());
        } catch (\RuntimeException $e) {
            throw new Exception($this->lang('file_open') . $e->getMessage(), self::STOP_CONTINUE);
        }
        return $this->sign_key_file !== '' ? $this->minnSmime($body) : $body;
    }

    private function minnSmime($body)
    {
        $signed = \Minn\Mail\Smime::sign($this->MIMEHeader . $this->getMailMIME() . static::$LE . $body, $this->sign_cert_file, $this->sign_key_file, $this->sign_key_pass, $this->sign_extracerts_file);
        if ($signed === null) {
            throw new Exception($this->lang('signing') . openssl_error_string());
        }
        [$this->MIMEHeader, $body] = [$signed[0], $signed[1]];
        return $body;
    }

    public function getBoundaries()
    {
        return $this->boundary;
    }

    protected function setMessageType()
    {
        $this->message_type = $this->minnDraft()->messageType();
    }

    public function headerLine($name, $value)
    {
        return $name . ': ' . $value . static::$LE;
    }

    public function textLine($value)
    {
        return $value . static::$LE;
    }

    public function addAttachment($path, $name = '', $encoding = self::ENCODING_BASE64, $type = '', $disposition = 'attachment')
    {
        if (!static::fileIsAccessible($path)) {
            return $this->minnRefuse($this->lang('file_access') . $path, self::STOP_CONTINUE);
        }
        if (!$this->validateEncoding($encoding)) {
            return $this->minnRefuse($this->lang('encoding') . $encoding);
        }
        $filename = (string) static::mb_pathinfo($path, PATHINFO_BASENAME);
        $name = $name === '' ? $filename : $name;
        $this->attachment[] = [$path, $filename, $name, $encoding, $type === '' ? static::filenameToType($path) : $type, false, $disposition, $name];
        return true;
    }

    public function getAttachments()
    {
        return $this->attachment;
    }

    public function encodeString($str, $encoding = self::ENCODING_BASE64)
    {
        try {
            return Transfer::encode((string) $str, (string) $encoding, static::$LE);
        } catch (\InvalidArgumentException $e) {
            $this->setError($this->lang('encoding') . $encoding);
            if ($this->exceptions) {
                throw new Exception($this->lang('encoding') . $encoding);
            }
            return '';
        }
    }

    public function encodeHeader($str, $position = 'text')
    {
        return HeaderWords::encode((string) $str, (string) $position, (string) $this->CharSet, $this->Mailer === 'mail' ? HeaderWords::MAIL_LINE : HeaderWords::SMTP_LINE, static::$LE);
    }

    public static function decodeHeader($value, $charset = self::CHARSET_ISO88591)
    {
        return HeaderWords::decode((string) $value, (string) $charset);
    }

    public function hasMultiBytes($str)
    {
        return function_exists('mb_strlen') && strlen((string) $str) > mb_strlen((string) $str, (string) $this->CharSet);
    }

    public function has8bitChars($text)
    {
        return Transfer::has8bit((string) $text);
    }

    public function base64EncodeWrapMB($str, $linebreak = null)
    {
        return implode($linebreak ?? static::$LE, HeaderWords::base64Lines((string) $str, (string) $this->CharSet));
    }

    public function encodeQP($string)
    {
        return Transfer::quotedPrintable((string) $string, static::$LE);
    }

    public function encodeQ($str, $position = 'text')
    {
        return HeaderWords::q((string) $str, (string) $position);
    }

    public function addStringAttachment($string, $filename, $encoding = self::ENCODING_BASE64, $type = '', $disposition = 'attachment')
    {
        if (!$this->validateEncoding($encoding)) {
            return $this->minnRefuse($this->lang('encoding') . $encoding);
        }
        $type = $type === '' ? static::filenameToType($filename) : $type;
        $this->attachment[] = [$string, $filename, static::mb_pathinfo($filename, PATHINFO_BASENAME), $encoding, $type, true, $disposition, 0];
        return true;
    }

    public function addEmbeddedImage($path, $cid, $name = '', $encoding = self::ENCODING_BASE64, $type = '', $disposition = 'inline')
    {
        if (!static::fileIsAccessible($path)) {
            return $this->minnRefuse($this->lang('file_access') . $path, self::STOP_CONTINUE);
        }
        if (!$this->validateEncoding($encoding)) {
            return $this->minnRefuse($this->lang('encoding') . $encoding);
        }
        $filename = (string) static::mb_pathinfo($path, PATHINFO_BASENAME);
        $type = $type === '' ? static::filenameToType($filename) : $type;
        $this->attachment[] = [$path, $filename, $name === '' ? $filename : $name, $encoding, $type, false, $disposition, $cid];
        return true;
    }

    public function addStringEmbeddedImage($string, $cid, $name = '', $encoding = self::ENCODING_BASE64, $type = '', $disposition = 'inline')
    {
        if (!$this->validateEncoding($encoding)) {
            return $this->minnRefuse($this->lang('encoding') . $encoding);
        }
        if ($type === '' && $name !== '') {
            $type = static::filenameToType($name);
        }
        $this->attachment[] = [$string, $name, $name, $encoding, $type, true, $disposition, $cid];
        return true;
    }

    protected function validateEncoding($encoding)
    {
        return in_array($encoding, [self::ENCODING_7BIT, self::ENCODING_QUOTED_PRINTABLE, self::ENCODING_BASE64, self::ENCODING_8BIT, self::ENCODING_BINARY], true);
    }

    protected function cidExists($cid)
    {
        foreach ($this->attachment as $attachment) {
            if ($attachment[6] === 'inline' && $attachment[7] === $cid) {
                return true;
            }
        }
        return false;
    }

    public function inlineImageExists()
    {
        return in_array('inline', array_column($this->attachment, 6), true);
    }

    public function attachmentExists()
    {
        return in_array('attachment', array_column($this->attachment, 6), true);
    }

    public function alternativeExists()
    {
        return !empty($this->AltBody);
    }

    public function clearQueuedAddresses($kind)
    {
        $this->RecipientsQueue = array_filter($this->RecipientsQueue, static fn ($params) => $params[0] !== $kind);
    }

    public function clearAddresses()
    {
        $this->minnClearKind('to');
    }

    public function clearCCs()
    {
        $this->minnClearKind('cc');
    }

    public function clearBCCs()
    {
        $this->minnClearKind('bcc');
    }

    private function minnClearKind($kind)
    {
        foreach ($this->{$kind} as $pair) {
            unset($this->all_recipients[strtolower($pair[0])]);
        }
        $this->{$kind} = [];
        $this->clearQueuedAddresses($kind);
    }

    public function clearReplyTos()
    {
        $this->ReplyTo = [];
        $this->ReplyToQueue = [];
    }

    public function clearAllRecipients()
    {
        $this->to = [];
        $this->cc = [];
        $this->bcc = [];
        $this->all_recipients = [];
        $this->RecipientsQueue = [];
    }

    public function clearAttachments()
    {
        $this->attachment = [];
    }

    public function clearCustomHeaders()
    {
        $this->CustomHeader = [];
    }

    public function clearCustomHeader($name, $value = null)
    {
        if ($value === null && str_contains((string) $name, ':')) {
            [$name, $value] = array_map('trim', explode(':', (string) $name, 2));
        }
        $this->CustomHeader = array_filter($this->CustomHeader, static fn ($h) => !($h[0] === trim((string) $name) && ($value === null || $h[1] === trim((string) $value))));
        return true;
    }

    public function replaceCustomHeader($name, $value = null)
    {
        if ($value === null && str_contains((string) $name, ':')) {
            [$name, $value] = explode(':', (string) $name, 2);
        }
        [$name, $value] = [trim((string) $name), trim((string) $value)];
        if ($name === '' || strpbrk($name . $value, "\r\n") !== false) {
            return $this->minnRefuse($this->lang('invalid_header'));
        }
        $replaced = false;
        foreach ($this->CustomHeader as $index => $header) {
            if ($header[0] === $name) {
                $replaced ? $this->minnDropHeader($index) : $this->CustomHeader[$index] = [$name, $value];
                $replaced = true;
            }
        }
        return true;
    }

    private function minnDropHeader($index)
    {
        unset($this->CustomHeader[$index]);
    }

    protected function setError($msg)
    {
        ++$this->error_count;
        if ($this->Mailer === 'smtp' && $this->smtp !== null) {
            $last = $this->smtp->getError();
            if (!empty($last['error'])) {
                $msg .= ' ' . $this->lang('smtp_error') . $last['error'];
                foreach (['detail' => 'smtp_detail', 'smtp_code' => 'smtp_code', 'smtp_code_ex' => 'smtp_code_ex'] as $field => $label) {
                    $msg .= empty($last[$field]) ? '' : ' ' . $this->lang($label) . $last[$field];
                }
            }
        }
        $this->ErrorInfo = $msg;
    }

    public static function rfcDate()
    {
        return date(self::RFC822_DATE_FORMAT);
    }

    protected function serverHostname()
    {
        $candidates = [(string) $this->Hostname, (string) ($_SERVER['SERVER_NAME'] ?? ''), function_exists('gethostname') ? (string) gethostname() : '', (string) php_uname('n')];
        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return static::isValidHost($candidate) ? $candidate : 'localhost.localdomain';
            }
        }
        return 'localhost.localdomain';
    }

    public static function isValidHost($host)
    {
        return HostEntry::validHost($host);
    }

    public function needsSMTPUTF8()
    {
        foreach (array_merge([(string) $this->From, (string) $this->Sender], array_map('strval', array_keys($this->all_recipients))) as $address) {
            if (static::has8bitChars($address)) {
                return true;
            }
        }
        return false;
    }

    protected function lang($key)
    {
        if (count(static::$language) < 1) {
            static::setLanguage();
        }
        return static::$language[$key] ?? $key;
    }

    private function minnRefuse($message, $code = self::STOP_MESSAGE)
    {
        $this->setError($message);
        $this->edebug($message);
        if ($this->exceptions) {
            throw new Exception($message, $code);
        }
        return false;
    }

    public function isError()
    {
        return $this->error_count > 0;
    }

    public function addCustomHeader($name, $value = null)
    {
        if ($value === null && str_contains((string) $name, ':')) {
            [$name, $value] = explode(':', (string) $name, 2);
        }
        [$name, $value] = [trim((string) $name), trim((string) $value)];
        if ($name === '' || strpbrk($name . $value, "\r\n") !== false) {
            if ($this->exceptions) {
                throw new Exception($this->lang('invalid_header'));
            }
            return false;
        }
        $this->CustomHeader[] = [$name, $value];
        return true;
    }

    public function getCustomHeaders()
    {
        return $this->CustomHeader;
    }

    public function msgHTML($message, $basedir = '', $advanced = false)
    {
        $basedir = (string) $basedir;
        if ($basedir !== '' && !str_ends_with($basedir, '/')) {
            $basedir .= '/';
        }
        foreach (HtmlMessage::images((string) $message) as $index => $image) {
            $cid = $this->minnEmbed($image['url'], $basedir, $index);
            if ($cid !== null) {
                $message = (string) preg_replace('/' . preg_quote($image['attribute'], '/') . '=(["\'])' . preg_quote($image['url'], '/') . '\1/', $image['attribute'] . '="cid:' . $cid . '"', (string) $message, 1);
            }
        }
        $this->isHTML();
        $this->Body = static::normalizeBreaks((string) $message);
        $this->AltBody = static::normalizeBreaks($this->html2text($this->Body, $advanced));
        if (!$this->alternativeExists()) {
            $this->AltBody = HtmlMessage::NO_TEXT . static::$LE;
        }
        return $this->Body;
    }

    /** The content id an image was embedded under, or null when it stays a link. */
    private function minnEmbed($url, $basedir, $index)
    {
        $data = HtmlMessage::dataImage($url);
        if ($data !== null) {
            $cid = HtmlMessage::cid($data[0]);
            return $this->addStringEmbeddedImage($data[0], $cid, 'embed' . $index, self::ENCODING_BASE64, $data[1]) ? $cid : null;
        }
        $path = HtmlMessage::localPath($url);
        // Without a base directory no file is read: a path would otherwise resolve against the working directory.
        if ($basedir === '' || $path === null || !static::fileIsAccessible($basedir . $path)) {
            return null;
        }
        $cid = HtmlMessage::cid($url);
        $file = (string) static::mb_pathinfo($path, PATHINFO_BASENAME);
        $dir = (string) static::mb_pathinfo($path, PATHINFO_DIRNAME);
        return $this->addEmbeddedImage($basedir . (in_array($dir, ['', '.'], true) ? '' : $dir . '/') . $file, $cid, $file, self::ENCODING_BASE64, static::_mime_types((string) static::mb_pathinfo($file, PATHINFO_EXTENSION))) ? $cid : null;
    }

    public function html2text($html, $advanced = false)
    {
        if (is_callable($advanced)) {
            return call_user_func($advanced, $html);
        }
        return HtmlMessage::text((string) $html, (string) $this->CharSet);
    }

    public static function _mime_types($ext = '')
    {
        return MimeTypes::forExtension((string) $ext);
    }

    public static function filenameToType($filename)
    {
        return MimeTypes::forFilename((string) $filename);
    }

    public static function mb_pathinfo($path, $options = null)
    {
        $parts = \Minn\Mail\PathParts::of((string) $path);
        return match ($options) {
            PATHINFO_DIRNAME, 'dirname' => $parts['dirname'],
            PATHINFO_BASENAME, 'basename' => $parts['basename'],
            PATHINFO_EXTENSION, 'extension' => $parts['extension'],
            PATHINFO_FILENAME, 'filename' => $parts['filename'],
            default => $parts,
        };
    }

    public function set($name, $value = '')
    {
        if (property_exists($this, $name)) {
            $this->{$name} = $value;
            return true;
        }
        $this->setError($this->lang('variable_set') . $name);
        return false;
    }

    public function secureHeader($str)
    {
        return trim(str_replace(["\r", "\n"], '', (string) $str));
    }

    public static function normalizeBreaks($text, $breaktype = null)
    {
        return Transfer::normalizeBreaks((string) $text, $breaktype ?? static::$LE);
    }

    public static function stripTrailingWSP($text)
    {
        return Transfer::stripTrailingSpace((string) $text);
    }

    public static function stripTrailingBreaks($text)
    {
        return Transfer::stripTrailingBreaks((string) $text);
    }

    public static function getLE()
    {
        return static::$LE;
    }

    protected static function setLE($le)
    {
        static::$LE = $le;
    }

    public function sign($cert_filename, $key_filename, $key_pass, $extracerts_filename = '')
    {
        $this->sign_cert_file = $cert_filename;
        $this->sign_key_file = $key_filename;
        $this->sign_key_pass = $key_pass;
        $this->sign_extracerts_file = $extracerts_filename;
    }

    public function DKIM_QP($txt)
    {
        return Dkim::quotedPrintable((string) $txt);
    }

    public function DKIM_Sign($signHeader)
    {
        if (!defined('PKCS7_TEXT')) {
            if ($this->exceptions) {
                throw new Exception($this->lang('extension_missing') . 'openssl');
            }
            return '';
        }
        return $this->minnDkim()->sign((string) $signHeader) ?? '';
    }

    public function DKIM_HeaderC($signHeader)
    {
        return Dkim::headers((string) $signHeader);
    }

    public function DKIM_BodyC($body)
    {
        return Dkim::body((string) $body);
    }

    public function DKIM_Add($headers_line, $subject, $body)
    {
        $extra = array_map('strtolower', array_values(array_intersect((array) $this->DKIM_extraHeaders, array_column($this->CustomHeader, 0))));
        return $this->minnDkim()->signatureHeader((string) $headers_line, (string) $subject, (string) $body, time(), $extra, static::$LE) ?? '';
    }

    private function minnDkim()
    {
        $key = $this->DKIM_private_string !== '' ? (string) $this->DKIM_private_string : (string) @file_get_contents((string) $this->DKIM_private);
        $dkim = new Dkim((string) $this->DKIM_domain, (string) $this->DKIM_selector, $key, (string) $this->DKIM_passphrase, (string) $this->DKIM_identity);
        return $this->DKIM_copyHeaderFields ? $dkim->copyingHeaders() : $dkim;
    }

    public static function hasLineLongerThanMax($str)
    {
        return Transfer::hasLongLine((string) $str);
    }

    public static function quotedString($str)
    {
        return AddressRules::quoted((string) $str);
    }

    public function getToAddresses()
    {
        return $this->to;
    }

    public function getCcAddresses()
    {
        return $this->cc;
    }

    public function getBccAddresses()
    {
        return $this->bcc;
    }

    public function getReplyToAddresses()
    {
        return $this->ReplyTo;
    }

    public function getAllRecipientAddresses()
    {
        return $this->all_recipients;
    }

    protected function doCallback($isSent, $to, $cc, $bcc, $subject, $body, $from, $extra)
    {
        if (!empty($this->action_function) && is_callable($this->action_function)) {
            call_user_func($this->action_function, $isSent, $to, $cc, $bcc, $subject, $body, $from, $extra);
        }
    }

    public function getOAuth()
    {
        return $this->oauth;
    }

    public function setOAuth($oauth)
    {
        $this->oauth = $oauth;
    }

    protected function getSmtpErrorMessage($base_key)
    {
        $message = $this->lang($base_key);
        $error = $this->smtp !== null ? $this->smtp->getError() : [];
        foreach (['error', 'detail'] as $field) {
            $message .= empty($error[$field]) ? '' : ' ' . $error[$field];
        }
        return $message;
    }

    protected function mailPassthru($to, $subject, $body, $header, $params)
    {
        $subject = $this->encodeHeader($this->secureHeader($subject));
        foreach (['Sending with mail()', 'Sendmail path: ' . ini_get('sendmail_path'), "Envelope sender: {$this->Sender}", "To: {$to}", "Subject: {$subject}", "Headers: {$header}", 'Additional params: ' . ($params ?? 'none')] as $line) {
            $this->edebug($line);
        }
        $result = $params !== null ? @mail($to, $subject, $body, $header, $params) : @mail($to, $subject, $body, $header);
        $this->edebug('Result: ' . ($result ? 'true' : 'false'));
        return $result;
    }

    protected function edebug($str)
    {
        if ($this->SMTPDebug > 0) {
            echo DebugOutput::mailer($this->Debugoutput, (string) $str, (int) $this->SMTPDebug) ?? '';
        }
    }

    /** The engine's own development transport: the message as one JSON line in wp-content/minn-mail.log. */
    protected function minnlogSend($header, $body)
    {
        $to = array_map(static fn ($pair) => $pair[0], $this->to);
        if (!MailLog::write(MailLog::forSite(), $this->From, $this->FromName, $to, (string) $this->Subject, (string) $this->Body)) {
            throw new Exception($this->lang('instantiate'), self::STOP_CRITICAL);
        }
        $this->doCallback(true, $this->to, $this->cc, $this->bcc, $this->Subject, $body, $this->From, []);
        return true;
    }

    private function minnDequeue()
    {
        foreach ([...array_values($this->RecipientsQueue), ...array_values($this->ReplyToQueue)] as [$kind, $address, $name]) {
            $this->addAnAddress($kind, $this->punyencodeAddress($address), $name);
        }
        $this->RecipientsQueue = [];
        $this->ReplyToQueue = [];
    }

    private function minnCheckSenders()
    {
        foreach (['From', 'Sender', 'ConfirmReadingTo'] as $field) {
            $this->{$field} = trim((string) $this->{$field});
            if ($this->{$field} === '') {
                continue;
            }
            $this->{$field} = $this->punyencodeAddress($this->{$field});
            if (!static::validateAddress($this->{$field})) {
                $error = $this->lang('invalid_address') . " ({$field}): " . $this->{$field};
                $this->edebug($error);
                throw new Exception($error);
            }
        }
    }

    private function minnCompose()
    {
        $this->setBoundaries();
        $this->lastMessageID = Composer::messageId((string) $this->MessageID, $this->uniqueid, $this->serverHostname());
        $this->MIMEHeader = '';
        $this->MIMEBody = $this->createBody();
        $header = $this->createHeader();
        if ($this->MIMEHeader !== '') {
            $header = substr($header, 0, -strlen($this->getMailMIME()));
        }
        $this->MIMEHeader = $header . $this->MIMEHeader;
        if ($this->DKIM_domain !== '' && $this->DKIM_selector !== '' && ($this->DKIM_private_string !== '' || (is_string($this->DKIM_private) && $this->DKIM_private !== '' && static::isPermittedPath($this->DKIM_private) && @is_file($this->DKIM_private)))) {
            $signature = $this->DKIM_Add($this->MIMEHeader . $this->mailHeader, $this->encodeHeader($this->secureHeader($this->Subject)), $this->MIMEBody);
            $this->MIMEHeader = static::stripTrailingWSP($this->MIMEHeader) . static::$LE . static::normalizeBreaks($signature) . static::$LE;
        }
    }

    private function minnDraft()
    {
        return new Draft(
            (string) $this->Mailer, static::$LE, (string) $this->CharSet, (string) $this->ContentType, (string) $this->Encoding,
            (string) $this->Subject, (string) $this->Body, (string) $this->AltBody, (string) $this->Ical,
            (string) $this->From, (string) $this->FromName, $this->to, $this->cc, $this->bcc, array_values($this->ReplyTo),
            $this->lastMessageID !== '' ? $this->lastMessageID : (string) $this->MessageID, (string) $this->MessageDate,
            $this->Priority === null ? null : (int) $this->Priority, (string) $this->XMailer, (string) $this->ConfirmReadingTo,
            array_values($this->CustomHeader), $this->attachment, $this->SingleTo ? 'omit' : 'list',
        );
    }

    private function minnSendmailCommand()
    {
        $safe = $this->Sender !== '' && static::validateAddress($this->Sender) && static::isShellSafe($this->Sender);
        $path = escapeshellcmd((string) $this->Sendmail);
        if (!$this->UseSendmailOptions) {
            return $path;
        }
        $sender = $safe ? ' -f' . escapeshellarg($this->Sender) : '';
        return $this->Mailer === 'qmail' ? $path . $sender : $path . ' -oi' . $sender . ' -t';
    }
}
