# SIFI — UNIVERSAL INTELLIGENCE & WEB RESEARCH EXPANSION
## AE-12 Execution + AE-13–AE-16 Roadmap Integration
**Mandat Pemilik Produk — 26 Oktober 2026**

Anda bertindak sebagai Principal Product Architect, Laravel Engineer, Search & Crawling Systems Architect, Intelligence Engineer, Security Engineer, QA Lead, dan SaaS Product Strategist untuk SIFI.

# 1. KEPUTUSAN STRATEGIS PEMILIK

SIFI (Strategic Issue & Foresight Intelligence) adalah platform intelligence universal.

Kota Yogyakarta hanya menjadi pilot use case, bukan batas geografis, sektoral, atau fungsional produk.

SIFI harus dapat memantau objek apa pun yang sah, misalnya:
- Wilayah dan pemerintahan.
- Perusahaan dan merek.
- Industri dan komoditas.
- Kebijakan dan regulasi.
- Proyek, program dan organisasi.
- Tokoh publik.
- Peristiwa dan isu strategis.
- Teknologi dan perkembangan pasar.
- Objek pemantauan lain yang ditentukan pengguna.

Pemilik produk juga menyetujui pengembangan kemampuan pencarian web, penelusuran informasi multi-langkah, dan crawling berkala seperti pengalaman riset modern pada ChatGPT, tetapi dengan nilai tambah intelligence SIFI.

SIFI bukan chatbot biasa dan bukan sekadar crawler.

TARGET PRODUK:

**DISCOVER → SEARCH → FETCH → EXTRACT → VERIFY LIMITATIONS → MONITOR → ANALYZE → FORESIGHT → BRIEF.**

# 2. BASELINE DAN STATUS

Baca baseline AE-11 terbaru:

`D:\Research\SIFI\05_VALIDATION\PROGRESS\SIFI_AE11_USER_OPERATED_ACQUISITION_2026_10_26\APPROVED_FINAL_REPORT.md`

Baca juga:
- ACTIVE_ENGINEERING_WORKFLOW.md.
- Checkpoint pengembangan aktif.
- SIFI MASTER PRODUCT ROADMAP v1.0.
- Addendum Laravel-first yang tersedia.
- Catatan AE-12 sebelumnya, bila sudah dibuat.

PENTING:

AE-12 BELUM DIEKSEKUSI.

Jangan mengklaim AE-12 selesai atau telah dipromosikan.

AE-06 sampai AE-11 sudah diterima. Jangan menginstal ulang milestone tersebut.

AI tetap OFF. Provider eksternal belum terkoneksi.

Jangan mengulang permintaan Gemini API key, membuat credential, melakukan AI generation, atau menggunakan grant/live GET lama.

Jangan restart, stop, atau takeover layanan yang sudah berjalan tanpa otorisasi baru.

# 3. ROADMAP ALIGNMENT

Audit Master Roadmap untuk mengidentifikasi kemampuan yang sudah tercakup:

- Lawful Source Discovery.
- Source Registry dan Rights Governance.
- Periodic Acquisition.
- API, RSS, sitemap, dan documented interfaces.
- Provenance.
- Deduplication.
- Evidence Qualification.
- Issue Intelligence.
- Trajectory dan Foresight.
- Decision Brief.

Bedakan kemampuan yang:
- Sudah direncanakan.
- Sudah diimplementasikan.
- Baru teruji dalam staging.
- Sudah aktif.
- Belum tersedia.

Susun addendum pengembangan yang menegaskan AE-12 sampai AE-16.

Jangan mengganti atau menghapus Master Roadmap canonical dan arsip historis.

Jangan membuat roadmap baru yang terputus dari arsitektur SIFI existing.

# 4. AE-12 — UNIVERSAL MONITORING OBJECT

PRIORITAS EKSEKUSI PERTAMA.

Audit model existing untuk workspace, ownership, coverage profile, source association, Issue Candidate, Case, Evidence, dan Brief.

Bangun konfigurasi Monitoring Object minimum yang memungkinkan pengguna:

