# The Minn site

`https://minn-engine.localhost` is two things at once: the engine's development
site, and the public face of the project. The front page you see there is a
block theme, `site/minn-site/` (its own git repository, gitignored from this
one), rendered by Minn Engine with no WordPress code in
the process. The same theme renders on the parked reference WordPress, and the
`site` suite diffs the two. The marketing site is the engine's own dogfood.

## Where things live

| Piece | Path |
|---|---|
| The theme | own git repo at `site/minn-site/` (nested, gitignored from the engine; remote https://github.com/minn-run/minn-theme, private). Development only; never ships inside `minn/` |
| On disk for both stacks | `wp-reference/wp-content/themes/minn-site` is a symlink to `../../../site/minn-site`; `public/wp-content/themes` already points into the reference's themes directory, so the engine and the reference read the same files |
| Active theme | the shared database's `template` and `stylesheet` options are `minn-site`; the site name option is still `Minn Engine` (fixtures pin it); the chrome says **Minn**. Minn is the product; Minn Engine is the developer name for the runtime, like WordPress core |
| Suite | `tests/site.test.php` (in `run-all.sh`): the theme's assets, the page's own invariants, and every template diffed against the reference |

## What the theme is

A from-scratch block theme in the Minn Admin design language: the same tokens
(`--bg`, `--panel`, `--accent`, dark by default, `[data-root-theme="light"]`
on the root), the same fonts (Hanken Grotesk and JetBrains Mono, bundled under
`assets/fonts/`, no external requests), and the same `minn-theme` localStorage
key, so a visitor's light or dark choice carries between minnadmin.com and this
site. `style.css` holds the design; `templates/front-page.html` is the whole
marketing page as one `core/html` block between the header and footer parts;
`templates/{index,archive,search,single,page,404}.html` cover every other
resolution with core template blocks so posts, archives, and search render in
the same look.

The page is built from `docs/vision.md`. Section by section: the thesis
(WordPress as a coordination standard, the five legs ranked), the graveyard
lesson and the Nginx precedent, the two tiers plus the three-audience visual
(tooling and plugin PHP Speak; humans are Mute: Minn Admin is the only UI),
the oracle method and the facts it caught, the license analysis, status
(working and not yet, said plainly), the hard parts, a FAQ, and the on-ramp:
WordPress users are pointed at Minn Admin first, because it is the shipped
phase and it is the interface the engine boots. The glossary behind the
visual is `contracts/lexicon.md`. The engine serves that file as a filterable
page at `/lexicon/` (raw at `/lexicon.md`): Speak / Hear / Mute chips, a
family search, sourced from the markdown so the page cannot drift from the
policy. It is an engine route, not a WordPress page, so the site-suite
oracle body-diff does not include it. Theme chrome (nav link, homepage
"Browse the lexicon", CSS) lives in the theme repo.

The theme runs no PHP on the engine. `functions.php` exists only for the
reference (it enqueues `style.css`, which the engine links on its own); the
theme toggle and the scroll reveal are inline scripts in the parts.

## Keeping the marketing page honest

- Numbers on the page (classes, suites, checks, inventory sizes, plugins loading
  on the dogfood site) are the real ones at the time of writing. Refresh them when
  they move: the check count is the `passed` total of a full `run-all.sh`.
- No em dash inside a sentence (the repository's prose rule applies to the page).
- "WordPress" appears only in truthful compatibility statements, and the footer
  carries the trademark line. Never in a name, never implying endorsement.
- The source is not on GitHub yet; the page says so and points at Minn Admin's
  repository. Swap the GitHub links when the engine repository goes public.

## The parity suites and the theme pin

The fixtures under `contracts/fixtures/theme/` and the geometry suite were
captured under twentytwentyfive, and several suites (styles, permalinks, api,
front-page, admin-surfaces) depend on that theme's markup. So every suite pins
twentytwentyfive while it runs and restores the site's own theme on shutdown:
`tests/lib.php` does it at require time, `tests/browser/pin-theme.js` does it
for the browser suites, and `run-all.sh` pins once for the whole run and sets
`MINN_TEST_KEEP_THEME=1` so the suites skip their own pin. The site suite pins
`minn-site` instead through `minn_test_pin_theme('minn-site')`. While a suite
runs the public site briefly shows twentytwentyfive; that is expected on the dev
box.

## What building the site taught the engine

Rendering a second theme at parity surfaced four facts the twentytwentyfive
pages never exercised, all recorded in `contracts/front/theme.md`:

1. The skip link targets the first `<main>`'s own id when the template gives it
   one; the reference injects `wp--skip-link--target` only when it does not.
2. `has-global-padding` on constrained containers depends on the theme's
   `settings.useRootPaddingAwareAlignments`; without it the class is absent.
3. A `post-content` block without a layout attribute still carries its default
   flow layout classes.
4. The rendered template is texturized as a whole, after the blocks, so straight
   quotes in a theme's own markup curl.

## Publishing

The theme is a normal block theme and installs on any WordPress or Minn Engine
site. When the marketing site goes to a real host, ship the `minn-site` theme
from its own repository, set the site name and tagline, and keep the page's
numbers in step with the release it describes. Copy and design edits belong in
that repository, not here.
