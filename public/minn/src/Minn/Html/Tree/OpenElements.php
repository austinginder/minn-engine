<?php

declare(strict_types=1);

namespace Minn\Html\Tree;

/**
 * The stack of open elements and the spec's scope tests over it: an
 * element is in scope when it is reached walking down from the current
 * node before any of the scope's boundary elements.
 */
final class OpenElements
{
    private const DEFAULT_SCOPE = [
        'html' => ['APPLET', 'CAPTION', 'HTML', 'TABLE', 'TD', 'TH', 'MARQUEE', 'OBJECT', 'TEMPLATE'],
        'math' => ['MI', 'MO', 'MN', 'MS', 'MTEXT', 'ANNOTATION-XML'],
        'svg' => ['FOREIGNOBJECT', 'DESC', 'TITLE'],
    ];

    /** @var list<Node> */
    private array $nodes = [];

    /** Puts a node on top. */
    public function push(Node $node): void
    {
        $this->nodes[] = $node;
    }

    /** Takes the current node off, or null when the stack is empty. */
    public function pop(): ?Node
    {
        return array_pop($this->nodes);
    }

    /** The current (bottom-most open) node. */
    public function current(): ?Node
    {
        return $this->nodes === [] ? null : $this->nodes[count($this->nodes) - 1];
    }

    /** The node at a position, 0 being the root. */
    public function at(int $index): ?Node
    {
        return $this->nodes[$index] ?? null;
    }

    /** How many nodes are open. */
    public function count(): int
    {
        return count($this->nodes);
    }

    /**
     * Every open node, root first.
     *
     * @return list<Node>
     */
    public function all(): array
    {
        return $this->nodes;
    }

    /** Whether this very node is open. */
    public function containsNode(Node $node): bool
    {
        return in_array($node, $this->nodes, true);
    }

    /** Whether an HTML element of the name is open anywhere. */
    public function hasHtml(string $name): bool
    {
        foreach ($this->nodes as $node) {
            if ($node->isHtml($name)) {
                return true;
            }
        }
        return false;
    }

    /** Whether one of the HTML elements is in the default scope. */
    public function inScope(string ...$names): bool
    {
        return $this->inSpecificScope($names, self::DEFAULT_SCOPE);
    }

    /** Whether the HTML element is in list item scope (the default plus OL and UL). */
    public function inListItemScope(string $name): bool
    {
        return $this->inSpecificScope([$name], ['html' => [...self::DEFAULT_SCOPE['html'], 'OL', 'UL']] + self::DEFAULT_SCOPE);
    }

    /** Whether the HTML element is in button scope (the default plus BUTTON). */
    public function inButtonScope(string $name): bool
    {
        return $this->inSpecificScope([$name], ['html' => [...self::DEFAULT_SCOPE['html'], 'BUTTON']] + self::DEFAULT_SCOPE);
    }

    /** Whether one of the HTML elements is in table scope (HTML, TABLE and TEMPLATE bound it). */
    public function inTableScope(string ...$names): bool
    {
        return $this->inSpecificScope($names, ['html' => ['HTML', 'TABLE', 'TEMPLATE']]);
    }

    /** Whether the HTML element is in select scope (anything but OPTGROUP and OPTION bounds it). */
    public function inSelectScope(string $name): bool
    {
        for ($i = count($this->nodes) - 1; $i >= 0; $i--) {
            $node = $this->nodes[$i];
            if ($node->isHtml($name)) {
                return true;
            }
            if (!$node->isHtml('OPTGROUP', 'OPTION')) {
                return false;
            }
        }
        return false;
    }

    /**
     * @param list<string> $names
     * @param array<string, list<string>> $boundary
     */
    private function inSpecificScope(array $names, array $boundary): bool
    {
        for ($i = count($this->nodes) - 1; $i >= 0; $i--) {
            $node = $this->nodes[$i];
            if ($node->type === '#tag' && $node->namespace === 'html' && in_array($node->name, $names, true)) {
                return true;
            }
            if ($node->type === '#tag' && in_array($node->name, $boundary[$node->namespace] ?? [], true)) {
                return false;
            }
        }
        return false;
    }
}