1. Membuat objek pemantauan.
2. Memberikan nama dan deskripsi.
3. Memilih atau menambahkan kategori objek.
4. Menentukan alias, keyword, dan exclusion terms.
5. Menentukan cakupan geografis opsional.
6. Menentukan tujuan analisis.
7. Mengatur horizon waktu.
8. Memilih sumber yang telah diizinkan.
9. Menyimpan dan memperbarui konfigurasi.
10. Menjeda atau mengarsipkan pemantauan.
11. Membuka Workbench dalam konteks objek.

Jangan mewajibkan semua objek memiliki wilayah geografis.

Jangan hardcode kategori objek sebagai pilihan tertutup.

Jangan menganggap semua objek berkaitan dengan pemerintah daerah.

Gunakan Laravel native serta domain model existing.

Satu source record dapat relevan terhadap beberapa objek, tetapi access control dan konteks analisis masing-masing harus tetap terjaga.

Buktikan mekanisme yang sama dengan tiga objek sintetis:
- Isu publik Kota Yogyakarta.
- Perusahaan/merek fiktif.
- Industri kendaraan listrik.

Tidak boleh diperlukan perubahan kode khusus untuk masing-masing objek.

Bangun vertical slice AE-12 sampai staging acceptance, bukan sekadar desain dan laporan.

# 5. AE-13 — UNIVERSAL SOURCE DISCOVERY

Rencanakan kemampuan SIFI menemukan sumber informasi baru yang relevan dengan Monitoring Object.

Sumber dapat berupa:
- API resmi.
- RSS/Atom.
- Sitemap.
- Website dengan akses yang diizinkan.
- Portal data terbuka.
- Publikasi dan dokumen.
- Dataset terstruktur.
- Search provider dengan API dan hak penggunaan yang sesuai.

Bedakan:

A. Source discovery: menemukan sumber potensial.

B. Source registration: mencatat identitas, metode akses, dan kebijakan.

C. Rights verification: memastikan bentuk penggunaan yang diizinkan.

D. Acquisition: mengambil data sesuai izin.

E. Evidence admission: menentukan apakah data memenuhi syarat mendukung suatu klaim.

Source discovery tidak memberikan izin otomatis untuk crawling atau evidence admission.

Rancang source ranking berdasarkan relevansi, kredibilitas sesuai konteks, kesegaran, ketersediaan, biaya, batas akses, dan diversitas asal informasi.

Jangan hanya mengutamakan sumber resmi pemerintah. Sumber yang relevan bergantung pada objek dan tujuan pengguna.

# 6. AE-14 — ON-DEMAND WEB RESEARCH

Bangun kemampuan agar pengguna dapat memberikan pertanyaan, misalnya:

"Cari perkembangan terbaru industri kendaraan listrik di Indonesia."

"Apa isu reputasi terbaru terkait perusahaan X?"

"Bandingkan perkembangan regulasi AI di beberapa negara."

SIFI harus dapat:
1. Memahami objek dan cakupan pertanyaan.
2. Menyusun pencarian terstruktur.
3. Mencari pada sumber terdaftar atau penyedia search resmi.
4. Menampilkan hasil dan URL sumber.
5. Memilih informasi yang relevan.
6. Mengambil informasi lanjutan sesuai izin.
7. Membandingkan beberapa sumber.
8. Mengenali duplikasi dan asal publikasi yang sama.
9. Mengidentifikasi fakta, klaim, kontradiksi, dan unknown.
10. Menyusun jawaban dengan provenance.
11. Menghubungkan hasil ke Monitoring Object.
12. Menawarkan penyimpanan hasil ke intelligence workflow setelah validasi aturan yang berlaku.

Pencarian deterministik harus tetap dapat berjalan tanpa AI.

AI opsional dapat membantu query planning, extraction, summarization, dan research reasoning setelah provider gratis benar-benar terkoneksi serta penggunaan data diotorisasi.

Jangan membuat URL, kutipan, atau sumber palsu.

Jangan menyatakan pencarian mencakup seluruh internet jika coverage sebenarnya terbatas.

# 7. AE-15 — SCHEDULED MULTI-SOURCE MONITORING

