# SIFI S0.3S2 — Coverage Profile Contract Draft

Status: WORKING DRAFT — NOT FROZEN

## 1. Purpose
CoverageProfile defines the geographic analytical lens used to determine
which issues are relevant to a user or deployment.

Changing CoverageProfile must not require changing core application code.

## 2. Minimum Fields
- coverage_profile_id
- profile_name
- target_geography_id
- target_administrative_level
- parent_profile_id
- country_code
- timezone
- default_language
- enabled_issue_domains
- enabled_relevance_classes
- local_entity_dictionary_ref
- source_pack_refs
- neighboring_area_rules_ref
- external_relevance_rules_ref
- profile_version
- active_state
- valid_from
- valid_to

## 3. Default Relevance Classes
Operational default:
- R0 DIRECT
- R1 CROSS_LOCAL
- R2 PROVINCIAL
- R3 NATIONAL

Optional analytical context:
- R4 CONTEXTUAL

Normally excluded:
- RX IRRELEVANT

## 4. Profile Inheritance
A local profile may inherit selected configuration from parent geography.

Example:
Kota Yogyakarta
  inherits selected rules from:
DI Yogyakarta
  inherits selected rules from:
Indonesia

Inheritance may apply to:
- shared sources
- jurisdiction rules
- national regulation relevance
- common taxonomy

Inheritance must NOT automatically make every parent-level Evidence Item
relevant to the child profile.

## 5. Same Corpus, Different View
The same evidence universe must support:

Profile:
Kota Yogyakarta

Profile:
DI Yogyakarta

Profile:
Indonesia

and may produce different relevant Issue sets without:
- rewriting evidence;
- recollecting the same source solely because profile changed;
- modifying core code.

## 6. Profile Portability
Stage-0 portability requires at least:
- primary profile: Kota Yogyakarta
- shadow profile: Kota Surabaya

The shadow profile is a structural portability test, not a second full pilot.

## 7. Explainability
Every admitted item or issue must be able to state:
- active profile
- relevance class
- geographic relation
- relevance reason
- evidence supporting that relation
- rule/model version
- human review state

## 8. Profile Failure Conditions
A Coverage Profile fails if:
- geography is embedded in core code;
- profile switch changes stored source truth;
- national items are automatically admitted locally;
- local items are automatically excluded nationally;
- relevance decisions are not reproducible;
- the user cannot inspect why an issue was included.

