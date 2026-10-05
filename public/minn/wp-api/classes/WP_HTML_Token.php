<?php
/**
 * The HTML API's small helper classes: a token as the processor tracks it,
 * spans and text replacements, attribute tokens, stack events, the parser
 * state, and the two element lists. The engine's processor keeps its own
 * state in Minn\Html\Tree; these exist for code that builds or inspects
 * them directly.
 */

class WP_HTML_Token
{
    public $bookmark_name = null;
    public $node_name = null;
    public $has_self_closing_flag = false;
    public $namespace = 'html';
    public $integration_node_type = null;
    public $on_destroy = null;

    public function __construct($bookmark_name, $node_name, $has_self_closing_flag, $on_destroy = null)
    {
        $this->bookmark_name = $bookmark_name;
        $this->node_name = $node_name;
        $this->has_self_closing_flag = $has_self_closing_flag;
        $this->on_destroy = $on_destroy;
    }

    public function __destruct()
    {
        if (is_callable($this->on_destroy)) {
            call_user_func($this->on_destroy, $this->bookmark_name);
        }
    }

    public function __wakeup()
    {
        throw new \LogicException(__CLASS__ . ' should never be unserialized');
    }
}

class WP_HTML_Span
{
    public $start;
    public $length;

    public function __construct(int $start, int $length)
    {
        $this->start = $start;
        $this->length = $length;
    }
}

class WP_HTML_Text_Replacement
{
    public $start;
    public $length;
    public $text;

    public function __construct(int $start, int $length, string $text)
    {
        $this->start = $start;
        $this->length = $length;
        $this->text = $text;
    }
}

class WP_HTML_Attribute_Token
{
    public $name;
    public $value_starts_at;
    public $value_length;
    public $start;
    public $length;
    public $is_true;

    public function __construct(string $name, int $value_start, int $value_length, int $start, int $length, bool $is_true)
    {
        $this->name = $name;
        $this->value_starts_at = $value_start;
        $this->value_length = $value_length;
        $this->start = $start;
        $this->length = $length;
        $this->is_true = $is_true;
    }
}

class WP_HTML_Stack_Event
{
    const PUSH = 'push';
    const POP = 'pop';

    public $token;
    public $operation;
    public $provenance;

    public function __construct(WP_HTML_Token $token, string $operation, string $provenance)
    {
        $this->token = $token;
        $this->operation = $operation;
        $this->provenance = $provenance;
    }
}

class WP_HTML_Processor_State
{
    const INSERTION_MODE_INITIAL = 'insertion-mode-initial';
    const INSERTION_MODE_BEFORE_HTML = 'insertion-mode-before-html';
    const INSERTION_MODE_BEFORE_HEAD = 'insertion-mode-before-head';
    const INSERTION_MODE_IN_HEAD = 'insertion-mode-in-head';
    const INSERTION_MODE_IN_HEAD_NOSCRIPT = 'insertion-mode-in-head-noscript';
    const INSERTION_MODE_AFTER_HEAD = 'insertion-mode-after-head';
    const INSERTION_MODE_IN_BODY = 'insertion-mode-in-body';
    const INSERTION_MODE_IN_TABLE = 'insertion-mode-in-table';
    const INSERTION_MODE_IN_TABLE_TEXT = 'insertion-mode-in-table-text';
    const INSERTION_MODE_IN_CAPTION = 'insertion-mode-in-caption';
    const INSERTION_MODE_IN_COLUMN_GROUP = 'insertion-mode-in-column-group';
    const INSERTION_MODE_IN_TABLE_BODY = 'insertion-mode-in-table-body';
    const INSERTION_MODE_IN_ROW = 'insertion-mode-in-row';
    const INSERTION_MODE_IN_CELL = 'insertion-mode-in-cell';
    const INSERTION_MODE_IN_SELECT = 'insertion-mode-in-select';
    const INSERTION_MODE_IN_SELECT_IN_TABLE = 'insertion-mode-in-select-in-table';
    const INSERTION_MODE_IN_TEMPLATE = 'insertion-mode-in-template';
    const INSERTION_MODE_AFTER_BODY = 'insertion-mode-after-body';
    const INSERTION_MODE_IN_FRAMESET = 'insertion-mode-in-frameset';
    const INSERTION_MODE_AFTER_FRAMESET = 'insertion-mode-after-frameset';
    const INSERTION_MODE_AFTER_AFTER_BODY = 'insertion-mode-after-after-body';
    const INSERTION_MODE_AFTER_AFTER_FRAMESET = 'insertion-mode-after-after-frameset';

