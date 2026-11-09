<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; object-src 'none'">
    <title>SIFI — Multi-Source Research Snapshot</title>
    <style>
        :root{color-scheme:dark;--bg:#0a111f;--panel:#111d30;--line:#2a3c55;--text:#e8f0ff;--muted:#a2b5cd;--mint:#58e2b0;--amber:#ffcc7b}
        *{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 75% 0%,#14354b 0%,var(--bg) 48%);color:var(--text);font:15px/1.5 system-ui,-apple-system,'Segoe UI',sans-serif}
        a{color:#a7eaff;overflow-wrap:anywhere;text-decoration:none}a:hover,a:focus{text-decoration:underline}
        main{max-width:1160px;margin:auto;padding:28px 28px 64px}
        .brand{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:24px;border-bottom:1px solid var(--line);padding-bottom:18px}
        .mark{font-size:16px;font-weight:850;letter-spacing:.16em}.tag{display:inline-block;padding:5px 11px;border:1px solid #377963;background:#123b34;color:var(--mint);border-radius:20px;font-size:11px;font-weight:750;letter-spacing:.08em}
        .eyebrow{color:var(--mint);font-weight:800;letter-spacing:.15em;font-size:11px;text-transform:uppercase}h1{font-size:clamp(30px,4vw,49px);line-height:1.1;letter-spacing:-.03em;margin:14px 0 12px}p{margin:0;color:var(--muted);max-width:760px}
        .metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:30px 0}.metric,.panel{border:1px solid var(--line);background:linear-gradient(145deg,rgba(21,38,60,.94),rgba(13,26,43,.97));border-radius:18px;min-width:0}.metric{padding:20px}.metric .n{font-size:36px;font-weight:800;line-height:1.1;font-variant-numeric:tabular-nums}.metric .t{font-size:12px;color:var(--muted);margin-top:8px}
        .columns{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(260px,.9fr);gap:16px}.panel{padding:22px;margin-bottom:16px}h2{margin:0 0 14px;font-size:17px;letter-spacing:-.01em}.story{padding:15px 0;border-top:1px solid var(--line)}.story:first-of-type{border-top:0}.story h3{margin:0 0 8px;font-size:15px;font-weight:650}.source{display:flex;flex-wrap:wrap;gap:8px;align-items:center;font-size:11px;color:var(--muted)}.pill{color:var(--mint);border:1px solid #2e7564;border-radius:8px;padding:2px 7px;font-size:10px;font-weight:800;letter-spacing:.08em}
        .term{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid var(--line);padding:9px 0}.term:last-child{border-bottom:0}.term strong{font-variant-numeric:tabular-nums;color:var(--mint)}
        .warning{border-color:#786144;background:rgba(78,56,27,.26)}.warning h2{color:var(--amber)}.warning p{font-size:13px;line-height:1.6}.small{font-size:11px;color:var(--muted);overflow-wrap:anywhere}.footer{border-top:1px solid var(--line);margin-top:26px;padding-top:18px}
        @media(max-width:830px){.columns{grid-template-columns:1fr}.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}main{padding:20px 18px 48px}.metric{padding:16px}}
        @media(max-width:430px){.metrics{gap:10px}.metric .n{font-size:30px}.metric .t{font-size:11px}.panel{padding:17px}.brand{align-items:flex-start;flex-direction:column}.story h3{font-size:14px}}
    </style>
</head>
<body>
<main>
    <div class="brand"><span class="mark">SIFI <span style="color:var(--mint)">/ INTELLIGENCE</span></span><span class="tag">SHADOW • READ ONLY</span></div>
    <div class="eyebrow">Research workspace / IH03G</div>
    <h1>Multi-Source<br>Research Snapshot</h1>
    <p>Perbandingan metadata akademik Crossref dan berita ANTARA dari dua receipt nyata yang sudah tersedia. Ini adalah inventaris perhatian sumber, bukan verifikasi peristiwa atau kebenaran klaim.</p>
    <section class="metrics" aria-label="Ringkasan data">
        <div class="metric"><div class="n">{{ $snapshot['source_counts']['CROSSREF'] }}</div><div class="t">Publikasi Crossref</div></div>
        <div class="metric"><div class="n">{{ $snapshot['source_counts']['ANTARA_RSS'] }}</div><div class="t">Berita ANTARA</div></div>
        <div class="metric"><div class="n">{{ $snapshot['distinct_canonical_urls'] }}</div><div class="t">URL kanonis berbeda</div></div>
        <div class="metric"><div class="n">{{ count($snapshot['same_url_cross_source_candidates']) }}</div><div class="t">Kandidat URL sama lintas sumber</div></div>
    </section>
    <div class="columns">
        <section class="panel" aria-label="Metadata sumber">
            <h2>Publikasi & berita terindeks</h2>
            @foreach($snapshot['titles'] as $row)
                <article class="story">
                    <h3><a href="{{ $row['canonical_url'] }}" rel="noopener noreferrer" target="_blank">{{ $row['title'] }}</a></h3>
                    <div class="source">
                        <span class="pill">{{ $row['provider'] }}</span>
                        <span>Terbit: {{ $row['published_at'] ?? 'Tidak diketahui' }}</span>
                        <span>Presisi: {{ $row['publication_precision'] }}</span>
                    </div>
                </article>
            @endforeach
        </section>
        <aside aria-label="Ringkasan dan batasan">
            <section class="panel"><h2>Istilah berulang pada judul</h2>
                @forelse($snapshot['recurring_title_terms'] as $term => $count)
                    <div class="term"><span>{{ $term }}</span><strong>{{ $count }}</strong></div>
                @empty
                    <p>Tidak tersedia istilah berulang.</p>
                @endforelse
            </section>
            <section class="panel warning"><h2>Batas interpretasi</h2>
                <p>Jumlah berita bukan jumlah peristiwa. Kesamaan judul bukan bukti penyalinan atau konfirmasi independen. Dua jenis sumber ini tidak dapat digunakan untuk mengukur laju penyebaran, tingkat prevalensi, dampak, ataupun lintasan isu.</p>
            </section>
            <section class="panel"><h2>Keamanan & provenance</h2>
                <div class="small">CROSSREF: {{ $snapshot['source_receipts']['CROSSREF'] }}</div>
                <div class="small" style="margin-top:9px">ANTARA: {{ $snapshot['source_receipts']['ANTARA_RSS'] }}</div>
                <div class="small" style="margin-top:14px">NETWORK REQUESTS: 0 / DATABASE WRITES: 0 / AI: OFF / EVIDENCE ADMISSION: NONE</div>
            </section>
        </aside>
    </div>
    <div class="footer small">SIFI — isolated local research preview. Tidak ada keputusan otomatis, klaim terverifikasi, atau akses produksi dari halaman ini.</div>
</main>
</body>
</html>
