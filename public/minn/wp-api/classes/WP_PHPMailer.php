<?php
/**
 * WordPress's mailer: PHPMailer whose messages come from WordPress's own
 * wording, translated through the default text domain when translations
 * are loaded. wp_mail() keeps one in the global $phpmailer.
 */

if (!class_exists('WP_PHPMailer', false)) :
class WP_PHPMailer extends PHPMailer\PHPMailer\PHPMailer
{
    public function __construct($exceptions = false)
    {
        parent::__construct($exceptions);
        static::setLanguage();
    }

    public static function setLanguage($langcode = 'en', $lang_path = '')
    {
        $strings = Minn\Mail\MailerStrings::wordpress();
        static::$language = function_exists('__') ? array_map(static fn ($text) => __($text), $strings) : $strings;
        return true;
    }
}
endif;
