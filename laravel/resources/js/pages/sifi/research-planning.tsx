import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
type Session = {
    id: string;
    parent_id: string | null;
    object_revision: number;
    question: string | null;
    plan: any | null;
    results: any | null;
    stopping_reason: string;
    expires_at: string;
    expired: boolean;
    created_at: string;
};
type Props = {
    object: {
        id: string;
        name: string;
        category: string;
        geography: string | null;
        objective: string;
        revision: number;
        state: string;
    };
    history: Session[];
    bounds: {
        query_limit: number;
        result_limit: number;
        history_limit: number;
        retention_days: number;
    };
    source_types: string[];
};
const control = 'mt-1 w-full rounded-lg border bg-background p-3 text-sm';
export default function ResearchPlanning({
    object: o,
    history,
    bounds,
    source_types,
}: Props) {
    const [question, setQuestion] = useState('');
    const [queryLimit, setQueryLimit] = useState(4);
    const [resultLimit, setResultLimit] = useState(12);
    const [language, setLanguage] = useState('');
    const [sourceType, setSourceType] = useState('');
    const [timeWindow, setTimeWindow] = useState('UNSPECIFIED');
    const [parent, setParent] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const base = `/sifi/monitoring-objects/${o.id}/research`;
    function submit(e: FormEvent) {
        e.preventDefault();
        setErrors({});
        router.post(
            base,
            {
                question,
                object_revision: o.revision,
                parent_session_id: parent,
                query_limit: queryLimit,
                result_limit: resultLimit,
                language: language || null,
                source_type: sourceType || null,
                time_window: timeWindow,
            },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: setErrors,
            },
        );
    }
    return (
        <>
            <Head title="SIFI · Rencana Riset" />
            <main className="min-h-screen min-w-0 space-y-5 bg-muted/20 p-4 lg:p-8">
                <header className="flex flex-wrap justify-between gap-4">
                    <div>
                        <p className="text-xs tracking-widest text-muted-foreground uppercase">
                            SIFI / AE14 RESEARCH PLANNING
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold">
                            Rencanakan riset
                        </h1>
                        <p className="mt-2 break-words">
                            {o.name} · revisi {o.revision}
                        </p>
                    </div>
                    <nav className="flex flex-wrap gap-4 text-sm">
                        <Link
                            className="underline"
                            href={`/sifi/monitoring-objects/${o.id}/research-multi`}
                        >
                            Penelusuran informasi
                        </Link>
                        <Link
                            className="underline"
                            href={`/sifi/monitoring-objects/${o.id}/research-bridge`}
                        >
                            Dokumen &amp; lineage fixture
                        </Link>
                        <Link
                            className="underline"
                            href="/sifi/monitoring-objects"
                        >
                            Objek pemantauan
                        </Link>
                        <Link
                            className="underline"
                            href={`/sifi/monitoring-objects/${o.id}/discovery`}
                        >
                            Temukan Sumber
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
                        PLANNER DETERMINISTIK · LOCAL SYNTHETIC CATALOG ·
                        NETWORK/AI OFF
                    </p>
                    <p>
                        Fitur ini menyusun dan menyimpan rencana pertanyaan,
                        lalu mencocokkannya hanya dengan 10 descriptor .invalid.
                        Tidak ada pencarian web, crawling, dokumen diambil,
                        sitasi, evidence, claim, grant, biaya, atau scheduler.
                    </p>
                    <p>
                        Roadmap berikutnya tetap provider-neutral: provider
                        nyata yang lawful harus mendapat proposal dan izin
                        terpisah dengan batas request/byte/waktu/biaya.
                    </p>
                </section>
                <form
                    onSubmit={submit}
                    className="grid min-w-0 gap-4 rounded-xl border bg-card p-5 md:grid-cols-2"
                >
                    <label className="text-sm font-medium md:col-span-2">
                        Pertanyaan riset
                        <textarea
                            className={control}
                            value={question}
                            onChange={(e) => setQuestion(e.target.value)}
                            minLength={8}
                            maxLength={600}
                            required
                            rows={4}
                            placeholder="Contoh: Sumber lokal mana yang relevan untuk memantau perkembangan kendaraan listrik?"
                        />
                    </label>
                    <label className="text-sm">
                        Maksimum query
                        <input
                            className={control}
                            type="number"
                            min={1}
                            max={bounds.query_limit}
                            value={queryLimit}
                            onChange={(e) =>
                                setQueryLimit(Number(e.target.value))
                            }
                        />
                    </label>
                    <label className="text-sm">
                        Maksimum hasil
                        <input
                            className={control}
                            type="number"
                            min={1}
                            max={bounds.result_limit}
                            value={resultLimit}
                            onChange={(e) =>
                                setResultLimit(Number(e.target.value))
                            }
                        />
                    </label>
                    <label className="text-sm">
                        Bahasa
                        <select
                            className={control}
                            value={language}
                            onChange={(e) => setLanguage(e.target.value)}
                        >
                            <option value="">Tidak dibatasi</option>
                            <option value="id">Indonesia</option>
                            <option value="en">English</option>
                        </select>
                    </label>
                    <label className="text-sm">
                        Jenis sumber
                        <select
                            className={control}
                            value={sourceType}
                            onChange={(e) => setSourceType(e.target.value)}
                        >
                            <option value="">Semua jenis</option>
                            {source_types.map((x) => (
                                <option key={x}>{x}</option>
                            ))}
                        </select>
                    </label>
                    <label className="text-sm md:col-span-2">
                        Jendela waktu (belum difilter; tanggal publikasi
                        UNKNOWN)
                        <input
                            className={control}
                            value={timeWindow}
                            onChange={(e) => setTimeWindow(e.target.value)}
                            maxLength={80}
                        />
                    </label>
                    {parent && (
                        <p className="text-sm md:col-span-2">
                            Follow-up dari sesi{' '}
                            <code className="break-all">{parent}</code>{' '}
                            <button
                                type="button"
                                className="underline"
                                onClick={() => setParent(null)}
                            >
                                Batalkan hubungan
                            </button>
                        </p>
                    )}
                    {Object.entries(errors).map(([k, v]) => (
                        <p
                            key={k}
                            role="alert"
                            className="break-words text-destructive md:col-span-2"
                        >
                            {v}
                        </p>
                    ))}
                    <button
                        disabled={busy || o.state !== 'CONFIGURED'}
                        className="rounded-lg bg-foreground px-4 py-3 font-semibold text-background disabled:opacity-40"
                    >
                        Buat draft rencana lokal
                    </button>
                    <p className="text-xs text-muted-foreground">
                        Budget eksternal selalu nol: 0 request, 0 byte, 0 biaya.
                        Retensi konteks {bounds.retention_days} hari.
                    </p>
                </form>
                <section className="space-y-4">
                    <h2 className="text-xl font-semibold">
                        Riwayat draft ({history.length}/{bounds.history_limit})
                    </h2>
                    {history.length === 0 && (
                        <p>Belum ada sesi riset untuk objek ini.</p>
                    )}
                    {history.map((s) => (
                        <article
                            key={s.id}
                            className="min-w-0 space-y-3 rounded-xl border bg-card p-4"
                        >
                            <div className="flex flex-wrap justify-between gap-2">
                                <h3 className="font-semibold break-words">
                                    {s.question ?? 'Konteks kedaluwarsa'}
                                </h3>
                                <span className="text-xs">
                                    {s.stopping_reason}
                                </span>
                            </div>
                            <p className="text-xs break-all">
                                Sesi {s.id} · objek v{s.object_revision} ·{' '}
                                {s.created_at}
                            </p>
                            {s.expired ? (
                                <p>
                                    Konteks kedaluwarsa; isi plan/result tidak
                                    ditampilkan.
                                </p>
                            ) : (
                                <>
                                    <div className="grid gap-4 lg:grid-cols-2">
                                        <div>
                                            <h4 className="font-medium">
                                                Multi-query plan
                                            </h4>
                                            {s.plan.queries.map(
                                                (q: any, i: number) => (
                                                    <p
                                                        key={i}
                                                        className="text-sm break-words"
                                                    >
                                                        {i + 1}. {q.query}{' '}
                                                        <small>
                                                            ({q.intent})
                                                        </small>
                                                    </p>
                                                ),
                                            )}
                                            <p className="mt-2 text-xs">
                                                Inklusi:{' '}
                                                {s.plan.inclusions.join(', ') ||
                                                    '—'}
                                            </p>
                                            <p className="text-xs">
                                                Eksklusi:{' '}
                                                {s.plan.exclusions.join(', ') ||
                                                    '—'}
                                            </p>
                                        </div>
                                        <div>
                                            <h4 className="font-medium">
                                                Hasil katalog terbatas
                                            </h4>
                                            <p className="text-sm">
                                                {s.results.result_count} hasil ·{' '}
                                                {
                                                    s.results
                                                        .distinct_origin_count
                                                }{' '}
                                                origin (independensi belum
                                                diverifikasi) ·{' '}
                                                {s.results.queries_used} query ·
                                                0 request/byte/biaya · 0 dokumen
                                            </p>
                                            {s.results.entries.map((x: any) => (
                                                <p
                                                    key={x.key}
                                                    className="text-sm break-words"
                                                >
                                                    • {x.title} · {x.type} ·{' '}
                                                    {x.origin}
                                                </p>
                                            ))}
                                        </div>
                                    </div>
                                    <p className="text-sm">
                                        {s.results.uncertainty}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {s.results.coverage}
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setParent(s.id);
                                            setQuestion('');
                                            window.scrollTo({
                                                top: 0,
                                                behavior: 'smooth',
                                            });
                                        }}
                                        className="rounded-lg border px-3 py-2 text-sm"
                                    >
                                        Buat follow-up
                                    </button>
                                </>
                            )}
                        </article>
                    ))}
                </section>
            </main>
        </>
    );
}
