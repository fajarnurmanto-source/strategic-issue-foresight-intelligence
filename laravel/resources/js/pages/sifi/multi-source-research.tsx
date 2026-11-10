import OwnerBoundRealSourceControls from '../../components/sifi/owner-bound-real-source-controls';
import OwnerBoundReceiptDashboard from '../../components/sifi/owner-bound-receipt-dashboard';
import {
    RealCollectorSummary,
    RealCollectorControl,
    type CollectorStatus,
} from './real-collector-controls';
import { FeedControls, FeedSummary, defaultFeedOptions } from './feed-controls';
import {
    MultilingualControls,
    MultilingualPlanSummary,
    CrossLanguageComparison,
    defaultMultilingualOptions,
} from './multilingual-controls';
import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

type Entry = {
    local_history_state?:
        | 'HISTORY_UNAVAILABLE'
        | 'PREVIOUSLY_OBSERVED'
        | 'FIRST_OBSERVED_IN_LOCAL_INDEX';
    updated_at?: string | null;
    revision_state?: string;
    publisher?: string;
    item_revision_sha256?: string;
    source_language?: string;
    declared_language?: string;
    language_detection?: { confidence: number; method: string };
    time_classification?: string;
    event_date?: string | null;
    region?: string;
    source_type?: string;
    relevance_group: string;
    id: string;
    title: string;
    canonical_url: string;
    snippet: string;
    origins: string[];
    lineage: {
        provider: string;
        query_index?: number;
        result_key?: string;
        url?: string;
        origin?: string;
        snippet?: string;
        response_sha256?: string;
    }[];
    engagement_snapshot?: {
        likeCount: number;
        repostCount: number;
        replyCount: number;
        quoteCount: number;
    };
    issue_term_candidates?: string[];
    published_at: string | null;
    retrieved_at: string | null;
    fixture_loaded_at: string | null;
    publication_precision?: string;
    snippet_kind?: string;
    attribution?: {
        provider: string;
        license_url: string | null;
        terms_url: string;
        notice: string;
    };
    rights_state: string;
    relevance: { question_hits: string[]; context_hits: string[] };
    origin_state: string;
};
type Pair = {
    left: string;
    right: string;
    shared_terms: string[];
    left_only_terms: string[];
    right_only_terms: string[];
    possible_republication: boolean;
    shared_declared_origin: boolean;
    conclusion: string;
};
type Envelope = {
    social_watch_history?: {
        state: string;
        first_observed_count: number | null;
        previously_observed_count: number | null;
        scope: string;
    };
    insights?: {
        schema_version: string;
        candidate_item_count: number;
        declared_origin_label_count: number;
        possible_republication_cluster_count: number;
        rights_unassessed_count: number;
        independent_origin_count: null;
        claim_confidence: null;
        production_admission: string;
    };
    collector?: Parameters<typeof RealCollectorSummary>[0]['value'];
    feed?: Parameters<typeof FeedSummary>[0]['feed'];
    omitted_unique_count?: number;
    multilingual_plan?: Parameters<typeof MultilingualPlanSummary>[0]['plan'];
    coverage_languages?: string[];
    filtered_counts?: Record<string, number>;
    mode?: string;
    budget?: { simulated_call_limit?: number; network_request_limit?: number };
    entries: Entry[];
    comparisons: Pair[];
    calls: {
        provider: string;
        query_index?: number;
        query?: string;
        state: string;
    }[];
    usage: {
        simulated_calls?: number;
        fixture_metadata_bytes?: number;
        network_requests?: number;
    };
    raw_result_count: number;
    unique_result_count: number;
    collapsed_hits: number;
    stopping_reason: string;
    coverage: string;
    provider_ids: string[];
};
type Session = {
    id: string;
    question: string | null;
    revision: number;
    created_at: string;
    expires_at: string;
    expired: boolean;
    multi: {
        feed_ingestion?: { candidate_id: string };
        state: string;
        envelope: Envelope;
        envelope_sha256: string;
        analyst_note: string | null;
        selected_ids: string[];
        saved_at: string | null;
    } | null;
};
type Props = {
    collector?: CollectorStatus;
    object: {
        id: string;
        name: string;
        revision: number;
        state: string;
        keywords_json?: string[];
        aliases_json?: string[];
    } | null;
    objectChoices: { id: string; name: string; state: string }[];
    history: Session[];
    providerIds: string[];
    scenarios: string[];
    selectedSessionId: string | null;
};
const input = 'mt-1 w-full min-w-0 rounded-lg border bg-background p-3 text-sm';
const button = 'rounded-lg border px-4 py-3 text-sm disabled:opacity-40';
const formatTime = (value: string | null) => {
    if (!value) return 'UNKNOWN';
    const date = new Date(
        value.includes('T') ? value : value.replace(' ', 'T') + 'Z',
    );
    return Number.isNaN(date.getTime())
        ? 'UNKNOWN'
        : new Intl.DateTimeFormat('id-ID', {
              timeZone: 'Asia/Jakarta',
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(date) + ' WIB';
};
const stopLabels: Record<string, string> = {
    COMPLETED: 'Penelusuran selesai',
    NO_RESULTS: 'Tidak ada hasil pada cakupan query',
    NO_RESULTS_PROVIDER_ERRORS: 'Tidak ada hasil; provider bermasalah',
    PARTIAL_COVERAGE: 'Hasil sebagian; provider lain belum tersedia',
    RESULT_LIMIT: 'Batas hasil tercapai',
    METADATA_BYTE_LIMIT: 'Batas metadata tercapai',
    TIME_LIMIT: 'Batas waktu tercapai',
    SIMULATED_CALL_LIMIT: 'Batas panggilan simulasi tercapai',
};
const stateLabels: Record<string, string> = {
    OK: 'Tersedia',
    QUOTA: 'Kuota simulasi habis',
    TIMEOUT: 'Waktu simulasi habis',
    UNAVAILABLE: 'Provider tidak tersedia',
    BYTE_LIMIT: 'Batas metadata',
    TIME_LIMIT: 'Batas waktu',
};

function ResearchResult({
    session: s,
    object: o,
}: {
    session: Session;
    object: NonNullable<Props['object']>;
}) {
    const m = s.multi!;
    const e = m.envelope;
    const isSocialReceipt = e.provider_ids.includes('BLUESKY_PUBLIC_SEARCH');
    const [selected, setSelected] = useState<string[]>(
        m.selected_ids.length
            ? m.selected_ids
            : e.entries.slice(0, 2).map((x) => x.id),
    );
    const [note, setNote] = useState(m.analyst_note ?? '');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const pair = e.comparisons.find(
        (p) => selected.includes(p.left) && selected.includes(p.right),
    );
    function save(ev: FormEvent) {
        ev.preventDefault();
        setErrors({});
        router.post(
            `/sifi/monitoring-objects/${o.id}/research-multi/${s.id}/save`,
            {
                revision: s.revision,
                object_revision: o.revision,
                envelope_sha256: m.envelope_sha256,
                selected_ids: selected,
                analyst_note: note,
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
        <article className="min-w-0 space-y-5 rounded-xl border bg-card p-4 sm:p-5">
            <header>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <h3 className="text-xl font-semibold break-words">
                        {s.question}
                    </h3>
                    <span className="rounded-full border px-3 py-1 text-sm">
                        {m.state === 'SAVED'
                            ? 'Penelitian tersimpan'
                            : e.mode === 'APPROVED_LIVE_CORPUS_METADATA'
                              ? 'Sinyal tercatat otomatis'
                              : 'Draft hasil'}
                    </span>
                </div>
                <p className="mt-2 text-sm text-muted-foreground">
                    {formatTime(s.created_at)} · kedaluwarsa{' '}
                    {formatTime(s.expires_at)} ·{' '}
                    {stopLabels[e.stopping_reason] ?? e.stopping_reason}
                </p>
            </header>
            <div className="flex flex-wrap gap-3">
                {m.state === 'SAVED' && (
                    <a
                        className="rounded-lg border px-3 py-2 text-sm underline"
                        href={`/sifi/monitoring-objects/${o.id}/research-multi/${s.id}/references`}
                    >
                        Unduh referensi penelitian
                    </a>
                )}
            </div>
            <p className="text-sm">
                {e.unique_result_count} hasil unik dari {e.raw_result_count} hit
                · {e.collapsed_hits ?? 0} hit digabung ·{' '}
                {e.omitted_unique_count ?? 0} hasil unik dibatasi ·{' '}
                {e.mode === 'APPROVED_LIVE_CORPUS_METADATA'
                    ? `${e.usage.network_requests ?? 0}/${e.budget?.network_request_limit ?? 1} permintaan API metadata`
                    : `${e.usage.simulated_calls ?? 0}/${e.budget?.simulated_call_limit ?? 4} panggilan simulasi, ${e.usage.fixture_metadata_bytes ?? 0} byte metadata`}
                . Dokumen penuh 0, biaya 0.
            </p>
            {!isSocialReceipt && (
                <a
                    className="inline-block rounded-lg border px-3 py-2 text-sm underline"
                    href={`#compare-${s.id}`}
                >
                    Bandingkan pilihan dan simpan
                </a>
            )}
            {isSocialReceipt && e.social_watch_history && (
                <section
                    aria-label="Riwayat Social Watch"
                    role="status"
                    className="space-y-1 rounded-lg border border-sky-500/30 bg-sky-500/10 p-3"
                >
                    <p className="font-semibold">
                        Pantauan otomatis · tanpa verifikasi unggahan satu per
                        satu
                    </p>
                    {e.social_watch_history.state ===
                    'LOCAL_HISTORY_AVAILABLE' ? (
                        <p className="text-sm">
                            Pertama kali terlihat pada riwayat objek ini:{' '}
                            <strong>
                                {e.social_watch_history.first_observed_count ??
                                    0}
                            </strong>{' '}
                            · sudah pernah terlihat:{' '}
                            <strong>
                                {e.social_watch_history
                                    .previously_observed_count ?? 0}
                            </strong>
                            .
                        </p>
                    ) : (
                        <p className="text-sm">
                            Perbandingan dengan pemantauan sebelumnya belum
                            tersedia; riwayat indeks lokal tidak aktif.
                        </p>
                    )}
                    <p className="text-xs text-muted-foreground">
                        Pertama terlihat di SIFI bukan berarti unggahan baru
                        atau kejadian baru di platform.
                    </p>
                </section>
            )}
            {e.collector && <RealCollectorSummary value={e.collector} />}
            {e.insights?.schema_version === 'sifi.research.insights.v1' && (
                <section
                    aria-label="Analisis otomatis metadata"
                    className="space-y-3 rounded-xl border border-sky-500/40 bg-sky-500/5 p-4"
                >
                    <h4 className="font-semibold">
                        Analisis otomatis kandidat sinyal{' '}
                        <span className="ml-2 text-xs font-normal">
                            SHADOW / READ-ONLY
                        </span>
                    </h4>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {(
                            [
                                [
                                    'Kandidat unik',
                                    e.insights.candidate_item_count,
                                ],
                                [
                                    'Label asal (belum independen)',
                                    e.insights.declared_origin_label_count,
                                ],
                                [
                                    'Kandidat publikasi ulang',
                                    e.insights
                                        .possible_republication_cluster_count,
                                ],
                                [
                                    'Hak belum dinilai',
                                    e.insights.rights_unassessed_count,
                                ],
                            ] as const
                        ).map(([label, total]) => (
                            <div
                                key={label}
                                className="rounded-lg border bg-card p-3"
                            >
                                <p className="text-xs text-muted-foreground">
                                    {label}
                                </p>
                                <p className="text-2xl font-semibold">
                                    {total}
                                </p>
                            </div>
                        ))}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Jumlah hit bukan jumlah peristiwa maupun asal
                        independen. Kredibilitas klaim, kecepatan lintas
                        platform, amplifier, prevalensi, dampak dan foresight
                        belum dapat dihitung dari metadata ini. Tidak ada
                        admission bukti, aktivasi koneksi atau hak produksi.
                    </p>
                </section>
            )}
            {e.feed && (
                <FeedSummary
                    feed={e.feed}
                    filtered={e.filtered_counts}
                    calls={e.calls}
                />
            )}
            {e.multilingual_plan && (
                <MultilingualPlanSummary
                    plan={e.multilingual_plan}
                    covered={e.coverage_languages}
                    filtered={e.filtered_counts}
                    providers={e.provider_ids}
                    failures={e.calls.filter((c) => c.state !== 'OK').length}
                />
            )}
            <details className="rounded-lg border p-3 text-sm">
                <summary className="cursor-pointer font-medium">
                    Rencana query dan status provider
                </summary>
                {e.calls.map((c, i) => (
                    <p className="mt-2 break-words" key={i}>
                        {c.provider} ·{' '}
                        {typeof c.query_index === 'number'
                            ? `query ${c.query_index + 1}`
                            : 'metadata publik'}
                        : {c.query ?? '—'} · {stateLabels[c.state] ?? c.state}
                    </p>
                ))}
            </details>
            {e.entries.length === 0 && (
                <p role="status" className="rounded-lg border p-4">
                    Tidak ada hasil yang dapat ditampilkan untuk query ini.
                    Periksa status provider di atas. Ini tidak membuktikan
                    ketiadaan informasi di internet.
                </p>
            )}
            <section className="space-y-3" aria-label="Hasil lintas provider">
                <h4 className="font-semibold">Hasil lintas sumber</h4>
                <p className="text-sm">
                    Pilih maksimal dua hasil untuk dibandingkan. Hasil simulasi
                    menampilkan URL inert. Tautan hasil nyata hanya terbuka atas
                    tindakan Anda; SIFI tidak mengambil halaman sumber otomatis.
                </p>
                {e.entries.map((x) => (
                    <div
                        key={x.id}
                        className="min-w-0 space-y-2 rounded-lg border p-4"
                    >
                        {isSocialReceipt ? (
                            <h5 className="min-w-0 font-semibold break-words">
                                {x.title}
                            </h5>
                        ) : (
                            <label className="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    className="mt-1"
                                    checked={selected.includes(x.id)}
                                    disabled={
                                        m.state === 'SAVED' ||
                                        (!selected.includes(x.id) &&
                                            selected.length >= 2)
                                    }
                                    onChange={(ev) =>
                                        setSelected(
                                            ev.target.checked
                                                ? [...selected, x.id]
                                                : selected.filter(
                                                      (id) => id !== x.id,
                                                  ),
                                        )
                                    }
                                />
                                <span className="min-w-0 font-semibold break-words">
                                    {x.title}
                                </span>
                            </label>
                        )}
                        <p className="text-xs break-words">
                            {x.source_language && (
                                <>
                                    Bahasa asli: {x.source_language} ·
                                    deklarasi: {x.declared_language} ·
                                    confidence heuristik{' '}
                                    {x.language_detection?.confidence} (
                                    {x.language_detection?.method}) · teks asli
                                    dipertahankan, terjemahan tidak dibuat.
                                    Wilayah: {x.region} · jenis: {x.source_type}{' '}
                                    · periode: {x.time_classification} · tanggal
                                    peristiwa: {x.event_date ?? 'UNKNOWN'}.
                                </>
                            )}
                        </p>
                        <p className="text-xs font-medium">
                            Kelompok relevansi:{' '}
                            {x.relevance_group === 'OBJECT_NAME_CANDIDATE_ONLY'
                                ? 'Penyebutan nama objek; topik dan wilayah belum diverifikasi'
                                : x.relevance_group ===
                                    'QUESTION_AND_OBJECT_MATCH'
                                  ? 'Pertanyaan dan konteks objek'
                                  : 'Pertanyaan'}
                        </p>
                        <p className="text-sm break-words">{x.snippet}</p>
                        {x.local_history_state &&
                            x.local_history_state !== 'HISTORY_UNAVAILABLE' && (
                                <p className="text-xs font-semibold text-sky-700 dark:text-sky-300">
                                    {x.local_history_state ===
                                    'PREVIOUSLY_OBSERVED'
                                        ? 'Sudah terlihat pada pemantauan sebelumnya'
                                        : 'Pertama terlihat dalam riwayat SIFI untuk objek ini'}
                                </p>
                            )}
                        {x.engagement_snapshot && (
                            <p className="rounded-lg bg-sky-500/10 p-2 text-xs">
                                Snapshot perhatian Bluesky · suka{' '}
                                {x.engagement_snapshot.likeCount} · repost{' '}
                                {x.engagement_snapshot.repostCount} · balasan{' '}
                                {x.engagement_snapshot.replyCount} · kutipan{' '}
                                {x.engagement_snapshot.quoteCount}.{' '}
                                <strong>
                                    Bukan jumlah kejadian atau prevalensi.
                                </strong>{' '}
                                {x.issue_term_candidates?.length
                                    ? `Kandidat topik: ${x.issue_term_candidates.join(', ')}.`
                                    : ''}
                            </p>
                        )}
                        <code className="block text-xs break-all">
                            {e.mode === 'APPROVED_LIVE_CORPUS_METADATA' ? (
                                <a
                                    href={x.canonical_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="underline"
                                >
                                    {x.canonical_url}
                                </a>
                            ) : (
                                x.canonical_url
                            )}
                        </code>
                        <p className="text-xs break-words text-muted-foreground">
                            Origin: {x.origins.join(', ')} · {x.origin_state} ·
                            independensi BELUM DIVERIFIKASI
                        </p>
                        <p className="text-xs">
                            Revisi item:{' '}
                            {x.revision_state ===
                            'MULTIPLE_VERSIONS_PRESERVED_NOT_SEPARATE_EVENTS'
                                ? 'Revisi isi sumber dipertahankan'
                                : x.revision_state
                                  ? 'Versi pertama pada pembacaan ini'
                                  : '—'}{' '}
                            · pembaruan: {x.updated_at ?? 'UNKNOWN'} ·
                            Publikasi: {x.published_at ?? 'UNKNOWN'}
                            {x.publication_precision &&
                                ' · presisi ' + x.publication_precision}{' '}
                            ·{' '}
                            {e.mode === 'APPROVED_LIVE_CORPUS_METADATA'
                                ? 'metadata sumber publik nyata'
                                : e.collector
                                  ? 'metadata sumber nyata'
                                  : 'dimuat sebagai fixture: ' +
                                    formatTime(x.fixture_loaded_at)}{' '}
                            · metadata internet:{' '}
                            {x.retrieved_at
                                ? formatTime(x.retrieved_at)
                                : 'tidak terjadi'}
                        </p>
                        <p className="text-xs break-words">
                            Relevansi pertanyaan:{' '}
                            {x.relevance.question_hits.join(', ') || '—'} ·
                            konteks objek:{' '}
                            {x.relevance.context_hits.join(', ') || '—'} · hak:{' '}
                            {x.rights_state}
                        </p>
                        <p className="text-xs break-words">
                            {x.attribution && (
                                <>
                                    Atribusi: {x.attribution.provider} ·{' '}
                                    {x.snippet_kind} ·{' '}
                                    {x.attribution.license_url ??
                                        (x.rights_state.startsWith('UNASSESSED')
                                            ? 'Hak snippet sumber belum disetujui'
                                            : 'Metadata bibliografi tanpa abstrak')}{' '}
                                    · {x.attribution.notice}
                                </>
                            )}
                        </p>
                        <details className="text-xs">
                            <summary className="cursor-pointer">
                                Provenance &amp; deduplikasi ({x.lineage.length}{' '}
                                hit)
                            </summary>
                            {x.lineage.map((hit, i) => (
                                <p key={i} className="mt-2 break-all">
                                    {hit.provider} ·{' '}
                                    {typeof hit.query_index === 'number'
                                        ? `query ${hit.query_index + 1}`
                                        : 'metadata publik'}{' '}
                                    · {hit.result_key ?? '—'} · {hit.url ?? '—'}{' '}
                                    · origin {hit.origin ?? '—'} · response{' '}
                                    {hit.response_sha256 ?? '—'}
                                </p>
                            ))}
                        </details>
                    </div>
                ))}
            </section>
            {!isSocialReceipt && (
                <section
                    id={`compare-${s.id}`}
                    aria-label="Perbandingan informasi"
                    className="space-y-3 rounded-lg border p-4"
                >
                    <h4 className="font-semibold">Bandingkan informasi</h4>
                    {(e.multilingual_plan || e.feed) &&
                        selected.length === 2 && (
                            <CrossLanguageComparison
                                entries={e.entries.filter((x) =>
                                    selected.includes(x.id),
                                )}
                            />
                        )}
                    {pair ? (
                        <>
                            <p className="text-sm">
                                {pair.possible_republication
                                    ? 'Kemungkinan publikasi ulang atau asal informasi bersama.'
                                    : 'Belum ada dasar untuk menyatakan kedua hasil independen.'}
                            </p>
                            <div className="grid min-w-0 gap-3 md:grid-cols-3">
                                <div>
                                    <h5 className="text-sm font-medium">
                                        Kesamaan kata pada snippet
                                    </h5>
                                    <p className="text-sm break-words">
                                        {pair.shared_terms.join(', ') ||
                                            (e.multilingual_plan || e.feed
                                                ? 'Perbandingan kata lintas bahasa tidak dilakukan'
                                                : 'Tidak ditemukan')}
                                    </p>
                                </div>
                                <div>
                                    <h5 className="text-sm font-medium">
                                        Hanya pada hasil pertama
                                    </h5>
                                    <p className="text-sm break-words">
                                        {pair.left_only_terms.join(', ') ||
                                            (e.multilingual_plan || e.feed
                                                ? 'Tidak dinilai dalam mode multilingual'
                                                : 'Tidak ada perbedaan kata')}
                                    </p>
                                </div>
                                <div>
                                    <h5 className="text-sm font-medium">
                                        Hanya pada hasil kedua
                                    </h5>
                                    <p className="text-sm break-words">
                                        {pair.right_only_terms.join(', ') ||
                                            (e.multilingual_plan || e.feed
                                                ? 'Tidak dinilai dalam mode multilingual'
                                                : 'Tidak ada perbedaan kata')}
                                    </p>
                                </div>
                            </div>
                            <p className="text-sm">{pair.conclusion}</p>
                        </>
                    ) : (
                        <p className="text-sm">
                            Pilih dua hasil untuk melihat persamaan dan
                            perbedaannya.
                        </p>
                    )}
                    <p className="text-xs text-muted-foreground">
                        Domain berbeda bukan bukti independensi. Snippet tidak
                        memverifikasi klaim; belum ada dokumen, kutipan dokumen,
                        atau evidence admission. Perbedaan kata juga bukan bukti
                        kontradiksi faktual.
                    </p>
                </section>
            )}
            {!isSocialReceipt &&
                (m.state === 'SAVED' ? (
                    <p
                        role="status"
                        className="rounded-lg border border-green-500/50 p-4 text-sm"
                    >
                        Tersimpan pada riwayat · {formatTime(m.saved_at)}
                        <span className="mt-2 block break-words whitespace-pre-wrap">
                            {m.analyst_note || 'Tanpa catatan analis.'}
                        </span>
                    </p>
                ) : (
                    <form onSubmit={save} className="space-y-3">
                        <label className="block text-sm font-medium">
                            Catatan penelitian
                            <textarea
                                value={note}
                                onChange={(ev) => setNote(ev.target.value)}
                                maxLength={2000}
                                rows={3}
                                className={input}
                                placeholder="Pisahkan pengamatan snippet, dugaan analis, dan informasi yang belum dapat diverifikasi."
                            />
                        </label>
                        {Object.entries(errors).map(([k, v]) => (
                            <p
                                role="alert"
                                key={k}
                                className="text-sm text-destructive"
                            >
                                {v}
                            </p>
                        ))}
                        <button
                            className={
                                button + ' bg-foreground text-background'
                            }
                            disabled={busy}
                        >
                            {busy ? 'Menyimpan…' : 'Simpan penelitian'}
                        </button>
                    </form>
                ))}
            {e.feed && m.state === 'SAVED' && (
                <section className="space-y-3 rounded-lg border p-4">
                    <h4 className="font-semibold">
                        Lanjutkan ke alur intelligence
                    </h4>
                    <p className="text-sm">
                        Pilihan tersimpan dapat menjadi draf simulasi untuk
                        ditelaah. Hak sumber tetap UNASSESSED, collector OFF,
                        evidence NOT_EVALUATED. Tidak membuat klaim
                        terverifikasi atau keputusan.
                    </p>
                    {m.feed_ingestion ? (
                        <p role="status" className="text-sm break-all">
                            Draf simulasi terhubung:{' '}
                            {m.feed_ingestion.candidate_id}.{' '}
                            <a
                                className="underline"
                                href={`/sifi/workbench?candidate=${m.feed_ingestion.candidate_id}`}
                            >
                                Buka Workbench
                            </a>
                        </p>
                    ) : (
                        <button
                            className={button}
                            disabled={busy || m.selected_ids.length === 0}
                            onClick={() =>
                                router.post(
                                    `/sifi/monitoring-objects/${o.id}/research-multi/${s.id}/feed-draft`,
                                    {
                                        revision: s.revision,
                                        object_revision: o.revision,
                                        envelope_sha256: m.envelope_sha256,
                                    },
                                    {
                                        preserveScroll: true,
                                        onStart: () => setBusy(true),
                                        onFinish: () => setBusy(false),
                                        onError: setErrors,
                                    },
                                )
                            }
                        >
                            Buat draf simulasi dari pilihan tersimpan
                        </button>
                    )}
                    {Object.entries(errors).map(([k, v]) => (
                        <p
                            key={k}
                            role="alert"
                            className="text-sm text-destructive"
                        >
                            {v}
                        </p>
                    ))}
                </section>
            )}
        </article>
    );
}

export default function MultiSourceResearch({
    object: o,
    objectChoices,
    history,
    providerIds,
    scenarios,
    selectedSessionId,
    collector,
}: Props) {
    const [feedOptions, setFeedOptions] = useState(defaultFeedOptions);
    const [multilingualOptions, setMultilingualOptions] = useState(
        defaultMultilingualOptions,
    );
    const [question, setQuestion] = useState('');
    const [scenario, setScenario] = useState('feed');
    const [queries, setQueries] = useState(2);
    const [limit, setLimit] = useState(10);
    const [key, setKey] = useState(() => crypto.randomUUID());
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const [opened, setOpened] = useState<string | null>(
        selectedSessionId ?? history[0]?.id ?? null,
    );
    function search(ev: FormEvent) {
        ev.preventDefault();
        if (!o) return;
        setErrors({});
        router.post(
            `/sifi/monitoring-objects/${o.id}/research-multi`,
            {
                question,
                object_revision: o.revision,
                scenario,
                ...(scenario === 'feed' ? { feed_options: feedOptions } : {}),
                ...(scenario === 'multilingual'
                    ? { multilingual_options: multilingualOptions }
                    : {}),
                query_limit: queries,
                result_limit: limit,
                submission_key: key,
            },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: setErrors,
                onSuccess: (page) => {
                    setKey(crypto.randomUUID());
                    setOpened(
                        (page.props as unknown as Props).selectedSessionId,
                    );
                },
            },
        );
    }
    const active = history.find((s) => s.id === opened) ?? history[0];
    return (
        <>
            <Head title="SIFI · Penelusuran Informasi" />
            <main className="min-h-screen min-w-0 space-y-5 bg-muted/20 p-4 lg:p-8">
                <header className="flex flex-wrap justify-between gap-3">
                    <div>
                        <p className="text-xs tracking-widest text-muted-foreground uppercase">
                            SIFI / RISET MULTI-SUMBER
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold">
                            Penelusuran Informasi
                        </h1>
                        <p className="mt-2 text-sm">
                            Pertanyaan → hasil lintas provider → perbandingan →
                            riwayat penelitian.
                        </p>
                    </div>
                    <nav className="flex flex-wrap gap-4 text-sm">
                        <Link
                            className="underline"
                            href={
                                o
                                    ? `/sifi/workbench?object=${o.id}`
                                    : '/sifi/workbench'
                            }
                        >
                            Workbench
                        </Link>
                        <Link
                            className="underline"
                            href="/sifi/monitoring-objects"
                        >
                            Kelola objek
                        </Link>
                        {o && (
                            <Link
                                className="underline"
                                href={`/sifi/monitoring-objects/${o.id}/research-bridge`}
                            >
                                Alur dokumen &amp; draf existing
                            </Link>
                        )}
                        <a
                            className="underline"
                            href="/sifi/research-source-preview"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Snapshot data nyata (lokal)
                        </a>
                    </nav>
                </header>
                <p className="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                    {active?.multi?.envelope.mode ===
                    'APPROVED_LIVE_CORPUS_METADATA' ? (
                        <>
                            <strong>
                                METADATA PENCARIAN BERIZIN · AI SIFI OFF.
                            </strong>{' '}
                            Cakupan terbatas pada query dan provider yang
                            diizinkan; hasil belum diverifikasi.
                        </>
                    ) : (
                        <>
                            <strong>
                                SYNTHETIC · SIMULASI SUMBER TERPILIH ·
                                NETWORK/AI OFF.
                            </strong>{' '}
                            Hasil .invalid buatan SIFI, bukan penelusuran
                            internet nyata.
                        </>
                    )}{' '}
                    Search result, hak retrieval, dokumen dan evidence adalah
                    tahap terpisah. Penyimpanan penelitian tidak memberi grant
                    atau admission.
                </p>
                <label className="block text-sm font-medium">
                    Objek pantauan
                    <select
                        className={input}
                        value={o?.id ?? ''}
                        onChange={(ev) =>
                            ev.target.value &&
                            router.visit(
                                `/sifi/monitoring-objects/${ev.target.value}/research-multi`,
                            )
                        }
                    >
                        <option value="">Pilih objek milik akun</option>
                        {objectChoices.map((x) => (
                            <option key={x.id} value={x.id}>
                                {x.name} · {x.state}
                            </option>
                        ))}
                    </select>
                </label>
                {!o ? (
                    <p>
                        Pilih atau buat objek terlebih dahulu. Tidak ada objek
                        dibuat otomatis.
                    </p>
                ) : (
                    <>
                        <p className="text-sm break-words">
                            {o.name} · revisi {o.revision} · {o.state}
                        </p>
                        <form
                            onSubmit={search}
                            className="space-y-4 rounded-xl border bg-card p-5"
                        >
                            <label className="block text-sm font-medium">
                                Pertanyaan penelitian
                                <textarea
                                    className={input}
                                    required
                                    minLength={8}
                                    maxLength={600}
                                    rows={3}
                                    value={question}
                                    onChange={(ev) =>
                                        setQuestion(ev.target.value)
                                    }
                                    placeholder="Misalnya: Bagaimana perkembangan kendaraan listrik?"
                                />
                            </label>
                            <label className="block text-sm font-medium">
                                Metode penelusuran
                                <select
                                    className={input}
                                    value={scenario}
                                    onChange={(ev) =>
                                        setScenario(ev.target.value)
                                    }
                                >
                                    <option value="feed">
                                        RSS, Atom &amp; agregator — simulasi
                                    </option>
                                    <option value="multilingual">
                                        Lintas bahasa — simulasi
                                    </option>
                                    {scenarios.includes('crossref_live') && (
                                        <option value="crossref_live">
                                            Crossref — internet nyata, publikasi
                                            2025
                                        </option>
                                    )}
                                    {scenarios
                                        .filter(
                                            (s) =>
                                                ![
                                                    'feed',
                                                    'multilingual',
                                                    'crossref_live',
                                                ].includes(s),
                                        )
                                        .map((s) => (
                                            <option key={s} value={s}>
                                                {s}
                                            </option>
                                        ))}
                                </select>
                            </label>
                            <details className="text-sm">
                                <summary className="cursor-pointer">
                                    Batas dan skenario fixture
                                </summary>
                                <div className="mt-3 grid gap-3 md:grid-cols-3">
                                    <label
                                        hidden={[
                                            'multilingual',
                                            'feed',
                                        ].includes(scenario)}
                                    >
                                        Maksimum query
                                        <input
                                            className={input}
                                            type="number"
                                            min={1}
                                            max={2}
                                            value={queries}
                                            onChange={(ev) =>
                                                setQueries(
                                                    Number(ev.target.value),
                                                )
                                            }
                                        />
                                    </label>
                                    <label
                                        hidden={[
                                            'multilingual',
                                            'feed',
                                        ].includes(scenario)}
                                    >
                                        Maksimum hasil
                                        <input
                                            className={input}
                                            type="number"
                                            min={1}
                                            max={10}
                                            value={limit}
                                            onChange={(ev) =>
                                                setLimit(
                                                    Number(ev.target.value),
                                                )
                                            }
                                        />
                                    </label>
                                    <label>
                                        Skenario fixture
                                        <select
                                            className={input}
                                            value={scenario}
                                            onChange={(ev) =>
                                                setScenario(ev.target.value)
                                            }
                                        >
                                            {scenarios.map((s) => (
                                                <option key={s} value={s}>
                                                    {(
                                                        {
                                                            crossref_live:
                                                                'Crossref — internet nyata, publikasi 2025',
                                                            feed: 'RSS, Atom & agregator — simulasi',
                                                            multilingual:
                                                                'Lintas bahasa — simulasi',
                                                            api_web_2025:
                                                                'Web 2025 + Wikipedia — simulasi',
                                                            api_fixture:
                                                                'API Wikipedia + Crossref — simulasi',
                                                            api_no_result:
                                                                'API tanpa hasil',
                                                            api_quota:
                                                                'API kuota habis',
                                                            api_timeout:
                                                                'API waktu habis',
                                                            api_partial:
                                                                'API hasil sebagian',
                                                        } as Record<
                                                            string,
                                                            string
                                                        >
                                                    )[s] ?? s}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                </div>
                            </details>
                            {scenario === 'crossref_live' && collector && (
                                <RealCollectorControl
                                    value={collector}
                                    onUseQuestion={() =>
                                        setQuestion(
                                            'Citra Negatif Apa Saja yang Menyerang Kota Yogyakarta sepanjang Tahun 2025',
                                        )
                                    }
                                />
                            )}
                            {scenario === 'feed' && (
                                <FeedControls
                                    value={feedOptions}
                                    onChange={setFeedOptions}
                                />
                            )}
                            {scenario === 'multilingual' && (
                                <MultilingualControls
                                    value={multilingualOptions}
                                    onChange={setMultilingualOptions}
                                />
                            )}
                            <p
                                hidden={scenario === 'crossref_live'}
                                className="text-xs break-words text-muted-foreground"
                            >
                                Provider:{' '}
                                {scenario === 'feed'
                                    ? 'Source pack RSS + Atom + open-news synthetic'
                                    : scenario === 'multilingual'
                                      ? 'Dua provider multilingual synthetic'
                                      : scenario === 'api_web_2025'
                                        ? 'Tavily keyless + Wikipedia (HTTP fake)'
                                        : scenario.startsWith('api_')
                                          ? 'Wikipedia Indonesia + Crossref (HTTP fake)'
                                          : providerIds.join(' + ')}
                                . Maksimum{' '}
                                {scenario === 'feed'
                                    ? 6
                                    : scenario === 'multilingual'
                                      ? 12
                                      : 4}{' '}
                                panggilan simulasi, metadata 64 KiB,{' '}
                                {['multilingual', 'feed'].includes(scenario)
                                    ? 2
                                    : scenario.startsWith('api_')
                                      ? 15
                                      : 1}{' '}
                                detik pemrosesan. Internet 0 request, 0 dokumen,
                                0 biaya; retry/fallback tidak ada.
                            </p>
                            {Object.entries(errors).map(([k, v]) => (
                                <p
                                    key={k}
                                    role="alert"
                                    className="text-sm break-words text-destructive"
                                >
                                    {v}
                                </p>
                            ))}
                            <button
                                disabled={busy || o.state !== 'CONFIGURED'}
                                className={
                                    button + ' bg-foreground text-background'
                                }
                            >
                                {busy
                                    ? 'Memproses…'
                                    : scenario === 'crossref_live'
                                      ? 'Ambil metadata nyata (maksimum 1 request)'
                                      : scenario === 'feed'
                                        ? 'Baca sumber terpilih (simulasi)'
                                        : scenario === 'multilingual'
                                          ? 'Telusuri lintas bahasa (simulasi)'
                                          : scenario.startsWith('api_')
                                            ? 'Telusuri dua API (simulasi)'
                                            : 'Telusuri dua provider fixture'}
                            </button>
                        </form>
                        <OwnerBoundRealSourceControls object={o} />
                        <OwnerBoundReceiptDashboard history={history} />
                        <section className="space-y-3">
                            <h2 className="text-xl font-semibold">
                                Riwayat penelitian
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Maksimum 30 sesi terbaru, retensi akses konteks
                                30 hari. Sesi ini juga memakai research history
                                existing.
                            </p>
                            {history.length === 0 && (
                                <p>Belum ada penelitian multi-sumber.</p>
                            )}
                            <div className="flex flex-wrap gap-2">
                                {history.map((s) => (
                                    <button
                                        key={s.id}
                                        className={
                                            button +
                                            ' max-w-full text-left break-words'
                                        }
                                        onClick={() => setOpened(s.id)}
                                    >
                                        {s.question ?? 'Konteks kedaluwarsa'} ·{' '}
                                        {s.expired
                                            ? 'Kedaluwarsa'
                                            : s.multi?.state === 'SAVED'
                                              ? 'Tersimpan'
                                              : 'Draft'}
                                    </button>
                                ))}
                            </div>
                            {busy && (
                                <p role="status">Menyiapkan hasil baru…</p>
                            )}
                            {active &&
                                !busy &&
                                (active.expired ? (
                                    <p>
                                        Konteks kedaluwarsa; isi tidak
                                        ditampilkan.
                                    </p>
                                ) : (
                                    active.multi && (
                                        <ResearchResult
                                            key={`${active.id}-${active.revision}`}
                                            session={active}
                                            object={o}
                                        />
                                    )
                                ))}
                        </section>
                    </>
                )}
                <p className="rounded-lg border p-3 text-sm">
                    Adapter Wikipedia, Crossref, dan kandidat web Tavily keyless
                    tersedia pada simulasi API. Kelayakan produksi Tavily masih
                    bersyarat; kuota keyless tidak dipublikasikan secara
                    numerik. Contoh simulasi berlabel SYNTHETIC .invalid; hasil
                    Crossref nyata ditandai dengan HTTP receipt dan DOI asli.
                    Collector Crossref on-demand tersedia hanya untuk konteks
                    yang diotorisasi; provider lain tetap simulasi atau
                    terkunci. Wikipedia mencakup ensiklopedia dan Crossref
                    metadata ilmiah, bukan seluruh web.
                </p>
                <p className="text-xs text-muted-foreground">
                    Menuju sumber nyata: adapter tetap provider-neutral;
                    aktivasi memerlukan review provider, hak, quota, biaya dan
                    otorisasi terpisah. Hasil baru belum otomatis masuk
                    Intelligence Brief; gunakan alur dokumen/draf existing
                    secara eksplisit setelah gate hak dan provenance terpenuhi.
                </p>
            </main>
        </>
    );
}
