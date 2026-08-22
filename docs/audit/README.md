# Audit notes

This directory holds optional audit artefacts that support architecture decisions without duplicating the frozen specification.

## Purpose

During discovery, integration behaviour is validated on a reference WordPress + WooCommerce site. Findings are generalised into [docs/plans/MASTER_PLAN.md](../plans/MASTER_PLAN.md). Site-specific evidence (exact option values, currency lists, hook callback inventories) is recorded here only when it helps future implementers repeat the audit method.

## What belongs here

- Repeatable audit checklists (hook enumeration, DOM contract verification).
- Currency or zone discovery snapshots taken at a point in time (generic labels only).
- Eligibility-filter audit worksheets for M2 provider truthfulness.

## What does not belong here

- Proprietary project branding, internal hostnames, or filesystem paths.
- Functional plugin code or configuration that alters a live site.

## M2 eligibility audit method

Before enabling the WooCommerce free-shipping provider:

1. List all callbacks registered on `woocommerce_shipping_free_shipping_is_available`.
2. Identify filters that alter cart subtotals used inside `WC_Shipping_Free_Shipping::is_available()`.
3. For each callback, determine whether a threshold-only message (`Free shipping on orders of {amount} or more`) could become untruthful.
4. If truthfulness cannot be proven, suppress the provider and record the conflicting hook in an admin diagnostic.

Re-run this audit on every target site before enabling the provider in production.
