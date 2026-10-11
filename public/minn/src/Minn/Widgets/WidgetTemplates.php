<?php

declare(strict_types=1);

namespace Minn\Widgets;

/**
 * The Underscore templates the Text and Custom HTML widgets print for their
 * controls (render_control_template_scripts), as the reference prints them
 * (probe widget-methods): a title field and the content area, the Text
 * widget's two hidden pointers, and, for a user who may not post unfiltered
 * HTML, the note on the tags the code editor refuses. The whitespace is the
 * reference's own and is part of the answer.
 */
final class WidgetTemplates
{
    private const CLOSE = "\t\t</script>\n\t\t";

    /** The note a user who may not post unfiltered HTML sees, when the code editor is off. */
    private const REFUSED_TAGS = "\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t<# if ( data.codeEditorDisabled ) { #>\n"
        . "\t\t\t\t\t\t<p>\n"
        . "\t\t\t\t\t\t\tSome HTML tags are not permitted, including:\t\t\t\t\t\t\t<code>script</code>, <code>iframe</code>, <code>form</code>, <code>input</code>, <code>style</code>\n"
        . "\t\t\t\t\t\t</p>\n"
        . "\t\t\t\t\t<# } #>\n"
        . "\t\t\t\t\t\t\t\n";

    /** The Text widget's template: title, the two pointers, the content area. */
    public static function text(): string
    {
        return self::open('text')
            . self::pointer('custom-html-widget-pointer', 'New Custom HTML Widget', "\t\t\t\t\t\t\t\t\t\t\t\t\t<p>Did you know there is a &#8220;Custom HTML&#8221; widget now? You can find it by scanning the list of available widgets on this screen. Check it out to add some custom code to your site!</p>\n\t\t\t\t\t\t\t\t\t\t\t\t")
            . self::pointer('paste-html-pointer', 'Did you just paste HTML?', "\t\t\t\t\t\t<p>Hey there, looks like you just pasted HTML into the &#8220;Visual&#8221; tab of the Text widget. You may want to paste your code into the &#8220;Code&#8221; tab instead. Alternately, try out the new &#8220;Custom HTML&#8221; widget!</p>\n\t\t\t\t\t\t")
            . "\t\t\t<p>\n"
            . "\t\t\t\t<label for=\"{{ elementIdPrefix }}text\" class=\"screen-reader-text\">Content:</label>\n"
            . "\t\t\t\t<textarea id=\"{{ elementIdPrefix }}text\" class=\"widefat text wp-editor-area\" style=\"height: 200px\" rows=\"16\" cols=\"20\"></textarea>\n"
            . "\t\t\t</p>\n"
            . self::CLOSE;
    }

    /** The Custom HTML widget's template for a user who may post unfiltered HTML. */
    public static function customHtml(): string
    {
        return self::customHtmlWith("\t\t\t\n");
    }

    /** The Custom HTML widget's template for a user who may not, with the note on refused tags. */
    public static function customHtmlRestricted(): string
    {
        return self::customHtmlWith(self::REFUSED_TAGS);
    }

    private static function customHtmlWith(string $note): string
    {
        return self::open('custom-html')
            . "\t\t\t<p>\n"
            . "\t\t\t\t<label for=\"{{ elementIdPrefix }}content\" id=\"{{ elementIdPrefix }}content-label\">Content:</label>\n"
            . "\t\t\t\t<textarea id=\"{{ elementIdPrefix }}content\" class=\"widefat code content\" rows=\"16\" cols=\"20\"></textarea>\n"
            . "\t\t\t</p>\n\n"
            . $note
            . "\t\t\t<div class=\"code-editor-error-container\"></div>\n"
            . self::CLOSE;
    }

    /** The script tag, the element id prefix both templates make, and the title field. */
    private static function open(string $widget): string
    {
        return "\t\t<script type=\"text/html\" id=\"tmpl-widget-{$widget}-control-fields\">\n"
            . "\t\t\t<# var elementIdPrefix = 'el' + String( Math.random() ).replace( /\\D/g, '' ) + '_' #>\n"
            . "\t\t\t<p>\n"
            . "\t\t\t\t<label for=\"{{ elementIdPrefix }}title\">Title:</label>\n"
            . "\t\t\t\t<input id=\"{{ elementIdPrefix }}title\" type=\"text\" class=\"widefat title\">\n"
            . "\t\t\t</p>\n\n";
    }

    /** A hidden pointer: its heading, its paragraph (with the reference's indents around it), a dismiss link. */
    private static function pointer(string $class, string $heading, string $paragraph): string
    {
        return "\t\t\t\t\t\t\t<div hidden class=\"wp-pointer {$class} wp-pointer-top\">\n"
            . "\t\t\t\t\t<div class=\"wp-pointer-content\">\n"
            . "\t\t\t\t\t\t<h3>{$heading}</h3>\n"
            . $paragraph . "<div class=\"wp-pointer-buttons\">\n"
            . "\t\t\t\t\t\t\t<a class=\"close\" href=\"#\">Dismiss</a>\n"
            . "\t\t\t\t\t\t</div>\n"
            . "\t\t\t\t\t</div>\n"
            . "\t\t\t\t\t<div class=\"wp-pointer-arrow\">\n"
            . "\t\t\t\t\t\t<div class=\"wp-pointer-arrow-inner\"></div>\n"
            . "\t\t\t\t\t</div>\n"
            . "\t\t\t\t</div>\n"
            . "\t\t\t\n";
    }
}
