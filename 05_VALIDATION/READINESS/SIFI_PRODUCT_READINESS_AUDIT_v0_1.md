# SIFI Product Readiness Assessment v0.1

Assessment date: 15 October 2026  
Basis: commit `7a5d4a8`, 350 Windows-local tests PASS

## Current indices

| View | Score | Meaning |
|---|---:|---|
| Controlled non-production prototype | 93% | The authorized synthetic/local scope is nearly complete and repeatably verified. |
| Operational product | 64% | Core and local operation exist, but real acquisition/coverage and representative operability remain absent. |
| Commercial readiness | 52% | Engineering foundation is substantial; the most consequential market, user, data, and production evidence remains missing. |

These are evidence-weighted planning indices, not probabilities of success and
not maturity promotions.

## Weighted commercial-readiness calculation

| Dimension | Weight | Earned | Evidence boundary |
|---|---:|---:|---|
| Governance, contracts, domain controls | 20 | 20 | Stage0 controls and S1.1–S1.5 implemented and regression protected. |
| Benchmark and functional verification | 15 | 13 | Eight materializable canonical cases PASS; 350 total tests PASS; CB22/CB23 remain no-result. |
| Local application and operations | 20 | 18 | Windows lifecycle, dashboard, workflow, resume, backup/restore, audit log, schema guard, and export verified. Real case intake is not established. |
| Live acquisition and representative coverage | 15 | 0 | No live or recurring production ingestion is authorized or verified. |
| Representative-user operability | 10 | 0 | CB22 has preparation artifacts only; no representative participant evidence. |
| Comparative decision value | 10 | 0 | CB23 has not been executed. |
| Production and commercial operations | 10 | 1 | Commercialisation controls exist, but deployment, support, security review, and operating evidence do not. |
| **Total** | **100** | **52** | **Commercial-readiness index: 52%** |

The prototype score uses only D1–D3: 51 earned out of 55 possible, rounded to
93%. The operational-product score uses D1–D5: 51 earned out of 80 possible,
rounded to 64%.

## What can move the score next

The next legitimate increase cannot come from more synthetic code alone. It
requires, in order:

1. external approval and rights-safe task material for CB22;
2. representative intended analysts completing the frozen study;
3. adjudicated CB22 evidence before any L5 claim;
4. an approved CB23 comparator design and measured result;
5. separately authorized live-source acquisition and coverage validation;
6. production security, deployment, support, and commercial operating proof.

Until those events occur, SIFI is a strong local non-production prototype and
L4 engineering candidate, not a product proven ready for sale.
