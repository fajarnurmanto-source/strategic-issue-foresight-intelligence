# SIFI S1.7I — Controlled Local Demo and Catalog Audit v0.1

Status: PASS — CONTROLLED LOCAL DEMO AND RECORD DISCOVERY VERIFIED

SIFI now has an explicit, idempotent local demo operation through
`scripts/demo-local.ps1`. It materializes one completed eleven-step workflow
session and one evidence-linked brief using only synthetic, rights-safe
objects. The records visibly state that they are analytical-only and cannot
authorize production action.

The append-only persistence layer now supports bounded latest-version listing.
Workflow and brief repositories expose that list through the local runtime,
and the read-only web application presents minimal catalog summaries before a
user chooses a record. Full record content remains behind exact-ID retrieval.

Verification completed:

- 7 focused S1.7H/S1.7I tests PASS;
- 333 full-regression tests PASS;
- demo creation and second-run idempotence PASS;
- actual Windows demo script PASS;
- browser catalog lists the demo session and brief;
- browser selection opens the evidence-linked brief;
- rendered brief shows `analytical_only=true` and
  `production_action_authorized=false`.

This slice improves local product demonstrability; it is not evidence of live
source coverage, factual monitoring performance, representative-user
operability, decision value, production deployment, CB22 PASS, or commercial
readiness. The next safe slice is a local operator guide and repeatable release
check for the current non-production application package.
