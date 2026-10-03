# SIFI Working Baseline Successor v0.2

Status: WORKING BASELINE SUCCESSOR — NOT FROZEN
Predecessor: SIFI Project Planning — Working Planning Baseline v0.1

## 1. Successor Scope
All provisions of v0.1 remain active unless explicitly superseded here.

This successor replaces any interpretation that makes Kota Yogyakarta
a permanent product boundary.

## 2. Product Geography Principle
SIFI is a geography-configurable Strategic Issue & Foresight Intelligence platform.

Kota Yogyakarta is the first pilot Coverage Profile, not the product boundary.

Core application code must not hard-code a city, regency, province,
or national geography where the behavior can be represented through
configuration, registries, hierarchy, ontology, or profile metadata.

## 3. Minimum Geographic Levels
Stage 0 must support at least:
1. Kabupaten/Kota
2. Province
3. National

The object model must remain extensible to lower or wider geographic levels.

## 4. Geographic Semantics
SIFI must keep distinct:
- source_editorial_scope
- source_geography
- event_geography
- mentioned_geographies
- affected_geography
- policy_jurisdiction
- relevance_geography
- coverage_profile_id
- geographic_relation
- relevance_state
- relevance_reason

Source geography must never be used as a shortcut for issue relevance.

## 5. Coverage Profile
A Coverage Profile is a configurable analytical lens.

Minimum concepts:
- profile_id
- profile_name
- geography_id
- geography_level
- parent_geography_id
- aliases
- timezone
- language
- local_entity_dictionary
- official_source_pack
- media_source_pack
- neighboring_area_rules
- external_relevance_rules
- enabled_issue_domains
- profile_version

## 6. Geographic Relevance Ladder
R0 DIRECT
Event and/or impact directly affects the active area.

R1 CROSS_LOCAL
Event occurs outside the active kabupaten/kota but has a defensible
cross-local effect.

R2 PROVINCIAL
Province-level issue applies to or materially affects the active area.

R3 NATIONAL
National issue has a defensible mechanism of effect on the active area.

R4 CONTEXTUAL
Useful comparator or strategic context without demonstrated direct impact.

RX IRRELEVANT
No defensible relation to the active Coverage Profile.

These are explainable relevance states, not one opaque score.

## 7. Relevance Dimensions
Where necessary SIFI exposes separate dimensions:
- geographic fit
- jurisdictional applicability
- affected-population linkage
- economic/fiscal linkage
- infrastructure linkage
- environmental linkage
- mobility linkage
- institutional linkage
- evidence strength

## 8. Cross-Level Intelligence
One Issue Object may span multiple geographic levels and Coverage Profiles.

The system must support relationships such as:

National
  -> Province
      -> Kabupaten/Kota

A national issue can therefore be highly relevant locally without occurring
physically inside that local area.

## 9. Source Architecture
Sources are not owned by one Coverage Profile.

SIFI must support:
- shared/global sources
- national source pack
- provincial source packs
- kabupaten/kota source packs
- Source-to-Coverage bindings

One source may serve multiple profiles.

## 10. Pilot and Portability
Primary pilot Coverage Profile: Kota Yogyakarta.

Working shadow portability profile: Kota Surabaya.

The shadow profile exists to test whether a materially different region can
be configured without changing core application code.

## 11. Portability Gate
Before Stage-0 freeze SIFI must prove:

1. A second Coverage Profile can be created without core-code changes.
2. The same corpus can be evaluated under different active profiles.
3. Kota Yogyakarta, DI Yogyakarta, and Indonesia views may produce
   different relevant-issue sets from the same evidence universe.
4. Differences are explainable by profile/relevance rules.
5. Source data does not need to be rewritten when the active profile changes.

## 12. Epistemic Guard
Geographic relevance is not empirical truth.

Media coverage level, publication volume, source popularity, and geographic
mention frequency do not by themselves establish prevalence, severity,
causality, or impact.

## 13. Implementation Guard
Before Stage-0 freeze:
- no geography-specific hard coding;
- no source popularity as relevance authority;
- no promotion from mention to affected geography without evidence;
- no automatic national-to-local relevance;
- no irreversible cross-level merge without audit trail;
- human review must remain available.

