# Release process — Universal Site Announcements

## Canonical version source

The version is declared in **three** places that must always agree:

| Location | Field |
|---|---|
| `universal-site-announcements.php` | `Version:` plugin header |
| `universal-site-announcements.php` | `USA_VERSION` constant |
| `readme.txt` | `Stable tag:` **and** a `= <version> =` changelog section |

`scripts/build-release-package.sh` refuses to build unless header == constant ==
`Stable tag`, and unless `readme.txt` has a `= <version> =` changelog heading.
`.github/workflows/release.yml` additionally refuses to publish unless all
three equal the pushed Git tag (leading `v` removed). No version file is ever
rewritten by CI.

## Package identity

| Item | Value |
|---|---|
| Deployable directory | `universal-site-announcements/` (sole top-level entry in the ZIP) |
| ZIP | `dist/universal-site-announcements-<version>.zip` |
| Checksum | `dist/universal-site-announcements-<version>.zip.sha256` |

**Included:** `universal-site-announcements.php`, `src/`, `assets/`,
`readme.txt`, `composer.json`, `README.md`, and a freshly generated production
`vendor/` (autoloader only — no third-party runtime dependencies).

**Excluded:** `.git/`, `.github/`, `tests/`, `docs/`, `scripts/`,
`composer.lock`, `phpcs.xml.dist`, `phpunit.xml.dist`, `.phpunit.result.cache`,
`.gitignore`, and any previous build output. There is no `LICENSE` file in this
repository (license is declared in the plugin header, `composer.json` and
`readme.txt`); the packaging script does not require one.

## Build and validate locally

```bash
composer install
bash scripts/build-release-package.sh          # version from the plugin file
bash scripts/build-release-package.sh 0.5.1     # must match the plugin file

cd dist
sha256sum -c universal-site-announcements-<version>.zip.sha256
unzip -l universal-site-announcements-<version>.zip
```

Requires `php`, `composer`, `zip`, `unzip`, `rsync` on `PATH`.

## Cutting a release

1. Bump the `Version:` header, `USA_VERSION`, and `readme.txt` (`Stable tag`
   plus a new `= <version> =` changelog section) in one commit.
2. Merge to **`main`** (the only release branch) and wait for CI to go green.
3. Push an annotated tag matching `v[0-9]+.[0-9]+.[0-9]+` (a `-rc.N` suffix
   marks a prerelease):
   ```bash
   git tag -a v0.5.1 -m "Universal Site Announcements 0.5.1"
   git push origin v0.5.1
   ```
4. `release.yml` re-runs PHPCS and the unit suite, builds the ZIP, verifies the
   packaged version == tag == all three declarations, generates the SHA-256
   checksum, and creates the GitHub Release with the ZIP + `.zip.sha256`
   attached.
5. Both assets appear on the Release page
   (`https://github.com/magpern/universal-site-announcements/releases/tag/v<version>`).

## Using the artifact for deployment

Normal WordPress plugin archive — `wp plugin install <zip> --activate` or
**Plugins → Add New → Upload Plugin**. Verify before deploying:

```bash
sha256sum -c universal-site-announcements-<version>.zip.sha256
```

Generated ZIPs/checksums are CI outputs — `.gitignore`d, never committed.

## Recovering from a failed release

- Failure before "Create GitHub Release" → nothing published. Fix the version
  declarations on `main`, delete the tag
  (`git push --delete origin v<version>`, `git tag -d v<version>`), re-tag.
- Failure during publish → delete the partial GitHub Release, re-run the
  workflow from the Actions tab.
- Always tag a commit already on `main`.

## Known limitations

This repository's automated quality gates are deliberately limited to **PHPCS**
and a **unit** test suite that runs against lightweight stubs
(`tests/bootstrap.php`) — there is no full-WordPress integration matrix, no
static-analysis (PHPStan) gate, and no JS test suite. `release.yml` runs the
gates the repository actually has; broader assurance is out of scope until the
repository grows those suites.
