# SIFI MASTER PRODUCT ROADMAP v1.0
## Strategic Issue & Foresight Intelligence
**Classification:** Canonical Planning / Continuation Baseline  
**Date:** 17 October 2026
## 0. Status Dokumen dan Otoritas
Dokumen ini mengkonsolidasikan mandat, arsitektur, gate, dan jalur pengembangan SIFI sejak pencetusan ide awal pada 3 Oktober 2026 sampai penutupan migrasi GitHub, canonicalization, CI integration, Windows portability fix, dan immutable GitHub Actions pinning pada 17 Oktober 2026. Dokumen ini adalah successor planning baseline untuk pekerjaan berikutnya; ia tidak mengubah bukti historis, tidak mempromosikan capability tanpa evidence, dan tidak memberi otorisasi produksi.
| Elemen | Baseline v1.0 |
| --- | --- |
| Working canonical repository | D:\Research\SIFI_GITHUB_CANDIDATE |
| GitHub | fajarnurmanto-source/strategic-issue-foresight-intelligence |
| Branch | main |
| Canonical HEAD | 6ce5dd982833e01d405ce806df9949cfb941c0cc |
| Frozen pre-migration reference | D:\Research\SIFI |
| Frozen reference HEAD | 3797858b1c093c5df75437dfb97bef8819ffe16d |
| Local regression | 383/383 PASS |
| GitHub CI | PASS |
| Supply-chain hardening | Immutable action SHA pinning PASS |
| Production | NOT AUTHORIZED |
| CB22 | NOT_READY; counted participants = 0; L5 belum tercapai |

> **AI MAY PROPOSE, HUMAN AUTHORITY DISPOSES.**
Aturan interpretasi: status engineering, test PASS, CI PASS, atau persentase readiness tidak boleh dikonversi secara diam-diam menjadi production authority, human-research authority, institutional authority, L5, L6, atau commercialization-ready.
## 1. Genesis: Mengapa SIFI Dibangun
Ide awal SIFI adalah membangun produk standalone Strategic Issue & Foresight Intelligence yang menyensor lingkungan eksternal melalui sumber publik/terbuka secara periodik, kemudian mengubah sinyal yang tersebar menjadi struktur isu, trajectory, risk/opportunity, foresight hypotheses, watch conditions, dan decision support yang dapat diaudit. SIFI bukan turunan dari social listening, bukan newsroom monitor, dan bukan sekadar dashboard sentimen.
Sejak baseline awal, posisi produk sengaja dipisahkan dari PI dan II: SIFI menghasilkan Issue Objects / Foresight Objects dan evidence packages; Planning Intelligence dapat mengonsumsinya untuk menguji relevansi rencana/intervensi; Investment Intelligence dapat mengonsumsinya untuk riset sektor, perusahaan, regulasi, makro, atau reputasi. SIFI tetap memiliki product boundary dan commercial identity sendiri.
> **MAXIMUM VERIFIED ACTIONABLE ISSUE & FORESIGHT DECISION VALUE**
Objektif ini menggantikan dorongan yang keliru untuk memaksimalkan jumlah halaman yang di-scrape, jumlah mention, jumlah posting sosial, jumlah alert, jumlah cluster, atau kompleksitas model.
## 2. Product Constitution: Non-Negotiable Mandates
- Public/open-source first, dengan lawful discovery dan explicit source-rights/access governance.
- Periodic collection adalah baseline yang sah; real-time bukan dependency V1 dan hanya boleh muncul bila value/cost evidence membenarkannya.
- Social media adalah optional attention/perception sensor, bukan single point of failure.
- Kota Yogyakarta adalah FIRST PILOT PROFILE, bukan batas produk.
- Minimum geography levels: kabupaten/kota, provinsi, nasional; dapat diperluas melalui Coverage Profile.
- SOURCE GEOGRAPHY, EVENT GEOGRAPHY, AFFECTED GEOGRAPHY, POLICY JURISDICTION, RELEVANCE GEOGRAPHY, dan COVERAGE PROFILE harus dipisahkan.
- Relevance menggunakan state/ladder yang explainable (R0-R4/RX atau successor equivalent), bukan satu opaque score.
- Point-in-time correctness wajib: bukti/rights/correction yang baru tidak boleh bocor ke historical cutoff.
- Individual profiling default P0/off; privacy and rights fail closed.
- Architecture harus additive, non-destructive, evidence-first, rights-aware, geography-configurable, point-in-time-correct, explainable, dan governance-first.
- Predecessor yang telah sah tidak dibuka ulang tanpa defect nyata, evidence, dan change-control authority.

