# SIFI IH03J — authenticated owner-bound local source collection

Scope: a single browser-submitted Crossref metadata request or ANTARA publisher RSS title/link request, reusing the reviewed IH03B/IH03E services. No new wire adapter, scheduler, fulltext, evidence admission, production admission or main database migration.

## Operational gate

The browser route is disabled unless all of these conditions hold:

- An authenticated, email-verified user owns a `CONFIGURED` monitoring object with the submitted revision.
- The application is in `local`/`testing` mode with `SIFI_IH03J_OWNER_COLLECTION=true`.
- The selected source's own switch is enabled (`SIFI_IH03B_CROSSREF_METADATA=true` or `SIFI_IH03E_ANTARA_RSS=true`), its review has not expired, and the current SQLite database is `:memory:` or an isolated `.sqlite` under `/mnt/d/Research/SIFI/05_VALIDATION/`.
- A real contact email is entered by the user for each request, and source-specific rate limits and one-shot authority (Crossref) remain enforced.

The normal operational database is deliberately ineligible: this release **does not** activate real provider collection in production or silently link the earlier synthetic receipt owner (id 1) to a signed-in account. To use the feature, launch a separate local Laravel instance with an isolated, initialized database and an explicitly created, verified test account/owned object. Preserve the operational database and keys.

The widget discovers eligibility from a same-origin authenticated, owner-authorized GET endpoint, then uses the existing Inertia CSRF-aware POST. All user-supplied endpoint/provider expansion, fulltext retrieval and overposted fields are denied. Successful receipts redirect into the existing owner-scoped research history; HTML and evidence remain separate. GDELT (previously HTTP 429) is not retried.

## Acceptance

Staging PHP lint, owner/authorization regression, TypeScript typechecking and frontend build must pass before the merge-first installer promotes the scope. A successful build alone is not formal visual/desktop/mobile/accessibility acceptance. Local live provider dispatch requires a separate explicit user action and is not part of installation.
