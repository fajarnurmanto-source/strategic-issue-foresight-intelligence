# SIFI — MASTER PRODUCT ROADMAP v1.1
## Successor Integrated Roadmap: Universal Intelligence, Laravel-first, Evidence-first, Commercialization-ready

**Tanggal penyusunan:** 8 November 2026 (Asia/Jakarta)  
**Status dokumen:** **OWNER-REQUESTED SUCCESSOR CANDIDATE — MENUNGGU PROMOSI KE REPOSITORY KANONIS**  
**Baseline yang tidak diubah:** `SIFI_MASTER_PRODUCT_ROADMAP_v1_0`, 17 Oktober 2026  
**Cakupan:** Strategic Issue & Foresight Intelligence (SIFI) — produk universal; Kota Yogyakarta merupakan pilot, bukan batas produk  
**Repositori target:** `fajarnurmanto-source/strategic-issue-foresight-intelligence` (private)  
**Kebijakan rilis:** Local development dan pengujian reversibel boleh sesuai mandat; **production, paid deployment, dan real-evidence admission tetap DENIED tanpa otorisasi sah**.

> **Peringatan pengendalian:** Dokumen ini adalah pembaruan *rencana* berdasarkan mandat dan bukti yang tersedia sampai 8 November 2026. Dokumen tidak memodifikasi sejarah v1.0, tidak menyatakan fitur yang belum diverifikasi sebagai telah terpasang, dan tidak dengan sendirinya meluluskan R1–R11. Versi ini baru menjadi *canonical planning successor* setelah pemilik menerima perubahan dan versi tepatnya tercatat dalam repositori kanonis. Angka status dari log pengguna diberi label `OWNER-REPORTED`, bukan inspeksi langsung runtime.

## 1. Ringkasan keputusan dan perubahan dari v1.0

### 1.1 Yang tetap berlaku dan tidak boleh dihapus

1. Dua sistem pengendalian berjalan **paralel**: **R0–R11** merupakan *formal evidence/admission gates*, sementara **E0–E12** di dokumen ini adalah *engineering capability milestones*. Kelulusan E tidak otomatis meluluskan R.
2. **Stage 0–6** baseline 3 Oktober dipertahankan sebagai busur sejarah produk, bukan daftar tes yang harus diulang atau penomoran yang menggantikan R0–R11.
3. **L0–L6** tetap tingkat kematangan klaim: desain ≠ implementasi ≠ verifikasi fungsional ≠ integrasi menyeluruh ≠ pengguna representatif ≠ nilai tambah keputusan.
4. **Core V2, provenance, rights/evidence kernel, Issue Objects, review authority, point-in-time history, dan semua karya yang telah sah** harus dipertahankan dan digunakan ulang. Perubahan pada predecessor membutuhkan alasan cacat nyata dan prosedur perubahan yang berlaku.
5. **AI boleh mengusulkan; otoritas manusia memutuskan.** Review manusia juga tidak otomatis membuktikan kebenaran empiris.
6. **Z0–Z3 rights/admission**, pembatasan profil individu, privasi, izin sumber, serta batas bukti dan produksi tetap *fail-closed*.

### 1.2 Mandat tambahan yang kini menjadi kewajiban pembangunan produk

| Perubahan | Sumber mandat | Konsekuensi roadmap |
|---|---|---|
| Laravel-first, reuse-first, results-first | Addendum 20 Okt; mandat 6 Nov | Laravel/React/Inertia untuk aplikasi; gunakan modul yang matang dan kompatibel, hindari duplikasi; pertahankan kernel analisis/evidence SIFI yang sudah ada sampai penggantinya benar-benar setara. |
| Universal Monitoring Object | Mandat AE-12, 26 Okt | Objek dapat berupa wilayah, organisasi, merek, orang publik, kebijakan, industri, isu, dan kategori extensible; tidak wajib punya geografi. |
| Universal lawful discovery, web research, scheduled monitoring | AE-13–AE-15, 26 Okt | Pencarian deterministik tanpa AI, registrasi/rights terpisah dari penemuan, monitoring terjadwal berkuota, cakupan transparan. |
| Research-to-Intelligence | AE-16, 26 Okt | Hasil riset/pantauan masuk satu pipeline SIFI; dilarang menciptakan mesin kebenaran kedua. |
| Autonomous media/social intelligence | Owner mandate 6 Nov; alignment 7 Nov | Normalisasi, dedup, origin independence, clustering, credibility, spread graph, velocity, scenarios, executive snapshots. |
| Comparative image/perception and declared assets | Mandat produk 6–7 Nov | Bandingkan sampel dan periode secara terbuka; aset hanya yang dilaporkan/dideklarasikan dengan sumber sah. |
| CBM-01 benchmarking + selective integration | Mandat 6 Nov | Benchmark *user outcomes* terhadap produk matang; hanya adopsi fitur bernilai, lawful, dengan lisensi yang cocok. |
| Superadmin Integration Hub | Mandat 6 Nov; alignment 7 Nov | Panel/role terpisah untuk provider/source/rights/quota/cost/health/audit; tidak perlu aplikasi/database kedua. |
| EBPD-01 evidence-based product development | Mandat 6 Nov | Ukur capability, usability, reliability, commercial viability; gunakan satu acceptance gate terintegrasi per slice, bukan audit mikro berulang. |
| Evidence-First Decision Workspace dan UX responsif | UI/UX handoff 18 Okt, hasil Core V2 Nov | Integrasi UI dengan status diketahui/tidak diketahui, source coverage, review, evidence, scenario, executive brief; desktop dan mobile layak digunakan. |
| GitHub ↔ local Laravel development bridge | Log pengguna 8 Nov | GitHub private menjadi transport pengembangan; source sync/branch/PR dan local apply harus dilacak terpisah dari `main`/produksi. |