### 2.1 Permanent epistemic guards
- MEDIA ATTENTION != EVENT FREQUENCY
- EVENT FREQUENCY != REAL-WORLD PREVALENCE
- REAL-WORLD PREVALENCE != IMPACT SEVERITY
- ISSUE RELEVANCE != LEGAL AUTHORITY
- LEGAL AUTHORITY != RESPONSE CAPABILITY
- ANALYTICAL URGENCY != AUTOMATIC POLICY ACTION
- Correlation != causality
- Publication count != independent origin count

Output SIFI harus mempertahankan unknowns, contradiction, insufficiency, observability gaps, dan abstention. Repetition media tidak boleh menjadi empirical truth.
## 3. Canonical Intelligence Chain
> **SOURCE -> LAWFUL DISCOVERY -> RIGHTS & EVIDENCE ADMISSION -> SIGNAL -> CLAIM / EVENT -> ISSUE -> EVIDENCE QUALIFICATION -> ISSUE TRAJECTORY -> RISK / OPPORTUNITY -> FORESIGHT / WATCH CONDITIONS -> HUMAN REVIEW -> DECISION SUPPORT -> FEEDBACK & LEARNING**
Arsitektur ini adalah continuity spine. Implementasi boleh berevolusi, tetapi tidak boleh menghapus boundary konseptual antara source, publication, claim, evidence, event, issue, review, dan decision support.
| Object / Layer | Mandat minimum |
| --- | --- |
| Source Object | identity, access method, rights, cadence, geography, source role, operational health |
| Evidence Item | provenance, timestamp, hash, qualification, contradiction, rights/retention state |
| Claim | apa yang diklaim; tidak identik dengan event atau truth |
| Event | kejadian yang didukung evidence; satu event dapat dipublikasikan berulang |
| Issue Object | stable identity, lifecycle, geography, evidence state, trajectory, uncertainty, review lineage |
| Foresight Object/Hypothesis | drivers, horizons, assumptions, scenarios/watch conditions, falsification condition |
| Review/Audit | human qualification, disagreement, correction, append-only lineage |
| Decision Support | non-binding analytical next step; bukan automatic policy action |

## 4. Rights, Privacy, Evidence Admission, dan Lawful Discovery
Prinsip ACCESSIBLE != PRODUCTION-USABLE bersifat permanen. Halaman yang dapat dibuka publik tidak otomatis boleh di-crawl, disimpan penuh, didistribusikan, atau digunakan secara komersial. Source Registry harus merekam access mode, terms/robots context bila relevan, attribution, rate limit, retention, lawful access, commercial-use status, dan revalidation.
| Zone | Makna roadmap |
| --- | --- |
| Z0 | Prohibited / hard block. Tidak boleh digunakan untuk production conclusion. |
| Z1 | Research/discovery/verification trigger only. Tidak boleh langsung menopang production conclusion. |
| Z2 | Production-admitted evidence setelah rights/admission criteria terpenuhi. |
| Z3 | Derived evidence/output; tidak boleh mencuci Z1 menjadi seolah-olah Z2. |

- No login/CAPTCHA/paywall/anti-bot bypass.
- Unknown lawful access, privacy clearance, retention, atau commercial rights harus fail closed pada penggunaan yang memerlukannya.
- Store metadata/derived features by default; full text hanya bila lawful, necessary, dan governed.
- Provenance dan parser/model version harus cukup untuk reproduksi dan re-evaluation.
- Coverage disclosure wajib: monitored universe, active sources, failures, blind spots, dan observability limits.

## 5. Geography-Configurable Product Architecture
Perubahan penting dalam evolusi awal SIFI adalah menjadikan geography sebagai konfigurasi produk, bukan hard-coded pilot. Kota Yogyakarta tetap pilot utama, tetapi corpus dan logic harus dapat dipakai oleh Coverage Profile lain tanpa perubahan kode inti.
| Dimension | Pertanyaan |
| --- | --- |
| Source geography | Sumber ini berakar/beroperasi di mana? |
| Event geography | Kejadian yang dilaporkan terjadi di mana? |
| Affected geography | Wilayah/populasi mana yang terdampak? |
| Policy jurisdiction | Otoritas/aturan mana yang relevan? |
| Relevance geography | Untuk profile pengguna mana isu ini relevan? |
| Coverage Profile | Kombinasi wilayah, domain, source universe, dan rule set apa yang sedang dipakai? |

