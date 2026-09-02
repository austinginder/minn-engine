# Security review, 2026-09-02

A second read of `public/minn/` (about 62,000 lines: 246 engine classes and the
WordPress facade) after the runtime landed, done as the fix list of the audit
that also proposed the next breaking changes. Seven reviewers read one namespace
each; every finding below was checked at the cited line before it was acted on,
and where the reference decides the answer, it was probed on the same database
first and the answer captured into a suite, so the fix cannot regress silently.

## Findings and what was done

Severity is the reviewer's, confirmed at the source; "Reference" says whether
WordPress on the same database behaves the same way (probed), which is what
decided the shape of each fix.

| # | Severity | Finding | Reference | Done |
|---|---|---|---|---|
| 1 | Critical | `Support\Kses` judged a URL's scheme on the raw attribute text, so `&#106;avascript:`, `javascript&colon;`, `jav&#x09;ascript:` and `j%61vascript:` hrefs passed the allowlist; `a[href]` is in the comment set, so an anonymous commenter could store one. | Decodes every reference and percent escape as deep as it goes, drops invisible characters, and cuts whatever stands before the first colon until an allowed scheme leads. | The same rule (`attributeUrl`, `deepDecode`); attribute values and text nodes are stored normalised the way the reference stores them; `tests/unit/kses.php` pins seventy captured cases; the security suite still matches byte for byte. |
| 2 | High | `Admin\Packages::remove()` accepted `..` as a folder name and removed wp-content; an archive whose only folder was `.` emptied the themes or plugins directory on overwrite. | n/a (engine surface) | A folder name refuses `.` and `..`, every destination must be a direct child of the kind's directory (`contained()`), archives refuse symbolic-link entries and are bounded in entries and size; four checks in the admin-surfaces suite. |
| 3 | High | Every uncaught throwable fed the recovery pause, so one crafted request that made a plugin's route throw took that plugin down for every visitor; a symlinked plugin was never blamed. | WordPress pauses only inside a recovery-mode session. | Recovery is armed only while `Plugins::load` runs; the first boot failure is a strike, the second inside ten minutes pauses; blame maps through the realpath map; a route that fatals three times pauses nothing (`recovery` suite, 17). |
| 4 | High | The declared-types catch-all answered no-route for any unknown `wp/v2` base before the runtime's routes ran, so a plugin route under `wp/v2` 404ed; engine routes never saw `rest_authentication_errors`, `rest_pre_dispatch`, or a `rest_endpoints` removal; an in-process `rest_do_request` to a core route ran anonymous. | Probed with the same fixture plugin on both stacks. | `Http\RouteMiss` lets a handler decline; `RuntimeRoutes::gate()` runs the three filters before the engine's router; the runtime's core-route callback dispatches the engine only; in-process calls run as the outer request's user (`rest-gate` suite, 9). |
| 5 | Medium | `wp/v2/users/{id}` wrote columns and meta before refusing a role change the caller may not make. | The permission check runs first. | The refusal is decided before the first write; a parity check in the users suite. |
| 6 | Medium | The symbol gate's class half wrote into a local nobody read, so a plugin that extended a missing class passed the gate and fatalled; a plugin declaring a pluggable function unguarded failed to compile with no gate message. | The reference loads `pluggable.php` after the plugins, so a plugin's own definition wins there. | The class references reach the table; the verdict gains `redeclares` (main file refuses the load, elsewhere advisory, since a polyfill behind a conditional include is exactly what the static read cannot follow); methods are never declarations; the cache carries a reader version; `tests/unit/symbols.php` (14). |
| 7 | Medium | Package fetches checked https on the first hop only and followed redirects blindly; archives had no limits; `Updates::state(true)` called a method with no parameter, so the intended refresh never ran; a language pack verified its hash only when the manifest named one. | n/a | `Http\Download` judges every hop (https, and the wordpress.org host for an update), caps the body; the bundle manifest must name the hash; `refresh()` is called; every applied update records its archive's SHA-256 under `archives` in `minn_updates`. |
| 8 | Medium | `Media\Canvas::open` decoded a file with no dimension check, so a few-kilobyte image claiming a huge canvas exhausted memory, and the attachment row was inserted before the sizes were made, leaving a headless attachment; mime came from the extension alone. | Refuses non-image bytes under an image name (`rest_upload_unknown_error` multipart, `rest_upload_sideload_error` raw) and renames a GIF uploaded as `.png` to `.gif`. | The header's dimensions are checked against the memory left before a pixel is decoded; sizes are made before the insert; bytes decide the type as the reference decides it; a store that cannot write refuses; a size name that leaves its folder is ignored on delete (media suite, two parity checks). |
| 9 | Low | Unknown-user sign-in skipped the password hash (timing enumeration); the throttle was read-then-insert on a unique column; a custom mail header kept its line breaks; a classic template that threw shipped half a page before the error page; the serialized encoder wrote floats at `precision` digits, changing a stored value on rewrite. | n/a | A real hash of a password nobody knows is verified when the user is missing; the throttle is one upsert; header lines are folded; output buffers are discarded before any error page; floats are written by PHP's own serializer. |

## Named and not fixed here

- No proxy-trust policy: behind a reverse proxy the throttle keys on the proxy's
  address and cookies lose `Secure` unless the host's wp-config sets `HTTPS`.
  A `MINN_TRUSTED_PROXIES` policy is the next hardening slice, not a fix.
- `Cookie::fromJar` falls back to the first `wordpress_logged_in_*` cookie it sees
  when the site's own name is absent; a stale cookie from another host on the
  same domain can shadow the right one. Kept for now: the suites and the oracle
  rely on cross-host cookies, and the fallback never grants more than the cookie
  itself proves.
- `Serialized::decode` maps an object to `stdClass` by design (it never
  instantiates), so an option holding an object that is read, changed, and
  written back loses its class name. Recorded as a gap; the fix is a
  `SerializedObject` value that re-emits the class, and it waits for a caller
  that needs it.
- `rest_request_before_callbacks`, `rest_request_after_callbacks`, and
  `rest_post_dispatch` do not yet run around engine routes.
- Hosting a plugin's pluggable override (loading the pluggable set after the
  plugins, as the reference does) is a runtime milestone; until then the gate
  names the collision.

## Where the pins live

`tests/unit/kses.php`, `tests/unit/symbols.php`, `tests/rest-gate.test.php`,
`tests/recovery.test.php`, and the checks added to the admin-surfaces, users,
and media suites, beside the security and hardening suites from the first review.
The full run-all passes after the changes.
