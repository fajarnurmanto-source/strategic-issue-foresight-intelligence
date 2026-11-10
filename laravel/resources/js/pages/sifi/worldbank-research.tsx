import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
type Row = {
    id: string;
    revision: number;
    state: string;
    expired: boolean;
    candidate_id: string | null;
    document_digest: string | null;
    result: {
        title: string;
        acquisition_mode?: string;
        acquisition_reason?: string;
    } | null;
    document: {
        body: string;
        body_sha256: string;
        mode: string;
        retrieved_at: string;
        published_at: null;
        quote: { text: string; byte_start: number; byte_end: number };
    } | null;
};
type Props = {
    object: { id: string; name: string; revision: number; state: string };
    sessions: { id: string; question: string }[];
    selected: string | null;
    authorization: {
        state: string;
        context_sha256: string;
        mode: string;
        pack: {
            id: string;
            endpoint: string;
            max_bytes: number;
            total_seconds: number;
        };
    } | null;
    retrievals: Row[];
    sources: { id: string; name: string; revision: number }[];
};
export default function WorldBankResearch({
    object: o,
    sessions,
    selected,
    authorization: a,
    retrievals,
    sources,
}: Props) {
    const [confirmed, setConfirmed] = useState(false);
    const [note, setNote] = useState('');
    const [source, setSource] = useState(sources[0]?.id ?? '');
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const base = `/sifi/monitoring-objects/${o.id}/research-worldbank`;
    const opts = {
        preserveScroll: true,
        onStart: () => setBusy(true),
        onFinish: () => setBusy(false),
        onError: setErrors,
    };
    const act = (r: Row, kind: string) =>
        router.post(
            `${base}/${r.id}/${kind}`,
            {
                revision: r.revision,
                object_revision: o.revision,
                confirm_one_shot: confirmed,
                ...(kind === 'draft'
                    ? {
                          document_sha256: r.document_digest,
                          source_id: source,
                          source_revision: sources.find((s) => s.id === source)
                              ?.revision,
                          analyst_note: note,
                      }
                    : {}),
            },
            opts,
        );
    return (
        <>
            <Head title="SIFI · Akuisisi riset terkurasi" />
            <div className="min-h-screen min-w-0 space-y-5 bg-muted/20 p-4 lg:p-8">
                <header className="space-y-2">
                    <p className="text-xs uppercase">
                        SIFI / CURATED RESEARCH FOUNDATION
                    </p>
                    <h1 className="text-3xl font-semibold">
                        Akuisisi riset terkurasi
                    </h1>
                    <p className="break-words">
                        {o.name} · revisi {o.revision}
                    </p>
                    <nav className="flex flex-wrap gap-4">
                        <Link
                            className="underline"
                            href={`/sifi/monitoring-objects/${o.id}/research`}
                        >
                            Pertanyaan &amp; rencana
                        </Link>
                        <Link
                            className="underline"
                            href={`/sifi/monitoring-objects/${o.id}/research-bridge`}
                        >
                            Dokumen fixture
                        </Link>
                        <Link className="underline" href="/sifi/source-catalog">
                            Source &amp; Coverage
                        </Link>
                    </nav>
                </header>
                <section className="space-y-2 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                    <p className="font-semibold">
                        {a?.mode ?? 'NO RESEARCH SESSION'} · AI OFF · SCHEDULER
                        OFF
                    </p>
                    <p>
                        World Bank adalah source pack pertama. Objek dan engine
                        tetap umum; pack ini hanya menjawab konteks PDB nasional
                        historis, bukan pencarian web umum.
                    </p>
                    <p>
                        Real GET belum dijalankan. Izin lama AE-10 tidak berlaku
                        di sini. Tidak ada tombol untuk menerbitkan atau
                        memperpanjang izin.
                    </p>
                    <p>
                        Dokumen terambil tetap terpisah dari claim/evidence yang
                        terverifikasi. UNKNOWN publication time, NOT VERIFIED,
                        dan NOT_EVALUATED dipertahankan.
                    </p>
                </section>
                <label className="block text-sm">
                    Sesi pertanyaan riset
                    <select
                        className="mt-1 w-full rounded-lg border bg-background p-3"
                        value={selected ?? ''}
                        onChange={(e) =>
                            router.get(base, { session: e.target.value })
                        }
                    >
                        <option value="">Pilih sesi</option>
                        {sessions.map((s) => (
                            <option value={s.id} key={s.id}>
                                {s.question}
                            </option>
                        ))}
                    </select>
                </label>
                {a && (
                    <section className="space-y-2 rounded-xl border bg-card p-4">
                        <h2 className="text-xl font-semibold">
                            Source pack &amp; izin baru: {a.state}
                        </h2>
                        <p className="text-sm break-all">{a.pack.id}</p>
                        <code className="block text-xs break-all">
                            GET {a.pack.endpoint}
                        </code>
                        <p className="text-sm">
                            Maksimal 3 record / {a.pack.max_bytes} byte /{' '}
                            {a.pack.total_seconds} detik / 1 attempt / 0 retry,
                            redirect, fallback, biaya.
                        </p>
                        <p className="text-sm">
                            MISSING → READY → CONSUMED / EXPIRED / BLOCKED. Izin
                            terikat owner, objek, sesi, revisi/digest plan, pack
                            dan endpoint.
                        </p>
                        <button
                            disabled={busy || o.state !== 'CONFIGURED'}
                            onClick={() =>
                                router.post(
                                    base,
                                    {
                                        session_id: selected,
                                        object_revision: o.revision,
                                        context_sha256: a.context_sha256,
                                    },
                                    opts,
                                )
                            }
                            className="rounded-lg border px-3 py-2"
                        >
                            Simpan keputusan source pack
                        </button>
                    </section>
                )}
                {Object.entries(errors).map(([k, v]) => (
                    <p
                        key={k}
                        role="alert"
                        className="break-words text-destructive"
                    >
                        {v}
                    </p>
                ))}
                <section className="space-y-3">
                    <h2 className="text-xl font-semibold">
                        Keputusan &amp; dokumen ({retrievals.length})
                    </h2>
                    <label className="flex items-start gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={confirmed}
                            onChange={(e) => setConfirmed(e.target.checked)}
                        />
                        <span>
                            Saya memahami mode data, batas satu attempt, serta
                            bahwa akuisisi bukan verifikasi/admission.
                        </span>
                    </label>
                    <label className="block text-sm">
                        Registered source milik akun
                        <select
                            className="mt-1 w-full rounded-lg border bg-background p-3"
                            value={source}
                            onChange={(e) => setSource(e.target.value)}
                        >
                            <option value="">Pilih sumber existing</option>
                            {sources.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name} · v{s.revision}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="block text-sm">
                        Catatan analis
                        <textarea
                            className="mt-1 w-full rounded-lg border bg-background p-3"
                            rows={3}
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                            maxLength={1000}
                        />
                    </label>
                    {retrievals.map((r) => (
                        <article
                            className="min-w-0 space-y-3 rounded-xl border bg-card p-4"
                            key={r.id}
                        >
                            <h3 className="font-semibold break-words">
                                {r.result?.title ?? 'Konteks kedaluwarsa'}
                            </h3>
                            <p>
                                {r.state} · v{r.revision}
                            </p>
                            {r.result?.acquisition_reason && (
                                <p role="status">
                                    {r.result.acquisition_reason} · tidak ada
                                    retry otomatis.
                                </p>
                            )}
                            {!r.expired &&
                                [
                                    'WB_PACK_DECISION',
                                    'WB_ATTEMPT_FAILED',
                                ].includes(r.state) && (
                                    <div className="flex flex-wrap gap-3">
                                        <button
                                            disabled={
                                                busy ||
                                                !confirmed ||
                                                a?.state !== 'READY'
                                            }
                                            onClick={() => act(r, 'acquire')}
                                            className="rounded-lg border px-3 py-2 disabled:opacity-40"
                                        >
                                            {a?.mode ===
                                            'SYNTHETIC_SIMULATOR_ONLY'
                                                ? 'Jalankan simulator one-shot'
                                                : 'GET nyata (butuh izin baru)'}
                                        </button>
                                        <button
                                            disabled={
                                                busy ||
                                                !confirmed ||
                                                a?.state !== 'CONSUMED'
                                            }
                                            onClick={() => act(r, 'recover')}
                                            className="rounded-lg border px-3 py-2 disabled:opacity-40"
                                        >
                                            Pulihkan receipt tanpa GET
                                        </button>
                                    </div>
                                )}
                            {r.document && (
                                <>
                                    <p className="font-semibold">
                                        {r.document.mode}
                                    </p>
                                    <p className="text-xs break-all">
                                        Body SHA-256 {r.document.body_sha256}
                                    </p>
                                    <p className="text-xs">
                                        Waktu respons: {r.document.retrieved_at}{' '}
                                        · publikasi record UNKNOWN
                                    </p>
                                    <blockquote className="border-l-2 pl-3 text-sm break-words">
                                        {r.document.quote.text}
                                    </blockquote>
                                    <p className="text-xs">
                                        UTF-8 byte span [
                                        {r.document.quote.byte_start},{' '}
                                        {r.document.quote.byte_end})
                                    </p>
                                    <details>
                                        <summary>Exact response body</summary>
                                        <pre className="text-xs break-all whitespace-pre-wrap">
                                            {r.document.body}
                                        </pre>
                                    </details>
                                </>
                            )}
                            {!r.expired &&
                                r.state === 'WB_DOCUMENT_SNAPSHOT' && (
                                    <button
                                        disabled={
                                            busy ||
                                            !confirmed ||
                                            !source ||
                                            note.length < 20
                                        }
                                        onClick={() => act(r, 'draft')}
                                        className="rounded-lg border px-3 py-2"
                                    >
                                        Buat draft intelligence existing
                                    </button>
                                )}
                            {r.candidate_id && (
                                <Link
                                    className="block underline"
                                    href={`/sifi/issue-candidates/${r.candidate_id}/executive-brief`}
                                >
                                    Buka lineage dan brief
                                </Link>
                            )}
                        </article>
                    ))}
                </section>
            </div>
        </>
    );
}