Rights jurisdiction juga tidak boleh disamakan dengan relevance geography. Product portability merupakan acceptance gate, bukan nice-to-have.
## 6. Human Review, Authority, dan Governance Constitution
SIFI dirancang untuk memanfaatkan AI secara constrained dan reviewable. AI boleh mengusulkan extraction, classification, clustering, hypothesis, summary, dan analytical next step; AI tidak boleh membuat final protected disposition, rights mutation, institutional action, atau empirical truth hanya melalui review.
| AI may | AI may not |
| --- | --- |
| Extract entity/claim/location/topic candidates | Invent absent facts |
| Propose issue grouping/lifecycle | Irreversibly merge/split without audit lineage |
| Summarize grounded evidence | Create unsupported causal explanation |
| Propose foresight drivers/scenarios/watch conditions | Present speculation as forecast certainty |
| Identify evidence gaps/next analytical steps | Make binding policy/production decisions |

Human review sendiri bukan empirical validation. Review authority != rights authority != institutional policy authority.
## 7. Maturity Model dan Klaim
| Level | Definisi evidence-based |
| --- | --- |
| L0 - Intent | Masalah, tujuan, dan boundary didefinisikan. |
| L1 - Contracted | Contract/schema/gate formal tersedia. |
| L2 - Implemented | Capability dibuat dalam code/system. |
| L3 - Functionally Verified | Capability lulus test yang relevan. |
| L4 - End-to-End | Workflow tertutup berjalan secara terintegrasi. |
| L5 - User-Validated | Intended users menjalankan workflow terkontrol dan acceptance evidence memenuhi gate. |
| L6 - Decision-Value | Ada bukti bahwa penggunaan SIFI meningkatkan keputusan dibanding credible baseline. |

Aturan permanen: L1 != L3; L4 != L5; engineering PASS != human validation; user satisfaction != decision superiority; commercial demand != empirical accuracy.

---

## 8. Historical Roadmap: Baseline v0.1 (3 October 2026)
Baseline awal menetapkan tujuh macro stages. Master Roadmap v1.0 mempertahankan intent stage ini sebagai product-development arc, tetapi menambahkan evidence gates yang lebih ketat berdasarkan pelajaran implementasi aktual.
| Macro Stage | Tujuan asli | Exit intent |
| --- | --- | --- |
| Stage 0 - Foundation | Definisikan product contract sebelum coding | Scope/evidence rules frozen untuk MVP build |
| Stage 1 - V0 Research Engine | Buktikan periodic acquisition dan corpus quality | Stable multi-cycle collection pada bounded source set |
| Stage 2 - V1 Issue Intelligence | Buktikan transformation dari publication menuju issue | Analyst menerima output sebagai operationally useful |
| Stage 3 - Evidence Intelligence | Bergerak melampaui media monitoring | Brief membedakan attention vs corroborated condition |
| Stage 4 - Foresight | Tambahkan structured forward-looking reasoning | Foresight reviewable/auditable, bukan free-form prediction |
| Stage 5 - Commercial Pilot | Uji buyer value dan deployment | Minimal satu paid/formally evaluated pilot dengan repeat-use evidence |
| Stage 6 - Productization | Standarisasi multi-customer delivery | Repeatable delivery tanpa founder-only manual operations |

## 9. Stage 0 Evolution: Foundation Before Coding
Stage 0 berkembang jauh dari baseline sederhana menjadi constitution lengkap mengenai product boundary, source governance, geography, rights, evidence, ontology, benchmark, human authority, dan foundation freeze.
| Sub-stage / lineage | Fokus yang terintegrasi |
| --- | --- |
| S0.0 | Execution environment gate; WSL Ubuntu, Python/Git/Docker capability, dedicated project root dan control baseline. |
| S0.1 | Project Control Foundation; repository/control files/project state; implementation tetap belum authorized. |
| S0.2 / S0.2R | Product contract dan Product & Pilot Boundary Successor; Kota Yogyakarta = pilot, bukan product boundary. |
| S0.3A-D | Source Governance Core: source taxonomy, authority context, access methods, collection contracts, source/evidence separation. |
| S0.3E-F | Geographic relevance/admission dan Named Pilot Source Universe. |
| S0.3R/S | Geography Relevance Contract, administrative geography model, Coverage Profile/portability successor work. |
| S0.3V-Z | Data rights, lawful signal discovery, source value/coverage regression, commercialisation gate, dan successor governance integration. |
| S0.4A/B | Stage-0 reconciliation/capability integrity audit; gap classification tanpa menghapus predecessor. |
| S0.5A-D | Canonical Evidence Model, Issue Ontology v0, Issue Object Schema v0, Human Review Authority. |
| S0.6A-C | Benchmark Corpus, Integrated Acceptance & Regression, Qualified Source Universe. |
| S0.7 | Foundation Verification & Freeze; implementation authority hanya boleh naik setelah gate yang sah. |

