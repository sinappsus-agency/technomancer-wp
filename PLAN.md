# SINAPPSUS n8n Connector Plan

Create a single WordPress plugin that lets admins define multiple automation flows, listen to WordPress and WooCommerce events, send structured payloads to n8n endpoints, expose a secured API so n8n can fetch or update approved WordPress data, and include built-in Notifuse and ERPNext integration areas.

## Build Order

1. Core plugin framework and settings model
2. Event registry and normalized payload builder
3. Flow engine and async delivery queue
4. Logs and replay foundation
5. Secured REST API for reads and tightly scoped writes
6. WooCommerce event layer
7. Notifuse integration
8. ERPNext integration
9. Admin polish, tooling, and operator docs

## Admin Areas

- Overview
- Flows
- Event Catalog
- API Access
- Notifuse
- ERPNext
- Logs
- Tools

## Core Rules

- One plugin, one admin experience
- Multiple flows, each independently enabled or disabled
- WooCommerce support optional
- Event payloads normalized into one consistent structure
- n8n API access protected by token validation and trusted-origin checks
- Notifuse built in
- ERPNext included as an optional advanced feature area

## Current Scope in Code

- Bootstrap and autoloading
- Flow storage
- Broader event hooks across WordPress and WooCommerce
- Webhook dispatch
- Admin shell with connection test actions
- REST shell with entity search and limited write actions
- Security layer for signed n8n requests
- Notifuse and ERPNext client foundations
- Notifuse Elementor widgets and profile-based list assignment
- ERPNext registration/profile fields and catalog or stock tooling
- ERPNext field mapping settings for customers and products
- ERPNext product metadata fields and scheduled sync jobs
- Notifuse shortcode or Elementor list targeting
- ERP field mapping UI instead of raw JSON entry
- Frontend Notifuse form UX with live status handling
- ERPNext contract diagnostics and manual sync execution

## Implementation Status

- Core build order items are implemented in code.
- Replay, dead-letter handling, signed requests, Notifuse sync, ERPNext sync, mapping UI, diagnostics, and manual sync controls are implemented.
- Internal plugin logging supports writes, filtered reads, stats, replay support, and log clearing.

## Remaining External Validation

1. Test against a live WordPress plus WooCommerce site.
2. Test against a real Notifuse instance and confirm exact API path and payload expectations.
3. Test against a real ERPNext instance and confirm doctypes, required fields, warehouse model, and stock behavior.
