<?php
/**
 * The gettext classes plugins build on (Polylang extends MO, Loco reads
 * entries): one file so each parent loads before its child. Behaviour from
 * contracts/fixtures/api/l10n.json; the reading, writing and plural rules
 * are Minn\I18n's.
 */

use Minn\I18n\Catalog;
use Minn\I18n\MoFile;
use Minn\I18n\PluralExpression;
use Minn\I18n\PluralRule;

#[AllowDynamicProperties]
class Translation_Entry
{
    public $is_plural = false;
    public $context = null;
    public $singular = null;
    public $plural = null;
    public $translations = [];
    public $translator_comments = '';
    public $extracted_comments = '';
    public $references = [];
    public $flags = [];

    public function __construct($args = [])
    {
        if (!isset($args['singular'])) {
            return;
        }
        foreach ((array) $args as $name => $value) {
            if (property_exists($this, (string) $name)) {
                $this->$name = $value;
            }
        }
        $this->is_plural = $this->plural !== null && $this->plural !== '';
        foreach (['translations', 'references', 'flags'] as $list) {
            $this->$list = is_array($this->$list) ? $this->$list : [];
        }
    }

    public function Translation_Entry($args = [])
    {
        _deprecated_constructor(self::class, '5.4.0', static::class);
        self::__construct($args);
    }

    public function key()
    {
        return $this->singular === null ? false : Catalog::key((string) $this->singular, $this->context === null ? null : (string) $this->context);
    }

    public function merge_with(&$other)
    {
        $this->flags = array_values(array_unique(array_merge($this->flags, $other->flags)));
        $this->references = array_values(array_unique(array_merge($this->references, $other->references)));
        if ($this->extracted_comments !== $other->extracted_comments) {
            $this->extracted_comments .= $other->extracted_comments;
        }
    }
}

#[AllowDynamicProperties]
class Translations
{
    public $entries = [];
    public $headers = [];

    public function add_entry($entry)
    {
        $entry = is_array($entry) ? new Translation_Entry($entry) : $entry;
        $key = $entry->key();
        if ($key === false) {
            return false;
        }
        $this->entries[$key] = &$entry;
        return true;
    }

    public function add_entry_or_merge($entry)
    {
        $entry = is_array($entry) ? new Translation_Entry($entry) : $entry;
        $key = $entry->key();
        if ($key === false) {
            return false;
        }
        if (isset($this->entries[$key])) {
            $this->entries[$key]->merge_with($entry);
        } else {
            $this->entries[$key] = $entry;
        }
        return true;
    }

    public function set_header($header, $value)
    {
        $this->headers[$header] = $value;
    }

    public function set_headers($headers)
    {
        foreach ((array) $headers as $header => $value) {
            $this->set_header($header, $value);
        }
    }

    public function get_header($header)
    {
        return $this->headers[$header] ?? false;
    }

    public function translate_entry(&$entry)
    {
        $key = $entry->key();
        return $key !== false && isset($this->entries[$key]) ? $this->entries[$key] : false;
    }

    public function translate($singular, $context = null)
    {
        $entry = new Translation_Entry(['singular' => $singular, 'context' => $context]);
        $found = $this->translate_entry($entry);
        return $found && ($found->translations[0] ?? '') !== '' ? $found->translations[0] : $singular;
    }

    public function select_plural_form($count)
    {
        return (int) $count === 1 ? 0 : 1;
    }

    public function get_plural_forms_count()
    {
        return 2;
    }

    public function translate_plural($singular, $plural, $count, $context = null)
    {
        $entry = new Translation_Entry(['singular' => $singular, 'plural' => $plural, 'context' => $context]);
        $found = $this->translate_entry($entry);
        $index = $this->select_plural_form($count);
        $form = $found && $index >= 0 && $index < $this->get_plural_forms_count() ? ($found->translations[$index] ?? '') : '';
        return $form !== '' ? $form : ((int) $count === 1 ? $singular : $plural);
    }

    public function merge_with(&$other)
    {
        foreach ($other->entries as $entry) {
            $this->entries[$entry->key()] = $entry;
        }
    }

    public function merge_originals_with(&$other)
    {
        foreach ($other->entries as $entry) {
            if (isset($this->entries[$entry->key()])) {
                $this->entries[$entry->key()]->merge_with($entry);
            } else {
                $this->entries[$entry->key()] = $entry;
            }
        }
    }
}

class Gettext_Translations extends Translations
{
    public $_nplurals = null;
    public $_gettext_select_plural_form = null;

    public function gettext_select_plural_form($count)
    {
        if (!is_callable($this->_gettext_select_plural_form)) {
            [$nplurals, $expression] = $this->nplurals_and_expression_from_header((string) $this->get_header('Plural-Forms'));
            $this->_nplurals = $nplurals;
            $this->_gettext_select_plural_form = $this->make_plural_form_function($nplurals, $expression);
        }
        return call_user_func($this->_gettext_select_plural_form, $count);
    }

