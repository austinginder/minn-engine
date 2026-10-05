<?php
/** One translation file as plugins see it, over Minn\I18n's readers and the .mo writer. */

use Minn\I18n\Catalog;
use Minn\I18n\MoFile;
use Minn\I18n\PluralRule;
use Minn\I18n\TranslationFiles;

class WP_Translation_File
{
    protected $headers = [];
    protected $parsed = false;
    protected $error = null;
    protected $file = '';
    protected $entries = [];
    protected $plural_forms = null;

    protected function __construct(string $file)
    {
        $this->file = $file;
    }

    public static function create(string $file, ?string $filetype = null)
    {
        if (!is_file($file) || !is_readable($file)) {
            return false;
        }
        $filetype ??= str_ends_with($file, '.php') ? 'php' : 'mo';
        return match ($filetype) {
            'mo' => new WP_Translation_File_MO($file),
            'php' => new WP_Translation_File_PHP($file),
            default => false,
        };
    }

    public static function transform(string $file, string $filetype)
    {
        $source = self::create($file);
        if ($source === false || !in_array($filetype, ['mo', 'php'], true)) {
            return false;
        }
        $target = $filetype === 'mo' ? new WP_Translation_File_MO('') : new WP_Translation_File_PHP('');
        return $target->import($source) ? $target->export() : false;
    }

    public function headers(): array
    {
        $this->parse_file();
        return $this->headers;
    }

    public function entries(): array
    {
        $this->parse_file();
        return $this->entries;
    }

    public function error()
    {
        $this->parse_file();
        return $this->error ?? false;
    }

    public function get_file(): string
    {
        return $this->file;
    }

    public function translate(string $text)
    {
        $this->parse_file();
        return $this->entries[$text] ?? false;
    }

    public function get_plural_form(int $number): int
    {
        $this->parse_file();
        $this->plural_forms ??= $this->make_plural_form_function($this->get_plural_expression_from_header((string) ($this->headers['plural-forms'] ?? '')));
        return ($this->plural_forms)($number);
    }

    protected function get_plural_expression_from_header(string $header): string
    {
        return $header === '' ? 'n != 1' : PluralRule::fromHeader($header)->expression;
    }

    protected function make_plural_form_function(string $expression): callable
    {
        $rule = PluralRule::fromExpression(2, $expression);
        return static fn (int $n): int => $rule->index($n);
    }

    protected function import(WP_Translation_File $source): bool
    {
        $this->headers = $source->headers();
        $this->entries = $source->entries();
        $this->error = $source->error() ?: null;
        $this->parsed = true;
        return $this->error === null;
    }

    protected function parse_file()
    {
        if ($this->parsed) {
            return;
        }
        $this->parsed = true;
        $catalog = TranslationFiles::read($this->file);
        if ($catalog === null) {
            $this->error = 'Invalid data';
            return;
        }
        foreach ($catalog->headers as $name => $value) {
            $this->headers[strtolower((string) $name)] = $value;
        }
        foreach ($catalog->entries as $key => $entry) {
            $this->entries[$key] = implode("\0", $entry['translations']);
        }
    }

    public function export()
    {
        return false;
    }
}

class WP_Translation_File_MO extends WP_Translation_File
{
    const MAGIC_MARKER = 2500072158;

    protected $uint32 = false;

    protected function detect_endian_and_validate_file(string $header)
    {
        $magic = unpack('V', substr($header, 0, 4))[1] ?? 0;
        return match ($magic) {
            self::MAGIC_MARKER => 'V',
            0xde120495 => 'N',
            default => false,
        };
    }

    protected function parse_file(): bool
    {
        parent::parse_file();
        return $this->error === null;
    }

    public function export(): string
    {
        $entries = [];
        foreach ($this->entries() as $key => $translation) {
            $entries[(string) $key] = ['translations' => explode("\0", (string) $translation), 'plural' => null];
        }
        return MoFile::write($this->headers(), $entries);
    }
}

class WP_Translation_File_PHP extends WP_Translation_File
{
    protected function parse_file()
    {
        parent::parse_file();
    }

    public function export(): string
    {
        return '<?php' . PHP_EOL . 'return ' . $this->var_export($this->headers() + ['messages' => $this->entries()]) . ';' . PHP_EOL;
    }

    private function var_export($value): string
    {
        return var_export($value, true);
    }
}
