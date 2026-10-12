<?php
/**
 * One request handed over by tests/tools/coverage-server.php, served by the
 * test site's index.php inside this process (run under phpdbg through
 * tests/tools/coverage-run.php, which notes the engine lines it runs), with
 * the answer written back for the server to send.
 *
 * The command line SAPI keeps no headers, so the engine's own calls are
 * caught where it makes them: header(), setcookie(), file_get_contents()
 * of php://input and the upload checks are unqualified calls inside the
 * engine's namespaces, which PHP resolves to a function of that namespace
 * first. The facade's calls (public/minn/wp-api, global code) are pointed
 * at the same shims as its files load (FacadeLoader), so its Link,
 * Location and CORS headers come through; headers plugin code sends with
 * the global header() are lost. The status is PHP's own
 * (http_response_code() works here). Only the facade's source is touched,
 * and the lines measured are the engine's own (src/Minn), read as written.
 *
 * php tests/tools/coverage-serve.php <request.json> <response.json>
 */

namespace Minn\Coverage {
    /** What the request said and what the engine answered, shared with the shims below. */
    final class Exchange
    {
        public static string $body = '';
        /** @var list<string> */
        public static array $headers = [];
        /** @var list<string> */
        public static array $cookies = [];
        /** @var array<string, true> */
        public static array $uploads = [];

        public static function header(string $line, bool $replace = true, int $code = 0): void
        {
            if ($code > 0) {
                \http_response_code($code);
            }
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                \http_response_code((int) $m[1]);
                return;
            }
            $name = strtolower(trim(strstr($line, ':', true) ?: $line));
            if ($replace) {
                self::$headers = array_values(array_filter(self::$headers, static fn (string $h): bool => strtolower(trim(strstr($h, ':', true) ?: $h)) !== $name));
            }
            if (str_contains($line, ':')) {
                self::$headers[] = $line;
            }
            if ($name === 'location' && $code === 0 && !in_array(\http_response_code(), [201, 301, 302, 303, 307, 308], true)) {
                \http_response_code(302);
            }
        }

        /** @param array<string, mixed>|int $options */
        public static function cookie(string $name, string $value, $options = [], string $path = '', string $domain = '', bool $secure = false, bool $httponly = false, bool $raw = false): bool
        {
            if (!is_array($options)) {
                $options = ['expires' => (int) $options, 'path' => $path, 'domain' => $domain, 'secure' => $secure, 'httponly' => $httponly];
            }
            $line = $name . '=' . ($value === '' ? 'deleted' : ($raw ? $value : rawurlencode($value)));
            $expires = (int) ($options['expires'] ?? 0);
            if ($value === '') {
                $expires = 1;
            }
            if ($expires > 0) {
                $line .= '; expires=' . gmdate('D, d M Y H:i:s', $expires) . ' GMT; Max-Age=' . max(0, $expires - time());
            }
            foreach (['path' => 'path', 'domain' => 'domain'] as $key => $label) {
                if (($options[$key] ?? '') !== '') {
                    $line .= "; {$label}=" . $options[$key];
                }
            }
            if (!empty($options['secure'])) {
                $line .= '; secure';
            }
            if (!empty($options['httponly'])) {
                $line .= '; HttpOnly';
            }
            if (($options['samesite'] ?? '') !== '') {
                $line .= '; SameSite=' . $options['samesite'];
            }
            self::$cookies[] = $line;
            return true;
        }

        public static function read(string $filename, mixed ...$rest): string|false
        {
            return $filename === 'php://input' ? self::$body : \file_get_contents($filename, ...$rest);
        }

        public static function uploaded(string $path): bool
        {
            return isset(self::$uploads[$path]) && is_file($path);
        }

        public static function move(string $from, string $to): bool
        {
            return self::uploaded($from) && rename($from, $to);
        }
    }
}

namespace Minn\Coverage {
    /**
     * The file stream wrapper for this process: a facade file is read with its
     * global header(), setcookie(), headers_sent() and headers_list() calls
     * pointed at this namespace's; anything else is read as it is.
     */
    final class FacadeLoader
    {
        /** @var resource|null */
        public $context;
        /** @var resource|null */
        private $handle = null;
        private ?string $source = null;
        private int $at = 0;
        private static string $facade = '';

