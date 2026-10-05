<?php

declare(strict_types=1);

namespace Minn\Html\Tree;

use Minn\Html\Decoder;
use Minn\Html\Scanner;
use Minn\Html\Tags;

/**
 * The HTML tree construction algorithm over the engine's tokenizer, as the
 * reference's HTML processor runs it: tokens go through the insertion
 * modes (HeadRules, BodyRules, TableRules) or the foreign content rules,
 * and every node opened or closed becomes an Event with its breadcrumbs.
 * Text is read one kind at a time (NUL bytes, white space, the rest). At
 * the end of the input every open element closes; nothing implied is
 * added. Where the reference does not build a tree (Unsupported), the walk
 * stops with that error.
 */
final class Builder
{
    public const FRAGMENT = 'fragment';
    public const DOCUMENT = 'document';

    private OpenElements $stack;
    private Formatting $formatting;
    private string $mode = 'initial';
    /** @var list<string> */
    private array $templateModes = [];
    private ?Node $headPointer = null;
    private ?Node $formPointer = null;
    private bool $framesetOk = true;
    private string $compat = 'no-quirks';
    /** @var list<Event> */
    private array $queue = [];
    /** @var list<Token> */
    private array $pending = [];
    private ?Token $token = null;
    private bool $done = false;
    private bool $skipNext = false;
    private bool $skipping = false;
    private ?Unsupported $error = null;
    private Node $context;

    public function __construct(private readonly Tags $tags, private readonly string $kind)
    {
        $this->context = Node::element('BODY', 'html', null);
        $this->reset();
    }

    /** Back to the state before the first token. */
    public function reset(): void
    {
        $this->stack = new OpenElements();
        $this->formatting = new Formatting();
        $this->templateModes = [];
        $this->headPointer = null;
        $this->formPointer = null;
        $this->framesetOk = true;
        $this->compat = 'no-quirks';
        $this->queue = [];
        $this->pending = [];
        $this->token = null;
        $this->done = false;
        $this->skipNext = false;
        $this->error = null;
        $this->mode = 'initial';
        if ($this->kind === self::FRAGMENT) {
            $this->stack->push(Node::element('HTML', 'html', null));
            $this->mode = 'in body';
        }
    }

    /** The next opened or closed node, or null at the end, at an incomplete token, or once unsupported markup stopped the walk. */
    public function next(): ?Event
    {
        while ($this->queue === [] && !$this->done) {
            $this->step();
        }
        return array_shift($this->queue);
    }

    /** Stops the walk for good (a failed seek leaves the processor like this). */
    public function halt(): void
    {
        $this->done = true;
        $this->queue = [];
    }

    /** Why the walk stopped early, if it did. */
    public function error(): ?Unsupported
    {
        return $this->error;
    }

    /**
     * The breadcrumbs of the open elements now.
     *
     * @return list<string>
     */
    public function breadcrumbs(): array
    {
        $names = array_map(static fn (Node $node): string => $node->name, $this->stack->all());
        return $this->kind === self::FRAGMENT ? ['HTML', 'BODY', ...array_slice($names, 1)] : $names;
    }

    /** The token being processed. */
    public function token(): Token
    {
        return $this->token ?? new Token('#eof', '#eof', 0, 0);
    }

    /** The stack of open elements. */
    public function stack(): OpenElements
    {
        return $this->stack;
    }

    /** The list of active formatting elements. */
    public function formatting(): Formatting
    {
        return $this->formatting;
    }

    /** Whether this builder parses a fragment (in a BODY context) rather than a document. */
    public function isFragment(): bool
    {
        return $this->kind === self::FRAGMENT;
    }

    /** The insertion mode. */
    public function mode(): string
    {
        return $this->mode;
    }

    /** Switches the insertion mode. */
    public function switchTo(string $mode): void
    {
        $this->mode = $mode;
    }

    /** Switches the insertion mode and processes the current token again under it. */
    public function reprocessIn(string $mode): void
    {
        $this->mode = $mode;
        $this->process($this->token());
    }

    /** Processes a token under the current insertion mode, or the foreign content rules when they apply. */
    public function process(Token $token): void
    {
        $this->token = $token;
        if (!$this->usesHtmlRules($token)) {
            ForeignRules::process($this, $token);
            return;
        }
        $this->processIn($this->mode, $token);
    }

    /** Processes a token using the rules of an insertion mode other than the current one. */
    public function processIn(string $mode, Token $token): void
    {
        $this->token = $token;
        match ($mode) {
            'in body' => BodyRules::process($this, $token),
            'in table', 'in caption', 'in column group', 'in table body', 'in row', 'in cell' => TableRules::process($this, $mode, $token),
            default => HeadRules::process($this, $mode, $token),
        };
    }

