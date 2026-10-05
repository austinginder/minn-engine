<?php

declare(strict_types=1);

namespace Minn\Html\Tree;

/**
 * Normalized HTML for the processor's walk, token by token: tag names
 * lower-cased (SVG's mixed-case names and attributes restored), the first
 * of repeated attributes kept and double-quoted, text and attribute values
 * escaped (& < > " '), RCDATA escaped and raw text left alone, a line
 * break after the PRE, LISTING and TEXTAREA openers, self-closed foreign
 * elements written " />", funky comments and doctypes in a body dropped.
 */
final class Serializer
{
    private const SVG_TAGS = ['altglyph' => 'altGlyph', 'altglyphdef' => 'altGlyphDef', 'altglyphitem' => 'altGlyphItem', 'animatecolor' => 'animateColor', 'animatemotion' => 'animateMotion', 'animatetransform' => 'animateTransform', 'clippath' => 'clipPath', 'feblend' => 'feBlend', 'fecolormatrix' => 'feColorMatrix', 'fecomponenttransfer' => 'feComponentTransfer', 'fecomposite' => 'feComposite', 'feconvolvematrix' => 'feConvolveMatrix', 'fediffuselighting' => 'feDiffuseLighting', 'fedisplacementmap' => 'feDisplacementMap', 'fedistantlight' => 'feDistantLight', 'fedropshadow' => 'feDropShadow', 'feflood' => 'feFlood', 'fefunca' => 'feFuncA', 'fefuncb' => 'feFuncB', 'fefuncg' => 'feFuncG', 'fefuncr' => 'feFuncR', 'fegaussianblur' => 'feGaussianBlur', 'feimage' => 'feImage', 'femerge' => 'feMerge', 'femergenode' => 'feMergeNode', 'femorphology' => 'feMorphology', 'feoffset' => 'feOffset', 'fepointlight' => 'fePointLight', 'fespecularlighting' => 'feSpecularLighting', 'fespotlight' => 'feSpotLight', 'fetile' => 'feTile', 'feturbulence' => 'feTurbulence', 'foreignobject' => 'foreignObject', 'glyphref' => 'glyphRef', 'lineargradient' => 'linearGradient', 'radialgradient' => 'radialGradient', 'textpath' => 'textPath'];
    private const SVG_ATTRIBUTES = ['attributename' => 'attributeName', 'attributetype' => 'attributeType', 'basefrequency' => 'baseFrequency', 'baseprofile' => 'baseProfile', 'calcmode' => 'calcMode', 'clippathunits' => 'clipPathUnits', 'diffuseconstant' => 'diffuseConstant', 'edgemode' => 'edgeMode', 'filterunits' => 'filterUnits', 'glyphref' => 'glyphRef', 'gradienttransform' => 'gradientTransform', 'gradientunits' => 'gradientUnits', 'kernelmatrix' => 'kernelMatrix', 'kernelunitlength' => 'kernelUnitLength', 'keypoints' => 'keyPoints', 'keysplines' => 'keySplines', 'keytimes' => 'keyTimes', 'lengthadjust' => 'lengthAdjust', 'limitingconeangle' => 'limitingConeAngle', 'markerheight' => 'markerHeight', 'markerunits' => 'markerUnits', 'markerwidth' => 'markerWidth', 'maskcontentunits' => 'maskContentUnits', 'maskunits' => 'maskUnits', 'numoctaves' => 'numOctaves', 'pathlength' => 'pathLength', 'patterncontentunits' => 'patternContentUnits', 'patterntransform' => 'patternTransform', 'patternunits' => 'patternUnits', 'pointsatx' => 'pointsAtX', 'pointsaty' => 'pointsAtY', 'pointsatz' => 'pointsAtZ', 'preservealpha' => 'preserveAlpha', 'preserveaspectratio' => 'preserveAspectRatio', 'primitiveunits' => 'primitiveUnits', 'refx' => 'refX', 'refy' => 'refY', 'repeatcount' => 'repeatCount', 'repeatdur' => 'repeatDur', 'requiredextensions' => 'requiredExtensions', 'requiredfeatures' => 'requiredFeatures', 'specularconstant' => 'specularConstant', 'specularexponent' => 'specularExponent', 'spreadmethod' => 'spreadMethod', 'startoffset' => 'startOffset', 'stddeviation' => 'stdDeviation', 'stitchtiles' => 'stitchTiles', 'surfacescale' => 'surfaceScale', 'systemlanguage' => 'systemLanguage', 'tablevalues' => 'tableValues', 'targetx' => 'targetX', 'targety' => 'targetY', 'textlength' => 'textLength', 'viewbox' => 'viewBox', 'viewtarget' => 'viewTarget', 'xchannelselector' => 'xChannelSelector', 'ychannelselector' => 'yChannelSelector', 'zoomandpan' => 'zoomAndPan'];
    private const RAW = ['SCRIPT', 'STYLE', 'XMP', 'IFRAME', 'NOEMBED', 'NOFRAMES'];

    /** An element's name as written: lower-case, SVG's mixed-case names restored. */
    public static function qualifiedName(Node $node): string
    {
        $lower = strtolower($node->name);
        return $node->namespace === 'svg' ? (self::SVG_TAGS[$lower] ?? $lower) : $lower;
    }

    /** An attribute's name as written on an element of the node's namespace. */
    public static function attributeName(Node $node, string $name): string
    {
        $lower = strtolower($name);
        return match ($node->namespace) {
            'svg' => self::SVG_ATTRIBUTES[$lower] ?? $lower,
            'math' => $lower === 'definitionurl' ? 'definitionURL' : $lower,
            default => $lower,
        };
    }

    /**
     * An opening tag; an element read whole (SCRIPT, TEXTAREA, ...) carries its text and closer.
     *
     * @param list<array{0: string, 1: string|true}> $attributes in document order, repeats already dropped
     */
    public static function open(Node $node, array $attributes, string $text): string
    {
        $out = '<' . self::qualifiedName($node);
        foreach ($attributes as [$name, $value]) {
            $out .= ' ' . self::attributeName($node, $name) . ($value === true ? '' : '="' . self::escape($value) . '"');
        }
        if ($node->namespace !== 'html') {
            return $out . ($node->isSelfClosing() ? ' />' : '>');
        }
        $out .= '>';
        if ($node->isHtml('PRE', 'LISTING')) {
            return $out . "\n";
        }
        if ($node->isHtml(...Node::ATOMIC)) {
            $body = $node->isHtml(...self::RAW) ? $text : self::escape($text);
            return $out . ($node->isHtml('TEXTAREA') ? "\n" : '') . $body . '</' . self::qualifiedName($node) . '>';
        }
        return $out;
    }

    /** A closing tag. */
    public static function close(Node $node): string
    {
        return '</' . self::qualifiedName($node) . '>';
    }

    /** A leaf: text escaped, a comment from its whole text, a CDATA section, a processing instruction; nothing for the rest. */
    public static function leaf(Node $node, string $fullText, string $target): string
    {
        return match ($node->type) {
            '#text' => self::escape($node->text()),
            '#comment' => '<!--' . $fullText . '-->',
            '#cdata-section' => '<![CDATA[' . $node->text() . ']]>',
            '#processing-instruction' => '<?' . $target . ' ' . $node->text() . '?>',
            default => '',
        };
    }

    private static function escape(string $text): string
    {
        return str_replace(['&', '<', '>', '"', "'"], ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'], $text);
    }
}