        /** Takes over the file wrapper; a facade file is one whose path holds $facade. */
        public static function install(string $facade): void
        {
            self::$facade = $facade;
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', self::class);
        }

        /** Runs a call with PHP's own file wrapper back in place. */
        private static function plain(callable $call): mixed
        {
            stream_wrapper_restore('file');
            try {
                return $call();
            } finally {
                stream_wrapper_unregister('file');
                stream_wrapper_register('file', self::class);
            }
        }

        public function stream_open(string $path, string $mode, int $options, ?string &$opened): bool
        {
            $including = ($options & 0x80) !== 0; // STREAM_OPEN_FOR_INCLUDE, which PHP does not name for userland
            if ($including && str_contains($path, self::$facade) && str_ends_with($path, '.php')) {
                $source = self::plain(static fn () => @file_get_contents($path));
                if (!is_string($source)) {
                    return false;
                }
                $this->source = (string) preg_replace_callback('/(?<![\w>:\\\\$])(?<!function )(header|setcookie|headers_sent|headers_list)\s*\(/', static fn (array $m): string => '\\Minn\\Coverage\\' . $m[1] . '(', $source);
                return true;
            }
            $handle = self::plain(fn () => $this->context ? @fopen($path, $mode, ($options & STREAM_USE_PATH) !== 0, $this->context) : @fopen($path, $mode, ($options & STREAM_USE_PATH) !== 0));
            if (!is_resource($handle)) {
                return false;
            }
            $this->handle = $handle;
            return true;
        }

        public function stream_read(int $count): string|false
        {
            if ($this->source !== null) {
                $chunk = substr($this->source, $this->at, $count);
                $this->at += strlen($chunk);
                return $chunk;
            }
            return fread($this->handle, $count);
        }

        public function stream_write(string $data): int|false
        {
            return $this->handle ? fwrite($this->handle, $data) : false;
        }

        public function stream_eof(): bool
        {
            return $this->source !== null ? $this->at >= strlen($this->source) : feof($this->handle);
        }

        public function stream_tell(): int|false
        {
            return $this->source !== null ? $this->at : ftell($this->handle);
        }

        public function stream_seek(int $offset, int $whence = SEEK_SET): bool
        {
            if ($this->source === null) {
                return fseek($this->handle, $offset, $whence) === 0;
            }
            $this->at = match ($whence) {
                SEEK_CUR => $this->at + $offset,
                SEEK_END => strlen($this->source) + $offset,
                default => $offset,
            };
            return true;
        }

        public function stream_close(): void
        {
            if ($this->handle) {
                fclose($this->handle);
            }
        }

        public function stream_flush(): bool
        {
            return $this->handle ? fflush($this->handle) : true;
        }

        public function stream_lock(int $operation): bool
        {
            return $this->handle ? flock($this->handle, $operation) : true;
        }

        public function stream_truncate(int $size): bool
        {
            return $this->handle ? ftruncate($this->handle, $size) : false;
        }

        public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
        {
            return false;
        }

        /** @return array<int|string, int>|false */
        public function stream_stat(): array|false
        {
            if ($this->source !== null) {
                return ['size' => strlen($this->source), 7 => strlen($this->source), 'mode' => 0100644, 2 => 0100644];
            }
            return fstat($this->handle);
        }

        /** @return array<int|string, int>|false */
        public function url_stat(string $path, int $flags): array|false
        {
            return self::plain(static fn () => ($flags & STREAM_URL_STAT_LINK) ? @lstat($path) : @stat($path));
        }

        public function unlink(string $path): bool
        {
            return self::plain(static fn () => @unlink($path));
        }

        public function rename(string $from, string $to): bool
        {
            return self::plain(static fn () => @rename($from, $to));
        }

        public function mkdir(string $path, int $mode, int $options): bool
        {
            return self::plain(static fn () => @mkdir($path, $mode, ($options & STREAM_MKDIR_RECURSIVE) !== 0));
        }

        public function rmdir(string $path, int $options): bool
        {
            return self::plain(static fn () => @rmdir($path));
        }