    /** Whether the frameset-ok flag is still set. */
    public function framesetOk(): bool
    {
        return $this->framesetOk;
    }

    /** Clears the frameset-ok flag. */
    public function framesetNotOk(): void
    {
        $this->framesetOk = false;
    }

    /** The document's compatibility mode ("no-quirks", "limited-quirks", "quirks"). */
    public function compat(): string
    {
        return $this->compat;
    }

    /** Sets the compatibility mode the doctype indicated. */
    public function setCompat(string $compat): void
    {
        $this->compat = $compat;
    }

    /** The head element pointer. */
    public function headPointer(): ?Node
    {
        return $this->headPointer;
    }

    /** The form element pointer. */
    public function formPointer(): ?Node
    {
        return $this->formPointer;
    }

    /** Sets (or clears) the form element pointer. */
    public function setFormPointer(?Node $node): void
    {
        $this->formPointer = $node;
    }

    /**
     * The stack of template insertion modes.
     *
     * @return list<string>
     */
    public function templateModes(): array
    {
        return $this->templateModes;
    }

    /** Pushes a template insertion mode. */
    public function pushTemplateMode(string $mode): void
    {
        $this->templateModes[] = $mode;
    }

    /** Pops the current template insertion mode. */
    public function popTemplateMode(): void
    {
        array_pop($this->templateModes);
    }

    /** The adjusted current node: the context element when only the fragment's root is open. */
    public function adjustedCurrent(): ?Node
    {
        if ($this->kind === self::FRAGMENT && $this->stack->count() === 1) {
            return $this->context;
        }
        return $this->stack->current();
    }

    /** Inserts an element for the current start tag, real, in the namespace. */
    public function insert(string $namespace = 'html'): Node
    {
        $token = $this->token();
        $node = Node::element($token->name, $namespace, $token->offset, $this->keptAttributes($token->name, $namespace));
        if ($token->selfClosing() && $namespace !== 'html') {
            $node = $node->selfClosed();
        }
        if ($namespace === 'html' && $token->isStart('HEAD')) {
            $this->headPointer = $node;
        }
        $this->pushNode($node, $token->offset);
        return $node;
    }

    /** Inserts an element the markup implied. */
    public function insertImplied(string $name): Node
    {
        $node = Node::element($name, 'html', null);
        if ($name === 'HEAD') {
            $this->headPointer = $node;
        }
        $this->pushNode($node, null);
        return $node;
    }

    /** Inserts and at once closes an element for the current start tag: a void element, one read whole, or self-closed foreign content. */
    public function insertLeafElement(string $namespace = 'html'): Node
    {
        $node = $this->insert($namespace);
        $this->stack->pop();
        if ($node->expectsCloser()) {
            $this->event('pop', $node, null);
        }
        return $node;
    }

    /** Inserts a leaf (text, comment, CDATA, processing instruction) for the current token, in the namespace. */
    public function insertLeaf(string $namespace = 'html'): void
    {
        $token = $this->token();
        $text = $token->type === '#text' ? $this->textValue($token, $namespace) : $token->text;
        $this->event('push', Node::leaf($token->type, $token->name, $namespace, $token->offset, $text), $token->offset);
    }

    /** Inserts a leaf at the top of the document (a doctype or a comment before or around the html element). */
    public function insertDocumentLeaf(): void
    {
        $token = $this->token();
        $node = Node::leaf($token->type, $token->name, 'html', $token->offset, $token->text);
        $this->queue[] = new Event('push', $node, [$node->name], $token->offset);
    }

    /** Closes the current node as implied. */
    public function pop(): ?Node
    {
        $node = $this->stack->pop();
        if ($node !== null) {
            $this->event('pop', $node, null);
        }
        return $node;
    }

    /** Pops until an HTML element of one of the names closes; when the current end tag names it, that last close is real. */
    public function popUntil(string ...$names): void
    {
        while (($node = $this->stack->current()) !== null) {
            $this->stack->pop();
            $closing = $node->isHtml(...$names);
            $this->event('pop', $node, $closing && $this->token()->isEnd($node->name) ? $this->token()->offset : null);
            if ($closing) {
                return;
            }
        }
    }

    /** Pops until this very node closes. */
    public function popUntilNode(Node $target): void
    {
        while (($node = $this->stack->current()) !== null) {
            $this->stack->pop();
            $this->event('pop', $node, $node === $target && $this->token()->isEnd($node->name) ? $this->token()->offset : null);
            if ($node === $target) {
                return;
            }
        }
    }

