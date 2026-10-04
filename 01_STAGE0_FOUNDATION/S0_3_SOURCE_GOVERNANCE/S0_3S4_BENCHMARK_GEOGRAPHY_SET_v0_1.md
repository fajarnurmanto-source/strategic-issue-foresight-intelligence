# SIFI S0.3S4 — Benchmark Geography Set v0.1

Status: WORKING BENCHMARK — NOT COMPLETE NATIONAL GAZETTEER

## Purpose
Provide the minimum geography set required to test hierarchical relevance,
profile switching, cross-local relations, and portability.

## Benchmark Nodes

### GEO-IDN
Name: Indonesia
Level: NATIONAL
Parent: NONE

### GEO-IDN-YOG
Name: Daerah Istimewa Yogyakarta
Level: PROVINCE
Parent: GEO-IDN

### GEO-IDN-YOG-YK
Name: Kota Yogyakarta
Level: CITY
Parent: GEO-IDN-YOG

### GEO-IDN-YOG-SLM
Name: Kabupaten Sleman
Level: REGENCY
Parent: GEO-IDN-YOG

### GEO-IDN-YOG-BTL
Name: Kabupaten Bantul
Level: REGENCY
Parent: GEO-IDN-YOG

### GEO-IDN-YOG-KP
Name: Kabupaten Kulon Progo
Level: REGENCY
Parent: GEO-IDN-YOG

### GEO-IDN-YOG-GK
Name: Kabupaten Gunungkidul
Level: REGENCY
Parent: GEO-IDN-YOG

### GEO-IDN-JI
Name: Jawa Timur
Level: PROVINCE
Parent: GEO-IDN

### GEO-IDN-JI-SBY
Name: Kota Surabaya
Level: CITY
Parent: GEO-IDN-JI

## Benchmark Coverage Profiles

### PROFILE-YK
Target: GEO-IDN-YOG-YK
Role: PRIMARY PILOT

### PROFILE-DIY
Target: GEO-IDN-YOG
Role: HIERARCHY TEST

### PROFILE-IDN
Target: GEO-IDN
Role: NATIONAL TEST

### PROFILE-SBY
Target: GEO-IDN-JI-SBY
Role: SHADOW PORTABILITY TEST

## Required Stage-0 Tests
1. Resolve Kota Yogyakarta as descendant of DI Yogyakarta and Indonesia.
2. Resolve Surabaya as outside DI Yogyakarta but inside Indonesia.
3. Permit a Bantul event to be relevant to Kota Yogyakarta through
   explicit affected-geography or functional linkage.
4. Permit national policy to be relevant to Kota Yogyakarta when a
   defensible jurisdictional or impact mechanism exists.
5. Reject unrelated local events from another geography.
6. Produce different Issue views from one unchanged evidence corpus
   under PROFILE-YK, PROFILE-DIY, and PROFILE-IDN.
7. Create PROFILE-SBY without changing core application logic.