### 1.3 Resolusi konflik mandat

- Instruksi **“Laravel-first” bukan “hapus semua Python SIFI”**. Untuk SIFI, Laravel menguasai autentikasi, aplikasi, workflow, konfigurasi, orkestrasi, UI dan kontrol konektor; kernel analisis/evidence dan kontrak ilmiah yang sudah matang tetap authoritative hingga pembandingan menunjukkan pengganti yang aman dan setara. Perintah meninggalkan *specialized Python* pada proyek II tidak otomatis berlaku pada SIFI.
- Instruksi **“zero-touch ingestion”** menghapus kebutuhan telaah manual tiap publikasi, **bukan** menghapus pemeriksaan hak akses sumber, pengecualian bukti, review keputusan material, atau otorisasi institusional.
- **Real-data-first** berarti mencoba sumber publik nyata yang *secara spesifik diizinkan* melalui mode disposable/shadow sedini mungkin. Ini tidak berarti menghidupkan crawler produksi, mengakses platform tanpa izin, atau mengakui data sebagai bukti sah.
- Instruksi mempercepat eksekusi berarti **reuse pengujian/baseline dan satu receipt QA terintegrasi**; tidak berarti mengabaikan regresi/security/rights yang diperlukan untuk perubahan nyata.
- Pengembangan teknis E0–E12 **boleh berlanjut sejajar** saat gate R1/R2 menunggu aktor/partisipan manusia. Tidak boleh mengubah `NOT_READY` menjadi PASS demi kelancaran backlog.

## 2. North Star, positioning, dan hasil produk

**North Star:** Pengguna dapat menetapkan **objek apa pun yang sah**, menemukan dan memantau sumber yang diizinkan, memahami kemunculan serta pergerakan isu, memeriksa dasar klaim dan keterbatasan, membandingkan aktor/skenario, lalu menghasilkan keputusan atau brief yang lebih tepat waktu dan dapat ditelusuri.

**Alur operator:** `DEFINE OBJECT → DISCOVER → SEARCH → REGISTER/RIGHTS → FETCH/MONITOR → NORMALIZE → CLUSTER → QUALIFY → INVESTIGATE → MAP SPREAD → FORESIGHT → HUMAN REVIEW → BRIEF → WATCH/REVISE`.

**Diferensiasi produk:** Bukan sekadar penghitung mention/sentimen atau chatbot pencarian web. Nilai utamanya adalah menghubungkan sumber, asal informasi independen, klaim, kondisi yang benar-benar teramati, Issue Object, ketidakpastian, skenario, dan tindakan yang dapat ditinjau.

**Persona utama:** (1) analis isu/riset; (2) koordinator/reviewer; (3) pimpinan pembaca executive brief; (4) superadmin/operator sumber; (5) calon pelanggan lintas organisasi. Mode tampilan eksekutif tidak mengubah hak akses server.

**Segmen pembuktian berurutan:** pilot bounded Yogyakarta → profil objek nonpemerintahan (merek/industri) → buyer persona yang tervalidasi → deployment lintas pelanggan. Keberhasilan pilot tidak cukup membuktikan cakupan universal.

### 2.1 Konstitusi epistemik dan batas statistik

- `publication count != independent-origin count != event count`.
- `media/social attention != real-world frequency != prevalence != impact severity`.
- `source reputation != claim corroboration != evidence admissibility`.
- `geographic source != event location != affected area != policy jurisdiction != relevance/coverage profile`.
- `correlation != causation`; engagement tinggi bukan bukti dukungan seluruh populasi.
- `human review != empirical verification`; `engineering PASS != L5`; `L5 != L6`; `commercial interest != product value proof`.
- Seluruh output menyimpan **cutoff historis, source/provenance, sampling/coverage, kontradiksi, unknown, abstention, revision/correction**. Perubahan baru tidak boleh diam-diam menulis ulang keadaan pada cutoff lama.

## 3. Arsitektur terintegrasi yang diwajibkan

