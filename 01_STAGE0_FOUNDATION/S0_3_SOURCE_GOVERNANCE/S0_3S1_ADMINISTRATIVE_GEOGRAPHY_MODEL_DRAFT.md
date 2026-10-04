# SIFI S0.3S1 — Administrative Geography Model Draft

Status: WORKING DRAFT — NOT FROZEN

## 1. Purpose
This contract defines how SIFI represents administrative geography
independently from any single pilot region.

Kota Yogyakarta is a benchmark geography, not a hard-coded product boundary.

## 2. Core Object
Object: GeographyNode

Minimum fields:
- geography_id
- canonical_name
- geography_type
- administrative_level
- parent_geography_id
- country_code
- official_administrative_code
- aliases
- valid_from
- valid_to
- active_state
- provenance
- schema_version

## 3. Minimum Administrative Levels
Required for Stage 0:
- NATIONAL
- PROVINCE
- REGENCY
- CITY

Future-compatible but not required for Stage-0 freeze:
- DISTRICT / KECAMATAN
- VILLAGE / KELURAHAN
- NON_ADMINISTRATIVE_REGION
- FUNCTIONAL_REGION

## 4. Hierarchical Relations
Minimum deterministic relations:
- SAME_AS
- PARENT_OF
- CHILD_OF
- ANCESTOR_OF
- DESCENDANT_OF

Additional relations may later include:
- ADJACENT_TO
- OVERLAPS
- FUNCTIONALLY_CONNECTED_TO

These additional relations must not be invented without evidence.

## 5. Geographic Identity Rule
Geography identity must use stable IDs.

Display names are attributes, not identifiers.

Aliases such as:
- Kota Yogyakarta
- Yogyakarta City
- Jogja
may resolve to the same GeographyNode only when defensibly mapped.

## 6. Event vs Administrative Geography
A GeographyNode describes a geographic entity.

It must not collapse:
- source geography
- event geography
- affected geography
- policy jurisdiction
- relevance geography

into one field.

## 7. Multi-Geography Events
One Evidence Item or Issue Object may reference multiple GeographyNodes.

Example:
TPA Piyungan event location:
- Kabupaten Bantul

Potential affected geographies:
- Kabupaten Bantul
- Kabupaten Sleman
- Kota Yogyakarta

The physical event location does not define the entire relevance geography.

## 8. Administrative Change
Boundary or administrative-code changes must be versionable.

Historical evidence must remain interpretable using the geography version
valid at the relevant time.

## 9. Unknown State
Unknown or unresolved geography must remain explicit:
- GEOGRAPHY_UNKNOWN
- GEOGRAPHY_AMBIGUOUS
- GEOGRAPHY_UNRESOLVED

Unknown geography must not default to the active Coverage Profile.

## 10. Stage-0 Guard
No core application rule may contain geography-specific logic such as:
if city == "Yogyakarta"

Region-specific behavior must be represented through data, profile,
registry, ontology, or explicit configuration.