    /** Generates implied end tags, leaving an element of the name open. */
    public function generateImpliedEndTags(string $except = ''): void
    {
        $implied = ['DD', 'DT', 'LI', 'OPTGROUP', 'OPTION', 'P', 'RB', 'RP', 'RT', 'RTC'];
        while (($node = $this->stack->current()) !== null && $node->isHtml(...$implied) && $node->name !== $except) {
            $this->pop();
        }
    }

    /** Generates all implied end tags thoroughly (table parts as well). */
    public function generateAllImpliedEndTags(): void
    {
        $implied = ['CAPTION', 'COLGROUP', 'DD', 'DT', 'LI', 'OPTGROUP', 'OPTION', 'P', 'RB', 'RP', 'RT', 'RTC', 'TBODY', 'TD', 'TFOOT', 'TH', 'THEAD', 'TR'];
        while (($node = $this->stack->current()) !== null && $node->isHtml(...$implied)) {
            $this->pop();
        }
    }

    /** Closes a P element: implied end tags but P's, then pops through the P. */
    public function closeP(): void
    {
        $this->generateImpliedEndTags('P');
        $this->popUntil('P');
    }

    /** Closes a P element when one is in button scope. */
    public function closePInButtonScope(): void
    {
        if ($this->stack->inButtonScope('P')) {
            $this->closeP();
        }
    }

    /** Reconstructs the active formatting elements; the reference stops when any would have to be reopened. */
    public function reconstructFormatting(): void
    {
        if ($this->formatting->needsReconstruction($this->stack)) {
            $this->unsupported('Cannot reconstruct active formatting elements when advancing and rewinding is required.');
        }
    }

    /** Asks the parser to drop one leading line feed from the next token (after PRE, LISTING). */
    public function skipNextNewline(): void
    {
        $this->skipNext = true;
    }

    /** Resets the insertion mode from the stack of open elements. */
    public function resetInsertionMode(): void
    {
        $nodes = $this->stack->all();
        for ($i = count($nodes) - 1; $i >= 0; $i--) {
            $last = $i === 0;
            $node = $last && $this->kind === self::FRAGMENT ? $this->context : $nodes[$i];
            $mode = match (true) {
                $node->isHtml('TD', 'TH') && !$last => 'in cell',
                $node->isHtml('TR') => 'in row',
                $node->isHtml('TBODY', 'THEAD', 'TFOOT') => 'in table body',
                $node->isHtml('CAPTION') => 'in caption',
                $node->isHtml('COLGROUP') => 'in column group',
                $node->isHtml('TABLE') => 'in table',
                $node->isHtml('TEMPLATE') => end($this->templateModes) ?: 'in template',
                $node->isHtml('HEAD') && !$last => 'in head',
                $node->isHtml('BODY') => 'in body',
                $node->isHtml('FRAMESET') => 'in frameset',
                $node->isHtml('HTML') => $this->headPointer === null ? 'before head' : 'after head',
                $last => 'in body',
                default => null,
            };
            if ($mode !== null) {
                $this->mode = $mode;
                return;
            }
        }
    }

    /**
     * Stops: the reference's processor does not build a tree for this markup.
     *
     * @return never
     */
    public function unsupported(string $message): void
    {
        $token = $this->token();
        [$start, $end] = $this->tags->tokenSpan();
        throw new Unsupported(
            $message,
            $token,
            $token->type === '#eof' ? '' : $this->tags->source($start, $end - $start),
            array_map(static fn (Node $node): string => $node->name, $this->stack->all()),
            $this->formatting->names(),
        );
    }

    private function step(): void
    {
        if ($this->pending === []) {
            $this->pending = $this->read();
        }
        $token = array_shift($this->pending);
        if ($token === null) {
            $this->done = true;
            return;
        }
        $this->skipping = $this->skipNext;
        $this->skipNext = false;
        $queued = count($this->queue);
        try {
            $this->process($token);
            if ($token->type === '#eof') {
                $this->finish();
            }
        } catch (Unsupported $e) {
            array_splice($this->queue, $queued);
            $this->error = $e;
            $this->done = true;
        }
    }

