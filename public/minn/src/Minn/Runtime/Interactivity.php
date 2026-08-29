<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * Server-side directive processing for the Interactivity API: the state and
 * config stores, and the pass that resolves data-wp-bind, data-wp-class,
 * data-wp-style, data-wp-text, and data-wp-each against state and context
 * before the markup leaves the server. Behaviour pinned by the interactivity
 * probe fixture.
 */
final class Interactivity
{
    private const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];
    private const RAW = ['script', 'style', 'textarea', 'title'];
    private const TAG = '/<(\/?)([a-zA-Z][^\s\/>]*)((?:\s+[^\s=\/>]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+))?)*)\s*(\/?)>/';
    private const UNRESOLVED = "\0unresolved";
    private const ATTR = '/\s+([^\s=\/>]+)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]+))?/';

    /** @var array<string, array<string, mixed>> */
    private array $state = [];
    /** @var array<string, array<string, mixed>> */
    private array $config = [];
    /** @var list<string> namespaces, innermost last, while processing */
    private array $namespaces = [];
    /** @var list<array<string, array<string, mixed>>> context layers, innermost last, while processing */
    private array $contexts = [];
    /** @var array<string, mixed>|null the element whose directives are being evaluated */
    private ?array $element = null;
    private bool $processing = false;

    /** @return array<string, mixed> */
    public function state(?string $namespace, array $state = []): array
    {
        if ($namespace === null || $namespace === '') {
            if (!$this->processing) {
                Runtime::hooks()->action('doing_it_wrong_run', ['WP_Interactivity_API::state', 'The namespace can only be omitted during directive processing.', '6.6.0']);
                return [];
            }
            $namespace = $this->namespace();
        }
        if ($state !== []) {
            $this->state[$namespace] = array_replace_recursive($this->state[$namespace] ?? [], $state);
        }
        return $this->state[$namespace] ?? [];
    }

    /** @return array<string, mixed> */
    public function config(string $namespace, array $config = []): array
    {
        if ($config !== []) {
            $this->config[$namespace] = array_replace_recursive($this->config[$namespace] ?? [], $config);
        }
        return $this->config[$namespace] ?? [];
    }

    /** @return array<string, mixed> */
    public function context(?string $namespace = null): array
    {
        if (!$this->processing) {
            Runtime::hooks()->action('doing_it_wrong_run', ['WP_Interactivity_API::get_context', 'The context can only be read during directive processing.', '6.6.0']);
            return [];
        }
        $namespace = $namespace === null || $namespace === '' ? $this->namespace() : $namespace;
        return $this->contexts === [] ? [] : (end($this->contexts)[$namespace] ?? []);
    }

    /** @return array<string, mixed>|null */
    public function element(): ?array
    {
        if (!$this->processing) {
            Runtime::hooks()->action('doing_it_wrong_run', ['WP_Interactivity_API::get_element', 'The element can only be read during directive processing.', '6.7.0']);
            return null;
        }
        return $this->element;
    }

    public function process(string $html): string
    {
        $tokens = $this->tokenize($html);
        if ($tokens === null) {
            return $html;
        }
        $wasProcessing = $this->processing;
        $this->processing = true;
        try {
            $out = $this->walk($tokens, $html);
        } finally {
            $this->processing = $wasProcessing;
        }
        return $out ?? $html;
    }

    private function namespace(): string
    {
        return $this->namespaces === [] ? '' : (string) end($this->namespaces);
    }

    /**
     * Start tags, end tags, and everything between, with balance checked; null
     * when the markup does not close what it opens.
     *
     * @return list<array{kind: string, start: int, end: int, name?: string, attrs?: list<array{name: string, value: ?string, start: int, end: int}>, void?: bool, close?: int}>|null
     */
    private function tokenize(string $html): ?array
    {
        $tokens = [];
        $stack = [];
        $offset = 0;
        $length = strlen($html);
        while ($offset < $length) {
            $lt = strpos($html, '<', $offset);
            if ($lt === false) {
                break;
            }
            if (substr($html, $lt, 4) === '<!--') {
                $close = strpos($html, '-->', $lt + 4);
                $offset = $close === false ? $length : $close + 3;
                continue;
            }
            if (!preg_match(self::TAG, $html, $m, PREG_OFFSET_CAPTURE, $lt) || $m[0][1] !== $lt) {
                $offset = $lt + 1;
                continue;
            }
            $name = strtolower($m[2][0]);
            $end = $lt + strlen($m[0][0]);
            if ($m[1][0] === '/') {
                if ($stack === [] || end($stack)['name'] !== $name) {
                    return null;
                }
                $open = array_pop($stack);
                $tokens[$open['index']]['close'] = count($tokens);
                $tokens[] = ['kind' => 'end', 'name' => $name, 'start' => $lt, 'end' => $end];
                $offset = $end;
                continue;
            }
            $attrs = [];
            if (preg_match_all(self::ATTR, $m[3][0], $am, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                $base = $m[3][1];
                foreach ($am as $a) {
                    $raw = $a[2][0] ?? null;
                    $value = $raw === null ? null : (($raw[0] === '"' || $raw[0] === "'") ? substr($raw, 1, -1) : $raw);
                    $attrs[] = ['name' => $a[1][0], 'value' => $value, 'start' => $base + $a[1][1], 'end' => $base + $a[0][1] + strlen($a[0][0])];
                }
            }
            $void = $m[4][0] === '/' || in_array($name, self::VOID, true);
            $tokens[] = ['kind' => 'start', 'name' => $name, 'attrs' => $attrs, 'start' => $lt, 'end' => $end, 'void' => $void];
            $offset = $end;
            if (in_array($name, self::RAW, true)) {
                $closeAt = stripos($html, '</' . $name, $end);
                if ($closeAt === false) {
                    return null;
                }
                $offset = $closeAt;
                $stack[] = ['name' => $name, 'index' => count($tokens) - 1];
            } elseif (!$void) {
                $stack[] = ['name' => $name, 'index' => count($tokens) - 1];
            }
        }
        return $stack === [] ? $tokens : null;
    }

    /** @param list<array<string, mixed>> $tokens */
    private function walk(array $tokens, string $html): ?string
    {
        $out = '';
        $cursor = 0;
        $count = count($tokens);
        /** @var list<array{close: int, ns: bool, ctx: bool, text: ?string, each: ?array}> $open */
        $open = [];
        $i = 0;
        while ($i < $count) {
            $token = $tokens[$i];
            $out .= substr($html, $cursor, $token['start'] - $cursor);
            $cursor = $token['end'];
            if ($token['kind'] === 'end') {
                $frame = array_pop($open);
                if ($frame !== null) {
                    if ($frame['text'] !== null) {
                        $out = substr($out, 0, $frame['textAt']) . $frame['text'];
                    }
                    if ($frame['ns']) {
                        array_pop($this->namespaces);
                    }
                    if ($frame['ctx']) {
                        array_pop($this->contexts);
                    }
                }
                $out .= substr($html, $token['start'], $token['end'] - $token['start']);
                if ($frame !== null && $frame['each'] !== null) {
                    $out .= $this->expandEach($frame['each'], $tokens, $i, $html);
                }
                $i++;
                continue;
            }
            $inTemplate = $open !== [] && end($open)['template'];
            $frame = ['ns' => false, 'ctx' => false, 'text' => null, 'textAt' => 0, 'each' => null, 'template' => $inTemplate || $token['name'] === 'template'];
            $tag = substr($html, $token['start'], $token['end'] - $token['start']);
            if (!$inTemplate) {
                $tag = $this->applyDirectives($token, $tag, $frame);
            }
            $out .= $tag;
            if ($frame['text'] !== null) {
                $frame['textAt'] = strlen($out);
            }
            if (!$token['void']) {
                $open[] = $frame;
            } else {
                if ($frame['ns']) {
                    array_pop($this->namespaces);
                }
                if ($frame['ctx']) {
                    array_pop($this->contexts);
                }
            }
            $i++;
        }
        $out .= substr($html, $cursor);
        return $out;
    }

    /**
     * @param array<string, mixed> $token
     * @param array<string, mixed> $frame
     */
    private function applyDirectives(array $token, string $tag, array &$frame): string
    {
        $attrs = $token['attrs'];
        $directives = [];
        foreach ($attrs as $attr) {
            $lname = strtolower($attr['name']);
            if (str_starts_with($lname, 'data-wp-')) {
                $directives[] = [$lname, (string) $attr['value']];
            }
        }
        if ($directives === []) {
            return $tag;
        }
        foreach ($directives as [$name, $value]) {
            if ($name === 'data-wp-interactive') {
                $namespace = $this->interactiveNamespace($value);
                if ($namespace !== null) {
                    $this->namespaces[] = $namespace;
                    $frame['ns'] = true;
                }
            }
        }
        foreach ($directives as [$name, $value]) {
            if ($name === 'data-wp-context') {
                $this->pushContext($value);
                $frame['ctx'] = true;
            }
        }
        $this->element = ['attributes' => $this->attributeMap($attrs)];
        $editor = new TagEditor($tag, self::relative($attrs, $token['start']));
        foreach ($directives as [$name, $value]) {
            if (str_starts_with($name, 'data-wp-bind--')) {
                $this->bind($editor, substr($name, 14), $value);
            } elseif (str_starts_with($name, 'data-wp-class--')) {
                $result = $this->evaluate($value, 'class');
                if ($result !== self::UNRESOLVED) {
                    $editor->toggleClass(substr($name, 15), (bool) $result);
                }
            } elseif (str_starts_with($name, 'data-wp-style--')) {
                $result = $this->evaluate($value, 'style');
                if ($result !== self::UNRESOLVED) {
                    $editor->setStyle(substr($name, 15), is_scalar($result) && $result !== false && $result !== true ? (string) $result : null);
                }
            } elseif ($name === 'data-wp-text') {
                $result = $this->evaluate($value, 'text');
                if ($result !== self::UNRESOLVED) {
                    $frame['text'] = is_string($result) || is_int($result) || is_float($result) ? $this->escape((string) $result) : '';
                }
            } elseif ($name === 'data-wp-each' || str_starts_with($name, 'data-wp-each--')) {
                if ($token['name'] === 'template') {
                    $frame['each'] = ['key' => $name === 'data-wp-each' ? 'item' : self::camel(substr($name, 14)), 'path' => $value, 'token' => $token];
                }
            }
        }
        $this->element = null;
        return $editor->html();
    }

    private function bind(TagEditor $editor, string $attribute, string $path): void
    {
        $result = $this->evaluate($path, $attribute);
        if ($result === self::UNRESOLVED) {
            return;
        }
        if (is_array($result) || is_object($result)) {
            Runtime::hooks()->action('doing_it_wrong_run', ['WP_Interactivity_API::data_wp_bind_processor', 'Attempted to bind a non-scalar value to the "' . $attribute . '" attribute. Ensure the state/context property or the derived state closure resolves to a string, number, or boolean.', '7.1.0']);
            return;
        }
        $special = str_starts_with($attribute, 'aria-') || str_starts_with($attribute, 'data-');
        if ($result === null || ($result === false && !$special)) {
            $editor->remove($attribute);
            return;
        }
        if (is_bool($result)) {
            if ($special) {
                $editor->set($attribute, $result ? 'true' : 'false');
            } else {
                $editor->set($attribute, true);
            }
            return;
        }
        $editor->set($attribute, (string) $result);
    }

    private function interactiveNamespace(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if ($value[0] === '{') {
            $decoded = json_decode($value, true);
            return is_array($decoded) && isset($decoded['namespace']) && is_string($decoded['namespace']) && $decoded['namespace'] !== '' ? $decoded['namespace'] : null;
        }
        return $value;
    }

    private function pushContext(string $value): void
    {
        $namespace = $this->namespace();
        $json = trim($value);
        if (preg_match('/^([\w\-\/]+)::(.*)$/s', $json, $m)) {
            $namespace = $m[1];
            $json = $m[2];
        }
        $layer = $this->contexts === [] ? [] : end($this->contexts);
        $decoded = $json === '' ? null : json_decode($json, true);
        if (is_array($decoded) && $namespace !== '') {
            $layer[$namespace] = array_replace_recursive($layer[$namespace] ?? [], $decoded);
        }
        $this->contexts[] = $layer;
    }

    /** The value a directive path names, or UNRESOLVED when the namespace or path is empty. */
    private function evaluate(string $reference, string $suffix): mixed
    {
        $namespace = $this->namespace();
        $path = trim($reference);
        if (preg_match('/^([\w\-\/]+)::(.*)$/s', $path, $m)) {
            $namespace = $m[1];
            $path = $m[2];
        }
        $negate = false;
        if ($path !== '' && $path[0] === '!') {
            $negate = true;
            $path = substr($path, 1);
        }
        if ($namespace === '' || $path === '') {
            Runtime::hooks()->action('doing_it_wrong_run', ['WP_Interactivity_API::evaluate', 'Namespace or reference path cannot be empty. Directive value referenced: ' . json_encode(['namespace' => $namespace === '' ? null : $namespace, 'value' => $reference, 'suffix' => $suffix, 'unique_id' => null]), '6.6.0']);
            return self::UNRESOLVED;
        }
        $segments = explode('.', $path);
        $root = array_shift($segments);
        $current = match ($root) {
            'state' => $this->state[$namespace] ?? [],
            'context' => $this->contexts === [] ? [] : (end($this->contexts)[$namespace] ?? []),
            default => null,
        };
        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } elseif (is_object($current) && isset($current->$segment)) {
                $current = $current->$segment;
            } else {
                $current = null;
                break;
            }
        }
        if ($current instanceof \Closure || (is_array($current) && is_callable($current) && count($current) === 2 && is_object($current[0]))) {
            $saved = $this->namespaces;
            $this->namespaces[] = $namespace;
            try {
                $current = $current();
            } finally {
                $this->namespaces = $saved;
            }
        }
        return $negate ? !$current : $current;
    }

    /**
     * @param array{key: string, path: string, token: array<string, mixed>} $each
     * @param list<array<string, mixed>> $tokens
     */
    private function expandEach(array $each, array $tokens, int $closeIndex, string $html): string
    {
        // Server-rendered children already present mean the list was rendered by the block itself.
        $next = $tokens[$closeIndex + 1] ?? null;
        if ($next !== null && $next['kind'] === 'start') {
            foreach ($next['attrs'] as $attr) {
                if (strtolower($attr['name']) === 'data-wp-each-child') {
                    return '';
                }
            }
        }
        $list = $this->evaluate($each['path'], 'each');
        if (!is_array($list) || $list === []) {
            return '';
        }
        $namespace = $this->namespace();
        $reference = preg_match('/^[\w\-\/]+::/', trim($each['path'])) ? trim($each['path']) : $namespace . '::' . trim($each['path']);
        $token = $each['token'];
        $inner = substr($html, $token['end'], $tokens[$closeIndex]['start'] - $token['end']);
        $out = '';
        foreach ($list as $item) {
            $layer = $this->contexts === [] ? [] : end($this->contexts);
            $layer[$namespace] = array_replace($layer[$namespace] ?? [], [$each['key'] => $item]);
            $this->contexts[] = $layer;
            try {
                $out .= $this->markChildren($this->process($inner), $reference);
            } finally {
                array_pop($this->contexts);
            }
        }
        return $out;
    }

    /** Every top-level start tag in the fragment gains data-wp-each-child first. */
    private function markChildren(string $fragment, string $reference): string
    {
        $tokens = $this->tokenize($fragment);
        if ($tokens === null) {
            return $fragment;
        }
        $out = '';
        $cursor = 0;
        $depth = 0;
        foreach ($tokens as $token) {
            if ($token['kind'] === 'start') {
                if ($depth === 0) {
                    $out .= substr($fragment, $cursor, $token['start'] - $cursor);
                    $editor = new TagEditor(substr($fragment, $token['start'], $token['end'] - $token['start']), self::relative($token['attrs'], $token['start']));
                    $editor->set('data-wp-each-child', $reference);
                    $out .= $editor->html();
                    $cursor = $token['end'];
                }
                if (!$token['void']) {
                    $depth++;
                }
            } else {
                $depth--;
            }
        }
        return $out . substr($fragment, $cursor);
    }

    /**
     * @param list<array{name: string, value: ?string}> $attrs
     * @return array<string, mixed>
     */
    private function attributeMap(array $attrs): array
    {
        $map = [];
        foreach ($attrs as $attr) {
            $map[$attr['name']] = $attr['value'] ?? true;
        }
        return $map;
    }

    /**
     * @param list<array{name: string, value: ?string, start: int, end: int}> $attrs
     * @return list<array{name: string, value: ?string, start: int, end: int}>
     */
    private static function relative(array $attrs, int $base): array
    {
        return array_map(static fn (array $a) => ['name' => $a['name'], 'value' => $a['value'], 'start' => $a['start'] - $base, 'end' => $a['end'] - $base], $attrs);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    private static function camel(string $name): string
    {
        return lcfirst(str_replace('-', '', ucwords($name, '-')));
    }
}