| Plane | Pemilik utama | Kewajiban dan guard |
|---|---|---|
| **Control/Superadmin** | Laravel (panel existing, role-gated) | Registry objek/sumber/provider, Coverage Profile, konfigurasi global, policy rights/license, secret reference, quota/budget/rate, status enable/suspend/revoke/retire, health, audit dan tenant isolation. |
| **Acquisition & research** | Existing Laravel services + lawful adapters; mesin khusus existing jika diperlukan | Discovery terpisah dari registration/rights; API/RSS/sitemap/dokumentasi; deterministic search; scheduler dan queue bounded; SSRF/redirect/response-size/timeouts/retries; immutable retrieval snapshots. |
| **Canonical observation & evidence** | Core V2 dan kontrak evidence yang telah terverifikasi | Hash/revision, source identity, origin independence, timestamp observed/published, rights Z0–Z3, PIT, claim/event separation, contradiction, abstention. Tidak ada real-evidence auto-admission. |
| **Intelligence** | Reuse SIFI kernels; PHP orchestration; AI optional | Entity/claim extraction, topic clusters, propagation graph, engagement velocity, credibility factors, context/relevance, risk and opportunity, watch conditions, probabilistic scenarios. Tidak menciptakan engine kebenaran kedua. |
| **Operator UX** | React/Inertia existing di Laravel | Evidence-First Decision Workspace, investigation, Issue Candidate/Case, brief, QA states, reviewer workflow, responsive accessible UI, explicit provenance/limitations. |
| **Integration/export** | Versioned SIFI API & output adapters | Scoped APIs, audit, source/rights qualifications, export briefs/snapshots, interoperability dengan PI/II/EVCMFI jika nanti diotorisasi. Tidak bocor data atau otoritas lintas produk. |

**Canonical intelligence chain:** `SOURCE → LAWFUL DISCOVERY → RIGHTS & ACCESS → OBSERVATION/SIGNAL → CLAIM/EVENT → ISSUE/CASE → EVIDENCE QUALIFICATION → TRAJECTORY/RISK → FORESIGHT/WATCH → HUMAN REVIEW → EXECUTIVE BRIEF`.

**Minimal connector contract:** `ConnectorDescriptor` (jenis, coverage, license, expiry, auth reference, rate/budget/retention, status), `DispatchContext` (actor/tenant, purpose, rights, scope, reserved budget, correlation ID), `ObservationEnvelope` (original URI, timestamps, content hash/revision, sampling, lineage, rights, `UNVERIFIED` state) dan `ConnectorOutcome` (success/partial/blocked/unavailable, usage cost, audit). Kontrak ini harus dibaca sebagai *target desain*, bukan API yang telah terbukti terpasang.

**AI & provider:** Penelusuran dasar harus bekerja tanpa AI. Jika terhubung secara sah, prioritas zero-spend Gemini/Groq/OpenRouter atau model lokal sesuai kebijakan; izin, kuota, data-sharing, batas biaya dan keamanan wajib aktif sebelum request. Ketersediaan layanan gratis dan kemampuan platform harus dibuktikan pada saat integrasi; tidak boleh menganggap kredensial telah tersedia. AI tidak boleh menerbitkan klaim empiris atau mengubah gate terlindungi.

## 4. Dua jalur milestone: formal gate (R) dan engineering outcome (E)

### 4.1 Formal master gates R0–R11 — dipertahankan dari v1.0

| Gate | Kriteria akhir yang tidak bisa diganti oleh test software | Status defensibel 8 Nov 2026 |
|---|---|---|
| **R0 — Canonical Engineering Baseline** | Canonical source/CI/regression/provenance reproducible | **COMPLETE pada baseline 17 Okt**; perlu rekonsiliasi versi Laravel vNext/bridge terbaru, bukan menghapus bukti R0. |
| **R1 — CB22 Real-Governance Readiness** | Dua role adjudikator/checker independen ditunjuk; conflict review; institutional-review determination; rekrutmen, screening, consent; frozen assets | **BELUM ADA BUKTI KELULUSAN BARU**. Secara historis `NEXT / NOT_READY`; keputusan orang nyata tetap dibutuhkan. |
| **R2 — Counted L5 Representative User Validation** | ≥5 peserta unik terhitung pada frozen protocol; completion ≥80%, routine help ≤20%, forbidden output=0, evidence linkage lengkap; matching review pada exact immutable bundle | **BLOCKED by R1** hingga syarat bukti sah dipenuhi. Rehearsal/UI QA tidak boleh dihitung. |
| **R3 — Lawful Live Acquisition Readiness** | Source packs dan access rights spesifik, health/coverage controls, collectors operasional pada profil bounded; admission jelas | **FUTURE**. Public shadow experiments tidak otomatis memenuhi R3. |
| **R4 — Operational Product Hardening** | Observability, identity/security, recovery, upgrade, reliability dan UX operator konsisten | **FUTURE**; QA komponen hanya bukti parsial. |
| **R5 — Qualified Real Evidence & Multi-cycle Data** | Cakupan/data asli sah, siklus berulang, corrections/drift, contradiction dan known unknowns | **FUTURE**; aktivitas monitoring bukan otomatis bukti empiris. |
| **R6 — L6 Incremental Decision Value** | Studi pembanding preregistered: prioritization, timeliness, false escalation/missed issues, workload, actionability | **FUTURE**; keberhasilan L5 tidak menggantikan L6. |
| **R7 — Production Readiness + Explicit Authority** | Security/privacy/legal, backup-restore, support/incident, release controls dan otorisasi produksi eksplisit | **DENIED/NOT ADMITTED** sampai otorisasi baru yang sah. |
| **R8 — Commercial Pilot Readiness** | Buyer/JTBD, scope/source/rights, data/IP, success metrics, pricing hypothesis, procurement/deployment support | **FUTURE**. |
| **R9 — Commercial Pilot/Proof** | Pilot berbayar atau formal dievaluasi; repeat use, willingness-to-pay, workload/cost, retained value | **FUTURE**; demand bukan evidence accuracy. |
| **R10 — Productization** | Repeatable onboarding/deploy/source packs/support/SLAs/upgrade, portability, multi-customer without founder-only work | **FUTURE**. |
| **R11 — COMMERCIALIZATION_READY** | Bukti bersama untuk product, L5, L6/decision value, operations, governance/legal, commercial demand dan delivery scalability | **NOT ACHIEVED**; enam dimensi harus terpenuhi, bukan persentase. |

