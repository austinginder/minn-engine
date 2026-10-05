<?php
/**
 * The exceptions the Requests library throws, by the names plugins catch:
 * a typed exception carrying data, argument errors, transport errors, and
 * one class per HTTP status (the message is "{code} {reason}").
 */

namespace WpOrg\Requests {
    class Exception extends \Exception
    {
        protected $type;
        protected $data;

        public function __construct($message, $type, $data = null, $code = 0)
        {
            parent::__construct((string) $message, (int) $code);
            $this->type = $type;
            $this->data = $data;
        }

        public function getType()
        {
            return $this->type;
        }

        public function getData()
        {
            return $this->data;
        }
    }
}

namespace WpOrg\Requests\Exception {
    use WpOrg\Requests\Exception;

    /** @internal "Class::method()" of whoever called the function that is complaining */
    function minn_caller(): string
    {
        $frame = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2] ?? [];
        return ($frame['class'] ?? '') . '::' . ($frame['function'] ?? '') . '()';
    }

    final class InvalidArgument extends \InvalidArgumentException
    {
        public static function create($position, $name, $expected, $received)
        {
            return new self(sprintf('%s: Argument #%d (%s) must be of type %s, %s given', minn_caller(), $position, $name, $expected, $received));
        }
    }

    final class ArgumentCount extends Exception
    {
        public static function create($expected, $received, $type)
        {
            return new self(sprintf('%s expects %s, %d given', minn_caller(), $expected, $received), $type);
        }
    }

    class Transport extends Exception
    {
    }

    /** An HTTP status as an exception: "{code} {reason}", type httpresponse. */
    class Http extends Exception
    {
        protected $code = 0;
        protected $reason = 'Unknown';

        public function __construct($reason = null, $data = null)
        {
            if ($reason !== null) {
                $this->reason = $reason;
            }
            parent::__construct(sprintf('%d %s', $this->code, $this->reason), 'httpresponse', $data, $this->code);
        }

        public function getReason()
        {
            return $this->reason;
        }

        /** The exception class for a status code (with a leading backslash when there is one), else StatusUnknown. */
        public static function get_class($code)
        {
            $class = sprintf('\\WpOrg\\Requests\\Exception\\Http\\Status%d', is_numeric($code) ? (int) $code : 0);
            return $code && class_exists($class) ? $class : Http\StatusUnknown::class;
        }
    }
}

namespace WpOrg\Requests\Exception\Transport {
    use WpOrg\Requests\Exception\Transport;

    final class Curl extends Transport
    {
        public const EASY = 'cURLEasy';
        public const MULTI = 'cURLMulti';
        public const SHARE = 'cURLShare';

        protected $code = -1;
        protected $type = 'Unknown';
        protected $reason = 'Unknown';

        public function __construct($message, $type, $data = null, $code = 0)
        {
            if ($type !== null) {
                $this->type = $type;
            }
            if ($code !== null) {
                $this->code = (int) $code;
            }
            if ($message !== null) {
                $this->reason = $message;
            }
            parent::__construct(sprintf('%d %s', $this->code, $this->reason), $this->type, $data, $this->code);
        }

        public function getReason()
        {
            return $this->reason;
        }
    }
}

namespace WpOrg\Requests\Exception\Http {
    use WpOrg\Requests\Exception\Http;
    use WpOrg\Requests\Response;

    /** A status the library has no class for; given the response, it takes the response's code. */
    final class StatusUnknown extends Http
    {
        protected $code = 0;
        protected $reason = 'Unknown';

        public function __construct($reason = null, $data = null)
        {
            if ($data instanceof Response) {
                $this->code = (int) $data->status_code;
            }
            parent::__construct($reason, $data);
        }
    }

    final class Status304 extends Http
    {
        protected $code = 304;
        protected $reason = 'Not Modified';
    }

    final class Status305 extends Http
    {
        protected $code = 305;
        protected $reason = 'Use Proxy';
    }

    final class Status306 extends Http
    {
        protected $code = 306;
        protected $reason = 'Switch Proxy';
    }

    final class Status400 extends Http
    {
        protected $code = 400;
        protected $reason = 'Bad Request';
    }

    final class Status401 extends Http
    {
        protected $code = 401;
        protected $reason = 'Unauthorized';
    }

    final class Status402 extends Http
    {
        protected $code = 402;
        protected $reason = 'Payment Required';
    }

    final class Status403 extends Http
    {
        protected $code = 403;
        protected $reason = 'Forbidden';
    }

    final class Status404 extends Http
    {
        protected $code = 404;
        protected $reason = 'Not Found';
    }

    final class Status405 extends Http
    {
        protected $code = 405;
        protected $reason = 'Method Not Allowed';
    }

    final class Status406 extends Http
    {
        protected $code = 406;
        protected $reason = 'Not Acceptable';
    }

    final class Status407 extends Http
    {
        protected $code = 407;
        protected $reason = 'Proxy Authentication Required';
    }

    final class Status408 extends Http
    {
        protected $code = 408;
        protected $reason = 'Request Timeout';
    }

    final class Status409 extends Http
    {
        protected $code = 409;
        protected $reason = 'Conflict';
    }

    final class Status410 extends Http
    {
        protected $code = 410;
        protected $reason = 'Gone';
    }

    final class Status411 extends Http
    {
        protected $code = 411;
        protected $reason = 'Length Required';
    }

    final class Status412 extends Http
    {
        protected $code = 412;
        protected $reason = 'Precondition Failed';
    }

    final class Status413 extends Http
    {
        protected $code = 413;
        protected $reason = 'Request Entity Too Large';
    }

    final class Status414 extends Http
    {
        protected $code = 414;
        protected $reason = 'Request-URI Too Large';
    }

    final class Status415 extends Http
    {
        protected $code = 415;
        protected $reason = 'Unsupported Media Type';
    }

    final class Status416 extends Http
    {
        protected $code = 416;
        protected $reason = 'Requested Range Not Satisfiable';
    }

    final class Status417 extends Http
    {
        protected $code = 417;
        protected $reason = 'Expectation Failed';
    }

    final class Status418 extends Http
    {
        protected $code = 418;
        protected $reason = 'I\'m A Teapot';
    }

    final class Status428 extends Http
    {
        protected $code = 428;
        protected $reason = 'Precondition Required';
    }

    final class Status429 extends Http
    {
        protected $code = 429;
        protected $reason = 'Too Many Requests';
    }

    final class Status431 extends Http
    {
        protected $code = 431;
        protected $reason = 'Request Header Fields Too Large';
    }

    final class Status500 extends Http
    {
        protected $code = 500;
        protected $reason = 'Internal Server Error';
    }

    final class Status501 extends Http
    {
        protected $code = 501;
        protected $reason = 'Not Implemented';
    }

    final class Status502 extends Http
    {
        protected $code = 502;
        protected $reason = 'Bad Gateway';
    }

    final class Status503 extends Http
    {
        protected $code = 503;
        protected $reason = 'Service Unavailable';
    }

    final class Status504 extends Http
    {
        protected $code = 504;
        protected $reason = 'Gateway Timeout';
    }

    final class Status505 extends Http
    {
        protected $code = 505;
        protected $reason = 'HTTP Version Not Supported';
    }

    final class Status511 extends Http
    {
        protected $code = 511;
        protected $reason = 'Network Authentication Required';
    }
}
