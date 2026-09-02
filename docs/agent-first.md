# Minn for agents

Who the engine is for, what an agent needs from a web platform, and how Minn becomes that platform without asking anyone to leave WordPress.

*Working draft, September 2026. Companion to `docs/vision.md` (the thesis) and `docs/architecture-review-2026-09-02.md` (what the code has to change first).*

## 1. Who Minn is really for

The vision says WordPress is a coordination standard and the contracts are the moat. Minn inherits the moat through compatibility. That answers "why compatible". It does not answer "for whom", and the honest answer is not the one a CMS usually gives.

Minn is not for site owners in the way WordPress is. A site owner meets Minn Admin, and Minn Admin runs on WordPress today. They will not notice the engine, and that is the point of the seams.

Minn is not for plugin authors either. A plugin author writes against WordPress's API, and Minn's runtime speaks it. There is nothing for them to target.

Minn is for whoever operates the site, and increasingly that is not a person. In order:

1. **The operator.** The `wp_*` schema, `wp-config.php`, WP-CLI and `wp/v2` were always the host's contract. Hosts also carry the security bill: the plugin that can do anything the PHP process can do is the host's problem when it does. A compatible runtime that is small enough to audit, and can confine a plugin, is a hedge every operator wants and no host can build on top of WordPress itself.
2. **The site owner, indirectly.** They want their plugins, not ports, and that decision is made. What they do not want is the exposure. "Your form plugin cannot create an administrator" is a sentence an owner understands and WordPress cannot say.
3. **The agent.** Growing fastest. An agent that operates a site needs a complete self-describing surface with permission as data, a way to hold less than full authority, a cheap way to try a change before applying it, a runtime it can reason about when something goes wrong, and a ledger of what it did. WordPress offers the first partially and the rest not at all.

### Why lean code compounds

The question behind this document was whether lean, well-thought-out code has effects that compound past what WordPress's codebase can reach. It does, but the mechanism is not the one usually named. Better code does not attract better humans. What compounds is that a small codebase can be held to a contract corpus completely, and a large one cannot.

Fast-forward both projects. WordPress's contributor is a human, its backward-compatibility promise is total, and its runtime is 1.8 million lines that nobody holds in context. Every change is archaeology. Minn's contributor is an agent gated by fifty-one suites and several thousand pinned facts, and the whole engine with its facade is about 80,000 lines, which a frontier model reads in one sitting. A WordPress change costs a person days of reading. A Minn change costs a capture, an implementation and a proof, and the proof is the one anyone would have demanded anyway.

That is also the customer-control argument made concrete. On WordPress the exit is "find another freelancer". On Minn it is "point any coding agent at the contracts", and the contracts are fixtures, not prose.

## 2. What an agent needs from a web platform

Five properties. Each is stated so it can be checked, with where WordPress and Minn stand.

| Property | WordPress | Minn today | Minn after the work below |
|---|---|---|---|
| **A catalogue.** Every action has a name, an input schema, an output schema and a permission, in one document. | The REST index lists args. Authorization lives in handler bodies. The Abilities API (6.9) is a registry plugins fill; core's own abilities are read-only and few. | 228 routes carry a policy attribute (144 declared, 84 still bare); args are declared on 15 and enforced on none; `wp-abilities/v1` is served with three abilities. No route has a name or an output schema. | Every route is an ability. One exporter writes OpenAPI and the abilities catalogue from the same typed table. |
| **Least authority.** A caller holds only what it needs, and what it used is visible. | A user is their role. An application password carries the user's full capabilities. A plugin is the process. | Policy is data on the route. An application password is still the user. A plugin is still the process. | A `Grant` per caller: user, agent key, plugin, extension. Judged beside the policy. Plugins confined at the doors their calls already funnel through. |
| **Try before apply.** Clone, change, diff, apply, or throw away. | Playground and wp-codebox, as separate products. | `minn install` / `eject` and a single-folder deploy unit. | SQLite behind `Db` and `minn clone`: a disposable copy in under a second, the parity suites as the diff. |
| **A runtime small enough to reason about.** | 1.8 million lines. A fatal is a white screen. | 80,000 lines. Recovery mode names the plugin that failed. | The same, with the caller of every refused action named. |
| **A ledger.** Every change attributable and reversible. | Revisions for posts. Nothing for options, roles, plugins, updates. | Revisions on REST post edits from the second edit on; transactions on five compound writes; the archive hash of each applied update, overwritten per folder. | An append-only journal of writes across options, roles, plugins and updates, per caller, with the revert beside each entry. |

