export type MultilingualOptions = {
    language_mode: string;
    languages: string[];
    result_language: string;
    region: string;
    period: string;
    source_type: string;
    start_date: string;
    end_date: string;
    provider_state: string;
};
export const defaultMultilingualOptions: MultilingualOptions = {
    language_mode: 'AUTO',
    languages: ['id', 'en', 'es', 'fr'],
    result_language: 'ALL',
    region: 'EXACT_OBJECT',
    period: 'ALL_CONTEXT',
    source_type: 'ALL',
    start_date: '',
    end_date: '',
    provider_state: 'normal',
};
const input = 'mt-1 w-full min-w-0 rounded-lg border bg-background p-2 text-sm';
const languages = [
    ['id', 'Indonesia'],
    ['en', 'English'],
    ['es', 'Español'],
    ['fr', 'Français'],
    ['ja', '日本語'],
];
export function MultilingualControls({
    value: v,
    onChange,
}: {
    value: MultilingualOptions;
    onChange: (v: MultilingualOptions) => void;
}) {
    const set = (key: keyof MultilingualOptions, value: string | string[]) =>
        onChange({ ...v, [key]: value });
    return (
        <section
            aria-label="Pengaturan riset lintas bahasa"
            className="space-y-3 rounded-lg border p-3"
        >
            <h3 className="font-semibold">
                Penelusuran lintas bahasa · SYNTHETIC
            </h3>
            <p className="text-xs">
                AUTO mencoba Indonesia, Inggris, Spanyol, dan Prancis. Lima
                bahasa fixture tersedia; ini bukan dukungan semua bahasa
                internet atau terjemahan otomatis. Maksimal 6 variasi query dan
                12 panggilan simulasi. Filter diterapkan saat penelusuran
                dijalankan.
            </p>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <label className="text-sm">
                    Bahasa pencarian
                    <select
                        className={input}
                        value={v.language_mode}
                        onChange={(e) => set('language_mode', e.target.value)}
                    >
                        <option>AUTO</option>
                        <option value="MANUAL">Pilih bahasa</option>
                    </select>
                </label>
                {v.language_mode === 'MANUAL' && (
                    <fieldset className="min-w-0 text-sm">
                        <legend>Bahasa yang diminta</legend>
                        <div className="flex flex-wrap gap-3">
                            {languages.map(([code, label]) => (
                                <label key={code} className="flex gap-1">
                                    <input
                                        type="checkbox"
                                        checked={v.languages.includes(code)}
                                        onChange={(e) =>
                                            set(
                                                'languages',
                                                e.target.checked
                                                    ? [...v.languages, code]
                                                    : v.languages.filter(
                                                          (x) => x !== code,
                                                      ),
                                            )
                                        }
                                    />
                                    {label}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                )}
                <label className="text-sm">
                    Bahasa sumber hasil
                    <select
                        className={input}
                        value={v.result_language}
                        onChange={(e) => set('result_language', e.target.value)}
                    >
                        <option value="ALL">Semua / termasuk UNKNOWN</option>
                        {languages.map(([c, l]) => (
                            <option key={c} value={c}>
                                {l}
                            </option>
                        ))}
                        <option>UNKNOWN</option>
                    </select>
                </label>
                <label className="text-sm">
                    Cakupan wilayah
                    <select
                        className={input}
                        value={v.region}
                        onChange={(e) => set('region', e.target.value)}
                    >
                        <option value="EXACT_OBJECT">
                            Konteks objek yang dipilih
                        </option>
                        <option value="WIDER_REGION">
                            Wilayah lebih luas · bukan objek yang sama
                        </option>
                        <option value="ALL">
                            Semua konteks, dengan label wilayah
                        </option>
                    </select>
                </label>
                <label className="text-sm">
                    Hubungan periode
                    <select
                        className={input}
                        value={v.period}
                        onChange={(e) => set('period', e.target.value)}
                    >
                        <option value="ALL_CONTEXT">
                            Semua, pisahkan klasifikasi tanggal
                        </option>
                        <option value="PUBLISHED">
                            Dipublikasikan dalam periode
                        </option>
                        <option value="EVENT">
                            Peristiwa dalam periode (deklaratif)
                        </option>
                        <option value="LATER">
                            Laporan kemudian tentang periode
                        </option>
                        <option value="UNKNOWN">
                            Tanggal publikasi UNKNOWN
                        </option>
                    </select>
                </label>
                <label className="text-sm">
                    Jenis sumber
                    <select
                        className={input}
                        value={v.source_type}
                        onChange={(e) => set('source_type', e.target.value)}
                    >
                        {[
                            'ALL',
                            'OFFICIAL',
                            'NEWS',
                            'ACADEMIC',
                            'OPEN_DATA',
                            'REGULATORY',
                            'RSS_ATOM',
                            'PUBLIC_API',
                        ].map((x) => (
                            <option key={x}>{x}</option>
                        ))}
                    </select>
                </label>
                <label className="text-sm">
                    Tanggal mulai
                    <input
                        type="date"
                        className={input}
                        value={v.start_date}
                        onChange={(e) => set('start_date', e.target.value)}
                    />
                </label>
                <label className="text-sm">
                    Tanggal akhir eksklusif
                    <input
                        type="date"
                        className={input}
                        value={v.end_date}
                        onChange={(e) => set('end_date', e.target.value)}
                    />
                </label>
                <label className="text-sm">
                    Kondisi provider simulasi
                    <select
                        className={input}
                        value={v.provider_state}
                        onChange={(e) => set('provider_state', e.target.value)}
                    >
                        {[
                            'normal',
                            'partial',
                            'no_result',
                            'quota',
                            'timeout',
                        ].map((x) => (
                            <option key={x}>{x}</option>
                        ))}
                    </select>
                </label>
            </div>
            <p className="text-xs">
                Jika tanggal dikosongkan dan pertanyaan menyebut satu tahun,
                periode diturunkan dari tahun itu. Tahun peristiwa berbeda dari
                tahun publikasi. Nama/alias/geografi memakai konfigurasi objek;
                wilayah lebih luas tidak dianggap identitas yang sama.
            </p>
        </section>
    );
}
type Plan = {
    untranslated_terms?: string[];
    requested_languages: string[];
    queries: { query: string; language: string; derivation: string }[];
    window: { start: string | null; end_exclusive: string | null };
    coverage_gaps: string[];
    question_translation: string;
};
export function MultilingualPlanSummary({
    plan,
    covered,
    filtered,
    providers,
    failures,
}: {
    plan: Plan;
    covered?: string[];
    filtered?: Record<string, number>;
    providers?: string[];
    failures?: number;
}) {
    return (
        <details open className="min-w-0 rounded-lg border p-3 text-sm">
            <summary className="font-semibold">
                Cakupan lintas bahasa dan query
            </summary>
            <p>
                Provider yang dipakai: {providers?.join(', ')}.{' '}
                {failures
                    ? 'Hasil sebagian atau tidak tersedia: ' +
                      failures +
                      ' panggilan provider gagal.'
                    : 'Semua panggilan fixture selesai; ini bukan bukti kelengkapan sumber internet.'}
            </p>
            <p>
                Diminta: {plan.requested_languages.join(', ')} · ditemukan pada
                fixture: {covered?.join(', ') || 'tidak ada'}. Deteksi bahasa
                bersifat heuristik, bukan penilaian kredibilitas.
            </p>
            <p>
                Istilah yang dipertahankan tanpa terjemahan:{' '}
                {plan.untranslated_terms?.join(', ') ||
                    'tidak ditandai; ini bukan terjemahan lengkap'}
                .
            </p>
            <p>
                Periode: {plan.window.start ?? 'UNSPECIFIED'} sampai sebelum{' '}
                {plan.window.end_exclusive ?? 'UNSPECIFIED'} · terjemahan
                pertanyaan: {plan.question_translation}.
            </p>
            <ol className="mt-2 space-y-2">
                {plan.queries.map((q, i) => (
                    <li key={i} className="break-words">
                        {q.language}: <code>{q.query}</code>
                        <span className="block text-xs">{q.derivation}</span>
                    </li>
                ))}
            </ol>
            <p className="mt-2 break-words">
                Dikeluarkan oleh filter:{' '}
                {Object.entries(filtered ?? {})
                    .map(([k, n]) => `${k} ${n}`)
                    .join(' · ')}
                .
            </p>
            {plan.coverage_gaps.map((gap, i) => (
                <p className="mt-1 text-xs" key={i}>
                    {gap}
                </p>
            ))}
        </details>
    );
}
type ComparisonEntry = {
    id: string;
    title: string;
    snippet: string;
    source_language?: string;
    published_at: string | null;
    event_date?: string | null;
    time_classification?: string;
    canonical_url: string;
    origins: string[];
};
export function CrossLanguageComparison({
    entries,
}: {
    entries: ComparisonEntry[];
}) {
    return (
        <section
            aria-label="Teks asli pilihan lintas bahasa"
            className="space-y-3 rounded-lg border p-3"
        >
            <h4 className="font-semibold">Bandingkan teks asli</h4>
            <p className="text-xs">
                Teks berikut tidak diterjemahkan atau ditulis ulang. Kesamaan
                makna, kebenaran informasi, serta independensi sumber belum
                diverifikasi.
            </p>
            <div className="grid gap-3 md:grid-cols-2">
                {entries.map((e) => (
                    <article
                        key={e.id}
                        className="min-w-0 space-y-2 rounded-lg border p-3"
                    >
                        <p className="text-sm">
                            Bahasa asli: {e.source_language ?? 'UNKNOWN'}
                        </p>
                        <h5 className="font-medium break-words">{e.title}</h5>
                        <p
                            className="text-sm break-words"
                            lang={
                                e.source_language === 'UNKNOWN'
                                    ? undefined
                                    : e.source_language
                            }
                        >
                            {e.snippet}
                        </p>
                        <p className="text-xs break-words">
                            Publikasi: {e.published_at ?? 'UNKNOWN'} ·
                            peristiwa: {e.event_date ?? 'UNKNOWN'} ·{' '}
                            {e.time_classification}. Asal deklaratif:{' '}
                            {e.origins.join(', ')}.
                        </p>
                        <code className="block text-xs break-all">
                            {e.canonical_url}
                        </code>
                    </article>
                ))}
            </div>
        </section>
    );
}