Metode successor permanen yang lahir pada fase ini: CURRENT STATE -> CAPABILITY OVERLAP AUDIT -> GAP CLASSIFICATION -> EXISTING CONTRACT REUSE -> MINIMUM SUCCESSOR EXTENSION -> CONTROLLED TEST -> REGRESSION -> ADMISSION -> FREEZE.
## 10. Stage 1 Implementation Lineage: From Contracts to Verified Local Product Slice
S1.x adalah lineage implementasi dan verifikasi aktual. Ia tidak dipetakan secara satu-ke-satu dengan macro Stage 1-6 baseline awal; beberapa capability dari macro stage yang berbeda telah dibangun sebagai controlled local vertical slice, tetapi production/live-source/commercial claims tetap belum diotorisasi.
| Observed implementation family | Capability |
| --- | --- |
| S1.1 - Core Domain Persistence | Canonical vocabulary, geography separation, versioned snapshots, append-only/file-backed state. |
| S1.2 - Rights & Evidence Kernel | Rights/privacy/profiling states, Z0-Z3 admission rules, point-in-time rights, derived-output constraints. |
| S1.3 - Claim/Evidence/Event Kernel | Publication-claim-evidence-event boundaries, origin independence, contradiction and PIT qualification. |
| S1.4 - Issue Identity & Persistence | Stable Issue identity, lifecycle, trajectory dimensions, evidence categories, version history. |
| S1.5 - Human Review Authority | Roles, protected transitions, correction/merge/split/reopen/disagreement/adjudication rules, append-only review history. |
| S1.6 - Canonical Benchmark & Authorization | Benchmark harness, materialization, adjudication, semantic authorization, execution boundary, phase closure. |
| S1.7A-R - Bounded Local Product Workflow | Workflow gate, persistence, runtime, orchestration, path boundary, briefs, backup/restore, local web, demo catalog, release, interactive demo, audit log, atomic orchestration, schema compatibility, resumability, markdown export. |
| S1.8A-B - CB22 Study/Local Web | Counted vs rehearsal validation kernel, consent/screening, frozen protocol/assets, two-role adjudication, local study interface. |

Pada 15 Oktober 2026, checkpoint produk melaporkan controlled non-production prototype 93%, operational-product index 65%, dan commercial-readiness index 53%. Angka ini adalah readiness snapshot, bukan otorisasi atau bukti commercialization-ready.
## 11. CB22: Engineering Closure vs Real-Governance Validation
CB22 adalah gate yang memisahkan engineering rehearsal dari representative user validation. Automated rehearsal yang menyelesaikan 11 workflow stages tidak dihitung sebagai participant evidence dan tidak menaikkan L5.
| CB22 current state | Status |
| --- | --- |
| Engineering ready | TRUE |
| Automated rehearsal | PASS, mode REHEARSAL, excluded from counted evidence |
| Counted gate ready | FALSE |
| Counted participants | 0 |
| Automated result | NOT_READY |
| Final adjudication receipt | None |
| Claim allowed | FALSE |

### 11.1 Human actions sebelum counted session pertama
1. Assign real PRIMARY_ADJUDICATOR dan real INDEPENDENT_CHECKER yang berbeda, menggunakan pseudonymous staff codes.
2. Selesaikan conflict review untuk kedua peran.
3. Tentukan dan dokumentasikan apakah external/institutional review required; project-owner approval tidak boleh otomatis ditafsirkan NOT_REQUIRED.
4. Konfigurasikan execution gate dengan determination nyata tersebut.
5. Rekrut representative analysts melalui authorized human coordinator; nama dan kontak disimpan di luar SIFI.
6. Lakukan screening dan individual informed consent sebelum timing counted session.

### 11.2 Counted-session acceptance
Minimal lima finalized, unique counted sessions harus dijalankan di bawah frozen protocol/task package tanpa perubahan mid-study. Aggregate gate harus fail closed untuk small/active sample, duplicate participant, insufficient completion, excess routine help, forbidden output, atau evidence-link failure. Final claim hanya boleh muncul setelah PRIMARY_ADJUDICATOR dan INDEPENDENT_CHECKER memberikan matching immutable votes pada bundle hash yang sama.
## 12. GitHub Migration, Canonicalization, dan Engineering Integrity Closure
Pada 17 Oktober 2026, repository dipindahkan dari local-only canonical state ke GitHub private repository tanpa history rewrite. Full-history audit, privacy/rights review, byte preservation, Windows portability, CI, dan immutable action pinning diselesaikan secara additive.
| Closure item | Final evidence state |
| --- | --- |
| Canonical working repo | D:\Research\SIFI_GITHUB_CANDIDATE |
| GitHub origin | fajarnurmanto-source/strategic-issue-foresight-intelligence |
| Canonical HEAD | 6ce5dd982833e01d405ce806df9949cfb941c0cc |
| Regression | 383/383 PASS |
| CI | SIFI Engineering Verification PASS |
| Checkout pin | actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 (v7.0.1) |
| Setup Python pin | actions/setup-python@5fda3b95a4ea91299a34e894583c3862153e4b97 (v7.0.0) |
| History rewrite / force push | FALSE |
| Old repo role | Frozen pre-migration reference |

