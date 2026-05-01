# Technomancer WP Plan

This document tracks the current functional scope of the plugin.

## Scope

- Event-driven WordPress and WooCommerce automation flows
- Secure REST API callbacks for automation runtimes
- Notifuse integration
- ERPNext integration
- Admin tools for flows, logs, replay, and diagnostics
- Automated GitHub release + ZIP packaging pipeline

## Operational Notes

- Plugin folder remains `sinappsus-n8n-connector` for compatibility.
- Main plugin bootstrap file remains `sinappsus-n8n-connector.php`.
- REST namespace remains `/wp-json/sinappsus-n8n/v1` for compatibility.
- Distribution artifact is `technomancer-wp-{version}.zip`.
