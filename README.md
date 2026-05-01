# SINAPPSUS n8n Connector

WordPress plugin for building multiple automation flows that react to WordPress and WooCommerce events, send structured payloads to n8n, and expose a secured API for approved n8n workflows to fetch or update specific WordPress data.

## Current Implementation

- Plugin bootstrap and autoloading
- Activation installer with flow and log tables
- Admin menu with Overview, Flows, Event Catalog, API Access, Notifuse, ERPNext, Logs, and Tools
- Flow CRUD for the first working flow model
- Event registry for broader WordPress and WooCommerce triggers
- Async webhook dispatch through scheduled events
- REST API for health, flow inspection, logs, entity reads, search, order notes, and limited metadata writes
- Request authorization with bearer token, origin checks, and HMAC signature validation
- Working Notifuse and ERPNext connection clients with admin test actions
- Retry scheduling, dead-letter logging, manual replay, and test-send tools
- Elementor subscribe and unsubscribe widgets for Notifuse
- Admin-controlled Notifuse list memberships per user profile
- ERPNext profile fields injected into registration and profile screens
- ERPNext catalog import, product export, and stock verification admin tools
- ERPNext product-level metadata fields inside WooCommerce product edit
- ERPNext scheduled product and stock sync jobs with interval control
- ERPNext mapping settings for customers and products
- ERPNext stock verification and sync via ERPNext Bin inventory records
- Frontend AJAX UX for Notifuse subscribe and unsubscribe forms
- Flow-level payload previews with saved sample entity IDs and meaningful payload modes
- Notifuse custom event tracking, consent-aware subscribe forms, selectable frontend lists, and transactional email triggers
- ERPNext upsert behavior with duplicate protection and unchanged-payload skip logic
- ERPNext doctype contract diagnostics and manual sync controls
- Clean internal log system with filtered reads, stats, replay support, and clear action
- Settings storage for API Access, Notifuse, and ERPNext

## Remaining External Validation

- Add live runtime verification against real Notifuse and ERPNext instances

## REST Endpoints

- `GET /wp-json/sinappsus-n8n/v1/health`
- `GET /wp-json/sinappsus-n8n/v1/events`
- `GET /wp-json/sinappsus-n8n/v1/flows`
- `GET /wp-json/sinappsus-n8n/v1/logs`
- `GET /wp-json/sinappsus-n8n/v1/entity/{type}/{id}`
- `GET /wp-json/sinappsus-n8n/v1/search`
- `POST /wp-json/sinappsus-n8n/v1/action/meta`
- `POST /wp-json/sinappsus-n8n/v1/action/order-note`

## n8n Callback Usage

Base callback URL:

`/wp-json/sinappsus-n8n/v1`

Headers for n8n callback requests:

- `Authorization: Bearer YOUR_API_TOKEN`
- `Content-Type: application/json` for POST requests
- `X-SINAPPSUS-Signature: HMAC_SHA256(raw_body, signing_secret)` when a signing secret is configured
- `Origin: https://your-n8n-domain.example` when you use trusted origins

Recommended n8n HTTP Request node setup:

- `Method`: `GET` for reads and searches, `POST` for writes
- `URL`: `https://your-site.example/wp-json/sinappsus-n8n/v1/...`
- `Authentication`: none inside n8n, send the bearer token manually as a header
- `Send Headers`: enabled
- `Send Body`: enabled only for POST routes, using JSON mode
- `Authorization` header value: `Bearer YOUR_API_TOKEN`
- `X-SINAPPSUS-Signature` header value: HMAC-SHA256 of the exact raw JSON body when a signing secret is configured

Endpoints n8n should call:

- `GET /entity/{type}/{id}` to read one user, post, page, attachment, or order
- `GET /search?type=user|post|order&term=...&limit=...` to search records
- `POST /action/meta` to write one meta field back onto a user, post, or order
- `POST /action/order-note` to add a WooCommerce order note

Endpoints intended for wp-admin operators rather than n8n bearer-token callbacks:

- `GET /events`
- `GET /flows`
- `GET /logs`

Example request bodies:

`POST /action/meta`

```json
{
  "entity_type": "order",
  "entity_id": 1234,
  "meta_key": "external_invoice_id",
  "meta_value": "INV-9001"
}
```

`POST /action/order-note`

```json
{
  "order_id": 1234,
  "note": "ERP invoice created successfully.",
  "customer_note": false
}
```

## Notes

WooCommerce-specific features activate only when WooCommerce is active.

## Automated Plugin Updates

This plugin now supports automated update checks through plugin-update-checker.

### 1) Install the library in the plugin

Place the library in one of these paths:

- `vendor/plugin-update-checker/plugin-update-checker.php`
- `lib/plugin-update-checker/plugin-update-checker.php`

Example command (inside this plugin directory):

`git submodule add https://github.com/YahnisElsts/plugin-update-checker.git lib/plugin-update-checker`

### 2) Configure update source

By default, the update source is:

`https://github.com/sinappsus-agency/technomancer-wp/`

You can override source, branch, slug, and token via filters:

```php
add_filter('snc_update_metadata_url', static function () {
  // Optional: provide a metadata JSON endpoint instead of a GitHub repo URL.
  return '';
});

add_filter('snc_update_source', static function () {
  return 'https://github.com/your-org/your-private-plugin-repo/';
});

add_filter('snc_update_branch', static function () {
  return 'main';
});

add_filter('snc_update_slug', static function () {
  return 'sinappsus-n8n-connector';
});

add_filter('snc_update_token', static function () {
  return 'ghp_xxxxx';
});
```

### 3) Where version and ZIP URL come from

The installed version comes from the plugin header in `sinappsus-n8n-connector.php`:

- `Version: 0.1.0`

The remote version and package URL come from one of these sources:

- GitHub mode (default): tags or releases from `snc_update_source`
- Manifest mode: JSON endpoint from `snc_update_metadata_url`

In GitHub mode:

- Bump the plugin header `Version` in your plugin.
- Create a matching Git tag/release in the update repo.
- For private repos, return a token via `snc_update_token`.
- ZIP download is taken from the GitHub release asset (when available) or repository archive.

In Manifest mode, your endpoint must return JSON with at least:

```json
{
  "name": "SINAPPSUS n8n Connector",
  "slug": "sinappsus-n8n-connector",
  "version": "0.1.1",
  "download_url": "https://updates.example.com/sinappsus-n8n-connector-0.1.1.zip",
  "requires": "6.0",
  "tested": "6.8",
  "requires_php": "8.0"
}
```

### 4) Optional advanced customization

When the checker instance is ready, this action fires:

`snc_update_checker_ready`

You can use it to apply additional plugin-update-checker options.
