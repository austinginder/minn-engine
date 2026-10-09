<?php

use Minn\Theme\FontFaces;

/** The @font-face rules for fonts given as families of faces (probe fonts), printed by Minn\Theme\FontFaces in one style element. */
class WP_Font_Face
{
    public function generate_and_print(array $fonts)
    {
        $css = FontFaces::css($fonts);
        if ($css !== '') {
            printf("<style class=\"wp-fonts-local\">\n%s</style>\n", $css);
        }
    }
}