## 3. The landscape, briefly

Dated research, gathered September 2026; each claim is worth a click before it goes further than this file.

- **WordPress** shipped the Abilities API in 6.9 (`wp_register_ability()` with JSON schemas, an execute callback and a permission callback, served at `wp-abilities/v1`), an MCP adapter as a pre-1.0 release, and a PHP AI client plus a Connectors hub in 7.0. The permission model is the user's capabilities. Nothing scopes what a plugin may do.
- **Cloudflare EmDash** (MIT, TypeScript) runs each plugin in its own isolate with a manifest declaring capabilities and hostname-restricted network. It speaks none of `wp_*`, `wp/v2` or plugin hooks. Sandboxing without compatibility.
- **Hosted MCP servers** exist for Shopify, Wix, Webflow, Sanity, Payload and Contentful, each for its own platform. Payload's per-collection toggles bound to a user are the closest thing to a scoped agent credential.
- **Generators** (Lovable, Big Sky, v0) own "make me a site from a prompt". Not a slot to compete for.
- **Automattic's** "OS of the Agentic Web" and wp-codebox say what agents want: a throwaway copy to try changes in.

The intersection is empty: run the existing `wp_*` data, the existing `wp/v2` clients, WP-CLI and hosting tooling, confine each plugin to declared capabilities, and describe every capability to an agent with a schema and a permission. WordPress has the first half. EmDash has the second. Minn can have both, because the compatibility exists and the runtime is small enough to gate.

## 4. The four surfaces Minn offers an agent

### The catalogue

Speak the Abilities API; it is the seam to speak, not to invent. `wp-abilities/v1` is served, so the WordPress MCP adapter and every client written for it work against Minn unmodified. That is the compatibility play applied to agents: inherit the client ecosystem through the interface.

The differentiator is one layer down. On Minn a route's authorization is a `Policy` on its attribute and its parameters are `Args` on the same attribute, so the engine's own surface can be exported as an ability catalogue mechanically: every route becomes `{name, description, input schema, output schema, permission}`. The same reflection that writes `docs/api/` writes an OpenAPI document and registers each engine route as an ability. An agent reads one document and knows everything Minn can do and what it takes to do it. WordPress cannot produce that document, because its authorization is scattered across handler bodies.

What this asks of the engine: the route attribute gains a name, a body set and an output; the gate validates declared args before it judges the policy; the 84 bare routes state their policy or state the 404 they answer first; the route table reads without a database; and an exporter reads it. The review has the line numbers.

### The grant

Today there is one kind of caller: a user, with the capabilities of their role. Minn adds three more, all judged by the same gate, all data:

| Caller | Authority comes from | Held as |
|---|---|---|
| **User** | role and capabilities, unchanged | the WordPress model |
| **Agent key** | an application password with a scope: a list of abilities, a policy floor (read-only, own content, no role grants), an expiry | `Auth\Grant` stored as an extra key in the reference's application-password meta, which WordPress ignores; judged beside the route's `Policy` |
| **WordPress plugin** | an inferred manifest: what its code asks for, read statically from the folder before it ever runs | `Runtime\Grant` per plugin, enforced at the doors below |
| **Minn extension** | a declared `needs` block in `minn.json` | `Extension\Grant` built by the loader; `Seams` hands out only what was granted |