    public function nplurals_and_expression_from_header($header)
    {
        $rule = PluralRule::fromHeader((string) $header === '' ? null : (string) $header);
        return [$rule->count, $rule->expression];
    }

    public function make_plural_form_function($nplurals, $expression)
    {
        $rule = PluralRule::fromExpression((int) $nplurals, (string) $expression);
        return static fn ($n): int => $rule->index((int) $n);
    }

    public function parenthesize_plural_exression($expression)
    {
        return '(' . trim((string) $expression) . ')';
    }

    public function make_headers($translation)
    {
        $headers = [];
        foreach (explode("\n", str_replace('\n', "\n", (string) $translation)) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }
        return $headers;
    }

    public function set_header($header, $value)
    {
        parent::set_header($header, $value);
        if ($header === 'Plural-Forms') {
            [$nplurals, $expression] = $this->nplurals_and_expression_from_header($this->get_header('Plural-Forms'));
            $this->_nplurals = $nplurals;
            $this->_gettext_select_plural_form = $this->make_plural_form_function($nplurals, $expression);
        }
    }
}

class MO extends Gettext_Translations
{
    public $_nplurals = 2;
    private $filename = '';

    public function get_filename()
    {
        return $this->filename;
    }

    public function import_from_file($filename)
    {
        $catalog = is_file($filename) && is_readable($filename) ? MoFile::read((string) file_get_contents($filename)) : null;
        if ($catalog === null) {
            return false;
        }
        $this->filename = (string) $filename;
        $this->set_headers($catalog->headers);
        foreach ($catalog->entries as $key => $entry) {
            $this->add_entry($this->make_entry($entry['plural'] === null ? $key : $key . "\0" . $entry['plural'], implode("\0", $entry['translations'])));
        }
        return true;
    }

    public function export_to_file($filename)
    {
        return file_put_contents($filename, $this->export()) !== false;
    }

    public function export()
    {
        $entries = [];
        foreach (array_filter($this->entries, [$this, 'is_entry_good_for_export']) as $entry) {
            $entries[(string) $entry->key()] = ['translations' => array_values($entry->translations), 'plural' => $entry->is_plural ? (string) $entry->plural : null];
        }
        return MoFile::write($this->headers, $entries);
    }

    public function is_entry_good_for_export($entry)
    {
        return $entry->translations !== [] && array_filter($entry->translations, static fn ($form): bool => (string) $form !== '') !== [];
    }

    public function export_to_file_handle($fh)
    {
        return fwrite($fh, $this->export()) !== false;
    }

    public function export_original($entry)
    {
        $original = Catalog::key((string) $entry->singular, $entry->context === null ? null : (string) $entry->context);
        return $entry->is_plural ? $original . "\0" . $entry->plural : $original;
    }

    public function export_translations($entry)
    {
        return $entry->is_plural ? implode("\0", $entry->translations) : (string) ($entry->translations[0] ?? '');
    }

    public function export_headers()
    {
        $block = '';
        foreach ($this->headers as $header => $value) {
            $block .= "{$header}: {$value}\n";
        }
        return $block;
    }

    public function get_byteorder($magic)
    {
        return match ((int) $magic & 0xFFFFFFFF) {
            0x950412de => 'little',
            0xde120495 => 'big',
            default => false,
        };
    }

    public function import_from_reader($reader)
    {
        return false;
    }

    public function make_entry($original, $translation)
    {
        [$key, $plural] = array_pad(explode("\0", (string) $original, 2), 2, null);
        [$context, $singular] = str_contains($key, "\x04") ? explode("\x04", $key, 2) : [null, $key];
        $args = ['singular' => $singular, 'context' => $context, 'translations' => explode("\0", (string) $translation)];
        return new Translation_Entry($plural === null ? $args : $args + ['plural' => $plural]);
    }

    public function select_plural_form($count)
    {
        return $this->gettext_select_plural_form($count);
    }

    public function get_plural_forms_count()
    {
        return PluralRule::fromHeader($this->get_header('Plural-Forms') ?: null)->count;
    }
}

#[AllowDynamicProperties]
class Plural_Forms
{
    const OP_CHARS = '|&><!=%?:';
    const NUM_CHARS = '0123456789';

    protected static $op_precedence = ['%' => 6, '<' => 5, '<=' => 5, '>' => 5, '>=' => 5, '==' => 4, '!=' => 4, '&&' => 3, '||' => 2, '?:' => 1, '?' => 1, '(' => 0, ')' => 0];
    protected $tokens = [];
    protected $cache = [];

    public function __construct($str)
    {
        $this->parse($str);
    }

    protected function parse($str)
    {
        $tree = PluralExpression::parse((string) $str);
        if ($tree === null) {
            throw new Exception('Syntax error');
        }
        $this->tokens = $tree;
    }

    public function get($num)
    {
        return $this->cache[(int) $num] ??= $this->execute((int) $num);
    }

    public function execute($n)
    {
        return PluralExpression::evaluate($this->tokens, (int) $n);
    }
}
