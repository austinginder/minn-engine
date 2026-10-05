# Minn Engine — vision

Indistinguishable at the seams. Radically simpler inside.

A from-scratch, MIT-licensed engine that speaks WordPress's operational contracts so precisely that the hosting stack cannot tell the difference, paired with Minn Admin as its native interface, and built agent-first from day one.

*Working draft, August 2026. The resumability promise was added October 2026.*

## 1. The thesis: WordPress is not only software, it is a coordination standard

WordPress remains the dominant web platform because customers, hosts, developers, and now AI agents all coordinate there. The portable unit of site, the frozen schema, the REST API, and the labor pool are the product. The PHP is one implementation of those contracts.

What "WordPress" actually consists of, ranked by how much it matters:

1. **A portable unit of site** — files plus a database dump, runnable on any PHP host on earth. Backup tools, migration tools, staging workflows, and entire fleet-management stacks exist because the unit is standardized.
2. **A stable data contract** — the posts, postmeta, options, and users schema has not meaningfully changed in twenty years. Twenty years of tooling compounds on that stability. Backward compatibility is the actual product.
3. **An operational surface** — WP-CLI, the REST API, the file layout, the config conventions. This is what hosting companies automate against, and it is the layer that makes managed WordPress an industry.
4. **A labor pool** — any freelancer can inherit any WordPress site cold. Turnover among designers and developers is high and rising; choosing WordPress is choosing to never be hostage to whoever built the site. This is the customer-control argument, and it is the strongest one.
5. **The plugin ecosystem** — the weakest leg now, and weakening. AI erodes the long tail: a redirect manager or a contact form can simply be generated. What AI does not erode is what a top-tier plugin actually is: a maintenance liability transferred to a vendor who ships security releases. AI-generated bespoke code has no update channel, which makes the engine's own update story more important, not less.

The AI shift sharpens rather than weakens the thesis. When anyone can generate anything on any platform, the rational customer targets the platform where generated work remains portable, inheritable, and operable after the developer leaves. Today that is WordPress, because of legs one through four. The question is whether those legs require the WordPress codebase at all.

## 2. The graveyard lesson: every challenger replaced the software and abandoned the contracts

Ghost, Craft, Statamic, October: none of them dented WordPress, and they all failed the same way. Each replaced the code, which was never the moat, and walked away from the contracts, which were. ClassicPress is the other lesson: it kept the contracts and stayed a fork, so the license came with the tree. A clean break means starting over on portability, tooling, hosting support, and the labor pool simultaneously. No product is good enough to win four moats at once.

Minnow, the earlier experiment, taught the same lesson from inside. It made two choices that doomed it: a clean break (its own docs warned against porting a site from WordPress) and mechanical transmutation of WordPress's source. The first abandoned the moat. The second, it turns out, was also the one legally radioactive move for an MIT project. Both lessons are load-bearing here.

The correct precedent is not "a better CMS." It is Nginx against Apache, MariaDB against MySQL, FrankenPHP against PHP-FPM: a from-scratch engine that speaks the incumbent's interface so precisely that the surrounding infrastructure cannot tell. You do not ask the world to move. You inherit it through compatibility.

## 3. The play: two tiers of compatibility, with a hard line between them

The strategy lives or dies on knowing exactly which WordPress surfaces to honor and which to refuse. The operational contract is sacred. The internal API is not. WordPress speaks to three audiences: tooling, plugin PHP, and humans in a browser. Minn answers the first two. The third is Minn Admin. `/wp-admin/` is not a Minn surface.

The family-level glossary (Speak / Hear / Mute) lives in `contracts/lexicon.md`. Agents read it before adding a runtime symbol.

**Tier 1 — the operational contract:**

- The database schema: read and write real `wp_*` tables, tolerating serialized-PHP blobs forever
- File layout and `wp-config.php` shape, so backup and migration tooling just works
- `wp/v2` REST core routes: posts, media, users, terms, settings
- WP-CLI verb compatibility for the ops core: `core`, `option`, `post`, `user`, `db`, `plugin list`
- Permalinks, feeds, sitemaps, and a block-rendering subset for `post_content`
- Login and session conventions the hosting layer probes

**Tier 2 — the WordPress runtime, reimplemented (decided 2026-08-29):**

The first draft of this document refused hook-level plugin compatibility and answered "but plugins" with agent-made ports. That answer was retired on 2026-08-29: a site owner does not want ported plugins, they want their plugins. So the engine also speaks WordPress to plugin code, the way Wine speaks Win32 to Windows programs and Mono spoke .NET: a clean-room reimplementation of the runtime plugins call into.

