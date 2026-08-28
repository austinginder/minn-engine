<?php
/**
 * Oracle tool: build the block-rendering battery in the SHARED database.
 *
 * One published post per block family, its stored markup written to
 * contracts/fixtures/blocks/{family}.html, its reference rendering (the
 * oracle's content.rendered) to {family}.rendered.html, and the post ids
 * to manifest.json. Idempotent: re-running updates the same posts.
 *
 *   php tests/tools/blocks-battery.php            (oracle must be running)
 *
 * The image family needs a real attachment; a generated PNG is imported
 * once and its id kept in the manifest.
 */

$root = dirname(__DIR__, 2);
$ref = 'http://127.0.0.1:8123';
$dir = "$root/contracts/fixtures/blocks";
@mkdir($dir, 0755, true);
$manifestFile = "$dir/manifest.json";
$manifest = is_file($manifestFile) ? (array) json_decode((string) file_get_contents($manifestFile), true) : ['posts' => [], 'image' => 0];

function wp(string $args): string
{
    global $root;
    return trim((string) shell_exec('cd ' . escapeshellarg("$root/wp-reference") . ' && wp ' . $args . ' 2>/dev/null'));
}

// The image: a 1200x800 PNG with a gradient, imported once.
if (empty($manifest['image']) || wp('post get ' . (int) $manifest['image'] . ' --field=ID') !== (string) (int) $manifest['image']) {
    $png = sys_get_temp_dir() . '/minn-battery.png';
    $canvas = imagecreatetruecolor(1200, 800);
    for ($x = 0; $x < 1200; $x += 4) {
        imagefilledrectangle($canvas, $x, 0, $x + 3, 800, imagecolorallocate($canvas, (int) ($x / 1200 * 255), 90, 200 - (int) ($x / 1200 * 120)));
    }
    imagepng($canvas, $png);
    $manifest['image'] = (int) wp('media import ' . escapeshellarg($png) . ' --title="Battery image" --porcelain');
}
$imageId = (int) $manifest['image'];
$imageUrl = wp("post get $imageId --field=guid");
$home = rtrim(wp('option get home'), '/');
$imageBase = preg_replace('/\.png$/', '', $imageUrl);

$families = [
    'text' => <<<HTML
<!-- wp:paragraph -->
<p>A plain paragraph with <strong>bold</strong>, <em>italic</em>, and a <a href="{$home}/hello-world/">link</a>. "Quoted" and it's texturized...</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center","dropCap":true,"fontSize":"large"} -->
<p class="has-text-align-center has-drop-cap has-large-font-size">Centered, drop cap, large.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Heading two</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3,"textAlign":"center"} -->
<h3 class="wp-block-heading has-text-align-center">Heading three, centered</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>First item</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Second item<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Nested item</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:list {"ordered":true,"start":3} -->
<ol start="3" class="wp-block-list"><!-- wp:list-item -->
<li>Third</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Fourth</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>Quoted paragraph.</p>
<!-- /wp:paragraph --><cite>Someone</cite></blockquote>
<!-- /wp:quote -->

<!-- wp:pullquote -->
<figure class="wp-block-pullquote"><blockquote><p>Pulled quote.</p><cite>A citation</cite></blockquote></figure>
<!-- /wp:pullquote -->

<!-- wp:verse -->
<pre class="wp-block-verse">Roses are red,
  violets are blue.</pre>
<!-- /wp:verse -->

<!-- wp:preformatted -->
<pre class="wp-block-preformatted">Preformatted   text
keeps    spacing.</pre>
<!-- /wp:preformatted -->

<!-- wp:code -->
<pre class="wp-block-code"><code>function hi() {
    return "quotes stay straight";
}</code></pre>
<!-- /wp:code -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Summary line</summary><!-- wp:paragraph -->
<p>Hidden detail.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->
HTML,
    'media' => <<<HTML
