<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * The MIME type a mailer gives an attachment by its file extension (the
 * table the reference's mailer answers with); anything else is
 * application/octet-stream.
 */
final class MimeTypes
{
    private const TYPES = [
        'ai' => 'application/postscript', 'aif' => 'audio/x-aiff', 'aifc' => 'audio/x-aiff', 'aiff' => 'audio/x-aiff', 'avi' => 'video/x-msvideo',
        'avif' => 'image/avif', 'bin' => 'application/macbinary', 'bmp' => 'image/bmp', 'css' => 'text/css', 'csv' => 'text/csv',
        'dcr' => 'application/x-director', 'dir' => 'application/x-director', 'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'dvi' => 'application/x-dvi', 'dxr' => 'application/x-director',
        'eml' => 'message/rfc822', 'eps' => 'application/postscript', 'gif' => 'image/gif', 'gtar' => 'application/x-gtar', 'heic' => 'image/heic',
        'heics' => 'image/heic-sequence', 'heif' => 'image/heif', 'heifs' => 'image/heif-sequence', 'htm' => 'text/html', 'html' => 'text/html',
        'ics' => 'text/calendar', 'jpe' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'jpg' => 'image/jpeg', 'js' => 'application/javascript',
        'log' => 'text/plain', 'm4a' => 'audio/mp4', 'm4v' => 'video/mp4', 'mid' => 'audio/midi', 'midi' => 'audio/midi', 'mif' => 'application/vnd.mif',
        'mka' => 'audio/x-matroska', 'mkv' => 'video/x-matroska', 'mov' => 'video/quicktime', 'movie' => 'video/x-sgi-movie', 'mp2' => 'audio/mpeg',
        'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4', 'mpe' => 'video/mpeg', 'mpeg' => 'video/mpeg', 'mpg' => 'video/mpeg', 'mpga' => 'audio/mpeg',
        'oda' => 'application/oda', 'pdf' => 'application/pdf', 'php' => 'application/x-httpd-php', 'php3' => 'application/x-httpd-php',
        'php4' => 'application/x-httpd-php', 'phps' => 'application/x-httpd-php-source', 'phtml' => 'application/x-httpd-php', 'png' => 'image/png',
        'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'ps' => 'application/postscript', 'qt' => 'video/quicktime', 'ra' => 'audio/x-realaudio', 'ram' => 'audio/x-pn-realaudio',
        'rm' => 'audio/x-pn-realaudio', 'rpm' => 'audio/x-pn-realaudio-plugin', 'rtf' => 'text/rtf', 'rtx' => 'text/richtext',
        'rv' => 'video/vnd.rn-realvideo', 'shtml' => 'text/html', 'sit' => 'application/x-stuffit', 'smi' => 'application/smil',
        'smil' => 'application/smil', 'swf' => 'application/x-shockwave-flash', 'tar' => 'application/x-tar', 'text' => 'text/plain',
        'tgz' => 'application/x-tar', 'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'txt' => 'text/plain', 'vcard' => 'text/vcard', 'vcf' => 'text/vcard',
        'wav' => 'audio/x-wav', 'wbxml' => 'application/vnd.wap.wbxml', 'webm' => 'video/webm', 'webp' => 'image/webp', 'wmlc' => 'application/vnd.wap.wmlc',
        'wmv' => 'video/x-ms-wmv', 'xht' => 'application/xhtml+xml', 'xhtml' => 'application/xhtml+xml', 'xl' => 'application/excel',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xml' => 'text/xml',
        'xsl' => 'text/xml', 'zip' => 'application/zip',
    ];

    /** The type for an extension, in any case. */
    public static function forExtension(string $extension): string
    {
        return self::TYPES[strtolower($extension)] ?? 'application/octet-stream';
    }

    /** The type for a file name or path: its last extension (a query string ignored). */
    public static function forFilename(string $filename): string
    {
        $at = strpos($filename, '?');
        if ($at !== false) {
            $filename = substr($filename, 0, $at);
        }
        return self::forExtension((string) pathinfo($filename, PATHINFO_EXTENSION));
    }
}