Rencanakan pengumpulan otomatis dari sejumlah sumber yang sah dengan jadwal yang dapat dikonfigurasi.

Gunakan Laravel Scheduler, Queue, HTTP Client, dan komponen existing bila sesuai.

Persyaratan:
- Source-specific access policy.
- Rights validation sebelum collection.
- Configurable cadence.
- Per-source rate limit.
- Request budget.
- Retry/backoff terbatas dan aman.
- Deduplication/idempotency.
- Checkpoint dan resumability.
- Incremental fetching.
- Source-health monitoring.
- Parser/schema drift detection.
- Timestamp serta content revision history.
- Attribution dan retention.
- Pause/resume.
- Operator-facing failure messages.
- Audit logs.
- No unauthorized sources.
- No anti-bot, CAPTCHA, login, atau paywall bypass.

Jangan menjadwalkan collector nyata dalam AE-12.

Scheduler live harus tetap OFF sampai memperoleh izin spesifik terhadap sumber, interval, endpoint, data, dan penyimpanan.

# 8. AE-16 — RESEARCH-TO-INTELLIGENCE

Semua hasil penelusuran dan monitoring harus menggunakan intelligence pipeline yang telah dikembangkan.

Tidak boleh dibuat second intelligence engine.

Gunakan:

Observation → Claim → Evidence → Issue → Trajectory → Risk/Opportunity → Foresight → Brief.

Pertahankan:
- Provenance.
- Source independence.
- Point-in-time correctness.
- Uncertainty.
- Contradiction.
- Abstention.
- Revision history.
- Non-binding recommendations.

Temuan penelusuran web bukan otomatis kebenaran empiris.

Source credibility tidak identik dengan claim verification.

AI-generated explanation tidak boleh menjadi evidence tanpa dasar.

# 9. SEARCH & CRAWLING PROVIDER STRATEGY

SIFI tidak harus membangun search engine seluruh internet dari nol.

Evaluasi penggunaan search APIs, RSS directories, official APIs, dan sumber yang terdokumentasi.

Prinsip:
- Free/zero-spend first.
- Provider-neutral.
- User-configurable.
- No vendor lock-in.
- Rights-aware.
- Privacy-aware.
- Explicit quota/error states.

Sampaikan keterbatasan penyedia pencarian gratis secara jujur.

Jangan menggunakan scraping Google/Bing search results secara tidak sah sebagai pengganti API resmi.

Jangan mengaktifkan koneksi search API eksternal tanpa memeriksa terms dan otorisasinya.

# 10. SECURITY & RELIABILITY

Gunakan AE-10 bounded acquisition sebagai fondasi keamanan HTTP.

Audit reuse untuk:
- Endpoint restrictions.
- SSRF prevention.
- Redirect controls.
- Response-size limits.
- Timeout.
- Grant/permission boundary.
- Secret redaction.
- Concurrency.
- Cross-user isolation.
- Retention.
- Error handling.

Jangan menganggap satu World Bank adapter otomatis aman untuk crawling web umum.

Untuk HTML parsing, dokumen, sitemap, atau search provider baru, lakukan threat modeling dan pengujian terpisah.

Dilarang menonaktifkan guard existing demi kemudahan crawling.

# 11. COMMERCIAL PRODUCT REQUIREMENTS

Target akhirnya adalah platform universal yang dapat digunakan banyak pelanggan dengan objek dan sumber yang berbeda.

Audit readiness untuk:
- Monitoring Object configuration.
- Multi-object support.
- Workspace ownership.
- Tenant isolation.
- Universal source configuration.
- Search and crawl coverage.
- Intelligence accuracy and traceability.
- Operational reliability.
- Security/privacy.
- Deployment packaging.
- Commercial licensing/rights.
- Onboarding.
- Supportability.
- Scalability.

Pisahkan:
- Pilot Yogyakarta readiness.
- Universal product readiness.
- Commercial deployment readiness.

Estimasi kesiapan komersial lama tidak boleh dinaikkan otomatis.

Lakukan rebaseline berdasarkan visi platform universal.

# 12. EXECUTION PLAN

