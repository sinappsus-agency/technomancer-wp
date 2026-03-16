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
