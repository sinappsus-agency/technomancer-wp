# Technomancer WP

Technomancer WP is a WordPress automation plugin that connects WordPress and WooCommerce events to external workflows (n8n), and includes built-in integration modules for Notifuse and ERPNext.

## What This Plugin Provides

- Event-driven automation flows
- Secured REST endpoints for controlled read/write actions
- WooCommerce integration hooks and product enhancements
- Notifuse integration tooling
- ERPNext integration tooling
- Delivery logs, replay support, and admin tooling

## API Namespace

The REST route namespace currently remains:

- `/wp-json/sinappsus-n8n/v1`

This is intentionally preserved for compatibility with existing automations.

## Automated Updates

This plugin uses plugin-update-checker and supports two update modes:

1. GitHub mode (default)
2. Metadata JSON mode

Update bootstrap is implemented in `src/Core/Updater.php`.

### Default GitHub Source

- `https://github.com/sinappsus-agency/technomancer-wp/`

### Updater Filters

```php
add_filter('snc_update_metadata_url', static function () {
  return ''; // Optional JSON manifest endpoint.
});

add_filter('snc_update_source', static function () {
  return 'https://github.com/sinappsus-agency/technomancer-wp/';
});

add_filter('snc_update_branch', static function () {
  return 'main';
});

add_filter('snc_update_slug', static function () {
  return 'technomancer-wp';
});

add_filter('snc_update_token', static function () {
  return ''; // Required for private repositories.
});
```

### Metadata JSON Example

```json
{
  "name": "Technomancer WP",
  "slug": "technomancer-wp",
  "version": "0.1.1",
  "download_url": "https://updates.example.com/technomancer-wp-0.1.1.zip",
  "requires": "6.0",
  "tested": "6.8",
  "requires_php": "8.0"
}
```

## Automated Release Pipeline

Workflow file:

- `.github/workflows/release-plugin.yml`

On push to `main`, it will:

1. Check out repository + submodules
2. Build release ZIP via `scripts/build-release.sh`
3. Create or update release tag `v{version}`
4. Upload ZIP asset used by update checks

ZIP artifact naming:

- `dist/technomancer-wp-{version}.zip`

## Release Process

1. Bump plugin header `Version` in `sinappsus-n8n-connector.php`
2. Commit and push to `main`
3. Confirm workflow success in GitHub Actions
4. Confirm release `v{version}` includes ZIP asset

If `Version` is not increased, installed sites will not detect a newer update.
