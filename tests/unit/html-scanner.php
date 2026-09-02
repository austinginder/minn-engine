<?php

declare(strict_types=1);

use Minn\Html\Scanner;
use Minn\Html\Tags;

return [
    'a tag: name, attributes with offsets, no self-closing flag' => static function () {
        $tag = Scanner::tag('<div class="a b" data-x>', 0, 1);
        return $tag !== null && $tag['name'] === 'div' && $tag['nameEnd'] === 4 && $tag['end'] === 24 && !$tag['selfClosing']
            && count($tag['attributes']) === 2 && $tag['attributes'][0]['lower'] === 'class' && $tag['attributes'][0]['value'] === 'a b' && $tag['attributes'][0]['quoted']
            && $tag['attributes'][1]['name'] === 'data-x' && $tag['attributes'][1]['value'] === null;
    },
    'a closer reads its name from two bytes in' => static fn () => Scanner::tag('</P >', 0, 2)['name'] === 'p',
    'the self-closing flag is the slash right before the bracket' => static fn () => Scanner::tag('<br/>', 0, 1)['selfClosing'] && !Scanner::tag('<a href=x/>', 0, 1)['selfClosing'],
    'input ending inside a tag is null' => static fn () => Scanner::tag('<div class="unterminated', 0, 1) === null,
    'a raw-text body runs to its closer' => static fn () => Scanner::rawText('<script>x<y</script><p>', 'script', 8) === ['textStart' => 8, 'textLength' => 3, 'end' => 20]
        && Scanner::rawText('<script>never closed', 'script', 8) === null,
    'markup declarations: comment kinds and the doctype' => static function () {
        $html = Scanner::markupDeclaration('<!-- hi -->', 0);
        $abrupt = Scanner::markupDeclaration('<!-->', 0);
        $cdata = Scanner::markupDeclaration('<![CDATA[x]]>', 0);
        $bogus = Scanner::markupDeclaration('<!whatever>', 0);
        $doctype = Scanner::markupDeclaration('<!DOCTYPE html>', 0);
        return $html['commentType'] === Tags::COMMENT_HTML && $html['textLength'] === 4
            && $abrupt['commentType'] === Tags::COMMENT_ABRUPT && $cdata['commentType'] === Tags::COMMENT_CDATA
            && $bogus['commentType'] === Tags::COMMENT_INVALID && $doctype['kind'] === 'doctype' && $doctype['end'] === 15
            && Scanner::markupDeclaration('<!-- open', 0) === null;
    },
    'a PHP tag is a processing instruction, another <? run a comment lookalike' => static fn () => Scanner::question('<?php echo 1; ?>', 0)['kind'] === 'pi'
        && Scanner::question('<?xml version="1"?>', 0)['commentType'] === Tags::COMMENT_PI,
    'a doctype splits into name, public, and system ids' => static fn () => Scanner::doctype('html') === ['name' => 'html', 'public' => null, 'system' => null]
        && Scanner::doctype('html PUBLIC "-//W3C//DTD" "http://x"')['system'] === 'http://x',
];
