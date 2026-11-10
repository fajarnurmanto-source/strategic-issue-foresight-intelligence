import { useState } from 'react';

type Provider = 'CROSSREF' | 'ANTARA_RSS';
type Entry = {
    title: string;
    canonical_url: string;
    published_at: string | null;
    publication_precision?: string;
};
type ReceiptSession = {
    id: string;
    created_at: string;
    expired: boolean;
    multi: {
        envelope: {
            mode?: string;
            provider_ids: string[];
            calls: { provider: string; state: string }[];
            entries: Entry[];
        };
    } | null;
};

const providers: Provider[] = ['CROSSREF', 'ANTARA_RSS'];
const display: Record<Provider, string> = {
    CROSSREF: 'Riset akademik · Crossref',
    ANTARA_RSS: 'Berita nasional · ANTARA',
};
const isTrustedUrl = (provider: Provider, url: string) =>
    provider === 'CROSSREF'
        ? url.startsWith('https://doi.org/')
        : url.startsWith('https://www.antaranews.com/');

/** Purely derived from Laravel's existing owned, digest-checked research history. */
export default function OwnerBoundReceiptDashboard({
    history,
}: {
    history: ReceiptSession[];
}) {
    const [filter, setFilter] = useState<'ALL' | Provider>('ALL');
    const recent = [...history]
        .filter((session) => !session.expired && session.multi)
        .sort((a, b) => b.created_at.localeCompare(a.created_at));
    const selected = providers.map((provider) => ({
        provider,
        session:
            recent.find((session) => {
                const envelope = session.multi?.envelope;
                return (
                    envelope?.mode === 'APPROVED_LIVE_CORPUS_METADATA' &&
                    envelope.provider_ids.length === 1 &&
                    envelope.provider_ids[0] === provider &&
                    envelope.calls.length === 1 &&
                    envelope.calls[0].provider === provider &&
                    envelope.calls[0].state === 'OK'
                );
            }) ?? null,
    }));
    const rows = selected.flatMap(({ provider, session }) =>
        (session?.multi?.envelope.entries ?? [])
            .slice(0, 5)
            .filter(
                (entry) =>
                    typeof entry.title === 'string' &&
                    entry.title.trim().length > 0 &&
                    typeof entry.canonical_url === 'string' &&
                    isTrustedUrl(provider, entry.canonical_url),
            )
            .map((entry) => ({
                ...entry,
                provider,
                sessionId: session?.id ?? '',
            })),
    );
    const displayed =
        filter === 'ALL' ? rows : rows.filter((row) => row.provider === filter);
    const distinct = new Set(rows.map((row) => row.canonical_url)).size;
    const termCounts = new Map<string, number>();
    for (const row of rows) {
        const words = new Set(
            row.title
                .toLocaleLowerCase('id-ID')
                .split(/[^\p{L}\p{N}]+/u)
                .filter(
                    (word) =>
                        word.length >= 4 &&
                        ![
                            'yang',
                            'dalam',
                            'untuk',
                            'dengan',
                            'from',
                            'that',
                            'this',
                        ].includes(word),
                ),
        );
        for (const term of words)
            termCounts.set(term, (termCounts.get(term) ?? 0) + 1);
    }
    const topTerms = [...termCounts.entries()]
        .filter(([, count]) => count > 1)
        .sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]))
        .slice(0, 6);

    return (
        <section
            aria-label="Snapshot dinamis multi-sumber"
            className="min-w-0 space-y-4 rounded-2xl border border-emerald-500/25 bg-card p-4 shadow-sm sm:p-6"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-xs font-bold tracking-widest text-emerald-600 uppercase dark:text-emerald-400">
                        IH03I / OWNER-BOUND / SHADOW READ ONLY
                    </p>
                    <h2 className="mt-2 text-xl font-semibold sm:text-2xl">
                        Intelligence lintas sumber
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Diperbarui dari riwayat riset milik akun ini, tanpa
                        request atau penyimpanan baru.
                    </p>
                </div>
                <span className="rounded-full border border-emerald-500/40 px-3 py-1 text-xs text-emerald-700 dark:text-emerald-300">
                    Bukti belum diterima
                </span>
            </div>

            <div
                className="grid grid-cols-2 gap-3 lg:grid-cols-4"
                aria-label="Metrik metadata"
            >
                <div className="rounded-xl border p-4">
                    <p className="text-3xl font-semibold tabular-nums">
                        {
                            rows.filter((row) => row.provider === 'CROSSREF')
                                .length
                        }
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Publikasi Crossref
                    </p>
                </div>
                <div className="rounded-xl border p-4">
                    <p className="text-3xl font-semibold tabular-nums">
                        {
                            rows.filter((row) => row.provider === 'ANTARA_RSS')
                                .length
                        }
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Tautan berita ANTARA
                    </p>
                </div>
                <div className="rounded-xl border p-4">
                    <p className="text-3xl font-semibold tabular-nums">
                        {distinct}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        URL kanonis unik
                    </p>
                </div>
                <div className="rounded-xl border p-4">
                    <p className="text-3xl font-semibold tabular-nums">
                        {selected.filter((item) => item.session).length}/2
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Sumber dengan receipt aktif
                    </p>
                </div>
            </div>

            {rows.length === 0 ? (
                <div
                    role="status"
                    className="rounded-xl border border-dashed p-5 text-sm"
                >
                    <p className="font-medium">
                        Belum ada receipt Crossref atau ANTARA yang aktif untuk
                        objek ini pada akun Anda.
                    </p>
                    <p className="mt-2 text-muted-foreground">
                        Snapshot IH03G sebelumnya berasal dari database uji
                        terisolasi dengan pemilik sintetis; datanya tidak
                        otomatis dipindahkan ke akun ini.
                    </p>
                </div>
            ) : (
                <>
                    <div
                        className="flex flex-wrap gap-2"
                        role="group"
                        aria-label="Filter sumber"
                    >
                        {(['ALL', ...providers] as const).map((provider) => (
                            <button
                                key={provider}
                                type="button"
                                aria-pressed={filter === provider}
                                onClick={() => setFilter(provider)}
                                className={`rounded-lg border px-3 py-2 text-xs font-medium ${filter === provider ? 'border-emerald-600 bg-emerald-600/10' : ''}`}
                            >
                                {provider === 'ALL'
                                    ? 'Semua'
                                    : provider === 'CROSSREF'
                                      ? 'Crossref'
                                      : 'ANTARA'}
                            </button>
                        ))}
                    </div>
                    <div className="grid gap-3 lg:grid-cols-[minmax(0,2fr)_minmax(220px,1fr)]">
                        <div className="min-w-0 space-y-3">
                            {displayed.map((row) => (
                                <article
                                    key={`${row.provider}-${row.canonical_url}`}
                                    className="min-w-0 rounded-xl border p-4"
                                >
                                    <p className="text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                        {display[row.provider]}
                                    </p>
                                    <h3 className="mt-1 font-semibold break-words">
                                        <a
                                            className="underline decoration-emerald-500/40 underline-offset-4"
                                            href={row.canonical_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            {row.title}
                                        </a>
                                    </h3>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        Tanggal publikasi:{' '}
                                        {row.published_at ?? 'Tidak diketahui'}{' '}
                                        · presisi{' '}
                                        {row.publication_precision ?? 'UNKNOWN'}
                                    </p>
                                </article>
                            ))}
                        </div>
                        <aside className="min-w-0 space-y-3">
                            <div className="rounded-xl border p-4">
                                <h3 className="font-semibold">
                                    Topik pada judul
                                </h3>
                                {topTerms.length === 0 ? (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Belum ada istilah berulang.
                                    </p>
                                ) : (
                                    topTerms.map(([term, count]) => (
                                        <div
                                            key={term}
                                            className="flex justify-between gap-4 border-b py-2 text-sm last:border-b-0"
                                        >
                                            <span className="break-words">
                                                {term}
                                            </span>
                                            <span className="font-semibold tabular-nums">
                                                {count}
                                            </span>
                                        </div>
                                    ))
                                )}
                            </div>
                            <div className="rounded-xl border border-amber-500/40 bg-amber-500/5 p-4 text-xs leading-relaxed">
                                <strong>Guard interpretasi:</strong> jumlah
                                publikasi bukan jumlah asal independen,
                                frekuensi peristiwa, prevalensi, atau ukuran
                                dampak. Snapshot tunggal tidak dapat menunjukkan
                                kecepatan penyebaran maupun prediksi.
                                Kredibilitas klaim belum dinilai.
                            </div>
                        </aside>
                    </div>
                </>
            )}
            <p className="text-xs text-muted-foreground">
                Sumber tampilan: riwayat tersimpan dan divalidasi server untuk
                objek milik pengguna yang login. AI: OFF · Evidence admission:
                NONE · Production admission: DENIED.
            </p>
        </section>
    );
}
