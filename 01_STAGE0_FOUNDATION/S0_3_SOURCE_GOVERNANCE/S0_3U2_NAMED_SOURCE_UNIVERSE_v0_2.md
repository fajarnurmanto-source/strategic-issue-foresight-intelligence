# SIFI S0.3U2 — Named Source Universe v0.2

Status: CANDIDATE SOURCE UNIVERSE — RIGHTS NOT YET FULLY VERIFIED

## 1. Principle

This document identifies candidate sources for Stage-0 benchmarking.

Inclusion here means:
SOURCE DISCOVERED / CANDIDATE

It does NOT mean:
PILOT APPROVED
or
commercial collection authorized.

## 2. Shared / National Candidates

### SRC-BPS
Canonical name:
Badan Pusat Statistik

Family:
OFFICIAL_STATISTICS

Candidate roles:
SHARED_DATA
CORE_NATIONAL

Potential profile bindings:
PROFILE-IDN
PROFILE-DIY
PROFILE-YK
PROFILE-SBY

Rights/access status:
PENDING S0.3V AUDIT

### SRC-DATAGOID
Canonical name:
Portal Satu Data Indonesia / data.go.id

Family:
OPEN_DATA

Candidate roles:
SHARED_DATA
CORE_NATIONAL

Potential profile bindings:
PROFILE-IDN
PROFILE-DIY
PROFILE-YK
PROFILE-SBY

Rights/access status:
PENDING S0.3V AUDIT

### SRC-BMKG
Canonical name:
Badan Meteorologi, Klimatologi, dan Geofisika

Family:
WEATHER_ENVIRONMENT

Candidate roles:
SHARED_OFFICIAL
SHARED_DATA

Potential profile bindings:
PROFILE-IDN
PROFILE-DIY
PROFILE-YK
PROFILE-SBY

Rights/access status:
PENDING S0.3V AUDIT

## 3. Yogyakarta Pilot Candidates

### SRC-JDIH-YK
Canonical name:
JDIH Pemerintah Kota Yogyakarta

Family:
OFFICIAL_REGULATION

Candidate roles:
CORE_LOCAL

Potential profile bindings:
PROFILE-YK

Rights/access status:
PENDING S0.3V AUDIT

### SRC-WARTA-YK
Canonical name:
Portal Berita Pemerintah Kota Yogyakarta

Family:
OFFICIAL_GOVERNMENT

Candidate roles:
CORE_LOCAL

Potential profile bindings:
PROFILE-YK
PROFILE-DIY where independently relevant

Rights/access status:
PENDING S0.3V AUDIT

### SRC-OPENDATA-YK
Canonical name:
Portal Open Data Kota Yogyakarta

Family:
OPEN_DATA

Candidate roles:
CORE_LOCAL
SHARED_DATA

Potential profile bindings:
PROFILE-YK

Rights/access status:
PENDING S0.3V AUDIT

## 4. Province-Level Candidate Pack

The DI Yogyakarta source pack must later contain candidates for:

- provincial official information
- provincial regulation
- provincial statistics/data
- province-wide services
- environment/hazard information
- province-relevant media

Named entries are deferred until verified.

## 5. Surabaya Shadow Pack

The Surabaya shadow profile must eventually contain:

- municipal official source
- municipal JDIH/regulation source
- municipal open-data/statistical source where available
- province-level parent sources
- local media cohort

The shadow pack exists to test portability.

It must not copy Yogyakarta local sources merely to satisfy schema.

## 6. Media Cohort

Commercial/local/national media sources are intentionally NOT approved here.

They require a separate rights/access audit covering:

- robots
- terms
- RSS/feed availability
- sitemap
- metadata retrieval
- article retrieval
- retention
- derived features
- commercial use
- redistribution
- attribution
- crawl limits
- fallback strategy

## 7. Source-Pack Structure

Expected logical structure:

SHARED_NATIONAL_PACK
  -> SRC-BPS
  -> SRC-DATAGOID
  -> SRC-BMKG
  -> future national regulation sources
  -> approved national media candidates

YOGYAKARTA_LOCAL_PACK
  -> SRC-JDIH-YK
  -> SRC-WARTA-YK
  -> SRC-OPENDATA-YK
  -> future approved local media candidates

DIY_PROVINCIAL_PACK
  -> future verified provincial candidates

SURABAYA_LOCAL_PACK
  -> future verified Surabaya candidates

## 8. Guard

A source being present in a pack means:
eligible for discovery/inspection according to Binding policy.

It does not mean:
every Evidence Item from that source is relevant to the active profile.

