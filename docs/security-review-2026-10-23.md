# Security review, 2026-10-23

The first sweep since the 0.1.0 release (v0.1.0, 2026-10-22), on the way to
0.2.0. It covers what changed in `public/minn/` and `install.sh` since the
tag: the entry-point shape files and the installer's cache flush, the
filesystem credentials on a host without `getmyuid()`, the reference's other
entry scripts (links OPML, post by email, signup, activation, trackback), the
main query without `template_redirect`, a child theme's PHP templates, the
stylesheet order, the engine's SQL quoting through the connection, the render
budget, PHPMailer's internationalized addresses, and the server variables at
boot. Three reviewers, changing nothing and sending no request, each read one
surface:

- the installer and the boot;
- the front end;
- data, SQL and mail.

Each started from the 2026-10-20 and 2026-10-22 reviews, so it reported only
what was new: eight findings and a parity note. Every one was then checked on
the test site, the same input sent to both stacks, before it was fixed; one
claim did not reproduce (below). One reviewer read the reference's own source
to say what the reference does; those claims were taken as hypotheses and each
was settled by a request to both stacks, never from that reading.

## Findings and what was done

| # | Severity | Finding | Reference | Done |
|---|---|---|---|---|
| 1 | Low | `?rest_route=` was answered as REST on every path, so `/wp-cron.php?rest_route=...` ran a REST request under `DOING_CRON`, and `/wp-admin/admin-ajax.php?rest_route=...` under `DOING_AJAX`, where plugins skip their checks. The shape files made these reachable on nginx hosts. | Answers REST there only where its script runs the main query (the front controller, signup, activation, trackback); `wp-cron.php`, `wp-login.php`, `wp-comments-post.php`, `xmlrpc.php`, `wp-links-opml.php`, `wp-mail.php` and `wp-admin/*.php` answer as themselves (measured, 14 paths, both methods). | `Engine::restRoute` takes the query only on those paths (`SCRIPTS_WITHOUT_REST`); suite `security-sweep`. |
| 2 | Low | The engine's request was read before the server variables were made whole, so on a server that leaves `REQUEST_URI` empty (or IIS outside FastCGI) the engine routed one path while plugins, redirects and rewrite saw the `X-Original-URL` one. | Routes by the made-whole value. | `Engine::serve` calls `Request::fixServerVars()` before `Request::fromGlobals()`. |
| 3 | Low | The curl installer's cache flush ran `wp eval` with the site's plugins loaded as the installing user, passed the site's address to curl where a leading `-` reads as an option, and echoed it raw. | n/a | The address is read with `--skip-plugins --skip-themes`, taken only when it is a plain `http(s)` address, and passed with `curl --url`. The flush itself still loads the plugins, since a host's purge hooks live there (named below). |
| 4 | Low | The links OPML printed a stored link's URL and feed with attribute escaping only, so a `javascript:` or `data:` address a plugin or import wrote reached feed readers. | Prints both as addresses: an unsafe scheme empty, `&` as `&#038;`, a space as `%20` (measured). | `Front\LinksOpml` escapes them as addresses (`Escape::url`); suite `probes` compares a stored `javascript:` link live. |
| 5 | Low | The render budget started over at every outermost render, so outside a block template (`the_content` on a classic theme, REST, feeds) each top-level synced block had a budget of its own, and a refused synced pattern was still read from the database. | No budget. | A request's render state holds one budget for the whole request (`RenderState::forRequest`; the command line keeps one per outermost render); a synced pattern asks the cycle guard and the budget before its read. Unit `render-state`. |
| 6 | Low | The engine's SQL quoting followed its own connection, while the query classes run through `$wpdb`: a `db.php` drop-in with a connection of its own (HyperDB, LudicrousDB) under a multibyte `DB_CHARSET` let a lead byte close a quote again (reproduced: `SELECT '\xbf\' OR 1=1 -- '` answered 1). | Quotes on the connection that runs the query. | The facade hands `Sql::through()` the connection `$wpdb` holds at each call, the engine's own when there is none; suite `security-sweep` runs the same input through a drop-in's gbk connection on both stacks. |
| 7 | Info | PHPMailer's validator switch to "eai" is a static and would outlive a request in a persistent worker. | The same static; PHP-FPM resets it per request. | Named below. |
| 8 | Low | One author's fan-out can spend the budget and blank the rest of a page. | Renders everything, slowly. | Named below. |

Did not reproduce: `/wp-trackback.php?p=5` redirecting to the post (the
reference answers the same "need an ID" 404 as the engine).

Parity found on the way, not security: a page's own PHP template chosen in
its meta (`template-full.php`, `page-templates/sub.php`) rendered the block
template on the engine; it renders as on the reference now, a folder down
included and a name that climbs out refused (suite `child-theme`). The
comment form's 405 carried a body the reference does not send.

## Named and not fixed

- **The installer's flush loads the site's plugins** (Low, accepted). `wp cache flush`
  is how a host's purge runs (Kinsta's mu-plugin); the installer runs as the
  site's own user in the documented flow, and `--no-cache-flush` skips it.
- **Install lays entry points a site had deleted** (Low, accepted). A deleted
  `xmlrpc.php` comes back as a shape file, as a WordPress core update brings
  the file back; the engine serves no XML-RPC, so nothing is opened, and eject
  removes it again.
- **The "eai" switch would outlive a request in a persistent worker** (Info,
  accepted). Minn runs a process per request; reset it per request with the
  other statics before a worker mode.
- **A budget truncates** (Low, accepted). Past 10,000 sources a request
  renders the rest as nothing, as a cycle renders; no page Minn has served
  comes near it, and the reference's alternative is unbounded work.

## Where the pins live

Suite `security-sweep` (the `?rest_route=` paths, the drop-in's gbk
connection), suite `probes` (the stored `javascript:` link in the OPML), unit
`render-state` (the per-request budget), suite `child-theme` (the page
templates), and `scorecard.php`'s findings list.