    public $stack_of_template_insertion_modes = [];
    public $stack_of_open_elements;
    public $active_formatting_elements;
    public $current_token = null;
    public $insertion_mode = self::INSERTION_MODE_INITIAL;
    public $context_node = null;
    public $encoding = null;
    public $encoding_confidence = 'tentative';
    public $head_element = null;
    public $form_element = null;
    public $frameset_ok = true;

    public function __construct()
    {
        $this->stack_of_open_elements = new WP_HTML_Open_Elements();
        $this->active_formatting_elements = new WP_HTML_Active_Formatting_Elements();
    }
}

class WP_HTML_Open_Elements
{
    private const SCOPE = ['APPLET', 'CAPTION', 'HTML', 'TABLE', 'TD', 'TH', 'MARQUEE', 'OBJECT', 'TEMPLATE', 'math MI', 'math MO', 'math MN', 'math MS', 'math MTEXT', 'math ANNOTATION-XML', 'svg FOREIGNOBJECT', 'svg DESC', 'svg TITLE'];

    public $stack = [];
    private $pop_handler = null;
    private $push_handler = null;

    public function set_pop_handler(Closure $handler): void
    {
        $this->pop_handler = $handler;
    }

    public function set_push_handler(Closure $handler): void
    {
        $this->push_handler = $handler;
    }

    public function at(int $nth): ?WP_HTML_Token
    {
        return $this->stack[$nth - 1] ?? null;
    }

    public function contains(string $node_name): bool
    {
        return in_array($node_name, array_map(static fn ($token) => $token->node_name, $this->stack), true);
    }

    public function contains_node(WP_HTML_Token $token): bool
    {
        return in_array($token, $this->stack, true);
    }

    public function count(): int
    {
        return count($this->stack);
    }

    public function current_node(): ?WP_HTML_Token
    {
        return $this->stack === [] ? null : end($this->stack);
    }

    public function current_node_is(string $identity): bool
    {
        $current = $this->current_node();
        return $current !== null && ($current->node_name === $identity || ($current->namespace . ' ' . $current->node_name) === $identity);
    }

    public function has_element_in_specific_scope(string $tag_name, $termination_list): bool
    {
        foreach ($this->walk_up() as $node) {
            $name = $node->namespace === 'html' ? $node->node_name : $node->namespace . ' ' . $node->node_name;
            if ($name === $tag_name) {
                return true;
            }
            if (in_array($name, (array) $termination_list, true)) {
                return false;
            }
        }
        return false;
    }

    public function has_element_in_scope(string $tag_name): bool
    {
        return $this->has_element_in_specific_scope($tag_name, self::SCOPE);
    }

    public function has_element_in_list_item_scope(string $tag_name): bool
    {
        return $this->has_element_in_specific_scope($tag_name, [...self::SCOPE, 'OL', 'UL']);
    }

    public function has_element_in_button_scope(string $tag_name): bool
    {
        return $this->has_element_in_specific_scope($tag_name, [...self::SCOPE, 'BUTTON']);
    }

    public function has_element_in_table_scope(string $tag_name): bool
    {
        return $this->has_element_in_specific_scope($tag_name, ['HTML', 'TABLE', 'TEMPLATE']);
    }