GitHub adalah engineering/traceability infrastructure, bukan authority bypass. CI tidak boleh melakukan live acquisition, counted human research, final adjudication, production activation, atau admission automation.

---

## 13. Successor Master Roadmap: Current State -> Commercialization Ready
Bagian ini adalah jalur pengembangan yang berlaku setelah canonical HEAD di atas. Roadmap bersifat gate-based, bukan kalender-based. Target tanggal boleh dibuat kemudian, tetapi tidak menggantikan acceptance evidence.
| Gate | State | Required evidence | Exit condition | Anti-overclaim |
| --- | --- | --- | --- | --- |
| R0 - Canonical Engineering Baseline | COMPLETE | Canonical repo, regression, CI, immutable action pinning, frozen reference | Current HEAD clean; engineering provenance reproducible | Tidak mengubah CB22/production authority |
| R1 - CB22 Real-Governance Readiness | NEXT | Real two-role governance, conflict review, external-review determination, recruitment/screening/consent | Counted gate legitimately open | No counted session before governance complete |
| R2 - CB22 Counted User Validation / L5 | BLOCKED by R1 | >=5 frozen unique counted sessions; workflow evidence; exact-bundle two-role adjudication | Final receipt permits CB22 PASS and representative-operability claim | No L5 claim from rehearsal |
| R3 - CB23 / Live Acquisition Readiness | FUTURE | Lawful source packs, operational collectors, source-health/coverage controls, rights/admission runtime | Stable governed live acquisition on bounded profiles | No access bypass; no silent Z1->Z2 |
| R4 - Operational Product Hardening | FUTURE | Reliability, observability, operator UX, backup/restore, schema migration, identity/access, incident handling | Repeatable bounded operations by intended operators | Engineering hardening independent from user-value proof |
| R5 - Real Evidence & Data Readiness | FUTURE | Qualified real-data coverage, drift/correction handling, contradiction, observability disclosure, multi-cycle evidence | Claims are supported by lawful real evidence with known limits | Attention/repetition cannot substitute prevalence/impact |
| R6 - Decision-Value Validation / L6 | FUTURE | Preregistered comparison against credible baseline; timeliness, correctness, prioritization, workload, actionability | Material incremental decision value demonstrated | Track A workability PASS != Track B decision-value PASS |
| R7 - Production Readiness & Authority | FUTURE | Security/privacy assessment, release governance, access control, secrets, recovery, monitoring, support, deployment authority | Explicit production admission by legitimate authority | No automatic promotion from CI/L6 |
| R8 - Commercial Pilot Readiness | FUTURE | Buyer persona, package, deployment boundary, pricing hypothesis, procurement/legal/IP/privacy posture, pilot success criteria | Safe to conduct paid/formally evaluated pilot | No commercialization claim before buyer evidence |
| R9 - Commercial Pilot / Proof | FUTURE | Paid/formally evaluated pilot(s), repeat use, willingness-to-pay, implementation cost, retention/value evidence | At least one validated commercial use case and economic viability evidence | Demand does not prove empirical accuracy |
| R10 - Productization | FUTURE | Repeatable deployment, onboarding, source packs, configuration, SLAs/support model, data portability, pricing, governance | Delivery no longer founder-only/bespoke; multi-customer repeatability | Avoid custom-consulting trap/vendor lock-in |
| R11 - COMMERCIALIZATION_READY | FUTURE FINAL GATE | Product, operational, legal/governance, decision-value, deployment, support, and commercial evidence bundle | All mandatory gates PASS; claim-evidence register supports commercialization-ready label | Label is evidence-gated, never percentage-only |

## 14. R1 - CB22 Real-Governance Readiness (Immediate Next Gate)
Ini adalah satu-satunya gate yang boleh menjadi fokus immediate product progression setelah Master Roadmap ini dibekukan. Engineering tambahan yang tidak diperlukan untuk membuka R1/R2 harus ditahan agar tidak menghindari human-evidence bottleneck.
| Requirement | Acceptance evidence |
| --- | --- |
| PRIMARY_ADJUDICATOR | Real assigned person/code; role binding recorded |
| INDEPENDENT_CHECKER | Different real person/code; independence recorded |
| Conflict review | Explicit completed determination for both roles |
| External/institutional review determination | REQUIRED / NOT_REQUIRED with basis; if required, approval evidence |
| Recruitment | Representative profile and authorized human coordinator |
| Screening | Eligibility checked before session |
| Consent | Individual informed consent before timing/session start |
| Frozen assets | Protocol/task package/schema hashes intact |

