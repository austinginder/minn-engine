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
| Active theme | the shared database's `template` and `stylesheet` options are `minn-site`; `blogname` is `Minn` (the product; the tab and `wp/v2/settings` title). Minn Engine is the developer name for the runtime, like WordPress core |
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
visual is `contracts/lexicon.md` (the Speak / Hear / Mute policy). The
**minn-site theme** serves that glossary as a filterable page at `/lexicon/`
(raw at `/lexicon.md`) from its own `content/lexicon.md`. `/code-size/` is
the same: a theme page. The engine does not register these routes.

`functions.php` enqueues `style.css` and owns those two pages (it answers
on `init`, so WordPress and the engine both serve them). The theme toggle
and the scroll reveal stay inline in the parts.

## The code-size page

`/code-size/` lives in the theme. The numbers come from
`tests/tools/code-size.php`, which downloads the current WordPress release
from wordpress.org into the gitignored `.cache/`, measures both trees, and
writes `contracts/code-size.json` plus `site/minn-site/content/code-size.json`.
Refresh at each release with `php tests/tools/code-size.php` (or
`--wordpress=7.2` for a named version). `tests/code-size.test.php` pins the
report's arithmetic, not the page.

## Keeping the marketing page honest

- Numbers on the page (classes, suites, checks, inventory sizes, plugins loading
  on the dogfood site) are the real ones at the time of writing. Refresh them when
  they move: the check count is the `passed` total of a full `run-all.sh`.
- No em dash inside a sentence (the repository's prose rule applies to the page).
- "WordPress" appears only in truthful compatibility statements, and the footer
  carries the trademark line. Never in a name, never implying endorsement.
- The source is not on GitHub yet; the page says so and points at Minn Admin's
  repository. Swap the GitHub links when the engine repository goes public.

## Languages during a test run

Two things keep a language switched on for testing from bending the suites.
`wp-reference/wp-content/languages` is a symlink to the engine's
`public/wp-content/languages` (the same arrangement as uploads), so both stacks
read the same packs and `/minn-admin/v1/languages` agrees. And every suite pins
en_US while it runs (`minn_test_pin_locale()` in `tests/lib.php`: `WPLANG` and
every user's `locale` meta go to empty and come back on shutdown; `run-all.sh`
pins once and sets `MINN_TEST_KEEP_LOCALE`; the browser suites do the same
through `pin-theme.js`), because the fixtures and the prose checks are English.

The reference counts a language as installed when its core pack is on disk
(`get_available_languages()`), so a language installed for testing needs the
core pack too: `cd wp-reference && wp language core install <locale>`. The
engine's own install fetches only the Minn Admin pack today; fetching the
core pack alongside it, so the folder matches what WordPress would leave, is
the language milestone's next step. The engine does not yet render its public
pages from core packs, which is why the pin matters: with a core pack present
and `WPLANG` set, the reference would render translated and the engine would
not. One fixture depends on a core pack being present: the api suite's
`plugin-surface` row for `wp_get_installed_translations('core')` lists the
core text domains, which is the same set for any locale, so keep at least one
core pack installed (es_ES today) or recapture the fixture.

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