        /** @var resource|null */
        private $dir = null;

        public function dir_opendir(string $path, int $options): bool
        {
            $this->dir = self::plain(static fn () => @opendir($path)) ?: null;
            return $this->dir !== null;
        }

        public function dir_readdir(): string|false
        {
            return readdir($this->dir);
        }

        public function dir_rewinddir(): bool
        {
            rewinddir($this->dir);
            return true;
        }

        public function dir_closedir(): bool
        {
            closedir($this->dir);
            return true;
        }

        public function stream_metadata(string $path, int $option, mixed $value): bool
        {
            return self::plain(static fn () => match ($option) {
                STREAM_META_TOUCH => @touch($path, ...array_filter((array) $value, static fn ($v) => $v !== null)),
                STREAM_META_OWNER_NAME, STREAM_META_OWNER => @chown($path, $value),
                STREAM_META_GROUP_NAME, STREAM_META_GROUP => @chgrp($path, $value),
                STREAM_META_ACCESS => @chmod($path, $value),
                default => false,
            });
        }

        /** @return resource|false */
        public function stream_cast(int $as)
        {
            return $this->handle ?? false;
        }
    }

    function header(string $line, bool $replace = true, int $code = 0): void
    {
        Exchange::header($line, $replace, $code);
    }

    function setcookie(string $name, string $value = '', $options = [], string $path = '', string $domain = '', bool $secure = false, bool $httponly = false): bool
    {
        return Exchange::cookie($name, $value, $options, $path, $domain, $secure, $httponly);
    }

    function headers_sent(mixed &$file = null, mixed &$line = null): bool
    {
        return false;
    }

    /** @return list<string> */
    function headers_list(): array
    {
        return [...Exchange::$headers, ...array_map(static fn (string $c): string => 'Set-Cookie: ' . $c, Exchange::$cookies)];
    }
}

namespace Minn\Http {
    function header(string $line, bool $replace = true, int $code = 0): void
    {
        \Minn\Coverage\Exchange::header($line, $replace, $code);
    }

    function setcookie(string $name, string $value = '', $options = [], string $path = '', string $domain = '', bool $secure = false, bool $httponly = false): bool
    {
        return \Minn\Coverage\Exchange::cookie($name, $value, $options, $path, $domain, $secure, $httponly);
    }

    function headers_sent(): bool
    {
        return false;
    }

    function file_get_contents(string $filename, mixed ...$rest): string|false
    {
        return \Minn\Coverage\Exchange::read($filename, ...$rest);
    }
}

namespace Minn\Runtime {
    function is_uploaded_file(string $path): bool
    {
        return \Minn\Coverage\Exchange::uploaded($path);
    }

    function move_uploaded_file(string $from, string $to): bool
    {
        return \Minn\Coverage\Exchange::move($from, $to);
    }
}

namespace Minn\Admin {
    function is_uploaded_file(string $path): bool
    {
        return \Minn\Coverage\Exchange::uploaded($path);
    }
}

namespace {
    use Minn\Coverage\Exchange;

    require __DIR__ . '/../lib.php';

