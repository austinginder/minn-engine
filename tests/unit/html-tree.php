<?php

declare(strict_types=1);

use Minn\Html\Decoder;
use Minn\Html\Scanner;
use Minn\Html\Tags;
use Minn\Html\TokenMap;
use Minn\Html\Tree\Builder;
use Minn\Html\Tree\Compat;
use Minn\Html\Tree\Formatting;
use Minn\Html\Tree\Node;
use Minn\Html\Tree\OpenElements;
use Minn\Html\Tree\Serializer;

/** HTML tree construction's parts; whole walks are pinned against the reference by tests/html-api.test.php. */
$walk = static function (string $html, string $kind = Builder::FRAGMENT): string {
    $builder = new Builder(new Tags($html), $kind);
    $out = [];
    while (($event = $builder->next()) !== null) {
        $out[] = ($event->isCloser() ? '-' : '+') . $event->node->name . ($event->offset === null ? '*' : '');
    }
    $error = $builder->error();
    return implode(' ', $out) . ($error !== null ? ' !' . $error->getMessage() : '');
};

return [
    'implied end tags close as virtual tokens; written ones are real' => static fn () => $walk('<p>one<p>two</p>') === '+P +#text -P* +P +#text -P' && $walk('<p>one<p>two') === '+P +#text -P* +P +#text -P*',
    'a table gets its implied body and row' => static fn () => $walk('<table><td>x</table>') === '+TABLE +TBODY* +TR* +TD +#text -TD* -TR* -TBODY* -TABLE',
    'end of input closes what is open and adds nothing' => static fn () => $walk('<!DOCTYPE html>', Builder::DOCUMENT) === '+html' && $walk('x', Builder::DOCUMENT) === '+HTML* +HEAD* -HEAD* +BODY* +#text -BODY* -HTML*',
    'the reference stops where it builds no tree' => static fn () => str_contains($walk('<table>x</table>'), '!Foster parenting is not supported.') && str_contains($walk('<b><p>x</b>y'), '!Cannot extract common ancestor') && str_contains($walk('</b>'), '"any other end tag"') && str_contains($walk('<plaintext>'), '!Cannot process PLAINTEXT'),
    'reopening a closed formatting element stops the walk' => static fn () => str_contains($walk('<b>1<i>2</b>3'), '!Cannot reconstruct active formatting elements'),
    'foreign content: SVG keeps its own rules until an HTML element breaks out' => static fn () => $walk('<svg><title>t</title><p>x</svg>') === '+SVG +TITLE +#text -TITLE -SVG* +P +#text -P*',
    'select is handled in body: an input closes it, a div may sit in it' => static fn () => $walk('<select><div>x</div></select>') === '+SELECT +DIV +#text -DIV -SELECT' && $walk('<select><input>') === '+SELECT -SELECT* +INPUT',
    'text arrives one kind at a time: NUL bytes dropped, white space split from the rest' => static fn () => $walk("\0 a") === '+#text +#text' && $walk("\0") === '',
    'scope stops at the scope boundaries' => static function (): bool {
        $stack = new OpenElements();
        foreach (['HTML', 'P', 'TABLE'] as $name) {
            $stack->push(Node::element($name, 'html', null));
        }
        $button = new OpenElements();
        foreach (['P', 'BUTTON'] as $name) {
            $button->push(Node::element($name, 'html', null));
        }
        return !$stack->inScope('P') && $stack->inTableScope('TABLE') && $stack->hasHtml('P') && $button->inScope('P') && !$button->inButtonScope('P');
    },
    "three identical formatting elements at most (Noah's Ark)" => static function (): bool {
        $list = new Formatting();
        $nodes = array_map(static fn () => Node::element('B', 'html', 1, ['class' => 'x']), range(1, 4));
        foreach ($nodes as $node) {
            $list->push($node);
        }
        return !$list->contains($nodes[0]) && $list->contains($nodes[3]) && count($list->names()) === 3;
    },
    'doctypes indicate their compatibility mode' => static fn () => Compat::of(['name' => 'html', 'public' => null, 'system' => null]) === 'no-quirks'
        && Compat::of(['name' => 'html', 'public' => '-//W3C//DTD HTML 4.01 Transitional//EN', 'system' => null]) === 'quirks'
        && Compat::of(['name' => 'html', 'public' => '-//W3C//DTD HTML 4.01 Transitional//EN', 'system' => 'x']) === 'limited-quirks'
        && Compat::read('<!DOCTYPE html bogus>')['mode'] === 'quirks' && Compat::read('<!DOCTYPE html><p>') === null,
    'SVG names keep their case when written out' => static fn () => Serializer::qualifiedName(Node::element('FOREIGNOBJECT', 'svg', null)) === 'foreignObject' && Serializer::attributeName(Node::element('SVG', 'svg', null), 'viewbox') === 'viewBox' && Serializer::qualifiedName(Node::element('DIV', 'html', null)) === 'div',
    'only the legacy names decode without a semicolon' => static fn () => Decoder::text('&level &notit; &copy') === '&level ¬it; ©' && Decoder::reference('data', '&notin;x', 0) === ['∉', 7] && Decoder::reference('attribute', '&not=x', 0) === null,
    'processing instructions: an alphanumeric target, not xml' => static fn () => Scanner::question('<?pi x?>', 0)['kind'] === 'pi' && Scanner::question('<?xml v?>', 0)['commentType'] === Tags::COMMENT_PI && Scanner::question('<?1a?>', 0)['commentType'] === Tags::COMMENT_INVALID,
    'a token map reads the longest word' => static function (): bool {
        $map = new TokenMap(['apple' => 'A', 'app' => 'a', 'b' => 'b'], 2);
        return $map->read('applesauce', 0, 'case-sensitive') === ['A', 5] && $map->contains('APP', 'ascii-case-insensitive') && !$map->contains('APP', 'case-sensitive') && array_keys($map->toArray()) === ['b', 'apple', 'app'];
    },
];
