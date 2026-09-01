# Writing Minn

Three walk-throughs, each ending at a passing test, and the one method behind
all of them. `docs/style.md` is the law; this is the tour.

## The one method: capture, implement, prove

Every behaviour in the engine came from the same loop.

1. **Boot the oracle**: a parked WordPress on the same database.
   `cd wp-reference && php -S 127.0.0.1:8123 router.php`
2. **Capture** what it does, with curl or wp-cli, into `contracts/fixtures/` or
   a sentence in the matching `contracts/*.md`. What you captured is data.
3. **Implement** from the capture. Never from WordPress source: not a function,
   not a regex, not a paraphrase. The MIT licence depends on this.
4. **Prove two ways**: a fixture suite that runs without the oracle, and a live
   diff that runs against it. Any divergence is a finding, and the oracle wins.
5. **Record** what the oracle taught you next to the code that depends on it,
   in one sentence, present tense.

Ten minutes of this beats an hour of reading documentation, because the oracle
tells you what WordPress does, not what it says it does.

## Your first controller

A route is a method with an attribute. The router enforces the capability
before the method runs; the method gets the request and the captures it names.

```php
<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\Posts;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;

/** wp/v2/acme/items: one read, cap-gated. */
final readonly class ItemsController
{
    public function __construct(private Posts $posts, private Caller $caller)
    {
    }

    #[Route(Method::Get, '/wp/v2/acme/items/{id:\d+}', cap: 'edit_posts')]
    public function show(Request $request, string $id): Response
    {
        $post = $this->posts->find((int) $id);
        if ($post === null) {
            throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 404);
        }
        return Reply::item(['id' => (int) $post['ID'], 'title' => $post['post_title']], Fields::fromQuery($request->query));
    }
}
```

Wire it in `Rest\Api::forRequest()` beside the others, then prove it. A
parity suite is a PHP file in `tests/`, named for the surface, that fetches the
same route from both stacks and diffs. Copy the shape of
`tests/navigation.test.php`: it is forty lines of helpers and then one
`nav_same()` call per behaviour. When the route is engine-only (no WordPress
counterpart), a fixture suite pins the shape instead.

What the shape gives you for free: `?_fields=` filtering, the reference's
headers, the error payload, `HEAD`, and the capability refusal codes. What you
must still do: capture the reference's exact status and message for every
refusal you throw.

## Your first dynamic block

A dynamic block is a closure the renderer calls with the parsed block. It
returns markup. The class it lives on is constructed once in
`Blocks\Renderer::forDb()` (content) or `Theme\PageRenderer` (theme blocks).

```php
<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic;

use Minn\Blocks\Block;
use Minn\Blocks\Renderer;
use Minn\Content\Posts;
use Minn\Support\Html;

/** core/acme-latest: the newest post's title, linked. */
final readonly class AcmeLatest
{
    public function __construct(private Posts $posts, private \Minn\Front\Permalinks $permalinks)
    {
    }

    public function register(Renderer $renderer): void
    {
        $renderer->registerDynamic('core/acme-latest', $this->render(...));
    }

    private function render(Block $block, Renderer $renderer): string
    {
        $page = $this->posts->published('post', page: 1, perPage: 1);
        if ($page->isEmpty()) {
            return '';
        }
        $post = $page->posts[0];
        return '<p class="wp-block-acme-latest"><a href="' . Html::attr($this->permalinks->forPost($post)) . '">'
            . Html::esc($post['post_title']) . '</a></p>';
    }
}
```

Prove it in the block battery: add a post to `tests/tools/blocks-battery.php`
whose content holds the block, re-run the battery, and let `tests/blocks.test.php`
diff the render against the oracle. If the reference does not know the block
(it is yours), pin the markup as a fixture and say so in `contracts/blocks.md`.

Two facts that bite every first block: escape at output with `Html::esc()` and
`Html::attr()`, never earlier; and any per-page counter (galleries, style
variations, images) comes from `RenderState`, never from your class, because
the reference counts across the whole response.

## Your first extension

An extension replaces a WordPress plugin on Minn. It is a folder under
`wp-content/plugins/` with a manifest and one class.

```json
{
  "name": "Acme Footer for Minn",
  "version": "1.0.0",
  "license": "MIT",
  "replaces": ["acme-footer/acme-footer.php"],
  "autoload": {"Minn\\Ext\\AcmeFooter\\": "src/"},
  "extension": "Minn\\Ext\\AcmeFooter\\Extension"
}
```

```php
<?php

declare(strict_types=1);

namespace Minn\Ext\AcmeFooter;

use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;
use Minn\Support\Html;

final class Extension implements MinnExtension
{
    public function register(Seams $minn): void
    {
        $line = (string) ($minn->site->option('acme_footer_line') ?? '');
        if ($line === '') {
            return;
        }
        $minn->footer(static fn (Seams $minn): string => '<p class="acme-footer">' . Html::esc($line) . '</p>');
        $minn->bodyClass('has-acme-footer');
    }
}
```

`Seams` is everything you can touch: nine registrations (`gateBlocks`,
`filterBlocks`, `shortcode`, `filterContent`, `head`, `footer`, `bodyClass`,
`title`, `filterDocument`) and the request you run in (`db`, `site`, `request`,
`reader`). There are no hook names, no globals, and no priority numbers: seams
run in registration order.

Prove it the way `tests/extensions.test.php` proves the fixture extension:
activate it through the `minn_active_extensions` option, fetch a page, assert
the footer line is there, deactivate in a shutdown handler. If the extension
replaces a real plugin, the dogfood suite is the judge: a page on the engine
must match the same page on the reference with the plugin active.

## The API, generated

`docs/api/README.md` indexes every namespace; each page lists every class
with its docblock, constructor, public properties and methods, read from the
code by `php tests/tools/api-docs.php`. `contracts/api/minn.json` is the same
model for tooling. Grep it before you write: `grep -n "function forPost" docs/api/front.md`.

## Where things live

| You want to | Look in |
|---|---|
| read or write a row | `Content\*` (repositories), `Db` (the one door) |
| shape a REST resource | `Rest\*Object` (the shape), `Rest\*Controller` (the routes) |
| render a block | `Blocks\Dynamic\*`, registered in `Blocks\Renderer::forDb` |
| render a themed page | `Theme\PageRenderer`, `Theme\Templates`, `Theme\GlobalStyles` |
| resolve a URL | `Front\Resolver`, `Front\Permalinks` |
| sign someone in | `Auth\*`, `Login\LoginController` |
| let a WordPress plugin call a function | `wp-api/*.php`, a mapping onto a `Minn\` class |
| prove something pure | `tests/unit/` |
| prove something against WordPress | `tests/*.test.php` with the oracle on 8123 |

## The four tests every change must pass

1. **The seam stays visible.** `post_name`, `wp_posts`, `rest_no_route` keep
   their names. Beauty is behind the seam, never a renaming of it.
2. **Raw SQL stays.** A builder abstracts the layer that must stay explicit.
3. **No magic.** No autowiring, no reflection-driven containers, no traits, no
   interfaces with one implementation. Every call can be followed with a search.
4. **The suite gets stricter, never looser.** The ratchets in
   `tests/style.test.php` only tighten.
