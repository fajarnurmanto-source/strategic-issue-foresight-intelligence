import { Link } from '@inertiajs/react';

type CollectorReceipt = {
    http_status: number | null;
    wire_delivery_confirmed: boolean;
    retrieved_at: string | null;
    response_sha256: string | null;
    response_bytes: number;
    failure_category: string | null;
};

type CollectorEntry = {
    id: string;
    title: string;
    url: string;
    publisher: string;
    published_at: string | null;
    date_precision: string;
    event_date: string | null;
    revision_state: string;
    source_language: string;
    region: string;
    rights_state?: string;
    evidence_admission?: 'NONE';
};

export type CollectorObservation = {
    session_id: string;
    question: string;
    state: string;
    saved_at: string | null;
    filtered_count: number;
    receipt: CollectorReceipt;
    entries: CollectorEntry[];
    provider_ids?: string[];
    source_kind?: string;
    coverage?: string;
    envelope_sha256?: string | null;
    independent_origin_state?: string;
    claim_verification?: string;
    evidence_admission?: 'NONE';
};

const kindLabels: Record<string, string> = {
    SCHOLARLY_BIBLIOGRAPHIC_METADATA:
        'Metadata bibliografi ilmiah (bukan arsip berita)',
    PUBLISHER_RSS_HEADLINES:
        'Judul dan tautan RSS penerbit (bukan isi artikel)',
    NEWS_LINK_METADATA:
        'Tautan berita dan metadata (bukan verifikasi kejadian)',
    OTHER_REVIEWED_PUBLIC_METADATA: 'Metadata publik terbatas',
    MULTI_OR_UNKNOWN_PUBLIC_METADATA:
        'Metadata gabungan atau jenis sumber belum diketahui',
};

export default function CollectorObservations({
    objectId,
    items,
}: {
    objectId: string;
    items: CollectorObservation[];
}) {
    return (
        <section
            aria-label="Observasi metadata sumber publik"
            className="min-w-0 space-y-4 rounded-xl border bg-card p-5"
        >
            <h2 className="text-xl font-semibold">
                Observasi sumber publik pada objek ini
            </h2>
            <p className="text-sm text-muted-foreground">
                Hasil metadata dari sumber publik yang diizinkan. Belum menjadi
                bukti atau klaim terverifikasi. Rincian teknis tersedia saat
                dibutuhkan; tidak ada langkah verifikasi manual per publikasi.
            </p>
            <Link
                className="inline-block underline"
                href={`/sifi/monitoring-objects/${objectId}/research-multi`}
            >
                Buka penelitian dan collector
            </Link>
            {!items.length && (
                <p>Belum ada receipt metadata nyata dalam riwayat objek ini.</p>
            )}
            {items.map((session) => (
                <article
                    key={session.session_id}
                    className="min-w-0 space-y-3 rounded-lg border p-4"
                >
                    <h3 className="font-medium break-words">
                        {session.question}
                    </h3>
                    <p className="text-sm">
                        Korpus:{' '}
                        {kindLabels[session.source_kind ?? ''] ??
                            kindLabels.MULTI_OR_UNKNOWN_PUBLIC_METADATA}
                        {' · '}Provider:{' '}
                        {session.provider_ids?.join(', ') || 'UNKNOWN'}
                        {' · '}Jumlah metadata ditampilkan:{' '}
                        {session.entries.length}
                    </p>
                    <details className="rounded-md border border-dashed p-3 text-sm">
                        <summary className="cursor-pointer font-medium">
                            Rincian teknis, hak dan provenance
                        </summary>
                        <div className="mt-3 space-y-2">
                            <p className="text-sm">
                                {session.state === 'SAVED'
                                    ? 'Disimpan oleh analis'
                                    : 'Draf penelitian'}
                                {' · '}HTTP{' '}
                                {session.receipt.http_status ?? 'UNKNOWN'}
                                {' · '}Respons tercatat:{' '}
                                {session.receipt.wire_delivery_confirmed
                                    ? 'Ya'
                                    : 'Belum terbukti'}
                                {' · '}
                                {session.receipt.response_bytes} byte
                                {' · '}
                                {session.receipt.retrieved_at ??
                                    'Waktu pengambilan UNKNOWN'}
                            </p>
                            <p className="text-xs break-all">
                                SHA-256 respons:{' '}
                                {session.receipt.response_sha256 ?? 'UNKNOWN'}
                            </p>
                            <p className="text-xs break-all">
                                SHA-256 envelope:{' '}
                                {session.envelope_sha256 ?? 'UNKNOWN'}
                            </p>
                            <p className="text-sm">
                                Cakupan:{' '}
                                {session.coverage ?? 'Tidak dinyatakan'}.{' · '}
                                Asal independen:{' '}
                                {session.independent_origin_state ??
                                    'NOT_VERIFIED'}
                                {' · '}Verifikasi klaim:{' '}
                                {session.claim_verification ?? 'NOT_PERFORMED'}
                            </p>
                            <p className="text-sm">
                                Kesalahan:{' '}
                                {session.receipt.failure_category ??
                                    'tidak ada'}
                                {' · '}
                                {session.filtered_count} hasil disaring karena
                                judul/periode
                                {' · '}Admission evidence:{' '}
                                {session.evidence_admission ?? 'NONE'}
                            </p>
                        </div>
                    </details>
                    {session.entries.map((entry) => (
                        <div
                            key={entry.id}
                            className="min-w-0 space-y-1 border-l-2 pl-3 text-sm"
                        >
                            <p className="font-medium break-words">
                                {entry.title}
                            </p>
                            <p className="break-words">
                                Asal yang dideklarasikan: {entry.publisher}{' '}
                                (belum diverifikasi independen)
                            </p>
                            <p>
                                Publikasi: {entry.published_at ?? 'UNKNOWN'} (
                                {entry.date_precision}){'; '}kejadian:{' '}
                                {entry.event_date ?? 'UNKNOWN'}
                            </p>
                            <p>
                                Hak penggunaan:{' '}
                                {entry.rights_state ?? 'UNASSESSED'}
                                {' · '}Bukti:{' '}
                                {entry.evidence_admission ?? 'NONE'}
                            </p>
                            <p>
                                Revisi: {entry.revision_state}
                                {' · '}bahasa: {entry.source_language}
                                {' · '}wilayah: {entry.region} (tidak otomatis
                                setara Kota Yogyakarta)
                            </p>
                            <a
                                className="break-all underline"
                                href={entry.url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Tautan asli: {entry.url}
                            </a>
                        </div>
                    ))}
                    <Link
                        className="inline-block underline"
                        href={`/sifi/monitoring-objects/${objectId}/research-multi?research=${session.session_id}`}
                    >
                        Buka kembali penelitian ini
                    </Link>
                </article>
            ))}
        </section>
    );
}
