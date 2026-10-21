<?php

declare(strict_types=1);

namespace Minn\Admin;

use Closure;

/**
 * The code editor's settings as wp_get_code_editor_settings gives them
 * (probe admin-doubts): CodeMirror's defaults and the three linters', the
 * mode and its helpers from the content type (named, or worked out from the
 * file's extension), then the caller's own settings merged over each part.
 */
final class CodeEditor
{
    /** A file extension => the content type it is edited as. */
    private const EXTENSIONS = [
        'css' => 'text/css', 'scss' => 'text/x-scss', 'less' => 'text/x-less', 'sass' => 'text/x-sass',
        'js' => 'text/javascript', 'mjs' => 'text/javascript', 'jsx' => 'text/jsx', 'json' => 'application/json',
        'php' => 'application/x-httpd-php', 'phtml' => 'application/x-httpd-php', 'php3' => 'application/x-httpd-php', 'php4' => 'application/x-httpd-php',
        'php5' => 'application/x-httpd-php', 'php7' => 'application/x-httpd-php', 'phps' => 'application/x-httpd-php',
        'svg' => 'application/svg+xml', 'xml' => 'text/xml', 'md' => 'text/x-gfm', 'sh' => 'text/x-sh', 'bash' => 'text/x-sh',
        'sql' => 'text/x-sql', 'html' => 'text/html', 'htm' => 'text/html', 'yml' => 'text/x-yaml', 'yaml' => 'text/x-yaml',
        'diff' => 'text/x-diff', 'patch' => 'text/x-diff', 'conf' => 'text/nginx',
    ];

    /**
     * The settings for one editor: $args' type, else its file's (the
     * extension, else $mimeOf the file, else text/plain); the caller's
     * codemirror, csslint, jshint and htmlhint settings merged over the
     * defaults.
     *
     * @param array<string, mixed> $args
     * @param Closure(string): string $mimeOf a file's content type, '' when unknown
     * @return array<string, array<string, mixed>>
     */
    public static function settings(array $args, string $espreeUrl, Closure $mimeOf): array
    {
        $settings = self::defaults($espreeUrl);
        $type = (string) ($args['type'] ?? '');
        $file = (string) ($args['file'] ?? '');
        if ($type === '' && $file !== '') {
            $type = self::EXTENSIONS[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? ($mimeOf($file) ?: 'text/plain');
        }
        $settings['codemirror'] = self::mode($settings['codemirror'], $type);
        foreach (['codemirror', 'csslint', 'jshint', 'htmlhint'] as $part) {
            if (!empty($args[$part]) && is_array($args[$part])) {
                $settings[$part] = array_merge($settings[$part], $args[$part]);
            }
        }
        return $settings;
    }

    /**
     * CodeMirror's settings with the mode for a content type and the
     * helpers that mode takes.
     *
     * @param array<string, mixed> $codemirror
     * @return array<string, mixed>
     */
    private static function mode(array $codemirror, string $type): array
    {
        $brackets = ['autoCloseBrackets' => true, 'matchBrackets' => true];
        $tags = ['autoCloseBrackets' => true, 'autoCloseTags' => true, 'matchTags' => ['bothTags' => true]];
        [$mode, $extra] = match ($type) {
            'text/css', 'text/x-scss', 'text/x-less', 'text/x-sass' => [$type, ['lint' => false] + $brackets],
            'text/javascript', 'application/javascript' => ['javascript', ['lint' => true] + $brackets],
            'application/json', 'application/x-json' => [['name' => 'javascript', 'json' => true], ['lint' => true] + $brackets],
            'application/ld+json' => [['name' => 'javascript', 'jsonld' => true], ['lint' => true] + $brackets],
            'text/jsx' => ['jsx', $brackets],
            'application/x-httpd-php' => ['php', ['autoCloseBrackets' => true, 'autoCloseTags' => true, 'matchBrackets' => true, 'matchTags' => ['bothTags' => true]]],
            'text/html' => ['htmlmixed', ['lint' => true] + $tags],
            'text/xml', 'application/xml', 'application/svg+xml', 'image/svg+xml', 'application/xhtml+xml' => ['xml', $tags],
            'text/x-markdown' => ['markdown', ['highlightFormatting' => true]],
            'text/x-gfm' => ['gfm', ['highlightFormatting' => true]],
            'text/x-sql', 'text/x-mysql' => ['sql', $brackets],
            'text/x-diff' => ['diff', []],
            'text/x-yaml' => ['yaml', []],
            'text/nginx' => ['nginx', []],
            default => [$type, []],
        };
        $codemirror['mode'] = $mode;
        if (($extra['lint'] ?? false) === true) {
            $codemirror['gutters'][] = 'CodeMirror-lint-markers';
        }
        return array_merge($codemirror, $extra);
    }

    /**
     * The defaults: CodeMirror's, then CSSLint's, JSHint's (with the module
     * parser's address) and HTMLHint's rules.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function defaults(string $espreeUrl): array
    {
        return [
            'codemirror' => ['indentUnit' => 4, 'indentWithTabs' => true, 'inputStyle' => 'contenteditable', 'lineNumbers' => true, 'lineWrapping' => true, 'styleActiveLine' => true, 'continueComments' => true, 'extraKeys' => ['Ctrl-Space' => 'autocomplete', 'Ctrl-/' => 'toggleComment', 'Cmd-/' => 'toggleComment', 'Alt-F' => 'findPersistent', 'Ctrl-F' => 'findPersistent', 'Cmd-F' => 'findPersistent'], 'direction' => 'ltr', 'gutters' => []],
            'csslint' => array_fill_keys(['errors', 'box-model', 'display-property-grouping', 'duplicate-properties', 'known-properties', 'outline-none'], true),
            'jshint' => ['esversion' => 11, 'module' => false, 'espreeModuleUrl' => $espreeUrl]
                + array_fill_keys(['boss', 'curly', 'eqeqeq', 'eqnull', 'expr', 'immed', 'noarg', 'nonbsp'], true) + ['quotmark' => 'single']
                + array_fill_keys(['undef', 'unused', 'browser'], true)
                + ['globals' => array_fill_keys(['_', 'Backbone', 'jQuery', 'JSON', 'wp', 'export', 'module', 'require', 'WorkerGlobalScope', 'self', 'OffscreenCanvas', 'Promise'], false)],
            'htmlhint' => array_fill_keys(['tagname-lowercase', 'attr-lowercase'], true) + array_fill_keys(['attr-value-double-quotes', 'doctype-first'], false)
                + array_fill_keys(['tag-pair', 'spec-char-escape', 'id-unique', 'src-not-empty', 'attr-no-duplication', 'alt-require'], true)
                + ['space-tab-mixed-disabled' => 'tab', 'attr-unsafe-chars' => true],
        ];
    }
}