    public function has_element_in_select_scope(string $tag_name): bool
    {
        foreach ($this->walk_up() as $node) {
            if ($node->node_name === $tag_name) {
                return true;
            }
            if ($node->node_name !== 'OPTGROUP' && $node->node_name !== 'OPTION') {
                return false;
            }
        }
        return false;
    }

    public function has_p_in_button_scope(): bool
    {
        return $this->has_element_in_button_scope('P');
    }

    public function pop(): bool
    {
        $item = array_pop($this->stack);
        if ($item === null) {
            return false;
        }
        $this->after_element_pop($item);
        return true;
    }

    public function pop_until(string $html_tag_name): bool
    {
        foreach ($this->walk_up() as $item) {
            $this->pop();
            if ($item->node_name === $html_tag_name) {
                return true;
            }
        }
        return false;
    }

    public function push(WP_HTML_Token $stack_item): void
    {
        $this->stack[] = $stack_item;
        $this->after_element_push($stack_item);
    }

    public function remove_node(WP_HTML_Token $token): bool
    {
        $at = array_search($token, $this->stack, true);
        if ($at === false) {
            return false;
        }
        array_splice($this->stack, (int) $at, 1);
        $this->after_element_pop($token);
        return true;
    }

    public function walk_down()
    {
        foreach ($this->stack as $item) {
            yield $item;
        }
    }

    public function walk_up(?WP_HTML_Token $above_this_node = null)
    {
        $skipping = $above_this_node !== null;
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            if ($skipping) {
                $skipping = $this->stack[$i] !== $above_this_node;
                continue;
            }
            yield $this->stack[$i];
        }
    }

    public function after_element_push(WP_HTML_Token $item): void
    {
        if ($this->push_handler !== null) {
            ($this->push_handler)($item);
        }
    }

    public function after_element_pop(WP_HTML_Token $item): void
    {
        if ($this->pop_handler !== null) {
            ($this->pop_handler)($item);
        }
    }

    public function clear_to_table_context(): void
    {
        $this->minnClearTo(['TABLE', 'TEMPLATE', 'HTML']);
    }

    public function clear_to_table_body_context(): void
    {
        $this->minnClearTo(['TBODY', 'TFOOT', 'THEAD', 'TEMPLATE', 'HTML']);
    }

    public function clear_to_table_row_context(): void
    {
        $this->minnClearTo(['TR', 'TEMPLATE', 'HTML']);
    }

    public function __wakeup()
    {
        throw new \LogicException(__CLASS__ . ' should never be unserialized');
    }

    private function minnClearTo(array $names): void
    {
        while (($current = $this->current_node()) !== null && !in_array($current->node_name, $names, true)) {
            $this->pop();
        }
    }
}

class WP_HTML_Active_Formatting_Elements
{
    private $stack = [];

    public function contains_node(WP_HTML_Token $token): bool
    {
        return in_array($token, $this->stack, true);
    }

    public function count(): int
    {
        return count($this->stack);
    }

    public function current_node(): ?WP_HTML_Token
    {
        return $this->stack === [] ? null : end($this->stack);
    }

    public function insert_marker(): void
    {
        $this->push(new WP_HTML_Token(null, 'marker', false));
    }

    public function push(WP_HTML_Token $token): void
    {
        $this->stack[] = $token;
    }

    public function remove_node(WP_HTML_Token $token): bool
    {
        $at = array_search($token, $this->stack, true);
        if ($at === false) {
            return false;
        }
        array_splice($this->stack, (int) $at, 1);
        return true;
    }

    public function walk_down()
    {
        foreach ($this->stack as $item) {
            yield $item;
        }
    }

    public function walk_up()
    {
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            yield $this->stack[$i];
        }
    }

    public function clear_up_to_last_marker(): void
    {
        while (($token = array_pop($this->stack)) !== null && $token->node_name !== 'marker') {
        }
    }
}