    [$requestFile, $responseFile] = [(string) ($argv[1] ?? ''), (string) ($argv[2] ?? '')];
    $request = json_decode((string) file_get_contents($requestFile), true);
    if (!is_array($request) || $responseFile === '') {
        fwrite(STDERR, "no request to serve\n");
        exit(1);
    }
    $site = minn_test_site_root() . '/public';
    $uri = (string) $request['uri'];
    $headers = array_change_key_case((array) $request['headers']);
    $host = (string) ($headers['host'] ?? parse_url(minn_test_url(), PHP_URL_HOST));
    $_SERVER = array_merge($_SERVER, [
        'REQUEST_URI' => $uri,
        'REQUEST_METHOD' => strtoupper((string) $request['method']),
        'QUERY_STRING' => (string) parse_url($uri, PHP_URL_QUERY),
        'HTTP_HOST' => $host,
        'SERVER_NAME' => (string) preg_replace('/:\d+$/', '', $host),
        'HTTPS' => 'on',
        'SERVER_PORT' => '443',
        'SERVER_PROTOCOL' => 'HTTP/1.1',
        'SERVER_SOFTWARE' => 'minn-line-coverage',
        'REMOTE_ADDR' => '127.0.0.1',
        'DOCUMENT_ROOT' => $site,
        'SCRIPT_NAME' => '/index.php',
        'SCRIPT_FILENAME' => $site . '/index.php',
        'PHP_SELF' => '/index.php',
        'REQUEST_TIME' => time(),
        'REQUEST_TIME_FLOAT' => microtime(true),
    ]);
    unset($_SERVER['argv'], $_SERVER['argc']);
    foreach ($headers as $name => $value) {
        if (in_array($name, ['content-type', 'content-length'], true)) {
            $_SERVER[strtoupper(str_replace('-', '_', $name))] = (string) $value;
        } elseif (!in_array($name, ['x-forwarded-for', 'x-forwarded-proto', 'x-forwarded-host'], true)) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = (string) $value;
        }
    }
    parse_str($_SERVER['QUERY_STRING'], $_GET);
    $_COOKIE = [];
    foreach (explode(';', (string) ($headers['cookie'] ?? '')) as $pair) {
        if (!str_contains($pair, '=')) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $pair, 2));
        $name = str_replace(['.', ' '], '_', $name);
        if ($name !== '' && !array_key_exists($name, $_COOKIE)) {
            $_COOKIE[$name] = urldecode($value);
        }
    }
    Exchange::$body = base64_decode((string) $request['body']);
    $type = strtolower((string) ($headers['content-type'] ?? ''));
    $_POST = [];
    $_FILES = [];
    if (str_starts_with($type, 'application/x-www-form-urlencoded')) {
        parse_str(Exchange::$body, $_POST);
    } elseif (str_starts_with($type, 'multipart/form-data') && preg_match('/boundary="?([^";]+)"?/i', $type, $m) === 1) {
        $fields = [];
        foreach (array_slice(explode('--' . $m[1], Exchange::$body), 1, -1) as $part) {
            [$head, $content] = explode("\r\n\r\n", ltrim($part, "\r\n"), 2) + [1 => ''];
            $content = substr($content, 0, -2);
            if (preg_match('/name="([^"]*)"/i', $head, $n) !== 1) {
                continue;
            }
            if (preg_match('/filename="([^"]*)"/i', $head, $f) === 1) {
                $tmp = tempnam(sys_get_temp_dir(), 'minn-cov-upload-');
                file_put_contents($tmp, $content);
                Exchange::$uploads[$tmp] = true;
                $mime = preg_match('/content-type:\s*([^\r\n]+)/i', $head, $t) === 1 ? trim($t[1]) : 'application/octet-stream';
                $file = ['name' => $f[1], 'full_path' => $f[1], 'type' => $mime, 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
                $key = $n[1];
                if (str_ends_with($key, '[]')) {
                    $key = substr($key, 0, -2);
                    foreach ($file as $field => $value) {
                        $_FILES[$key][$field][] = $value;
                    }
                } else {
                    $_FILES[$key] = $file;
                }
            } else {
                $fields[] = rawurlencode($n[1]) . '=' . rawurlencode($content);
            }
        }
        parse_str(implode('&', $fields), $_POST);
    }
    $_REQUEST = array_merge($_GET, $_POST);

    // The answer is written when the outermost buffer closes, after every buffer the engine
    // or a plugin opened has been flushed through its handler into it.
    $answer = '';
    ob_start(static function (string $chunk, int $phase) use (&$answer, $responseFile): string {
        $answer .= $chunk;
        if ($phase & PHP_OUTPUT_HANDLER_FINAL) {
            foreach (Exchange::$uploads as $tmp => $_) {
                @unlink($tmp);
            }
            file_put_contents($responseFile, json_encode([
                'status' => http_response_code() ?: 200,
                'headers' => Exchange::$headers,
                'cookies' => Exchange::$cookies,
                'body' => base64_encode($answer),
            ]));
        }
        return '';
    });
    chdir($site);
    Minn\Coverage\FacadeLoader::install('/minn/wp-api/');
    require $site . '/index.php';
}