Lakukan pekerjaan dalam urutan berikut:

PHASE A — Audit cepat existing architecture dan gap AE-12–AE-16.

PHASE B — Susun Universal Intelligence & Web Research Roadmap Addendum.

PHASE C — Bangun AE-12 Monitoring Object vertical slice di isolated staging.

PHASE D — Uji tiga tipe objek, authorization, isolation, regression, dan browser desktop/mobile.

PHASE E — Siapkan exact AE-12 promotion scope.

PHASE F — Rekomendasikan executable AE-13 next milestone.

Jangan mengimplementasikan AE-13–AE-16 sekaligus dalam satu scope besar sebelum AE-12 terbukti berhasil.

Namun, rancang kontrak AE-12 agar tidak perlu dibongkar ketika Source Discovery dan On-Demand Research ditambahkan.

Hindari overengineering.

# 13. DELIVERY CONTROL

Pertahankan:
- Existing source.
- Local user edits.
- Protected files.
- Database domain.
- Git HEAD.
- Laravel/AQ01 runtime.
- Existing manifest dan frontend assets.

Dilarang tanpa otorisasi:
- Source promotion.
- Active database migration.
- Live source crawling.
- Search provider external calls.
- AI generation.
- Credential creation.
- Billing changes.
- Launcher/service lifecycle actions.
- Destructive Git actions.

Gunakan isolated staging dan synthetic fixtures.

Selesaikan audit, implementasi, dan automated testing yang dapat dilakukan tanpa izin baru.

Jika membutuhkan perubahan source aktif, siapkan:
- Exact diff.
- Frozen scope SHA-256.
- Backup verification.
- Rollback rehearsal.
- Migration impact.
- Asset/manifest impact.
- Targeted/full regression.
- Browser acceptance.
- One exact-scope approval request.

Gunakan satu approval untuk satu frozen promotion scope, bukan banyak approval mikro.

# 14. ACCEPTANCE

AE-12 dianggap berhasil di staging apabila:

1. Tiga objek berbeda dapat dibuat tanpa custom code.
2. Scope tiap objek dapat dibedakan.
3. Source associations mengikuti rights policy.
4. Data dan receipt tidak bocor antar-owner.
5. Pause/archive berfungsi.
6. Object revisions dapat diaudit.
7. Existing AE-07–AE-11 functionality tidak regresi.
8. Tidak ada external collection.
9. AI tetap OFF.
10. Browser desktop/mobile PASS.

Untuk AE-13–AE-16, laporkan acceptance criteria tersendiri dan dependency yang benar-benar diperlukan.

# 15. FINAL REPORT

Simpan hasil pada:

`D:\Research\SIFI\05_VALIDATION\PROGRESS`

Laporkan:
- Audit existing.
- Keselarasan dengan Master Roadmap.
- Universal Vision & Web Research Addendum.
- AE-12 capability yang benar-benar dibangun.
- Tiga-object demonstration.
- Hasil security/regression/browser.
- Source dan database integrity.
- Staging versus active status.
- Frozen promotion scope.
- Revised commercial-readiness scorecard.
- Roadmap AE-13 sampai AE-16.
- AE-13 first executable work package.
- Blockers yang benar-benar relevan.

Perbarui checkpoint dan ACTIVE_ENGINEERING_WORKFLOW.md.

## FINAL COMMAND

**EXECUTE AE-12 UNIVERSAL MONITORING OBJECT DEVELOPMENT NOW.**

Secara paralel, integrasikan AE-13 sampai AE-16 ke product roadmap melalui addendum.

Jangan membuat ulang SIFI, jangan menjadikan Yogyakarta domain tetap, dan jangan mengklaim search/crawling yang belum ada sebagai kemampuan aktif.

Bangun fondasi universal yang dapat diperluas menjadi web discovery, on-demand research, scheduled crawling, dan research-to-intelligence.

**ULTIMATE GOAL: SIFI memungkinkan pengguna menentukan objek apa pun, mencari dan memantau informasi dari sumber yang sah, menganalisis perubahan serta evidence, dan menghasilkan strategic intelligence yang dapat ditelusuri.**