- The hook engine (`add_action`, `add_filter`, priorities, `remove_filter`, `current_filter`) and the request lifecycle in the observed order (`muplugins_loaded`, `plugins_loaded`, `init`, `wp`, `template_redirect`, `wp_head`, `the_content`, `wp_footer`, REST, cron, activation and uninstall)
- The procedural API plugins reach for, as a thin facade over the engine's own classes: options and transients, meta, posts, terms, users, `WP_Query` and the Loop globals, `$wpdb` with `prepare` and `dbDelta`, capabilities and nonces, i18n and `.mo` files, shortcodes, script and style enqueueing, HTTP, mail, cron, rewrite rules, `register_rest_route`, kses, filesystem and image APIs
- Admin *registrations*, not admin screens: `add_menu_page` records a row so plugins boot; the Settings API exists as recording. Nothing is rendered. `/wp-admin/` 302s to `/minn-admin/`. Plugin settings PHP is ignored by design. Minn Admin adapters own the UI. This is Hear, not Speak (`contracts/lexicon.md`).
- Block registration from `block.json` and the editor globals plugin scripts expect
- The long tail of runtime symbols, in the order real plugins fatal without them. XML-RPC, the Customizer, the upgrader UI, and multisite stay mute as products.

Plugins are loaded in-process from `active_plugins`, unmodified. No hook shim over WordPress code: there is no WordPress code here to shim. The runtime is the engine.

What this costs, named honestly: the engine is no longer "low tens of thousands of lines", the MIT-extension ecosystem bonus below is gone (anything targeting the WordPress API is a WordPress plugin), and the clean-room discipline in section 5 has to hold across a surface of roughly three thousand functions and twenty-five hundred hooks instead of a REST namespace. The machinery that makes that tractable is in section 5.

**Definition of done, phase one: Kinsta, CaptainCore, Disembark, and UpdraftPlus cannot tell it isn't WordPress.** That sentence is the whole spec. The compatibility suite is a battery of real fleet tooling run against a Minn Engine site: backups restore, migrations round-trip, WP-CLI automation runs, monitors stay green, and Minn Admin boots unmodified.

**The second definition of done: any WordPress site can resume on Minn.** Total compatibility is not the goal. Minn will never run wp-admin, the block editor, or the Customizer, and some sites will need a newer theme or a few different plugins. What Minn promises the site owner is narrower and stricter than compatibility: nothing they had is lost, nothing locks them in, and the site keeps going.

In one sentence: **Minn is a lossless home for a WordPress site. Swap it in and everything you had is still there and still yours. Swap back any time and WordPress finds the site as Minn left it.**

Three guarantees, in order:

1. **Nothing is lost.** Absolute. Every byte WordPress stored survives, including what Minn does not understand: plugin tables, unknown meta, serialized options, block markup the engine cannot render, cron events for plugins it does not run. The engine never deletes or rewrites what it does not understand.
2. **Nothing is locked in.** Absolute. Whatever Minn writes, WordPress reads. Point the same database and files back at WordPress and it runs without a repair step. The same passwords sign in, and pending reset links and application passwords still work.
3. **Nothing stops.** Graded, with a published floor that only rises. The daily work of the site keeps going after the swap. Every public URL answers the same way. Everyone signs in with the same password. Content and media show and can be edited. Scheduled posts publish and email sends.

The first two are gates. The third is where "learn Minn Admin, upgrade the theme, replace a plugin" is allowed.

A ladder makes "usable" precise. Every kind of data sits on a rung: **preserved** (intact, survives a round trip), **visible** (Minn Admin can show it), **editable** (Minn Admin can change it), and **live** (the site uses it). The target is everything preserved, all core WordPress data editable, and plugin data raised rung by rung as adapters arrive. A plugin that has to be replaced leaves its data preserved, and the replacement is what lifts it.

**The test is a round trip.** Take a real site and snapshot it. Swap in Minn and run a scripted day of work: sign in, edit a post, upload an image, publish, approve a comment, change a setting. Swap back and check three things. Every row the day did not touch is byte for byte the same, and every change traces to an operation. WordPress opens the site with no repairs. The day's work is visible to WordPress. Run it across real sites, with fleet backups as the corpus, not one test site. The gates are zero unexplained changes and every swap-back clean. The published scores are URL parity, sign-in parity, and the share of posts that render the same.

The first breaks this goal found were small and real. Password reset keys and application passwords were stored under hashes only one stack could read: a reset link sent before a switch died after it, and every application password made on WordPress stopped working on Minn. Both now use WordPress's own hash, and the suites prove each direction (2026-10-05).

