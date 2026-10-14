# Releasing Minn

A release is a signed `minn.zip` on a GitHub release of this repository. The
update service (updates.minn.run) offers the latest one to every installed
engine, which checks it before it replaces itself (`Ops\EngineUpdate`).

## The gate

A release is cut only when every release measure is at its target (the
`/dev-minn-engine-coverage` gate): the WordPress API accounted for and
proven, hooks, SQL, line coverage, a green full run, ratchets never risen,
no open items, a security sweep since the last release with every finding
pinned, engine purity, the PHP floor, the latest WordPress patch release, no
warnings in the log, public-repo hygiene, the upgrade path, and the hosting
tools. A tracked measure may be short of its target but must not have moved
the wrong way since the last release.

## Steps

1. **Version.** Set the new version in `public/minn/bootstrap.php`
   (`MINN_ENGINE_VERSION`), the fallback in `Cli/MinnCommand.php` and
   `tests/tools/engine-runtime.php`, then `php tests/tools/route-catalogue.php`
   (it records the version). Style must stay green.
2. **Changelog.** `changelog.md` at the repository root: the version's
   heading carries its date, its recap says what the release is for people
   running a site. It never ships; engines read it from GitHub. Minn Admin's
   changelog is Minn Admin's own.
3. **Full run.** `./tests/run-all.sh | tee <log>` on the release commit, with
   the test site quiet. Then `php tests/tools/scorecard.php --run-log=<log>`
   and commit the scorecard with the release.
4. **Build.** `php scripts/build-release.php` builds `dist/minn.zip` from the
   engine as committed, bundles the latest Minn Admin release whole at
   `minn/admin` (checked against the sha256 GitHub publishes), and signs it
   with the working key into `dist/minn.zip.sig`, refusing a key
   `EngineUpdate::KEYS` does not trust. It also writes `dist/minn.zip.sha256`.
5. **Check the build.** Unpack it and run the install suite against it:
   `MINN_TEST_ENGINE_DIR=<unpacked>/minn php tests/install.test.php`. From
   the second release on, also update a site running the previous release
   through `php minn/bin/minn update` (the upgrade path).
6. **Publish (the maintainer).** Tag the release commit `v<version>`, push
   `main` and the tag, and create the GitHub release with `minn.zip` and
   `minn.zip.sig` attached. The update service offers nothing without both.
   Confirm `https://updates.minn.run/v1/minn/releases/latest` names the new
   version.
7. **The site.** `php tests/tools/compat-status.php --site`, commit the theme
   (`site/minn-site`), and deploy minn.run. Redeploy `minn.run/install` when
   `install.sh` changed.

## Keys

`Ops\EngineUpdate::KEYS` holds two public keys (`scripts/release-key.php`):

- the **working key** (`~/Keys/minn-release/ed25519.key`), which signs every
  release;
- the **recovery key**, kept offline away from the working key, which signs
  only a release that replaces a lost or exposed working key.

To replace the working key: make a new working key and a new recovery key,
put both public halves in `KEYS` (dropping the old ones), and sign that
release with the recovery key (`build-release.php --key=PATH`). Every
installed engine accepts it, and trusts only the new pair from then on. A key
is added to `KEYS` in a release signed by a key already trusted, before it
signs anything itself.