**Peraturan khusus R1/R2:** Tim teknis dapat menyiapkan UI, paket, instrumen dan rehearsal, tetapi hanya penanggung jawab manusia yang sah dapat menentukan review institusional, merekrut/menyetujui peserta, memberi consent dan menyelesaikan adjudication. Perintah otomatis berhenti pada batas tersebut.

### 4.2 Engineering product milestones E0–E12 — jalur eksekusi paralel

`E` adalah capability increment, **bukan** kelulusan R. Karena repo live pada 8 Nov tidak diperiksa langsung dari lingkungan penyusun dokumen, status awal selain yang memiliki receipt jelas adalah `VERIFY/REUSE`, bukan `TODO` mutlak. Jangan membuat ulang modul yang sebenarnya sudah tersedia.

| ID / Prioritas | Outcome terintegrasi yang harus dapat digunakan | Minimum acceptance / dependency |
|---|---|---|
| **E0 / P0** — Canonical source & Core V2 rebasing | Current Laravel vNext, Core V2, mandates, source bridge, ownership/dirty paths, release gate dan kontrak eksisting dipetakan satu kali | Exact repo/HEAD/AGENTS/mandate SHA dan feature map; hindari perubahan ke active runtime; bekukan baseline dan cegah duplikasi credential/engine. |
| **E1 / P0** — AE-12 Universal Monitoring Object | Objek configurable dengan alias, keyword/exclusions, kategori extensible, optional geography, tujuan, horizon, permitted sources, pause/archive, revision, scoped workbench | Tiga synthetic object profiles (Yogyakarta issue, fictitious brand, EV industry) dibuat tanpa custom code; tenant isolation; browser desktop/mobile; AI/collector eksternal tetap OFF selama acceptance AE-12. |
| **E2 / P0** — CBM01 Superadmin Integration Hub & unified registry | Registry lawful-source, AI, search, RSS, news, regulatory, statistical, research, approved-social connector; enable/suspend/revoke/retire, quota/cost/license/health/audit | Control plane role-gated yang memakai layanan existing; no duplicated secrets, encrypted reference, no paid fallback, SSRF/privacy/rights checks at dispatch. Bukan pengganti analyst workbench. |
| **E3 / P0** — One real-public lawful shadow vertical slice | Sekurang-kurangnya satu sumber publik yang diizinkan mengalir `discovery → rights → retrieval → normalized shadow observation → existing analyst consumer` | Rights/retention/scope jelas; timestamp, hash, revision, provenance; no production admission, no evidence promotion, measured retrieval errors/cost. Jika izin belum sah, status `BLOCKED_SOURCE_RIGHTS`, bukan data rekaan. |
| **E4 / P1** — AE-13 Universal Source Discovery | Kandidat sumber dinilai dari relevance, credibility-context, freshness, diversitas origin, access rights, coverage, price | Distinct statuses `DISCOVERED/REGISTERED/RIGHTS_REVIEWED/ACQUISITION_ELIGIBLE`; discovery tidak memberi hak fetch otomatis. |
| **E5 / P1** — AE-14 On-Demand Research | Query/object scoped search, lawful fetch, related sources, duplicate/origin separation, conflict/unknown, attributable cited response | Deterministic path tanpa AI; zero fabricated source/URL; insufficient coverage ditandai; bisa menghubungkan hasil ke Monitoring Object tanpa otomatis admit evidence. |
| **E6 / P1** — AE-15 Scheduled Multi-source Monitoring | Laravel Scheduler/Queue dengan scoped cadence, idempotence, bounded retries, checkpoints, source health/drift, pause/resume, failure UX | Latency/resource quota observed; safe replay and backfill policy; live scheduling hanya setelah source-specific approval. |
| **E7 / P1** — AE-16 Research-to-Intelligence Integration | Discovery/research/monitoring tersambung ke Core V2: observation→claim/event→issue→evidence→trajectory→brief | Reuse canonical domain identity/contracts; preserve PIT, contradictions, lineage, abstention; no second intelligence engine or evidence laundering. |
| **E8 / P1** — Autonomous credibility & propagation intelligence | Dedup, independent origins, clusters, source reliability, primary corroboration, contradictions, observability/manipulation risks; propagation network roles (trigger, amplifier, elevator, bridge, dampener); volume/velocity/acceleration | Explicit formulas, normalization/window, platform and sampling coverage, confidence intervals/limits, alternative explanations; avoid inferring offline events and motive from popularity. |
| **E9 / P2** — Comparative intelligence | Comparative media/public perception of officials, organizations or brands; lawful declared/reported asset/net-worth comparisons | Equivalent periods/samples and caveats; only sourced reported/declarative assets; no inferred hidden wealth, demographic profiling, defamatory certainty or unsupported ranking. |
| **E10 / P2** — Foresight and watch | Probabilistic trajectories, drivers, opposing scenarios, trigger/watch conditions, falsification, revisions, executive snapshot | Calibration and scenario reviewability; clear distinction forecast vs observed condition vs institutional recommendation. |
| **E11 / P1 continuous** — UX/UI and Executive Brief | Evidence-First Decision Workspace; overview, issue explorer, investigation, evidence, chronology, scenario, brief/export, role-specific admin | Task completion, trustworthy status labels, responsive desktop/mobile, keyboard accessibility, WCAG 2.2 AA target, visual baseline and stable export with provenance. Extend existing UI, not a second product. |
| **E12 / P1 continuous** — Product operations, storage, and commercial foundation | Observability, queue/cost performance, backup/restore, tenant policies, easy launcher/deployment, reproducible source packs, controlled storage lifecycle, onboarding/support and buyer instrumentation | One integrated QA receipt per value slice; zero unexpected spend; reversible deploy; commercial metrics instrumented without suggesting R7–R11 PASS. |