## 15. R2 - L5 Representative User Validation
L5 adalah user-validation claim, bukan code-quality claim. Session harus counted, unique, analyzable, dan terhubung ke exact workflow/evidence lineage. Developer rehearsal tidak boleh dihitung.
| Metric / control | Minimum gate |
| --- | --- |
| Unique counted participants | >=5 |
| Completion threshold | >=80% sesuai frozen protocol |
| Routine-help ceiling | <=20% sesuai frozen protocol |
| Forbidden output | 0 allowed |
| Evidence linkage | Must be complete; missing/mismatch demotes assessment |
| Duplicate participants | Not permitted to create PASS |
| Final review | Two distinct assigned roles vote on exact same immutable bundle |

## 16. R3-R5 - Live Acquisition, Operational Hardening, Real Evidence
Setelah L5, fokus berpindah dari “can intended users operate the bounded workflow?” menjadi “can the product operate safely and reliably against real source/data conditions?” Live acquisition harus ditangani sebagai gate terpisah karena rights, coverage, source changes, and operational failure modes berbeda dari synthetic/controlled validation.
- Build source packs as governed configuration, bukan hard-coded crawl list.
- Prioritize official API/RSS/sitemap/documented interfaces; direct HTML extraction hanya bila lawful/necessary.
- Track collection health, source failure, cadence, content change, parser version, and coverage gaps.
- Introduce drift/correction workflows without overwriting historical point-in-time state.
- Maintain source substitution plan to reduce aggregator/platform dependence.
- Preserve uncertainty and contradiction in real Issue Objects.

## 17. R6 - Decision-Value Validation (L6)
SIFI tidak cukup membuktikan bahwa workflow bekerja. Ia harus membuktikan bahwa workflow memberikan incremental decision value dibanding baseline yang kredibel.
| Track A - Product Workability | Track B - Incremental Decision Value |
| --- | --- |
| Acquisition, admission, issue construction, review, output, persistence, reproducibility, reliability | Issue prioritization quality, missed significant issues, false escalation |
| Privacy/rights controls, bounded workflow usability | Timeliness, reviewer workload, evidence adequacy, actionability |
| Can SIFI do the intended workflow? | Does SIFI materially improve the decision process? |

Historical benchmark, user satisfaction, atau paid demand sendiri tidak cukup untuk L6. Evaluation harus preregister cutoff/baseline/metrics bila historical replay digunakan dan harus aktif mencari falsification evidence.
## 18. R7 - Production Readiness dan Explicit Production Authority
Production readiness harus dipisahkan menjadi technical readiness dan authority. Technical PASS tidak otomatis memberi legal/institutional authorization.
- Identity/access lifecycle, least privilege, audit trail, secrets/key handling.
- Backup/restore/recovery objectives dan tested restore.
- Operational monitoring, health checks, incident response, change/release control.
- Data retention/deletion, privacy handling, rights revalidation, source deactivation.
- Deployment packaging (local/on-prem/cloud/hybrid sesuai evidence dan buyer need).
- Security/privacy assessment dan explicit production admission.
- Support ownership, escalation path, rollback/fail-closed behavior.

## 19. R8-R10 - Commercial Pilot, Commercial Proof, Productization
Commercialization tetap mempertahankan intent baseline awal: masuk melalui kategori belanja issue/media intelligence, lalu membedakan produk melalui structured issue intelligence, evidence qualification, trajectory, dan foresight. Namun commercial proof harus evidence-gated.
| Commercial layer | Buyer language | Proof required |
| --- | --- | --- |
| Entry | Media / issue intelligence | Operational time/value gain, clean source universe, deduped events, auditable evidence |
| Expansion | Strategic issue intelligence | Recurrence/structural issue detection, evidence state, cross-domain implications |
| Premium | Foresight intelligence | Reviewable hypotheses, drivers, horizons, watch conditions, falsification |
| Platform | External intelligence API / reusable Issue Objects | Stable contracts, integration value, governance and data portability |

