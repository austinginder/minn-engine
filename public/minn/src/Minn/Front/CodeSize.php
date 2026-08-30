<?php

declare(strict_types=1);

namespace Minn\Front;

/**
 * The code-size report tests/tools/code-size.php writes: how much PHP, JS
 * and CSS ships in WordPress core and in Minn with Minn Admin. The site
 * root's contracts/ copy wins over the engine's data/ fallback.
 */
final readonly class CodeSize
{
    /** @param list<array<string, mixed>> $stacks */
    private function __construct(public string $measured, public array $method, public array $stacks)
    {
    }

    public static function locate(string $engineDir): ?string
    {
        foreach ([dirname($engineDir, 2) . '/contracts/code-size.json', $engineDir . '/data/code-size.json'] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    public static function fromFile(string $path): self
    {
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || !isset($data['stacks']) || count($data['stacks']) < 2) {
            throw new \RuntimeException('code-size.json is not a report');
        }
        return new self((string) ($data['measured'] ?? ''), (array) ($data['method'] ?? []), array_values($data['stacks']));
    }

    public function stack(string $id): array
    {
        foreach ($this->stacks as $stack) {
            if ($stack['id'] === $id) {
                return $stack;
            }
        }
        throw new \RuntimeException("no stack {$id}");
    }

    /** How many times the first stack's count is the second's, one decimal. */
    public static function ratio(int $a, int $b): float
    {
        return $b === 0 ? 0.0 : round($a / $b, 1);
    }

    public function versionLabel(string $id): string
    {
        $stack = $this->stack($id);
        $label = $stack['label'] . ' ' . $stack['version'];
        return isset($stack['adminVersion']) ? $label . ' with Minn Admin ' . $stack['adminVersion'] : $label;
    }
}