**Dependency guidance:** E0 precedes irreversible integration decisions. E1 creates the universal object context. E2/rights policies are a prerequisite for E3 external dispatch. E3/E4/E5/E6 feed E7; E8–E10 depend on provenance-quality observations; E11 and E12 are *cross-cutting*, not polishing phases deferred to the end. Independent slices may proceed concurrently only if contracts and ownership do not conflict.

**Next implementation slice after E0:** Choose the largest useful **missing** capability from E1–E3 by inspecting current code. If AE-12 or connector hub is already demonstrated in verified code, **REUSE/HARDEN/EXTEND** it; do not repeat milestone simply because an older document called it `NOT EXECUTED`. Aim for one full operator journey per increment, not just settings screens.

## 5. Capability acceptance contracts and measurable evidence

### 5.1 Core metrics (define thresholds before comparing)

| Dimension | Primary measurements | Non-negotiable caveat |
|---|---|---|
| Discovery/acquisition | Eligible sources, sampled coverage, successful/blocked fetches, median/p95 delay, drift, cadence, cost per 1,000 observations | `sources observed / sources available` requires a defined sampling frame; no claim to cover all web/social media. |
| Dedup/origin | Cluster precision/recall against labeled fixture, independent-origin attribution, missing origin flag | Cross-posting does not multiply independent sources or events. |
| Credibility | Factor-specific calibration: reliability, independent corroboration, direct evidence, contradiction, observability and manipulation risk | Composite number is not a probability of truth unless validated; ABSTAIN on insufficient data. |
| Social dynamics | Distinct posts/origins, exposure proxy, velocity per unit time, acceleration, engagement normalization, sampling and bot/manipulation suspicion | Role labels are hypotheses, not assertions about intent or identity. |
| Foresight | Forecast horizon, calibration/Brier or appropriate score where outcomes exist, watch-condition trigger quality, uncertainty/counterexamples | Scenario plausibility is not actual future certainty. |
| Operator UX | Task completion and time, error recovery, routine help, readability and accessible workflows | Internal test users cannot replace R2 representative counted users. |
| Reliability/security | p95 latency, peak RAM/CPU, failure rate, scoped role/tenant tests, quota enforcement, restore success, rollback time | Test coverage is not production admission. |
| Commercial | Validated buyer pain, frequency/repeat use, effort saved, price sensitivity, implementation/support cost, trial-to-paid | Paid intent cannot verify empirical claims or bypass R8/R9. |

### 5.2 Four EBPD-01 acceptance dimensions

1. **Capability:** end-to-end source/object→analysis→evidence→brief journey demonstrable with explicit provenance and error states.
2. **Usability:** primary user can understand status, unknowns and actions; core workflow usable on desktop/mobile with keyboard and accessible semantics.
3. **Reliability:** lawful access, rights, quotas, secrets, isolation, retries, data integrity, resource use and rollback verified on exact candidate.
4. **Commercial viability:** reusable configuration, deployment, supportability, covered source economics and traceable buyer value; label `UNVALIDATED` until actual buyer proof.

