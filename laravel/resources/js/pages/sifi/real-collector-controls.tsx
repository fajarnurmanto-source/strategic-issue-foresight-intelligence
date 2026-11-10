export type CollectorStatus = {
    state: string;
    source: string;
    rights: string;
    max_requests_per_day: number;
    min_interval_seconds: number;
    limit: number;
    bytes: number;
    timeout_seconds: number;
    coverage: string;
    review_expires: string;
};
export function RealCollectorControl({
    value: v,
    onUseQuestion,
}: {
    value: CollectorStatus;
    onUseQuestion: () => void;
}) {
    return (
        <section
            className="space-y-3 rounded-lg border border-blue-500/50 p-4"
            aria-label="Collector internet nyata"
        >
            <h2 className="font-semibold">Crossref · collector on-demand</h2>
            <p className="text-sm">
                Status: {v.state} · scheduler OFF. Membaca metadata publikasi
                ilmiah tahun 2025, bukan artikel lengkap. Tidak memerlukan akun
                atau API berbayar.
            </p>
            <p className="text-sm">{v.coverage}</p>
            <p className="text-xs">
                Satu request per tombol; maksimal {v.limit} hasil, {v.bytes}{' '}
                byte, {v.timeout_seconds} detik. Batas {v.max_requests_per_day}
                /hari, jeda {v.min_interval_seconds} detik. Tanpa
                redirect/retry/fallback, biaya0. Hak: {v.rights}; review sampai{' '}
                {v.review_expires}.
            </p>
            <button
                type="button"
                className="rounded-lg border px-3 py-2 text-sm"
                onClick={onUseQuestion}
            >
                Gunakan pertanyaan Kota Yogyakarta 2025 existing
            </button>
        </section>
    );
}
type Receipt = {
    state: string;
    original_question: string;
    request_query: string;
    http_status: number | null;
    wire_delivery_confirmed: boolean;
    retrieved_at: string | null;
    response_sha256: string | null;
    response_bytes: number;
    elapsed_ms: number | null;
    dispatch_attempts: number;
    failure_category: string | null;
    coverage: string;
};
export function RealCollectorSummary({ value: v }: { value: Receipt }) {
    return (
        <section
            className="space-y-2 rounded-lg border p-4"
            aria-label="Receipt collector nyata"
        >
            <h4 className="font-semibold">Status pengambilan sumber asli</h4>
            <p className="text-sm">
                Collector: {v.state} · HTTP {v.http_status ?? 'UNKNOWN'} ·
                respons diterima:{' '}
                {v.wire_delivery_confirmed ? 'Ya' : 'Belum terbukti'} ·
                percobaan dispatch {v.dispatch_attempts}.
            </p>
            <p className="text-sm break-words">
                Diambil: {v.retrieved_at ?? 'UNKNOWN'} · {v.response_bytes} byte
                · {v.elapsed_ms ?? 'UNKNOWN'} ms · kesalahan:{' '}
                {v.failure_category ?? 'tidak ada'}.
            </p>
            <p className="text-xs break-all">
                SHA-256 respons: {v.response_sha256 ?? 'UNKNOWN'}
            </p>
            <p className="text-sm">Pertanyaan asli: {v.original_question}</p>
            <p className="text-sm">
                Query bibliografi: {v.request_query}; filter publikasi 2025. Ini
                sublangkah penemuan sumber, bukan penggantian pertanyaan atau
                jawaban citra negatif.
            </p>
            <p className="text-xs">
                {v.coverage} Event2025 UNKNOWN; tidak ada claim/evidence
                admitted otomatis.
            </p>
        </section>
    );
}