<!-- wp:image {"id":{$imageId},"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$imageBase}-1024x683.png" alt="Battery alt" class="wp-image-{$imageId}"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":{$imageId},"sizeSlug":"medium","linkDestination":"media","align":"center"} -->
<figure class="wp-block-image aligncenter size-medium"><a href="{$imageUrl}"><img src="{$imageBase}-300x200.png" alt="" class="wp-image-{$imageId}"/></a><figcaption class="wp-element-caption">A caption</figcaption></figure>
<!-- /wp:image -->

<!-- wp:image {"id":{$imageId},"width":"400px","sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full is-resized"><img src="{$imageUrl}" alt="" class="wp-image-{$imageId}" style="width:400px"/></figure>
<!-- /wp:image -->

<!-- wp:gallery {"linkTo":"none"} -->
<figure class="wp-block-gallery has-nested-images columns-default is-cropped"><!-- wp:image {"id":{$imageId},"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$imageBase}-1024x683.png" alt="" class="wp-image-{$imageId}"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":{$imageId},"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$imageBase}-1024x683.png" alt="" class="wp-image-{$imageId}"/><figcaption class="wp-element-caption">Second</figcaption></figure>
<!-- /wp:image --></figure>
<!-- /wp:gallery -->

<!-- wp:cover {"url":"{$imageUrl}","id":{$imageId},"dimRatio":50,"customOverlayColor":"#123456","layout":{"type":"constrained"}} -->
<div class="wp-block-cover"><span aria-hidden="true" class="wp-block-cover__background has-background-dim" style="background-color:#123456"></span><img class="wp-block-cover__image-background wp-image-{$imageId}" alt="" src="{$imageUrl}" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center","placeholder":"Write title…","fontSize":"large"} -->
<p class="has-text-align-center has-large-font-size">Cover title</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:cover -->

<!-- wp:media-text {"mediaId":{$imageId},"mediaType":"image"} -->
<div class="wp-block-media-text is-stacked-on-mobile"><figure class="wp-block-media-text__media"><img src="{$imageBase}-1024x683.png" alt="" class="wp-image-{$imageId} size-full"/></figure><div class="wp-block-media-text__content"><!-- wp:paragraph -->
<p>Beside the media.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:media-text -->
HTML,
    'layout' => <<<HTML
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:paragraph -->
<p>Inside a constrained group.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:paragraph -->
<p>Row one</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Row two</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:paragraph -->
<p>Stacked</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"grid","minimumColumnWidth":"12rem"}} -->
<div class="wp-block-group"><!-- wp:paragraph -->
<p>Grid cell</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph -->
<p>Left column</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"66.66%"} -->
<div class="wp-block-column" style="flex-basis:66.66%"><!-- wp:paragraph -->
<p>Right column</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:separator {"className":"is-style-wide"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:spacer {"height":"60px"} -->
<div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{$home}/">Primary</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button">Outline</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:table -->
<figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr><th>Name</th><th>Value</th></tr></thead><tbody><tr><td>One</td><td>1</td></tr><tr><td>Two</td><td>2</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p>Before the more tag.</p>
<!-- /wp:paragraph -->

<!-- wp:more -->
<!--more-->
<!-- /wp:more -->

<!-- wp:paragraph -->
<p>After the more tag.</p>
<!-- /wp:paragraph -->
HTML,
    'dynamic' => <<<HTML
<!-- wp:latest-posts {"postsToShow":3,"displayPostDate":true} /-->

<!-- wp:categories /-->

<!-- wp:archives /-->

<!-- wp:search {"label":"Search","buttonText":"Search"} /-->

<!-- wp:tag-cloud /-->

<!-- wp:latest-comments {"commentsToShow":3} /-->
HTML,
];

foreach ($families as $family => $markup) {
    $file = "$dir/$family.html";
    file_put_contents($file, $markup . "\n");
    $id = (int) ($manifest['posts'][$family] ?? 0);
    if ($id > 0 && wp("post get $id --field=ID") === (string) $id) {
        wp("post update $id " . escapeshellarg($file) . " --post_name=" . escapeshellarg("zz-block-battery-$family"));
    } else {
        $id = (int) wp("post create " . escapeshellarg($file) . " --post_type=post --post_status=publish --post_title=" . escapeshellarg("Block battery: $family") . " --post_name=" . escapeshellarg("zz-block-battery-$family") . " --porcelain");
        $manifest['posts'][$family] = $id;
    }
    $json = (string) file_get_contents("$ref/wp-json/wp/v2/posts/$id?_fields=content");
    $rendered = (string) (json_decode($json, true)['content']['rendered'] ?? '');
    file_put_contents("$dir/$family.rendered.html", $rendered);
    echo "$family: post $id, " . strlen($rendered) . " bytes rendered\n";
}
file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