**Lean QA rule:** Reuse evidence for unchanged code/input/scope. For each meaningful new capability, run targeted behavior/negative/security tests, the necessary Core V2/cross-stack regression, TS/build/browser desktop-mobile/accessibility/visual checks *where UI changed*, and one receipt. Do not create repetitive audit-test cycles without a change or risk signal. Stop promotion if rights, authority, regression, or rollback fails.

## 6. Benchmarking and selective open-source adoption

Candidate **benchmark families** (not feature parity claims): Brand24, Brandwatch Consumer Research, Talkwalker, plus fit-for-purpose data/search/dashboard products when evidence warrants.

For every contemplated feature, record: **operator job → competitor behavior → SIFI existing capability → benefit hypothesis → lawful source feasibility → reusable OSS/Laravel module → license/version/security fit → integration cost → actual user-value metric → KEEP/REUSE/HARDEN/EXTEND/DEFER/REJECT**.

**High-value benchmark scenarios:** coverage health and alerts; mention spikes vs independent origins; contributor/propagation explorer; source-aware sentiment; cross-format text/media intelligence; executive snapshots; ergonomic investigation handoff; customizable monitoring objects.

**Reuse decision:** Native Laravel and existing SIFI modules first, then permissively licensed mature components with clear maintenance evidence. Do not adopt a module simply because competitor markets the feature. Avoid proprietary implementation copying, unauthorized crawling, bypass of API terms, or forced external paid dependency.

## 7. UX, product packaging, dan komersialisasi

**Information architecture (adapt existing routes):** Dashboard/Ringkasan, Monitoring Objects, Issues/Investigations, Research/Sources, Briefs/Watch; Superadmin Integration Hub terpisah menurut role. Dalam Issue Workspace tampilkan `Diketahui`, `Belum diketahui`, sumber/rights, evidence/contradiction, cutoff, origin count vs publication count, pergerakan isu, skenario, audit/review dan export. Sistem dapat menunjukkan apa yang belum bisa disimpulkan.

**Responsive QA:** Desktop, tablet dan mobile; accessible forms, focus order, visible status without color alone, keyboard workflows, zoom/reflow; target WCAG 2.2 AA untuk fitur baru. Scroll-driven storytelling hanya bila membantu kronologi/skenario dan harus menghormati reduced-motion.

**Commercial packaging hypothesis** (divalidasi di R8/R9; bukan penawaran final): Entry media/issue intelligence; Expansion structured issue/evidence; Premium trajectory/foresight; Platform governed API/export. Ukuran paket: cakupan sumber & wilayah, objek, analyst seats, frequency, history/retention, reports, API/export, service/SLA dan rights cost. Mulai dari bounded pilot; universality/multi-customer harus dibuktikan, bukan diasumsikan dari desain.

**R11 definition of done** tidak berubah: (i) product workflow+L5; (ii) incremental decision value L6/equivalent; (iii) reliable operations; (iv) lawful/governed security/privacy/IP/deployment; (v) validated buyer/economics; (vi) repeatable non-founder-only delivery. Bahkan jika R11 PASS pada satu target market, otorisasi tiap deployment tetap spesifik.

## 8. Autopilot development contract — GitHub ↔ lokal

### 8.1 Jalur transport dan prioritas sumber

- **Frozen legacy planning:** Master Roadmap v1.0 pada 17 Okt. Jangan mengedit ulang sejarah atau HEAD lama.
- **Owner-reported bridge 8 Nov:** Laravel vNext source `/home/fajar/projects/sifi-laravel-vnext`; snapshot `/mnt/d/Research/SIFI/05_VALIDATION/SIFI_GITHUB_SOURCE_SYNC_20261108_134500`; branch `sync/sifi-laravel-vnext-ih03j-20261108_134500`; 519 files published; docs-only delta applied `PASS`; source bridge and canonical governance branch/PR #2 reported `PASS`.
- **Pending confirmation:** branch PR #2 contains governance context, not necessarily this successor roadmap or merged `main`. The user's logs do not establish current live HEAD, runtime behavior, merge result, nor exact bytes installed after 8 Nov.
- **Canonical development contract:** Before each executable change read current `AGENTS.md`, merged/pending mandatory documents, actual repo HEAD/dirty status, service/schema ownership, and last accepted evidence. Never use a bare GitHub branch label as proof of live installation.

### 8.2 Per-run executor loop, results-first

