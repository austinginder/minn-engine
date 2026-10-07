# site/

The marketing theme is its own git repository, checked out at `minn-site/`
(WordPress slug) with remote https://github.com/austinginder/minn-site (private).
This engine repository gitignores that folder so copy and design commits stay
out of the engine history.

`wp-reference/wp-content/themes/minn-site` already points here. The engine
never ships this theme; it is the front page of minn-engine.localhost.
`tests/site.test.php` still diffs that the engine can render it.
