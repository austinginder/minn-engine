<?php
/** A domain's translations as get_translations_for_domain() hands them out, over the controller. */

class WP_Translations
{
    protected $textdomain = 'default';
    protected $controller = null;

    public function __construct(WP_Translation_Controller $controller, string $textdomain = 'default')
    {
        $this->controller = $controller;
        $this->textdomain = $textdomain;
    }

    public function __get(string $name)
    {
        if ($name === 'entries') {
            $entries = [];
            foreach ($this->controller->get_entries($this->textdomain) as $original => $translations) {
                $entries[$original] = $this->make_entry($original, $translations);
            }
            return $entries;
        }
        return $name === 'headers' ? $this->controller->get_headers($this->textdomain) : null;
    }

    private function make_entry($original, $translations): Translation_Entry
    {
        [$context, $singular] = str_contains((string) $original, "\x04") ? explode("\x04", (string) $original, 2) : [null, (string) $original];
        return new Translation_Entry(['singular' => $singular, 'context' => $context, 'translations' => explode("\0", (string) $translations)]);
    }

    public function translate_plural($singular, $plural, $count = 1, $context = '')
    {
        $translation = $this->controller->translate_plural([(string) $singular, (string) $plural], (int) $count, (string) $context, $this->textdomain);
        return $translation !== false ? $translation : ((int) $count === 1 ? $singular : $plural);
    }

    public function translate($singular, $context = '')
    {
        $translation = $this->controller->translate((string) $singular, (string) $context, $this->textdomain);
        return $translation !== false ? $translation : $singular;
    }
}