1. Read current roadmap, owner mandates, actual repository and **last completed meaningful capability**, not an obsolete checklist.
2. Pick exactly one bounded **operator-value vertical slice** from the highest-priority unmet E milestone; tie it to R-gate evidence it can contribute without claiming R PASS.
3. Inventory existing Laravel/React/Core V2 code and trustworthy OSS; choose `REUSE/HARDEN/EXTEND` when sufficient. Avoid redundant rewrite, Python-to-PHP migration for its own sake, or cosmetic micro-increments.
4. Produce reviewable code in an isolated branch/disposable stage; implement source→consumer→UI end-to-end with lawful synthetic or approved shadow data.
5. Use one proportional acceptance gate for changed scope, including regression/security/rights/cost/UX. Reuse current reviewed evidence when unaffected; record failures honestly.
6. Commit/push development branch and open a bounded PR, with changed files, outcome demonstration, checks, resource/cost, risks, and reversible application notes.
7. Where preauthorized and safe, apply **exact reviewed source delta** locally with hash/diff guard, verified backup and rollback, preserving user edits, operational DB/WAL, secrets, active services and source protections. No automatic merges, migrations, production admission, public deploy, paid calls or irreversible actions beyond standing permission.
8. Update `E` capability ledger and evidence; **do not promote R1–R11** except upon genuine distinct acceptance evidence and legitimate authority. On human/rights/payment blockers, continue independent safe capability slices; stop only the blocked action and surface one specific decision request.

**Daily executor convention:** One cohesive, useful incremental PR/run; if implementation is blocked by source/context, deliver a precise blocked-state report rather than a fabricated PASS. Do not keep running micro QA loops to fill time. Scheduled automation can prepare evidence and PRs, not manufacture human participants, rights, or institutional approvals.

### 8.3 Promotion, rollback, and protected state

- Authoritative authority from `AGENTS.md` and `SIFI_CBM01_EBPD01_MANDATE_20261106.md`: `LOCAL_DEVELOPMENT_AUTHORIZATION=GRANTED`; safe reversible local install and automated tests preauthorized; `PRODUCTION_ADMISSION=DENIED`; `REAL_EVIDENCE_ADMISSION=DENIED`; destructive operations need separate approval.
- Existing Core V2 shadow code, protected databases/WAL, immutable lineage, `.env`/secrets and locally edited files are excluded from blind replacement; check exact scopes prior to local apply.
- Rights and license decision before external dispatch; no bypass, surprise bills, paid fallbacks, credential extraction, broad recursive deletion, or publication of private data.
- Previous direct `SIFI_CBM01_DIRECT_AI_BRIDGE_Install.py` was **withdrawn** in the 7 Nov alignment and must **not** be executed. Any replacement requires new target contract and clean candidate verification.
- **Storage/disk mandate:** historical SIFI workspace was reported at approximately 50 GB, with large `02_RUNTIME`, `runtime` and validation trees; the owner wants obsolete foundation and historical R17 material retired. Treat this as an **E12 storage-lifecycle task**, first identifying exact file identity, owner/current references, irreplaceable evidence and backup. Prefer deduplication, archive or recoverable relocation; delete only exact confirmed obsolete targets under separate destructive-operation approval. Never recursively purge a broad project root or erase a frozen canonical/evidence baseline to save disk space.

## 9. Snapshot status & evidence ledger (dated, not live attestation)

| Evidence time/source | What is supported | What is **not** supported |
|---|---|---|
| **17 Oct — master v1.0** | R0 engineering baseline `COMPLETE` at recorded HEAD `6ce5dd9`; CI and regression baseline; R1 was next formal gate | Not proof of Nov source HEAD, R1/R2, live acquisition or commercial product. |
| **20 Oct — Laravel addendum** | Laravel-first local prototype direction, additive architecture ownership | Explicitly *noncanonical* and not authority to rewrite evidence/CB22 or promote production. |
| **26 Oct — AE-12–16 owner mandate** | Universal object/web research/monitoring direction; AE-06–11 accepted in its then-current source | Historical “AE-12 NOT EXECUTED” is not automatically current on 8 Nov. |
| **6 Nov — CBM01/EBPD01 mandatory** | Results/reuse/real-data/Laravel-first; competitor selective integration; Superadmin Hub; evidence-based product acceptance | No permission for paid services, real-evidence admission or production. |
| **7 Nov — mandatory alignment** | `INTEGRATION_HOLD / REBASE_REQUIRED` on prior direct AI bridge, withdrawn installer, planned P0/P1/P2 outcomes | Not evidence that the new unified integrations have been installed. |
| **6–8 Nov — development status and owner logs** | Local Core V2 UI/QA and reviewer-follow-up receipts were reported; GitHub snapshot push, two-way docs-only apply and governance PR #2 reported `PASS` | No fresh direct runtime verification, universal-source end-to-end coverage, formal R1/R2 adjudication, or merged/production deployment. |

**Live status fields to refresh by executor:** `CURRENT_LARAVEL_HEAD`, `CURRENT_BRANCH`, `DIRTY_PATHS`, `MANDATE_HASH_MATCH`, `CORE_V2_GATE`, `E_MILESTONE_IMPLEMENTED`, `R_GATE_AUTHORITY`, `RIGHTS_SOURCE_PACKS`, `COST_QUOTA`, `QA_RECEIPT`, `BACKUP_ROLLBACK`, `PR_STATUS`, `LIVE_INSTALL_STATUS`. Unknown fields remain `NOT_VERIFIED`.

## 10. Next actions and dependency decisions

