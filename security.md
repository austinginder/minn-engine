# Security policy

Minn Engine runs real sites: it serves their pages, signs their users in, and
updates itself. Security reports get priority over everything else.

## Reporting a vulnerability

Email **security@minn.run**. You can expect an acknowledgment within 48 hours
and a fix or a concrete timeline within a week for anything exploitable.
Credit is yours unless you ask otherwise; coordinated disclosure timing is
negotiable and reasonable.

Please include the Minn version (`php minn/bin/minn status`, or the
`X-Powered-By` header on `/minn-admin/`), the role of the user in your
reproduction, and steps or a request trace. A comparison with what WordPress
does on the same request helps: Minn aims to answer as WordPress answers, so
a difference is often the bug.

## Supported versions

The latest release. Minn updates itself from signed releases, so staying
current is cheap; fixes are not backported.

## Scope

- The engine (`public/minn/` in this repository, `minn/` on a site): its
  REST routes, sign-in, cookies and nonces, capabilities, the plugin and
  theme runtime, uploads, and everything it serves.
- `minn/bin/minn` and `install.sh`: preflight, install, eject and update.
- The update path: `updates.minn.run` as the engine uses it, and the checks
  an update makes before it replaces the engine.

Out of scope: the minn.run website; Minn Admin, which has its own policy
(security@minnadmin.com); third-party plugins and themes (report those to
their authors, unless the problem is how Minn runs them); and issues that
need an already-compromised administrator account or server.

## Design notes for reviewers

- **No WordPress code.** The engine is written from WordPress's observable
  behaviour, not its source, and is checked against a real WordPress on the
  same database. A behaviour that differs from WordPress in a way that
  weakens a check is a bug even where WordPress's own code would be safe.
- **Signed updates.** An update downloads only from
  `https://updates.minn.run/v1/minn/download/`, checks the archive's sha256,
  then an Ed25519 signature against the keys built into the engine
  (`Ops\EngineUpdate::KEYS`: a working key and an offline recovery key), and
  refuses an older version or a development checkout. The signing keys are
  never in this repository, on GitHub or on the update service.
- **One door to the outside.** The engine never contacts wordpress.org.
  Update checks, directory search and downloads go to updates.minn.run,
  which passes nothing about the site on. Outbound requests refuse private
  and reserved addresses, at every redirect hop, unless the caller lists them.
- **No `unserialize()` on stored data.** Stored PHP values are read by the
  engine's own parser, which instantiates nothing itself: an object record
  comes back as plain data unless the caller rebuilds a class it knows. The
  one `unserialize()` left is WordPress's encoded widget instance, decoded
  only after its HMAC matches the site's own and with `allowed_classes` off.
- **Routes state who they are for.** REST and admin routes carry their
  access check on the route itself (`#[Route(..., policy: ...)]`); a style
  ratchet keeps the few older routes without one from growing.
- **Install and eject are reversible.** WordPress's own files are parked,
  not deleted, and eject acts only on an install record that names its own
  webroot.
