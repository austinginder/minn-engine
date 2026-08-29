<?php

/** The filesystem abstraction, always the direct method: PHP's own file functions. */
class WP_Filesystem_Base
{
    public $verbose = false;
    public $cache = [];
    public $method = '';
    public $errors = null;
    public $options = [];

    public function abspath()
    {
        return $this->find_folder(ABSPATH) ?: ABSPATH;
    }

    public function wp_content_dir()
    {
        return $this->find_folder(WP_CONTENT_DIR);
    }

    public function wp_plugins_dir()
    {
        return $this->find_folder(WP_PLUGIN_DIR);
    }

    public function wp_themes_dir($theme = false)
    {
        return $this->find_folder(get_theme_root($theme ?: null));
    }

    public function wp_lang_dir()
    {
        return $this->find_folder(WP_LANG_DIR);
    }

    public function find_base_dir($base = '.', $verbose = false)
    {
        return $this->abspath();
    }

    public function get_base_dir($base = '.', $verbose = false)
    {
        return $this->abspath();
    }

    public function find_folder($folder)
    {
        $folder = trailingslashit(str_replace('\\', '/', (string) $folder));
        return $this->is_dir($folder) ? $folder : false;
    }

    public function search_for_folder($folder, $base = '.', $loop = false)
    {
        return $this->find_folder($folder);
    }

