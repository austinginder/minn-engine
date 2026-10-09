<?php
/**
 * The POMO stream readers (probe pomo-streams): POMO_StringReader over a byte
 * string and POMO_FileReader, POMO_CachedFileReader and
 * POMO_CachedIntFileReader over a file the probe writes. Integers read in
 * each byte order, integer lists, reads past the end, seeking, positions,
 * lengths, the string helpers, and the old PHP 4 constructors. The probe's
 * file is removed. Same protocol as api-probe.php; it writes nothing else.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$file = rtrim(get_temp_dir(), '/') . '/zz-pomo-streams-' . getmypid() . '.bin';
register_shutdown_function(static function () use ($file): void {
    if (is_file($file)) {
        unlink($file);
    }
});
$bytes = pack('V', 0x950412de) . pack('N', 7) . pack('V', 1) . pack('V', 2) . 'tail';
file_put_contents($file, $bytes);
$hex = static fn ($value) => is_string($value) ? bin2hex($value) : $value;
$deprecated = [];
add_action('deprecated_constructor_run', static function ($class, $version) use (&$deprecated): void {
    $deprecated[] = [$class, $version];
}, 10, 2);
add_filter('deprecated_constructor_trigger_error', '__return_false');

$string = new POMO_StringReader($bytes);
$say('string reader', [
    get_parent_class($string) === POMO_Reader::class,
    $string->length(), $string->pos(),
    $string->readint32(), $string->pos(),
    $string->setEndian('big'), $string->readint32(),
    $string->setEndian('little'), $string->readint32array(2), $string->pos(),
    $hex($string->read(3)), $hex($string->read(10)), $string->pos(),
    $string->readint32(), $string->readint32array(3),
    $string->seekto(4), $string->pos(), $hex($string->read(4)),
    $string->seekto(999), $string->pos(), $hex($string->read(2)),
    $hex($string->read_all()),
    $string->is_resource(), $string->close(),
    $string->substr('abcdef', 1, 3), $string->strlen("caf\u{e9}"), $string->str_split('abcdefg', 3),
]);
$empty = new POMO_StringReader();
$say('empty string reader', [$empty->length(), $empty->readint32(), $empty->readint32array(2), $hex($empty->read(5)), $hex($empty->read_all())]);

$reader = new POMO_FileReader($file);
$say('file reader', [
    $reader->is_resource(), $reader->pos(), $reader->feof(),
    $reader->readint32(), $reader->setEndian('big'), $reader->readint32(), $reader->setEndian('little'),
    $reader->readint32array(2), $reader->pos(),
    $hex($reader->read(2)), $reader->seekto(0), $reader->pos(), $hex($reader->read(4)),
    $hex($reader->read_all()), $reader->feof(), $hex($reader->read(4)), $reader->readint32(),
    $reader->close(), $reader->is_resource(),
]);
$missing = @new POMO_FileReader($file . '.none');
$say('file reader, no file', [$missing->is_resource()]);

$cached = new POMO_CachedFileReader($file);
$cachedInt = new POMO_CachedIntFileReader($file);
$say('cached readers', [
    get_parent_class($cached) === POMO_StringReader::class, $cached->length(), $cached->readint32(), $hex($cached->read(4)),
    get_parent_class($cachedInt) === POMO_CachedFileReader::class, $cachedInt->length(), $cachedInt->readint32array(4),
    (static function () use ($file) {
        $none = @new POMO_CachedFileReader($file . '.none');
        return [$none->length(), $hex = bin2hex((string) $none->read_all())];
    })(),
]);

$old = [];
$o = new POMO_StringReader('x');
$o->POMO_StringReader('abcd');
$old[] = [$o->length(), $o->read(2)];
$o->POMO_Reader();
$old[] = $o->pos();
$f = new POMO_FileReader($file);
$f->POMO_FileReader($file);
$old[] = $f->readint32();
$f->close();
$c = new POMO_CachedFileReader($file);
$c->POMO_CachedFileReader($file);
$old[] = $c->length();
$ci = new POMO_CachedIntFileReader($file);
$ci->POMO_CachedIntFileReader($file);
$old[] = $ci->length();
$say('the PHP 4 constructors', [$old, $deprecated]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