1. **Promote roadmap knowledge:** Preserve `v1.0` intact; propose this v1.1 as `docs/canonical/SIFI_MASTER_PRODUCT_ROADMAP_v1_1_20261108.md` in a GitHub PR alongside links to AGENTS, Oct20 addendum, Oct26 AE mandate, Nov6 mandatory and Nov7 alignment. Merge only through legitimate review; no direct overwrite of `main`.
2. **Single-pass current-code reconciliation:** Verify exact vNext source, mandatory hashes and existing Core V2/retrieval/source/UX contracts; determine whether E1/E2/E3 already implemented, partial or missing. Update capability ledger from actual code and receipts.
3. **Choose next high-value build:** Finish the highest-value incomplete P0 vertical slice—universal object, unified connector control-plane, or lawful public shadow stream—**integrated all the way into the analyst workspace**. Preserve zero-spend, rights and fail-closed behavior.
4. **Parallel governance track:** Prepare R1 CB22 assignment/recruitment/screening/consent packages for the owner and authorized human coordinators. Do not claim readiness until real roles and determinations exist. Start R2 counted sessions only when R1 passes.
5. **Expand intelligence and benchmark:** After source/control-plane contract is stable, pursue E4–E10, with real usefulness measures and reuse of existing research/evidence engines. E11/E12 remain continuous across every slice.
6. **Commercial gate discipline:** Gather buyer/operations evidence without conflating generic technical completion with L5, L6, R7 or R11; reassess % progress only with a disclosed weighting and verified evidence date.

## 11. Traceability register — authoritative inputs

1. **SIFI Master Product Roadmap v1.0 (17 Oct 2026)** — `SIFI_MASTER_PRODUCT_ROADMAP_v1_0.md` ([Library](https://chatgpt.com/library?file_id=libfile_d411234684388191957ca70d4a813cbf&account_id=personal)). Frozen R0–R11, Stage 0–6, L0–L6 and epistemic rules.
2. **SIFI UI/UX Design & Frontend Engineering Handoff v1.0 (18 Oct 2026)** — [Library](https://chatgpt.com/library?file_id=libfile_34d84737f1a48191b52da0ebd3272091&account_id=personal). Evidence-First UX proposal, accessibility, flows, UI states; do not mistake proposed design for implementation.
3. **SIFI Roadmap Laravel-First Addendum (20 Oct 2026)** — [Library](https://chatgpt.com/library?file_id=libfile_d114c4b549348191b1085c160e8d39e3&account_id=personal). Noncanonical execution overlay, existing Python analytical kernel retained.
4. **SIFI Increment Plan 10–16 (20 Oct 2026)** — [Library](https://chatgpt.com/library?file_id=libfile_5cdff453d7bc8191a645fe19a25c93cb&account_id=personal). Historical proposed increments; mapping to E but no formal gate promotion.
5. **Universal Intelligence & Web Research Expansion, AE-12–16 (26 Oct 2026)** — [Library](https://chatgpt.com/library?file_id=libfile_1c11983be3548191938742c67967a894&account_id=personal). Owner mandate and acceptance for monitoring object, source discovery, research, scheduler, intelligence.
6. **SIFI-CBM-01 / SIFI-EBPD-01 Mandate (6 Nov 2026)** — [Library](https://chatgpt.com/library?file_id=libfile_fc95d8b5a0a88191ab7c69e1c1c3e92d&account_id=personal). Mandatory owner development criteria and current authorization boundaries.
7. **SIFI Mandatory Alignment (7 Nov 2026)** — [Library](https://chatgpt.com/library?file_id=libfile_5f8ed12e3a3481918440cff1902c0b7f&account_id=personal). P0–P2 priorities, architecture, evidence guard, withdrawn direct AI bridge, integration hold and next stage contract.
8. **Owner-provided WSL/GitHub logs (8 Nov 2026)** — source snapshot/sync success, docs-only apply, canonical governance PR #2 and production denied; **owner-reported, not independent runtime attestation**.

## 12. Version control and succession contract

- **v1.0 frozen:** tanggal/HEAD/status historis tetap tidak berubah dan tidak di-rewrite.
- **v1.1 candidate:** dokumen ini menambahkan mandat setelah 17 Oktober dan memperjelas engineering backlog E0–E12 di samping R0–R11; tidak mengklaim audit atau deployment baru.
- **Upon owner/repo acceptance:** record PR/merge commit, SHA-256 exact roadmap, source-mandate hash provenance, approval date, and canonical path. Setelah itu `SIFI Milestone Executor` harus membaca v1.1 bersama v1.0 dan AGENTS pada setiap run.
- **For future changes:** amend under a successor version with `added / superseded / retained`, explicit authority, and delta; do not silently renumber R or alter counted CB22 protocol, past evidence and Z0–Z3 semantics.

**Bottom line:** Cepat membangun produk universal yang berguna dengan reuse-first, real-data-first dan Laravel-first; tetap jujur bahwa **pembuatan fitur**, **hak mengakses data**, **kelayakan bukti**, **keberhasilan pengguna**, **nilai keputusan**, **kesiapan komersial** dan **otorisasi produksi** adalah keputusan serta bukti yang berbeda.