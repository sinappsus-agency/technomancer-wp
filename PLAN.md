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

- Parent folder remains `sinappsus-n8n-connector` by repository choice.
- Main plugin bootstrap file is `technomancer-wp.php`.
- REST namespace is `/wp-json/technomancer-wp/v1`.
- Distribution artifact is `technomancer-wp-{version}.zip`.
