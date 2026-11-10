import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
type Result = {
    key: string;
    title: string;
    url: string;
    snippet: string;
    origin: string;
};
type Document = {
    body: string;
    body_sha256: string;
    parser_version: string;
    quote: { text: string; byte_start: number; byte_end: number };
    fixture_loaded_at: string;
};
type Retrieval = {
    id: string;
    revision: number;
    state: string;
    expired: boolean;
    candidate_id: string | null;
    result: Result | null;
    document: Document | null;
};
type Props = {
    object: { id: string; name: string; revision: number; state: string };
    sessions: { id: string; question: string }[];
    selected: string | null;
    search: {
        query_sha256: string;
        state: string;
        results: Result[];
        distinct_origins: number;
        provider: string;
    } | null;
    retrievals: Retrieval[];
};
export default function ResearchBridge({
    object: o,
    sessions,
    selected,
    search,
    retrievals,
}: Props) {
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [confirmed, setConfirmed] = useState(false);
    const [note, setNote] = useState('');
    const base = `/sifi/monitoring-objects/${o.id}/research-bridge`;
    const opts = {
        preserveScroll: true,
        onStart: () => setBusy(true),
        onFinish: () => setBusy(false),
        onError: setErrors,
    };
    const decide = (e: Result) =>
        router.post(
            base,
            {
                session_id: selected,
                result_key: e.key,
                query_sha256: search?.query_sha256,
                object_revision: o.revision,
            },
            opts,
        );
    const act = (r: Retrieval, kind: 'snapshot' | 'draft') =>
        router.post(
            `${base}/${r.id}/${kind}`,
            {
                revision: r.revision,
                object_revision: o.revision,
                confirm_fixture: confirmed,
                ...(kind === 'draft' ? { analyst_note: note } : {}),
            },
            opts,
        );
    return (
        <>
            <Head title="SIFI · Dokumen riset" />
            <div className="min-h-screen min-w-0 space-y-5 bg-muted/20 p-4 lg:p-8">
                <header className="space-y-2">
                    <p className="text-xs tracking-widest uppercase">
                        SIFI / RESEARCH BRIDGE
                    </p>
                    <h1 className="text-3xl font-semibold">
                        Dokumen &amp; jejak riset
                    </h1>
                    <p className="break-words">
                        {o.name} · revisi {o.revision}
                    </p>
                    <nav className="flex flex-wrap gap-4 text-sm">
                        <Link
                            className="underline"
                            href={`/sifi/monitoring-objects/${o.id}/research-worldbank`}
                        >
                            Source pack terkurasi
                        </Link>
                        <Link
                            className="underline"
                            href={`/sifi/monitoring-objects/${o.id}/research`}
                        >
                            Rencana riset
                        </Link>
                        <Link
                            className="underline"
                            href={`/sifi/workbench?object=${o.id}`}
                        >
                            Workbench objek
                        </Link>
                    </nav>
                </header>
                <section className="space-y-2 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                    <p className="font-semibold">
                        SIMULASI DOKUMEN · NETWORK / AI OFF
                    </p>
                    <p>
                        Metadata hasil, keputusan pengambilan, snapshot dokumen,
                        dan draft analisis adalah tahap berbeda. Hanya materi
                        buatan sendiri SYNTHETIC .invalid yang tersedia. Tidak
                        ada halaman internet diambil.
                    </p>
                    <p>
                        Provider dapat dipertukarkan melalui kontrak internal;
                        koneksi provider nyata belum tersedia. Hak sumber nyata
                        UNASSESSED; origin independen belum diverifikasi.
                    </p>
                </section>
                <label className="block text-sm">
                    Sesi riset
                    <select
                        aria-label="Sesi riset"
                        className="mt-1 w-full rounded-lg border bg-background p-3"
                        value={selected ?? ''}
                        onChange={(e) =>
                            router.get(base, { session: e.target.value })
                        }
                    >
                        <option value="">Pilih sesi</option>
                        {sessions.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.question}
                            </option>
                        ))}
                    </select>
                </label>
                {Object.entries(errors).map(([k, v]) => (
                    <p
                        role="alert"
                        key={k}
                        className="break-words text-destructive"
                    >
                        {v}
                    </p>
                ))}
                {!search && (
                    <p>Buat rencana riset untuk objek ini terlebih dahulu.</p>
                )}
                {search && (
                    <section className="space-y-3">
                        <h2 className="text-xl font-semibold">
                            Hasil metadata ({search.results.length})
                        </h2>
                        <p className="text-sm">
                            {search.state} · {search.distinct_origins} origin ·
                            independensi NOT_VERIFIED · 0 request jaringan /
                            biaya
                        </p>
                        {search.results.length === 0 && (
                            <p>
                                Tidak ada hasil dalam katalog lokal. Tidak
                                menyimpulkan ketiadaan informasi di internet.
                            </p>
                        )}
                        <div className="grid min-w-0 gap-3 lg:grid-cols-2">
                            {search.results.map((e) => (
                                <article
                                    className="min-w-0 space-y-2 rounded-xl border bg-card p-4"
                                    key={e.key}
                                >
                                    <h3 className="font-semibold break-words">
                                        {e.title}
                                    </h3>
                                    <p className="text-sm break-words">
                                        {e.snippet}
                                    </p>
                                    <code className="block text-xs break-all">
                                        {e.url}
                                    </code>
                                    <button
                                        disabled={
                                            busy || o.state !== 'CONFIGURED'
                                        }
                                        onClick={() => decide(e)}
                                        className="rounded-lg border px-3 py-2 text-sm"
                                    >
                                        Pilih {e.key} untuk draft keputusan
                                    </button>
                                </article>
                            ))}
                        </div>
                    </section>
                )}
                <section className="space-y-3 rounded-xl border bg-card p-4">
                    <h2 className="text-xl font-semibold">
                        Keputusan &amp; snapshot ({retrievals.length})
                    </h2>
                    <label className="flex items-start gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={confirmed}
                            onChange={(e) => setConfirmed(e.target.checked)}
                        />
                        <span>
                            Saya memahami snapshot dan draft berikut hanya
                            fixture sintetis, bukan evidence terverifikasi.
                        </span>
                    </label>
                    <label className="block text-sm">
                        Catatan analis untuk membuat draft
                        <textarea
                            className="mt-1 w-full rounded-lg border bg-background p-3"
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                            maxLength={1000}
                            rows={3}
                        />
                    </label>
                    {retrievals.map((r) => (
                        <article
                            className="min-w-0 space-y-2 rounded-lg border p-3"
                            key={r.id}
                        >
                            <h3 className="font-semibold break-words">
                                {r.result?.title ?? 'Konteks kedaluwarsa'}
                            </h3>
                            <p className="text-sm">
                                {r.state} · v{r.revision}
                            </p>
                            {r.document && (
                                <>
                                    <pre className="text-sm break-words whitespace-pre-wrap">
                                        {r.document.body}
                                    </pre>
                                    <p className="text-xs break-all">
                                        SHA-256 {r.document.body_sha256} ·{' '}
                                        {r.document.parser_version}
                                    </p>
                                    <blockquote className="border-l-2 pl-3 text-sm">
                                        Kutipan fixture: {r.document.quote.text}
                                    </blockquote>
                                    <p className="text-xs">
                                        Byte span [{r.document.quote.byte_start}
                                        , {r.document.quote.byte_end}) · tidak
                                        ada tanggal publikasi / retrieval
                                        internet.
                                    </p>
                                </>
                            )}
                            {!r.expired && r.state === 'DECISION_DRAFT' && (
                                <button
                                    disabled={busy || !confirmed}
                                    onClick={() => act(r, 'snapshot')}
                                    className="rounded-lg border px-3 py-2 text-sm"
                                >
                                    Buat snapshot fixture
                                </button>
                            )}
                            {!r.expired && r.state === 'FIXTURE_SNAPSHOT' && (
                                <button
                                    disabled={
                                        busy || !confirmed || note.length < 20
                                    }
                                    onClick={() => act(r, 'draft')}
                                    className="rounded-lg border px-3 py-2 text-sm"
                                >
                                    Buat draft melalui pipeline existing
                                </button>
                            )}
                            {r.candidate_id && (
                                <Link
                                    className="block underline"
                                    href={`/sifi/issue-candidates/${r.candidate_id}/executive-brief`}
                                >
                                    Buka lineage &amp; brief draft
                                </Link>
                            )}
                        </article>
                    ))}
                    <p className="text-xs text-muted-foreground">
                        Kutipan berasal dari body fixture dan diverifikasi
                        terhadap hash serta offset byte. Draft tetap NOT
                        VERIFIED / NOT_EVALUATED, tanpa evidence admission.
                    </p>
                </section>
            </div>
        </>
    );
}