    /** @return list<Token> the next token from the tokenizer (text in runs), an end-of-input token, or nothing when paused */
    private function read(): array
    {
        $current = $this->adjustedCurrent();
        $foreign = $current !== null && $current->namespace !== 'html';
        $this->tags->parseAs($foreign ? $current->namespace : 'html', $foreign && !$current->isHtmlIntegrationPoint() && !$current->isMathTextIntegrationPoint() ? 'foreign' : 'html');
        if (!$this->tags->nextToken()) {
            return $this->tags->pausedAtIncompleteToken() || $this->token?->type === '#eof' ? [] : [new Token('#eof', '#eof', 0, strlen($this->tags->html()))];
        }
        [$start] = $this->tags->tokenSpan();
        $type = (string) $this->tags->tokenType();
        return match ($type) {
            Tags::TAG => [new Token('#tag', (string) $this->tags->tag(), ($this->tags->isCloser() ? Token::CLOSER : 0) | ($this->tags->selfClosing() ? Token::SELF_CLOSING : 0), $start)],
            Tags::TEXT => $this->textRuns(),
            Tags::DOCTYPE => [new Token('#doctype', 'html', 0, $start, $this->tags->modifiableText())],
            default => [new Token($type, $type, 0, $start, $this->tags->modifiableText())],
        };
    }

    /** @return list<Token> */
    private function textRuns(): array
    {
        [$start, $length] = $this->tags->textSpan();
        $raw = $this->tags->source($start, $length);
        $runs = [];
        for ($at = 0; $at < $length; $at += $size) {
            $nul = strspn($raw, "\0", $at);
            $space = $nul > 0 ? 0 : strspn($raw, " \t\n\f\r", $at);
            [$kind, $size] = match (true) {
                $nul > 0 => ['null', $nul],
                $space > 0 => ['whitespace', $space],
                default => ['generic', $length - $at],
            };
            $runs[] = new Token('#text', '#text', 0, $start + $at, substr($raw, $at, $size), $kind);
        }
        return $runs;
    }

    private function textValue(Token $token, string $namespace): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $token->kind === 'generic' ? Decoder::text($token->text) : $token->text);
        $text = $namespace === 'html' ? str_replace("\0", '', $text) : str_replace("\0", "\u{FFFD}", $text);
        return $this->skipping && str_starts_with($text, "\n") ? substr($text, 1) : $text;
    }

    private function usesHtmlRules(Token $token): bool
    {
        $node = $this->adjustedCurrent();
        if ($node === null || $node->namespace === 'html' || $token->type === '#eof') {
            return true;
        }
        $start = $token->isStart();
        if ($node->isMathTextIntegrationPoint() && (($start && !in_array($token->name, ['MGLYPH', 'MALIGNMARK'], true)) || $token->type === '#text')) {
            return true;
        }
        if ($node->isIn('math', 'ANNOTATION-XML') && $token->isStart('SVG')) {
            return true;
        }
        return $node->isHtmlIntegrationPoint() && ($start || $token->type === '#text');
    }

    /** Closes everything still open at the end of the input; nothing implied is added. */
    private function finish(): void
    {
        while ($this->stack->count() > ($this->kind === self::FRAGMENT ? 1 : 0)) {
            $this->pop();
        }
        $this->done = true;
    }

    private function pushNode(Node $node, ?int $offset): void
    {
        $this->stack->push($node);
        $this->queue[] = new Event('push', $node, $this->breadcrumbs(), $offset);
    }

    private function event(string $op, Node $node, ?int $offset): void
    {
        $crumbs = $this->breadcrumbs();
        if ($op === 'push') {
            $crumbs[] = $node->name;
        }
        $this->queue[] = new Event($op, $node, $crumbs, $offset);
    }

    /**
     * The attributes an element keeps for later comparison: all of a formatting element's (Noah's Ark), and encoding on MathML annotation-xml.
     *
     * @return array<string, string|true>
     */
    private function keptAttributes(string $name, string $namespace): array
    {
        $names = match (true) {
            $namespace === 'html' && in_array($name, BodyRules::FORMATTING, true) => (array) $this->tags->attributeNames(''),
            $namespace === 'math' && $name === 'ANNOTATION-XML' => ['encoding'],
            default => [],
        };
        $kept = [];
        foreach ($names as $attribute) {
            $value = $this->tags->attribute((string) $attribute);
            if ($value !== null) {
                $kept[(string) $attribute] = $value;
            }
        }
        return $kept;
    }

    /** The current tag's attribute, decoded; null when absent. */
    public function attribute(string $name): string|true|null
    {
        return $this->tags->attribute($name);
    }

    /** The doctype's name and identifiers, for the compatibility mode. @return array{name: ?string, public: ?string, system: ?string} */
    public function doctype(): array
    {
        return Scanner::doctype($this->token()->text);
    }
}
