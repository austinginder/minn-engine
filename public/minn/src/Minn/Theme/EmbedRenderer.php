<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Front\Kind;
use Minn\Front\PrintedResponse;
use Minn\Front\Resolution;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Runtime\Runtime;

/**
 * A post's embed page (its /embed/ address, or ?embed= on it), the card
 * another site's iframe shows, as the reference prints it: the main query
 * stood with the embed asked for, then the embed template (the theme's
 * own embed-{type}.php or embed.php, else the engine's theme-compat one)
 * through template_include, with embed_head, embed_content,
 * embed_content_meta and embed_footer. An address that embeds nothing
 * gets the 404 card.
 */
final readonly class EmbedRenderer
{
    public function __construct(
        private MainQueryBridge $bridge,
    ) {
    }

    /** Whether a request for this resolution asks for its embed page. */
    public static function asked(Request $request, Resolution $resolution): bool
    {
        $embed = ($request->query('embed') ?? '') !== '' || ($resolution->vars['embed'] ?? '') !== '';
        return $embed && in_array($resolution->kind, [Kind::Single, Kind::Page, Kind::NotFound], true);
    }

    /**
     * The embed page, under the status the request's main query settled on;
     * the body classes the theme's page would carry (the Minn bar's aside).
     *
     * @param list<string> $bodyClasses
     */
    public function render(Resolution $resolution, array $bodyClasses): Response
    {
        Runtime::current()->set('classic_body_classes', array_values(array_diff($bodyClasses, ['minn-front-bar'])));
        $response = PrintedResponse::stand($this->bridge, ['embed' => true], static function (): void {
            $template = (string) \apply_filters('template_include', \get_embed_template());
            if ($template !== '' && is_file($template)) {
                \load_template($template, false);
            }
        }, $resolution);
        return $response ?? Response::html('', $resolution->status);
    }
}
