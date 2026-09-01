# Universal Site Announcements 0.5.2 — release notes

## Added
- Self-updates from a private update server via the
  [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) v5
  Composer dependency. Active only when `PRIVATE_UPDATE_SERVER` is defined in
  `wp-config.php`; otherwise behaviour is identical to 0.5.1.
- CI workflow that publishes the release package to the update server on each `v*` tag.

## Install
Deploy `universal-site-announcements` **0.5.2** / tag **`v0.5.2`**. Define
`PRIVATE_UPDATE_SERVER` in `wp-config.php` to receive updates.
Rollback: **0.5.1** / `v0.5.1`.