### 19.1 Commercial pilot acceptance
- Buyer persona and job-to-be-done explicitly selected.
- Pilot scope, source pack, retention, deployment, support, and success metrics frozen.
- Pricing tested as hypothesis based on coverage, domains, analyst seats, intelligence depth, retention, API/export, and service component.
- Usage/repeat-use/willingness-to-pay and implementation effort recorded.
- Legal/privacy/IP/procurement constraints identified and resolved enough for the pilot.
- Exit/handover/data portability defined; avoid vendor lock-in.

### 19.2 Productization exit
Productization dinyatakan PASS hanya ketika deployment, configuration, onboarding, support/SLA, source packs, governance, upgrade/migration, and data portability dapat diulang untuk lebih dari satu customer profile tanpa founder-only manual operations atau bespoke engineering sebagai default.
## 20. R11 - COMMERCIALIZATION_READY Final Gate
Label COMMERCIALIZATION_READY hanya boleh diterbitkan bila evidence bundle menunjukkan kesiapan lintas enam dimensi berikut. Persentase readiness dapat dipakai sebagai dashboard, tetapi tidak boleh menggantikan gate.
| Dimension | Minimum commercialization-ready evidence |
| --- | --- |
| Product | Stable bounded product workflows; L5 achieved; product scope/version clear |
| Decision value | L6 or equivalent credible comparative value evidence for target use case |
| Operational | Repeatable deployment/operations, observability, recovery, support ownership |
| Governance/legal | Rights/privacy/security/IP/procurement posture fit for target market; explicit authority |
| Commercial | Validated buyer problem, willingness-to-pay/paid pilot evidence, implementation economics |
| Delivery scalability | Standardized onboarding/source packs/configuration/support; no founder-only dependency |

Commercialization-ready tidak sama dengan “production authorized everywhere”. Setiap jurisdiction/customer deployment tetap memerlukan local rights, security, privacy, procurement, and authority checks.
## 21. Product Claim-Evidence Register
Setiap claim penting harus memiliki evidence register. Struktur minimum: Claim; Evidence; Scope; Alternative Explanation; Falsification Condition; Rights Qualification; Review Status; Disposition (VERIFIED / PARTIAL / UNVERIFIED / CONTRADICTED / HOLD).
| Contoh claim | Evidence challenge |
| --- | --- |
| SIFI identifies emerging issues earlier than manual monitoring | Apakah issue benar-benar observable pada cutoff yang sama? Apakah earlier detection berguna dan qualified untuk action, bukan hanya timestamp lebih awal? |
| SIFI reduces analyst workload | Bandingkan waktu/effort dengan credible baseline tanpa menurunkan evidence quality atau meningkatkan missed issues. |
| SIFI improves issue prioritization | Uji against preregistered cases/outcomes; ukur false escalation dan missed significant issues. |
| SIFI is commercialization-ready | Harus ditopang gate R11 lintas product, L6, operational, legal/governance, commercial, delivery scalability. |

## 22. Anti-Overclaim Register
| Claim yang DILARANG saat ini | Mengapa |
| --- | --- |
| CB22 PASS / L5 achieved | Counted participants masih 0; real governance/counting belum dilakukan. |
| Production ready / production authorized | CI/regression bukan authority; production gate belum dibuka. |
| Live-source operationally validated | Current closure berfokus controlled local product slice dan engineering. |
| Decision superiority / L6 | Belum ada comparative decision-value validation terhadap credible baseline. |
| Commercially validated | Belum ada paid/formally evaluated pilot dengan repeat-use/value evidence pada successor roadmap. |
| Commercialization-ready | R1-R11 belum lengkap. |

## 23. Risk Register - Master Level
| Risk | Failure mode | Control / roadmap response |
| --- | --- | --- |
| Source access change | Coverage loss, legal/commercial risk | Diversified source universe, rights registry, source substitution, source packs |
| Aggregator/platform dependence | Systemic blind spot | Direct registry + multiple discovery paths; social optional |
| Rights/copyright/privacy failure | Legal/reputation harm | Z0-Z3, explicit access contract, metadata-first, privacy fail-closed |
| Duplicate/syndication inflation | False urgency | Origin independence, dedupe/same-event logic, publication != event |
| Attention mistaken for reality | Bad strategic conclusion | Epistemic guards, evidence qualification, official/research corroboration |
| Coverage bias/observability gap | Overstated confidence | CoverageProfile, health/gap disclosure, uncertainty |
| LLM hallucination/instability | Loss of trust | Constrained schemas, provenance, human review, model/version logs |
| Ontology rigidity | Blind spots/rework | Versioned ontology, correction lineage, benchmark before freeze |
| Foresight storytelling | Speculative credibility failure | Drivers, assumptions, horizons, watch/falsification, review |
| Human authority ambiguity | Invalid final disposition | Role binding, conflict review, independent checker, explicit authority |
| Bespoke consulting drift | Low scalability | Standard source packs/configuration/onboarding/API; R10 gate |
| Commercial demand overread | False empirical confidence | Separate commercial proof from accuracy/L6 |
| Security/production shortcut | Operational risk | R7 independent gate; no CI-based auto-promotion |

