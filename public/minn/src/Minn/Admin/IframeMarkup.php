<?php

declare(strict_types=1);

namespace Minn\Admin;

/**
 * The fixed markup of the two bare admin documents plugins print into, as
 * iframe_header and wp_iframe print it on the reference (probe
 * admin-doubts): the globals the admin scripts read (ajaxurl, pagenow and
 * the rest), the body's opening with the no-js switch, and the onload call
 * at the end. What hooks print goes between these pieces.
 */
final class IframeMarkup
{
    private const LOAD_EVENT = "addLoadEvent = function(func){if(typeof jQuery!=='undefined')jQuery(function(){func();});else if(typeof wpOnload!=='function'){wpOnload=func;}else{var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}};";

    /**
     * iframe_header's head after its title: the load-event helper, the
     * thickbox closer and the admin globals.
     *
     * @param array{ajaxurl: string, pagenow: string, typenow: string, adminpage: string, thousands: string, decimal: string, rtl: int} $page
     */
    public static function headerScript(string $title, array $page): string
    {
        return '<title>' . $title . "</title>\n\t<script>\n" . self::LOAD_EVENT . "\nfunction tb_close(){var win=window.dialogArguments||opener||parent||top;win.tb_remove();}\n"
            . "var ajaxurl = '" . $page['ajaxurl'] . "',\n\tpagenow = '" . $page['pagenow'] . "',\n\ttypenow = '" . $page['typenow'] . "',\n\tadminpage = '" . $page['adminpage'] . "',\n\t"
            . "thousandsSeparator = '" . addslashes($page['thousands']) . "',\n\tdecimalPoint = '" . addslashes($page['decimal']) . "',\n\tisRtl = " . $page['rtl'] . ";\n</script>\n\t";
    }

    /** iframe_header's body opening, the no-js class switched to js. */
    public static function headerBody(string $idAttribute, string $classes): string
    {
        return "</head>\n\t" . '<body' . $idAttribute . ' class="wp-admin wp-core-ui no-js iframe ' . $classes . '">' . "\n<script>\n(function(){\nvar c = document.body.className;\nc = c.replace(/no-js/, 'js');\ndocument.body.className = c;\n})();\n</script>\n\t";
    }

    /** wp_iframe's head after the document opening: its title, the load-event helper and the media popup's globals. */
    public static function iframeScript(string $title, string $ajaxurl, int $rtl): string
    {
        return "\t<title>" . $title . "</title>\n\t\t<script>\n\t" . self::LOAD_EVENT . "\n\tvar ajaxurl = '" . $ajaxurl . "', pagenow = 'media-upload-popup', adminpage = 'media-upload-popup',\n\tisRtl = " . $rtl . ";\n\t</script>\n\t";
    }

    /** wp_iframe's body opening, the no-js class switched to js. */
    public static function iframeBody(string $idAttribute, string $classes): string
    {
        return "\t</head>\n\t<body" . $idAttribute . ' class="wp-core-ui no-js ' . $classes . '">' . "\n\t<script>\n\tdocument.body.className = document.body.className.replace('no-js', 'js');\n\t</script>\n\t";
    }

    /** The onload call that closes wp_iframe's document. */
    public static function iframeEnd(): string
    {
        return "\t<script>if(typeof wpOnload==='function')wpOnload();</script>\n\t</body>\n\t</html>\n\t";
    }
}
