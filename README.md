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
- ERPNext doctype contract diagnostics and manual sync controls
- Clean internal log system with filtered reads, stats, replay support, and clear action
- Settings storage for API Access, Notifuse, and ERPNext

## Immediate Next Pass

- Expand the event catalog to the full matrix
- Harden ERPNext object mapping and duplicate protection
- Add richer Notifuse consent/list management and transactional triggers
- Add sample payload generation and flow-level payload previews in the admin UI
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

## Notes

WooCommerce-specific features activate only when WooCommerce is active.