## 24. Evidence and Gate Operating Rules
- Every future gate begins with authoritative state recovery (repo, branch, HEAD, worktree, latest predecessor, authority).
- No silent backfill, no retroactive legalization, no silent authority escalation.
- Prefer REUSE/HARDEN/EXTEND/DEFER/REJECT after gap classification; do not create duplicate engines/gates.
- One maturity level at a time: pursue the minimum-sufficient improvement that removes the highest real blocker.
- Every implementation change must have acceptance test and regression consideration.
- Historical evidence remains immutable; corrections are successor/append-only records.
- GitHub/CI is engineering evidence only. Human research, live acquisition, production, and commercial gates remain separate.

## 25. Continuation Contract for New Threads
Setiap thread SIFI berikutnya harus memulai dari canonical repo dan roadmap ini, bukan mengandalkan conversational reconstruction. Minimal handoff: canonical path, branch, HEAD, current gate, completed gates, blockers, production/CB22 state, dan explicit next authorized action.
> **Read current authoritative state -> identify one next gate -> execute evidence-first -> verify -> regress -> freeze/record -> only then advance.**
Thread baru tidak boleh memulai implementation automation hanya karena roadmap menyebut future capability. Future roadmap adalah direction; authority datang dari current gate evidence dan explicit decision.
## 26. Current Handoff Snapshot - 17 October 2026
| Field | Value |
| --- | --- |
| Canonical repo | D:\Research\SIFI_GITHUB_CANDIDATE |
| GitHub | fajarnurmanto-source/strategic-issue-foresight-intelligence |
| Branch | main |
| HEAD | 6ce5dd982833e01d405ce806df9949cfb941c0cc |
| Old repo | D:\Research\SIFI (frozen pre-migration reference) |
| Regression | 383/383 PASS |
| CI | PASS |
| Immutable actions | PASS |
| Current product gate | R1 - CB22 Real-Governance Readiness |
| CB22 | NOT_READY; counted participants 0 |
| Production | NOT AUTHORIZED |
| L5 | Not achieved |
| L6 | Not achieved |
| Commercialization-ready | Not achieved |

Authorized next focus: real governance for CB22 (PRIMARY_ADJUDICATOR, INDEPENDENT_CHECKER, conflict review, external-review determination, recruitment/screening/consent), followed by at least five frozen counted sessions and exact-bundle independent adjudication.
## Appendix A. Source/Artifact Lineage Used to Reconstruct This Master Roadmap
- SIFI Project Planning / Working Planning Baseline v0.1 (3 October 2026).
- 00_CONTROL/PROJECT_STATE.md and environment/project-control lineage.
- SIFI Working Baseline Successor v0.2 and S0.2R Product & Pilot Boundary Successor.
- S0.3 Source Governance Core and S0.3 geography/relevance/coverage/right-access successor artifacts.
- Issue-Reality-Coverage-Authority-Decision Integrity mandate (8 October 2026).
- Verified Product Capability & Decision Value Audit (VPC-DVA) mandate.
- S1.x implementation/test lineage through CB22 study kernel and local web.
- SIFI Progress Report - CB22 Engineering and Rehearsal (15 October 2026).
- GitHub migration, canonicalization, CI, portability, and immutable action pinning closure (17 October 2026).

Where historical labels and later implementation labels do not map one-to-one, this roadmap preserves both layers rather than silently renumbering them.
## Appendix B. New-Thread Start Capsule
SIFI CONTINUATION HANDOFF - MASTER ROADMAP v1.0
- Canonical repo: D:\Research\SIFI_GITHUB_CANDIDATE
- GitHub: fajarnurmanto-source/strategic-issue-foresight-intelligence
- Branch: main
- Canonical HEAD: 6ce5dd982833e01d405ce806df9949cfb941c0cc
- GitHub migration/canonicalization/CI/supply-chain hardening: COMPLETE
- Local regression: 383/383 PASS
- Production: NOT AUTHORIZED
- CB22: NOT_READY; counted participants 0; L5 not achieved
- Next gate: R1 CB22 Real-Governance Readiness, then R2 >=5 frozen COUNTED sessions and two-role exact-bundle adjudication
- Permanent governance: AI MAY PROPOSE, HUMAN AUTHORITY DISPOSES; evidence-first; rights-aware; geography-configurable; point-in-time-correct; additive/non-destructive; fail-closed.
