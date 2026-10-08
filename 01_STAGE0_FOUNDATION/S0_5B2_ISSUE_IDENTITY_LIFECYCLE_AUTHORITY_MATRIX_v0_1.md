# SIFI S0.5B2 — Issue Identity, Lifecycle & Authority Matrix v0.1

Status: WORKING CONTROL — NOT FROZEN

## 1. Purpose

This matrix defines high-risk semantic boundaries that must remain explicit
before an Issue Object may be qualified.

| ID | Question | Required distinction | Forbidden shortcut |
|---|---|---|---|
| M01 | Are multiple publications one Event? | Publication identity vs Event identity | Count URLs as Events |
| M02 | Are multiple Events one Issue? | Event lineage vs Issue identity | Keyword similarity alone |
| M03 | Is recurrence present? | Related repeated Events across time | Repeated articles alone |
| M04 | Is the Issue structural? | Durable condition/mechanism evidence | Persistent media attention alone |
| M05 | Did the Issue worsen? | Condition trajectory vs attention trajectory | Media spike = worsening |
| M06 | Did evidence strengthen? | Evidence trajectory vs publication volume | More articles = stronger evidence |
| M07 | Did public interpretation change? | Narrative trajectory vs empirical condition | Narrative change = reality change |
| M08 | Is a merge justified? | Shared canonical Issue identity | Similar labels force merge |
| M09 | Is a split justified? | Distinct problem/mechanism/trajectory | Preserve one cluster for convenience |
| M10 | Has an Issue re-emerged? | Historical lineage + new related Events | Create unrelated new Issue automatically |
| M11 | Is geography affected? | affected_geography | source location shortcut |
| M12 | Is the Issue relevant? | relevance_geography | administrative containment shortcut |
| M13 | Who has legal competence? | COMPETENT_AUTHORITY | geographic relevance = authority |
| M14 | Who can operationally respond? | OPERATIONAL_RESPONDER | legal mandate = capability |
| M15 | Is coordination appropriate? | referral/coordination role | assume direct user responsibility |
| M16 | Is the condition empirically established? | EMPIRICAL_CONDITION | allegation/reporting = fact |
| M17 | Is prevalence established? | observation model / denominator | incident/article count = prevalence |
| M18 | Is causality established? | mechanism + competing explanations | correlation/co-occurrence |
| M19 | Is future development established? | STRATEGIC_HYPOTHESIS | scenario = forecast/fact |
| M20 | Is action automatic? | analytical implication vs human decision | priority = binding policy action |

## 2. Required Lineage Properties

Issue identity operations must preserve:

- predecessor identity;
- successor identity;
- operation type;
- rationale;
- supporting Evidence Items;
- reviewer;
- operation time;
- point-in-time state;
- reversibility where permitted.

## 3. Required Separation

The following dimensions must remain inspectable independently:

- attention;
- evidence strength;
- empirical condition;
- narrative;
- persistence;
- trajectory;
- geography;
- institutional authority;
- operational capability;
- uncertainty;
- hypothesis state.

No universal opaque issue score is authorised by this contract.