The agent key is the one WordPress is missing most obviously. An application password on WordPress is the user, full stop. On Minn the credential can carry less than the user: "this agent may read content, draft posts under its own author, and run the SEO audit ability, until Friday". The router judges the policy and the grant together, and the journal records which key did what. That is how a site owner hands an agent the keys without handing it the site.

### The staging primitive

An agent wants to clone, try, diff and apply. Minn has install, eject, a single-folder deploy unit and no build step. Two additions make the loop cheap enough to run on every change: a SQLite dialect behind `Db` (it is the one door, so the driver is one class), and `minn clone <site> <scratch>`, which snapshots files and database into a scratch root and boots the engine there.

Then the parity suites become the diff engine. An agent stands up a copy in under a second, makes its change, renders the site's own pages on both copies with the original as the oracle, and presents the difference. This is the method the engine was built with, handed to whoever operates it.

### The journal

Revisions cover posts, and only from the second edit on. Nothing covers the writes that actually hurt: an option changed, a role granted, a plugin activated, an update applied. The journal is an append-only line per write at the funnels that already exist: `Runtime\Options`, the capability write, `Content\PluginState`, `Ops\Updates`, theme activation, sessions, cron outcomes. Each line names the actor (user, agent key, plugin, extension, cron, CLI), the door, the value before and the value after. Revert is a line read back through the same door.

Minn Admin's system view reads it as one more log source. An agent reads it through one ability. An operator reads it when something went wrong, which is when a 1.8-million-line runtime offers a white screen.

## 5. Confinement: how far "any plugin just works" and "every plugin is confined" can coexist

The two promises pull against each other. This is how far the second goes without breaking the first.

A loaded plugin reaches the world through seven doors. Through the facade, most of them already funnel to one place, and the loader already knows which file it is including, so a call can be attributed to the plugin that made it once that knowledge is kept. Through raw PHP, some cannot be fenced in process, and the manifest says which is which.

| Door | Funnel | Attributable | Enforceable per plugin |
|---|---|---|---|
| Options | `Runtime\Options` (four statements), plus the engine's own `Site::setOption`, which becomes the same door | yes | yes: a deny list per plugin for `active_plugins`, `siteurl`, `home`, `default_role`, `users_can_register`, `wp_user_roles`, `admin_email`, and the engine's own recovery and symbol-cache options |
| Roles and capabilities | the capabilities meta write and the `wp_user_roles` option, gathered into `Auth\Grants` | yes | yes: "may not grant administrator or `manage_options`" is one rule in one place |
| Outbound HTTP | `Http\Client::send` | yes | yes for `wp_remote_*`: host allowlist; `curl_init`, `fsockopen` and the facade's own mailer are flagged statically |
| SQL | `Db::run` and `wpdb::query`, which today calls the driver directly with a public handle and joins `Db` as `Db::raw()` | yes, one frame | partially: table names parsed from the statement, writes outside declared tables refused; `new mysqli` cannot be mediated |
| Filesystem | none | no | static only: write builtins outside uploads flagged; do not promise enforcement |
| Code execution | the include sites, `eval` | symbol gate + recovery | static only: `eval`, variable includes and `activate_plugin` flagged |
| Hooks and pluggables | `Hooks::remove`, `removeAll`, and by-reference edits of `$wp_filter` | partially | log first: hooks are WordPress's cooperation model, so cross-plugin removal is recorded, not refused; engine-owned callbacks are protected |

Three tiers, in the order to build them:

1. **Infer (a day).** The symbol gate already tokenises every plugin folder and records every call, builtins included. It drops the literals. Record the literal arguments for a fixed sink list (hosts, tables, roles, option names) and flag `eval`, variable includes and backticks, and print the result in `wp minn preflight` and in the Extensions view before activation: *this plugin wants network (api.example.com), writes tables (wp_foo), grants (administrator), overrides (wp_mail)*.
2. **Enforce at the funnels (a milestone).** Options, capabilities and HTTP first, SQL second, with attribution kept from the include through the hook table. A site chooses its mode: `observe` (journal only), `enforce` (refuse what the manifest did not declare), `strict` (refuse and pause the plugin). Borrow Chrome's split of install-time versus optional runtime grants, and Apex's per-namespace governor limits so a misbehaving plugin degrades rather than takes the site down.
3. **Isolate (later, native extensions only).** WordPress plugins cannot leave the process without breaking hooks. Minn extensions can, because `Seams` is typed, closure-based and global-free. A WASM extension gets only the host functions exposed to it. That is where "cannot open a socket" becomes true rather than policed. The precondition is the `needs` grant, declared now while there are ten extensions.

The incidents of 2026 write the pitch: a purchased plugin catalogue pushing a backdoor through legitimate updates, a CDN compromise creating rogue administrators on a million sites, an update pipeline shipping a `wp-config.php` stealer. Each needed the process's authority. On a runtime where "slider plugin grants administrator" is a refused write in a journal, most of them are a log line.

## 6. A new kind of marketplace

List capabilities with declared permissions, not plugins with screenshots.

What exists: wordpress.org has human review, no permission model and no revenue for authors. The MCP registries verify a namespace and do no security review. The agent directories review humans-first and pin commits without re-review on bumps. Nobody combines review, runtime permission grants and revenue in one listing.

A Minn listing is three things: a manifest, a provenance record and a review status. Four kinds of thing are worth listing.

| Kind | What the listing shows | What Minn adds |
|---|---|---|
| **WordPress plugins**, unchanged | the inferred permission manifest, the WP Registry finding history, the permission diff between versions | the manifest and the diff; nobody else has either |
| **Minn extensions** on `Seams` | the declared `needs`, the licence, later the isolation level | the only category where "cannot" is literal |
| **Abilities** | schema, permission, what registered it | agent-callable units: an SEO audit, a form-to-CRM bridge, an image pipeline; discovered through the MCP adapter, scoped by whatever registered them |
| **Recipes** | an operator-side procedure: "migrate this site and confirm parity", "quarterly plugin audit", "set up a newsletter" | skills in the coding-agent sense; they need nothing from the site except the catalogue |

Trust borrows what worked elsewhere: install-time versus optional runtime grants as the UX, a permission diff between two versions as the review trigger, and a build hash as identity, which WP Registry already is.

Where it lives: not in a storefront. WP Registry already keys on build hashes and holds findings, coverage and salvage patches. The listing is a registry view that also carries manifests. Minn Admin's Extensions view is the client, and it already installs from a URL or a GitHub release. Revenue, if any, is host-side: the operator pays for confinement and review, and Anchor is the first operator. Build the manifest, the diff and the registry link. Let the listing be a page that reads them.

## 7. Wiring in WP Registry

CaptainCore Manager did this once for the fleet: a registry client, a coverage map from installed hashes to findings, and salvage patches for abandoned plugins. The engine has the same raw material natively, and one lesson to bake in: always ask the public projection, never the authenticated one, so an embargoed finding never reaches a site owner's screen. The registry's own code settles the details below.

**The build hash** is the registry's identity for a build and four clients already compute it the same way: every file under the folder as a line `sha256  prefix/slug/path` (two spaces, paths relative to `wp-content`, `node_modules` and `.git` skipped, `.DS_Store` and `error_log` skipped, a symlink hashed as `SYMLINK:` plus its target), sorted bytewise on the path, joined with newlines, a trailing newline, hashed once more. `Ops\BuildHash` reproduces it; the rule goes into `contracts/` as data so it cannot drift.

**R1, coverage.** Batch the hashes through the public check route (a shard by prefix, or one POST for up to five thousand), cache the verdicts twelve hours beside the update offers, and show them in preflight and the Extensions view. What the display has to say: the public answer carries a verdict, a malware flag and a key issue, and no slug or findings count; a finding count needs one more public call per build; a build whose only findings are embargoed reads as clean, so the word on screen is "no published findings", never "verified safe"; a per-site variant of an audited plugin reads as unaudited, and the registry has no public query by slug and version; single-file plugins are hashed by no client today. Preflight goes AMBER on an unaudited build and RED on an open critical.

