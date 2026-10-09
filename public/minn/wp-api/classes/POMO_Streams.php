<?php
/**
 * The byte readers the gettext classes read .mo files through (probe
 * pomo-streams): integers in either byte order, integer lists (keyed from 1,
 * as unpack gives them), reads that stop at the end, over a string, a file,
 * or a file read into a string up front. Each keeps its old PHP 4
 * constructor, deprecated since 5.4.0.
 */

#[AllowDynamicProperties]
class POMO_Reader
{
    public $endian = 'little';
    public $_pos;
    public $is_overloaded;

    public function __construct()
    {
        $this->is_overloaded = false;
        $this->_pos = 0;
    }

    /** The PHP 4 constructor. */
    public function POMO_Reader()
    {
        _deprecated_constructor(self::class, '5.4.0', static::class);
        self::__construct();
    }

    /** Reads integers big-endian or little-endian from here on. */
    public function setEndian($endian)
    {
        $this->endian = $endian;
    }

    /** The next four bytes as an integer; false when fewer are left. */
    public function readint32()
    {
        $bytes = $this->read(4);
        if ($this->strlen($bytes) !== 4) {
            return false;
        }
        $int = unpack($this->endian === 'big' ? 'N' : 'V', $bytes);
        return reset($int);
    }

    /** The next $count integers, keyed from 1; false when fewer are left. */
    public function readint32array($count)
    {
        $bytes = $this->read(4 * $count);
        if ($this->strlen($bytes) !== 4 * $count) {
            return false;
        }
        return unpack(($this->endian === 'big' ? 'N' : 'V') . $count, $bytes);
    }

    public function substr($input_string, $start, $length)
    {
        return substr((string) $input_string, (int) $start, (int) $length);
    }

    /** The length in bytes. */
    public function strlen($input_string)
    {
        return strlen((string) $input_string);
    }

    public function str_split($input_string, $chunk_size)
    {
        return str_split((string) $input_string, (int) $chunk_size);
    }

    /** Where the reader stands (a file reader does not keep count, so 0). */
    public function pos()
    {
        return $this->_pos;
    }

    public function is_resource()
    {
        return true;
    }

    public function close()
    {
        return true;
    }
}

class POMO_FileReader extends POMO_Reader
{
    public $_f;

    public function __construct($filename)
    {
        parent::__construct();
        $this->_f = fopen($filename, 'rb');
    }

    /** The PHP 4 constructor. */
    public function POMO_FileReader($filename)
    {
        _deprecated_constructor(self::class, '5.4.0', static::class);
        self::__construct($filename);
    }

    /** Up to $bytes bytes from the file ("" at its end). */
    public function read($bytes)
    {
        return (string) fread($this->_f, (int) $bytes);
    }

    /** Whether the file could be moved to $pos. */
    public function seekto($pos)
    {
        if (fseek($this->_f, (int) $pos, SEEK_SET) === -1) {
            return false;
        }
        $this->_pos = (int) $pos;
        return true;
    }

    /** Whether the file is open. */
    public function is_resource()
    {
        return is_resource($this->_f);
    }

    public function feof()
    {
        return feof($this->_f);
    }

    public function close()
    {
        return fclose($this->_f);
    }

    /** The rest of the file from where the reader stands. */
    public function read_all()
    {
        return (string) stream_get_contents($this->_f);
    }
}

class POMO_StringReader extends POMO_Reader
{
    public $_str = '';

    public function __construct($str = '')
    {
        parent::__construct();
        $this->_str = $str;
        $this->_pos = 0;
    }

    /** The PHP 4 constructor. */
    public function POMO_StringReader($str = '')
    {
        _deprecated_constructor(self::class, '5.4.0', static::class);
        self::__construct($str);
    }

    /** Up to $bytes bytes from here, the position moving on but never past the end. */
    public function read($bytes)
    {
        $data = $this->substr($this->_str, $this->_pos, $bytes);
        $this->_pos = min($this->_pos + (int) $bytes, $this->strlen($this->_str));
        return $data;
    }

    /** Moves to $pos (the end at most); the new position. */
    public function seekto($pos)
    {
        $this->_pos = min((int) $pos, $this->strlen($this->_str));
        return $this->_pos;
    }

    public function length()
    {
        return $this->strlen($this->_str);
    }

    /** The rest of the string from here. */
    public function read_all()
    {
        return $this->substr($this->_str, $this->_pos, $this->strlen($this->_str));
    }
}

class POMO_CachedFileReader extends POMO_StringReader
{
    public function __construct($filename)
    {
        parent::__construct();
        $this->_str = file_get_contents($filename);
        $this->_pos = 0;
    }

    /** The PHP 4 constructor. */
    public function POMO_CachedFileReader($filename)
    {
        _deprecated_constructor(self::class, '5.4.0', static::class);
        self::__construct($filename);
    }
}

class POMO_CachedIntFileReader extends POMO_CachedFileReader
{
    /** The PHP 4 constructor. */
    public function POMO_CachedIntFileReader($filename)
    {
        _deprecated_constructor(self::class, '5.4.0', static::class);
        self::__construct($filename);
    }
}