Phase one and resumability answer different people. Phase one is the hosting stack unable to tell. Resumability is the site owner never losing anything.

## 4. The engine

**It stays PHP.** The temptation is Go or Rust, and it is wrong for this strategy: the moat being inherited is PHP hosting. Modern PHP on FrankenPHP-class runtimes is fast, and the infrastructure already speaks it. Target PHP 8.4+, minimal dependencies, no framework baggage, no build step.

**It is layered on purpose.** A small core (router, entities over the wp schema, REST layer, capabilities, a template engine, block rendering, cron, mail) written as modern namespaced PHP, and above it the WordPress runtime facade: the procedural functions, classes, globals and hooks plugin code calls, each a thin delegation into the core. The core stays legible and suite-gated; the facade is wide by necessity and generated from the interface inventory where it can be.

**Minn Admin is the interface, already built.** Minn Admin never touches wp-admin internals. It boots from `window.MINN`, speaks `wp/v2` plus its own namespace, and ships with a hundred-adapter ecosystem, a validator-enforced descriptor contract, and a deep suite culture. An engine that serves those surfaces gets a complete, mature admin on day one.

**Extensions: declarative contracts, imperative handlers.** A manifest declares what an extension is: its entities, routes, capability requirements, admin surfaces, and settings. Plain PHP handlers implement behavior behind those declarations. This generalizes what Minn Admin's surface descriptors already proved.

**Security by declaration.** Authorization in WordPress is imperative and scattered, and years of real-world extension audits show that most access-control failures are stories about exactly that. In Minn Engine, a route's capability requirement is metadata: mechanically auditable, diffable across releases, and verifiable by an agent. Prepared statements only. No unserialize of untrusted data. Escaping at defined boundaries.

**Agent-first is also the product's answer to who it is for.** `docs/agent-first.md` takes the argument the rest of the way: the operator, the site owner and the agent as the three audiences in that order, the four surfaces an agent gets (a catalogue, a grant, a staging primitive, a journal), how far a WordPress plugin can be confined without breaking it, the marketplace the manifests make possible, and the WP Registry wiring.

**Agent-first is a build methodology, not a feature.** The reason nobody has done the ops-compatible rewrite is that it is an enormous amount of boring, well-specified work, which is precisely what agents now do well when gated by suites. Every unit ships with a behavioral suite, the suites rather than the prose are ground truth, and contracts are machine-readable so agents can build, audit, and extend without archaeology. Agent legibility also answers the turnover argument directly: the take-over story for a customer stops being "find another WordPress freelancer" and becomes "point any coding agent at the contracts."

## 5. The license question: can a WordPress-compatible engine be MIT?

**Yes. A clean-room reimplementation of functional interfaces can be MIT licensed, and the license freedom is precisely the reason to reimplement rather than fork.** GPL obligations attach to distributing WordPress's code or derivative works of it. Minn Engine distributes neither. Compatibility is not derivation.

**What copyright does not protect.** US copyright law (17 U.S.C. §102(b)) never protects "any idea, procedure, process, system, method of operation, concept, principle, or discovery." Interfaces, schemas, and protocols are systems and methods of operation. On top of that sits the merger doctrine: where there is only one way to express something (a compatible route must be named `/wp/v2/posts`; a compatible column must be named `post_content`), the expression merges with the idea and is not protectable.

**The precedents.** Google v. Oracle (2021): reimplementing an API surface for a new platform was fair use. Lotus v. Borland (1995): a command hierarchy is an uncopyrightable method of operation. Sega v. Accolade and Sony v. Connectix: reverse engineering for compatibility is fair use. SAS v. World Programming (EU/UK): functionality, languages, and data formats are not copyrightable, and interoperability is explicitly protected under the EU Software Directive. In practice: Wine reimplements the Windows API, Samba reimplements SMB, FerretDB speaks MongoDB's protocol under Apache 2.0, and WP-CLI itself is MIT licensed at the heart of the GPL WordPress ecosystem.

**Fork versus reimplementation is the whole question.** A fork of WordPress is GPL forever; ClassicPress has no choice about its license. A reimplementation written from specifications of behavior is a new work, and its author chooses the license. The decision to build from scratch and the decision to be MIT are the same decision.

**The traps that would actually cause a problem:**