**R2, salvage.** When wordpress.org has no offer and the public patches manifest has a salvage build for that slug and vulnerable version, `Ops\Updates` lists it as the offer, marked as a registry salvage with its report linked. A salvage qualifies when it is listed on wordpress.org, wordpress.org has nothing newer, and the download is a zip (an advisory-only entry carries a sentinel URL instead). The manifest carries no hash for the patched build, so verification is "install, re-hash, look up": a correct install resolves as audited and clean. Fetch with GET, never HEAD. The Beacon closure feed lights the plugin as closed upstream in the same view.

**R3, the new part.** The inferred manifest from tier 1 above is a registry artifact keyed by build hash. Nothing accepts one today: the only anonymous write is the component-upload route, which validates a zip by construction, and every other write needs the origin's admin credential. R3 therefore starts on the registry side: a hash-keyed manifest route with a public read projection gated on the same nameability rule the shards use, so a bespoke plugin's declared capabilities do not become another oracle for its code. Then the registry answers a question no scanner answers: what changed in what this plugin is allowed to do between two versions? A slider whose new build adds a capability grant and a call to a host it never called is the supply-chain pattern in one line of diff, before any site installs it.

## 8. What this asks of the engine first

The internal API is about to freeze at 0.1.0. Everything above is cheaper before that than after. `docs/architecture-review-2026-09-02.md` carries the finding-by-finding list with its costs; the order is:

1. The functional gaps the review found on the way, starting with the WordPress `cron` option that nothing fires.
2. The route attribute settled (name, body, output, index, the 404 an `Own` policy answers first), declared args enforced before the policy, the route table typed and database-free.
3. Who: a plugin identity kept from the include, attribution on the runtime, an owner on every hook.
4. Where: one funnel per door as a typed method, `wpdb` joining `Db`, the state bag retired, `Runtime::set` no longer public.
5. The extension manifest declares `needs`; `Seams` loses `db`; one `Seams` per extension.
6. The engine's front end goes through `Seams`; the facade becomes one participant; render state travels as an argument.
7. One `PostType` declaration; reversibility owned by the writer; one door for authority with per-user grants honoured; a typed actor.
8. One verb to build a shape; `Access::Floor` becomes a factory; `Ops` refuses in its own type.

Then, additive: the catalogue exporter and route abilities, the agent key, the inferred manifest and the doors in `observe` mode, the journal, SQLite and `minn clone`, R1 and R2, then `enforce` and R3.

## 9. Honest hard parts

- **Worker mode is blocked by plugins, not by the engine.** Plugin files register their hooks as they are included, and an include happens once per process. A second request in the same process inherits the first's registries. Plugin-free sites can run in a worker; plugin-bearing sites stay one request per process until plugin registration can be replayed. The attribution journal is the same data a replay needs, which is a reason to design its entries now.
- **Raw PHP is not fenced.** A plugin that opens its own database connection or socket walks past every door. The manifest says which door each call went through, and the static scan flags the bypasses, but "confined" is a property of facade-bound code.
- **The MCP adapter is pre-1.0** and the Abilities API's write side has no date. Speaking it is cheap; depending on it is early.
- **Nobody may want confinement.** Hosts have shipped "any plugin, any authority" for twenty years and site owners rarely ask. The bet is that agents change that, because an agent's operator asks what the agent can do before letting it run, and the same question then gets asked of plugins.
- **A Minn-native extension ecosystem may never form.** Anything targeting the WordPress API is a WordPress plugin. Extensions on `Seams` are the one place a new licence and a new permission model both apply, and it may stay a small place. The plan above does not depend on it.
- **Bus factor.** The customer-control argument transfers only once someone other than the founder can maintain the engine. MIT, machine-readable contracts, exhaustive suites and agent legibility are the mitigation, and they deserve naming.