    public function gethchmod($file)
    {
        $perms = intval($this->getchmod($file), 8);
        $info = 'u';
        if (($perms & 0xC000) === 0xC000) {
            $info = 's';
        } elseif (($perms & 0xA000) === 0xA000) {
            $info = 'l';
        } elseif (($perms & 0x8000) === 0x8000) {
            $info = '-';
        } elseif (($perms & 0x6000) === 0x6000) {
            $info = 'b';
        } elseif (($perms & 0x4000) === 0x4000) {
            $info = 'd';
        } elseif (($perms & 0x2000) === 0x2000) {
            $info = 'c';
        } elseif (($perms & 0x1000) === 0x1000) {
            $info = 'p';
        }
        $info .= ($perms & 0x0100) ? 'r' : '-';
        $info .= ($perms & 0x0080) ? 'w' : '-';
        $info .= ($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-');
        $info .= ($perms & 0x0020) ? 'r' : '-';
        $info .= ($perms & 0x0010) ? 'w' : '-';
        $info .= ($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-');
        $info .= ($perms & 0x0004) ? 'r' : '-';
        $info .= ($perms & 0x0002) ? 'w' : '-';
        $info .= ($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-');
        return $info;
    }

    public function getchmod($file)
    {
        return '777';
    }

    public function getnumchmodfromh($mode)
    {
        $realmode = '';
        $legal = ['', 'w', 'r', 'x', '-'];
        $attarray = preg_split('//', $mode);
        for ($i = 0, $c = count($attarray); $i < $c; $i++) {
            $key = array_search($attarray[$i], $legal, true);
            if ($key) {
                $realmode .= $legal[$key];
            }
        }
        $mode = str_pad($realmode, 10, '-', STR_PAD_LEFT);
        $trans = ['-' => '0', 'r' => '4', 'w' => '2', 'x' => '1'];
        $mode = strtr($mode, $trans);
        $newmode = $mode[0];
        $newmode .= $mode[1] + $mode[2] + $mode[3];
        $newmode .= $mode[4] + $mode[5] + $mode[6];
        $newmode .= $mode[7] + $mode[8] + $mode[9];
        return $newmode;
    }

    public function is_binary($text)
    {
        return (bool) preg_match('|[^\x20-\x7E]|', (string) $text);
    }

    public function chown($file, $owner, $recursive = false)
    {
        return false;
    }

    public function connect()
    {
        return true;
    }

    public function get_contents($file)
    {
        return false;
    }

    public function get_contents_array($file)
    {
        return false;
    }

    public function put_contents($file, $contents, $mode = false)
    {
        return false;
    }

    public function cwd()
    {
        return false;
    }

    public function chdir($dir)
    {
        return false;
    }

    public function chgrp($file, $group, $recursive = false)
    {
        return false;
    }

    public function chmod($file, $mode = false, $recursive = false)
    {
        return false;
    }

    public function owner($file)
    {
        return false;
    }

    public function group($file)
    {
        return false;
    }

    public function copy($source, $destination, $overwrite = false, $mode = false)
    {
        return false;
    }

    public function move($source, $destination, $overwrite = false)
    {
        return false;
    }

    public function delete($file, $recursive = false, $type = false)
    {
        return false;
    }

    public function exists($path)
    {
        return false;
    }

    public function is_file($file)
    {
        return false;
    }

    public function is_dir($path)
    {
        return false;
    }

    public function is_readable($file)
    {
        return false;
    }

    public function is_writable($path)
    {
        return false;
    }

    public function atime($file)
    {
        return false;
    }

    public function mtime($file)
    {
        return false;
    }

    public function size($file)
    {
        return false;
    }

    public function touch($file, $time = 0, $atime = 0)
    {
        return false;
    }

    public function mkdir($path, $chmod = false, $chown = false, $chgrp = false)
    {
        return false;
    }

    public function rmdir($path, $recursive = false)
    {
        return false;
    }

    public function dirlist($path, $include_hidden = true, $recursive = false)
    {
        return false;
    }
}

class WP_Filesystem_Direct extends WP_Filesystem_Base
{
    public function __construct($arg)
    {
        $this->method = 'direct';
        $this->errors = new WP_Error();
    }

    public function get_contents($file)
    {
        return @file_get_contents($file);
    }

    public function get_contents_array($file)
    {
        return @file($file);
    }

    public function put_contents($file, $contents, $mode = false)
    {
        $fp = @fopen($file, 'wb');
        if (!$fp) {
            return false;
        }
        mbstring_binary_safe_encoding();
        $data_length = strlen($contents);
        $bytes_written = fwrite($fp, $contents);
        reset_mbstring_encoding();
        fclose($fp);
        if ($data_length !== $bytes_written) {
            return false;
        }
        $this->chmod($file, $mode);
        return true;
    }

    public function cwd()
    {
        return getcwd();
    }

    public function chdir($dir)
    {
        return @chdir($dir);
    }

    public function chgrp($file, $group, $recursive = false)
    {
        if (!$this->exists($file)) {
            return false;
        }
        if (!$recursive || !$this->is_dir($file)) {
            return @chgrp($file, $group);
        }
        $file = trailingslashit($file);
        foreach ($this->dirlist($file) as $filename => $filemeta) {
            $this->chgrp($file . $filename, $group, $recursive);
        }
        return true;
    }

    public function chmod($file, $mode = false, $recursive = false)
    {
        if (!$mode) {
            if ($this->is_file($file)) {
                $mode = FS_CHMOD_FILE;
            } elseif ($this->is_dir($file)) {
                $mode = FS_CHMOD_DIR;
            } else {
                return false;
            }
        }
        if (!$recursive || !$this->is_dir($file)) {
            return @chmod($file, $mode);
        }
        $file = trailingslashit($file);
        foreach ($this->dirlist($file) as $filename => $filemeta) {
            $this->chmod($file . $filename, $mode, $recursive);
        }
        return true;
    }

    public function chown($file, $owner, $recursive = false)
    {
        if (!$this->exists($file)) {
            return false;
        }
        if (!$recursive || !$this->is_dir($file)) {
            return @chown($file, $owner);
        }
        $file = trailingslashit($file);
        foreach ($this->dirlist($file) as $filename => $filemeta) {
            $this->chown($file . $filename, $owner, $recursive);
        }
        return true;
    }

    public function owner($file)
    {
        $owneruid = @fileowner($file);
        if (!$owneruid) {
            return false;
        }
        if (!function_exists('posix_getpwuid')) {
            return $owneruid;
        }
        $ownerarray = posix_getpwuid($owneruid);
        return $ownerarray ? $ownerarray['name'] : false;
    }

    public function getchmod($file)
    {
        return substr(decoct(@fileperms($file)), -3);
    }

    public function group($file)
    {
        $gid = @filegroup($file);
        if (!$gid) {
            return false;
        }
        if (!function_exists('posix_getgrgid')) {
            return $gid;
        }
        $grouparray = posix_getgrgid($gid);
        return $grouparray ? $grouparray['name'] : false;
    }

    public function copy($source, $destination, $overwrite = false, $mode = false)
    {
        if (!$overwrite && $this->exists($destination)) {
            return false;
        }
        $rtval = copy($source, $destination);
        if ($mode) {
            $this->chmod($destination, $mode);
        }
        return $rtval;
    }

    public function move($source, $destination, $overwrite = false)
    {
        if (!$overwrite && $this->exists($destination)) {
            return false;
        }
        if ($overwrite && $this->exists($destination) && !$this->delete($destination, true)) {
            return false;
        }
        if (@rename($source, $destination)) {
            return true;
        }
        if ($this->copy($source, $destination, $overwrite) && $this->exists($destination)) {
            $this->delete($source);
            return true;
        }
        return false;
    }

    public function delete($file, $recursive = false, $type = false)
    {
        if (empty($file)) {
            return false;
        }
        $file = str_replace('\\', '/', $file);
        if ($type === 'f' || $this->is_file($file)) {
            return @unlink($file);
        }
        if (!$recursive && $this->is_dir($file)) {
            return @rmdir($file);
        }
        $file = trailingslashit($file);
        $filelist = $this->dirlist($file, true);
        $retval = true;
        if (is_array($filelist)) {
            foreach ($filelist as $filename => $fileinfo) {
                if (!$this->delete($file . $filename, $recursive, $fileinfo['type'])) {
                    $retval = false;
                }
            }
        }
        if (file_exists($file) && !@rmdir($file)) {
            $retval = false;
        }
        return $retval;
    }

    public function exists($path)
    {
        return @file_exists($path);
    }

    public function is_file($file)
    {
        return @is_file($file);
    }

    public function is_dir($path)
    {
        return @is_dir($path);
    }

    public function is_readable($file)
    {
        return @is_readable($file);
    }

    public function is_writable($path)
    {
        return @is_writable($path);
    }

    public function atime($file)
    {
        return @fileatime($file);
    }

    public function mtime($file)
    {
        return @filemtime($file);
    }

    public function size($file)
    {
        return @filesize($file);
    }

    public function touch($file, $time = 0, $atime = 0)
    {
        if ($time === 0) {
            $time = time();
        }
        if ($atime === 0) {
            $atime = time();
        }
        return touch($file, $time, $atime);
    }

    public function mkdir($path, $chmod = false, $chown = false, $chgrp = false)
    {
        $path = untrailingslashit($path);
        if (empty($path)) {
            return false;
        }
        if (!$chmod) {
            $chmod = FS_CHMOD_DIR;
        }
        if (!@mkdir($path)) {
            return false;
        }
        $this->chmod($path, $chmod);
        if ($chown) {
            $this->chown($path, $chown);
        }
        if ($chgrp) {
            $this->chgrp($path, $chgrp);
        }
        return true;
    }

    public function rmdir($path, $recursive = false)
    {
        return $this->delete($path, $recursive);
    }

    public function dirlist($path, $include_hidden = true, $recursive = false)
    {
        if ($this->is_file($path)) {
            $limit_file = basename($path);
            $path = dirname($path);
        } else {
            $limit_file = false;
        }
        if (!$this->is_dir($path) || !$this->is_readable($path)) {
            return false;
        }
        $dir = dir($path);
        if (!$dir) {
            return false;
        }
        $path = trailingslashit($path);
        $ret = [];
        while (false !== ($entry = $dir->read())) {
            $struc = [];
            $struc['name'] = $entry;
            if ($struc['name'] === '.' || $struc['name'] === '..') {
                continue;
            }
            if (!$include_hidden && $struc['name'][0] === '.') {
                continue;
            }
            if ($limit_file && $struc['name'] !== $limit_file) {
                continue;
            }
            $struc['perms'] = $this->gethchmod($path . $entry);
            $struc['permsn'] = $this->getnumchmodfromh($struc['perms']);
            $struc['number'] = false;
            $struc['owner'] = $this->owner($path . $entry);
            $struc['group'] = $this->group($path . $entry);
            $struc['size'] = $this->size($path . $entry);
            $struc['lastmodunix'] = $this->mtime($path . $entry);
            $struc['lastmod'] = gmdate('M j', $struc['lastmodunix']);
            $struc['time'] = gmdate('h:i:s', $struc['lastmodunix']);
            $struc['type'] = $this->is_dir($path . $entry) ? 'd' : 'f';
            if ($struc['type'] === 'd') {
                if ($recursive) {
                    $struc['files'] = $this->dirlist($path . $struc['name'], $include_hidden, $recursive);
                } else {
                    $struc['files'] = [];
                }
            }
            $ret[$struc['name']] = $struc;
        }
        $dir->close();
        unset($dir);
        return $ret;
    }
}