1. **Copied code, including mechanically transformed code** — any actual WordPress source carries GPL no matter how it is reorganized. The Minnow transmuter generated its build from WordPress's own source; that output was a derivative work and could never have been MIT.
2. **Bundled GPL assets** — no WordPress core JavaScript, block-library CSS, bundled themes, or Dashicons ship in the engine.
3. **Loading GPL plugin code in-process** — the engine ships no plugin and derives nothing from one; a host that loads GPL programs is not their derivative (Wine and Mono are the precedent). The live question is the other direction: reimplementing three thousand functions without ever reading their source. That is a process question, answered by the machinery below, and it is the reason the lawyer hour moves from "before launch" to "before the runtime milestone".
4. **Trademark, not copyright, is the live wire** — "WordPress" is a WordPress Foundation trademark, and the current enforcement climate is aggressive. Nominative fair use permits truthful statements ("compatible with WordPress"), but the name stays out of the project name, domain, and anything implying endorsement.

**The hygiene program.** Clean-room purity is the gold standard, not a strict legal requirement; infringement requires copying protected expression, not mere exposure to it. But a documented process is cheap insurance, and agents make it enforceable: spec-first development from behavioral fixtures captured from a live WordPress instance; implementation from the spec, never from WordPress source; an automated similarity gate in CI against the WordPress source tree; tracked provenance for every file; and an hour with an open-source-savvy IP lawyer before the runtime milestone. This document is analysis, not legal advice.

**Where the runtime's spec comes from.** Function names, signatures, defaults, class and method names, hook names and arities, globals: all interface, and all extracted mechanically from the running reference by reflection into `contracts/api/` (the header file). Behaviour comes from probe batteries run through `wp eval` on the reference and recorded as fixtures, then from whole-plugin parity (same plugin, same database, both stacks, diff everything). WordPress's own PHPUnit suite, kept outside the repo like the reference itself, is the conformance harness: running GPL tests against MIT code creates no derivative, and its pass rate is the honest measure of "any plugin works". developer.wordpress.org prints source inline and is therefore not a spec source. Agents implementing the facade are handed the inventory and the fixtures, never the tree.

**The ecosystem bonus that was, and is not.** An earlier draft counted on Minn-native extensions choosing their own licences. With the WordPress runtime as the extension API, plugins are WordPress plugins and inherit whatever WordPress's licence claim reaches. The engine itself stays MIT; that is the promise that matters to the host and the site owner.

## 6. Honest hard parts

- **Lossless means touching less.** An engine that rewrites a row it only half understands (a serialized option re-encoded, meta normalized, block markup reflowed) breaks the first resumability guarantee silently. The engine writes the fields an operation names and nothing else, and the round trip is what proves it.
- **Serialized PHP is forever.** Reading real WordPress databases means tolerating serialized-PHP blobs in options and postmeta indefinitely. Greenfield sites keep it out of new data; migrated sites drag the long tail in. Design greenfield-first.
- **The runtime is the long road.** Front-end plugins work once the hook engine and the content API exist. A plugin's own settings screen is never hosted: Minn Admin adapters own that UI. Editor plugins need the block editor globals. Each layer is measured by the reference test suite and by real plugins. "Any plugin" means front end and data, reached layer by layer, not declared.
- **WooCommerce-shaped sites are out of scope for years.** A large share of real small-business sites are brochure, forms, and content, which is exactly the tractable slice.
- **Bus factor.** The customer-control argument only transfers once someone other than the founder can maintain the engine. MIT plus machine-readable contracts plus exhaustive suites is the mitigation, and agent legibility is the honest answer, but it deserves naming.

## 7. Sequencing: a strangler fig, not a big bang

- **Phase 0 (shipped)** — Minn Admin is the daily admin on WordPress: a complete, REST-pure admin with a proven extension contract, running against real sites today. Classic wp-admin stays as a fallback. On the engine there is no wp-admin; Minn Admin is the admin.
- **Phase 1 (next)** — the engine serves greenfield sites on our own hosting. Front end plus REST for new sites where the whole stack is ours to verify. Minn Admin boots unmodified. The compatibility suite runs the fleet tooling against it until the hosting layer cannot tell.
- **Phase 2** — the WordPress runtime: plugins load unmodified, layer by layer (front end, admin registrations, editor, long tail), measured against the reference test suite. There is no wp-admin; Minn Admin is the admin.
- **Phase 3 (earned)** — existing sites switch over, gated by the round trip in section 3: a site moves when it passes the gates and its scores clear the floor, with the serialized-PHP and dynamic-block caveats enforced honestly by the tooling itself.

Minnow wanted to be a clean break. Minn Engine wants the opposite: indistinguishable at the seams, and radically simpler inside.
