import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
type Ae16Filter = {
    query: string;
    date_window: {
        start: string;
        end_exclusive: string;
        time_basis: string;
        timezone: string;
    };
    geography: string;
    media_types: string[];
    sentiment: string;
    verification?: string;
};
const freshFilter = (): Ae16Filter => ({
    query: 'Citra negatif Kota Yogyakarta',
    date_window: {
        start: '2025-01-01',
        end_exclusive: '2026-01-01',
        time_basis: 'publication',
        timezone: 'Asia/Jakarta',
    },
    geography: 'KOTA_YOGYAKARTA',
    media_types: ['news', 'video', 'audio', 'image', 'document'],
    sentiment: 'positive',
    verification: 'ALL',
});
const mediaChoices = [
    { value: 'news', label: 'Berita' },
    { value: 'video', label: 'Video' },
    { value: 'audio', label: 'Audio' },
    { value: 'image', label: 'Gambar/poster' },
    { value: 'document', label: 'Dokumen/PDF' },
];
export type MonitoringObject = {
    id: string;
    name: string;
    description: string;
    category: string;
    geography: string | null;
    objective: string;
    horizon: string;
    state: string;
    revision: number;
    aliases_json: string[];
    keywords_json: string[];
    exclusions_json: string[];
    source_ids: string[];
    audit: { revision: number; action: string; created_at: string }[];
    ae16_filter?: Ae16Filter | null;
};
type Source = {
    id: string;
    name: string;
    rights: { allowed: boolean; reason: string };
    collector: string;
};
type Props = {
    objects: MonitoringObject[];
    sourceChoices: Source[];
    limit: number;
};
const control = 'mt-1 w-full rounded-lg border bg-background p-3 text-sm';
const states: Record<string, string> = {
    CONFIGURED: 'Konfigurasi siap',
    PAUSED: 'Dijeda',
    ARCHIVED: 'Diarsipkan',
};
function Editor({
    object: o,
    sources,
}: {
    object?: MonitoringObject;
    sources: Source[];
}) {
    const [name, setName] = useState(o?.name ?? '');
    const [description, setDescription] = useState(o?.description ?? '');
    const [category, setCategory] = useState(o?.category ?? '');
    const [aliases, setAliases] = useState(o?.aliases_json.join('\n') ?? '');
    const [keywords, setKeywords] = useState(o?.keywords_json.join('\n') ?? '');
    const [exclusions, setExclusions] = useState(
        o?.exclusions_json.join('\n') ?? '',
    );
    const [geography, setGeography] = useState(o?.geography ?? '');
    const [objective, setObjective] = useState(o?.objective ?? '');
    const [horizon, setHorizon] = useState(o?.horizon ?? '');
    const [state, setState] = useState(o?.state ?? 'CONFIGURED');
    const [ids, setIds] = useState(o?.source_ids ?? []);
    const [filter, setFilter] = useState<Ae16Filter>(
        o?.ae16_filter ?? freshFilter(),
    );
    const changeWindow = (name: 'start' | 'end_exclusive', value: string) =>
        setFilter((f) => ({
            ...f,
            date_window: { ...f.date_window, [name]: value },
        }));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const lines = (s: string) =>
        s
            .split('\n')
            .map((x) => x.trim())
            .filter(Boolean);
    function save(e: FormEvent) {
        e.preventDefault();
        setErrors({});
        const data = {
            name,
            description,
            category,
            aliases: lines(aliases),
            keywords: lines(keywords),
            exclusions: lines(exclusions),
            geography: geography || null,
            objective,
            horizon,
            state,
            source_ids: ids,
            ae16_filter: filter,
            ...(o ? { revision: o.revision } : {}),
        };
        const opts = {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onError: setErrors,
        };
        if (o) router.patch(`/sifi/monitoring-objects/${o.id}`, data, opts);
        else router.post('/sifi/monitoring-objects', data, opts);
    }
    return (
        <form
            onSubmit={save}
            className="min-w-0 space-y-4 rounded-xl border bg-card p-5 shadow-sm"
        >
            <h2 className="text-xl font-semibold">
                {o ? 'Ubah objek' : 'Buat objek pemantauan'}
            </h2>
            <p className="text-sm text-muted-foreground">
                Konfigurasi manual, bukan aktivasi kolektor.
                Alias/keyword/exclusion satu istilah per baris; pengisian
                formulir tidak mengambil data. Gunakan Temukan Sumber untuk
                pencocokan katalog lokal.
            </p>
            <label className="block text-sm font-medium">
                Nama objek
                <input
                    className={control}
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    minLength={3}
                    maxLength={180}
                    required
                />
            </label>
            <label className="block text-sm font-medium">
                Deskripsi
                <textarea
                    className={control}
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    minLength={10}
                    maxLength={2000}
                    required
                    rows={3}
                />
            </label>
            <label className="block text-sm font-medium">
                Kategori objek
                <input
                    className={control}
                    list="object-categories"
                    value={category}
                    onChange={(e) => setCategory(e.target.value)}
                    minLength={2}
                    maxLength={100}
                    required
                />
                <datalist id="object-categories">
                    <option value="Isu publik" />
                    <option value="Perusahaan / merek" />
                    <option value="Industri" />
                    <option value="Kebijakan" />
                </datalist>
            </label>
            <p className="text-xs text-muted-foreground">
                Gunakan saran atau ketik kategori sendiri. Kategori bukan daftar
                tertutup.
            </p>
            <div className="grid min-w-0 gap-4 md:grid-cols-3">
                {[
                    { name: 'Alias', value: aliases, set: setAliases, max: 20 },
                    {
                        name: 'Keyword',
                        value: keywords,
                        set: setKeywords,
                        max: 30,
                    },
                    {
                        name: 'Exclusion terms',
                        value: exclusions,
                        set: setExclusions,
                        max: 30,
                    },
                ].map((f) => (
                    <label
                        key={f.name}
                        className="block min-w-0 text-sm font-medium"
                    >
                        {f.name}
                        <textarea
                            className={control}
                            value={f.value}
                            onChange={(e) => f.set(e.target.value)}
                            rows={4}
                            maxLength={f.max * 101}
                        />
                        <span className="text-xs text-muted-foreground">
                            Maksimum {f.max} istilah, 100 karakter per istilah.
                        </span>
                    </label>
                ))}
            </div>
            <p className="rounded-lg bg-primary/5 p-3 text-xs text-muted-foreground">
                Alur E4: simpan objek dengan keyword dahulu. Setelah tersimpan,
                klik “Pantau periode” pada kartu objek untuk memilih tanggal dan
                menelusuri sumber yang diizinkan.
            </p>
            <label className="block text-sm font-medium">
                Geografi (opsional)
                <input
                    className={control}
                    value={geography}
                    onChange={(e) => setGeography(e.target.value)}
                    maxLength={180}
                />
            </label>
            <p className="text-xs text-muted-foreground">
                Kosong berarti tidak dibatasi geografi. Bukan pernyataan lokasi
                kejadian atau yurisdiksi.
            </p>
            <label className="block text-sm font-medium">
                Tujuan analisis
                <textarea
                    className={control}
                    value={objective}
                    onChange={(e) => setObjective(e.target.value)}
                    required
                    minLength={10}
                    maxLength={2000}
                    rows={3}
                />
            </label>
            <label className="block text-sm font-medium">
                Horizon waktu
                <input
                    className={control}
                    value={horizon}
                    onChange={(e) => setHorizon(e.target.value)}
                    required
                    minLength={3}
                    maxLength={160}
                    placeholder="Misalnya 90 hari; bukan jadwal pengambilan"
                />
            </label>
            <details className="rounded-lg border p-3">
                <summary className="cursor-pointer text-sm font-semibold">
                    Opsi lama AE-16 (lanjutan, bukan periode E4)
                </summary>
                <fieldset className="mt-3 min-w-0 space-y-3 rounded-lg border p-4">
                    <legend className="px-1 text-sm font-semibold">
                        AE-16 · Filter riset multimodal
                    </legend>
                    <p className="text-xs text-muted-foreground">
                        Tersimpan sebagai konfigurasi objek, belum menjalankan
                        pengambilan data atau AI. Metadata yang belum pasti
                        perlu review manusia.
                    </p>
                    <label className="block text-sm font-medium">
                        Query
                        <input
                            className={control}
                            maxLength={600}
                            value={filter.query}
                            onChange={(e) =>
                                setFilter((f) => ({
                                    ...f,
                                    query: e.target.value,
                                }))
                            }
                        />
                    </label>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="text-sm font-medium">
                            Mulai (inklusif)
                            <input
                                type="date"
                                className={control}
                                value={filter.date_window.start}
                                onChange={(e) =>
                                    changeWindow('start', e.target.value)
                                }
                            />
                        </label>
                        <label className="text-sm font-medium">
                            Akhir (eksklusif)
                            <input
                                type="date"
                                className={control}
                                value={filter.date_window.end_exclusive}
                                onChange={(e) =>
                                    changeWindow(
                                        'end_exclusive',
                                        e.target.value,
                                    )
                                }
                            />
                        </label>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Dasar waktu: tanggal publikasi · zona Asia/Jakarta. Jika
                        tidak diketahui, masuk review.
                    </p>
                    <label className="block text-sm font-medium">
                        Wilayah
                        <select
                            className={control}
                            value={filter.geography}
                            onChange={(e) =>
                                setFilter((f) => ({
                                    ...f,
                                    geography: e.target.value,
                                }))
                            }
                        >
                            <option value="KOTA_YOGYAKARTA">
                                Kota Yogyakarta
                            </option>
                        </select>
                    </label>
                    <fieldset className="space-y-2">
                        <legend className="text-sm font-medium">
                            Format media
                        </legend>
                        <div className="flex flex-wrap gap-3">
                            {mediaChoices.map((m) => (
                                <label
                                    key={m.value}
                                    className="inline-flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        checked={filter.media_types.includes(
                                            m.value,
                                        )}
                                        onChange={(e) =>
                                            setFilter((f) => ({
                                                ...f,
                                                media_types: e.target.checked
                                                    ? [
                                                          ...f.media_types,
                                                          m.value,
                                                      ]
                                                    : f.media_types.filter(
                                                          (x) => x !== m.value,
                                                      ),
                                            }))
                                        }
                                    />
                                    {m.label}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                    <label className="block text-sm font-medium">
                        Sentimen terhadap Kota Yogyakarta
                        <select
                            className={control}
                            value={filter.sentiment}
                            onChange={(e) =>
                                setFilter((f) => ({
                                    ...f,
                                    sentiment: e.target.value,
                                }))
                            }
                        >
                            <option value="positive">Positif</option>
                            <option value="negative">Negatif</option>
                            <option value="neutral">Netral</option>
                            <option value="all">Semua</option>
                        </select>
                    </label>
                </fieldset>
            </details>
            <label className="block text-sm font-medium">
                Status konfigurasi
                <select
                    className={control}
                    value={state}
                    onChange={(e) => setState(e.target.value)}
                >
                    {Object.entries(states).map(([k, v]) => (
                        <option key={k} value={k}>
                            {v}
                        </option>
                    ))}
                </select>
            </label>
            <fieldset className="min-w-0 space-y-3 rounded-lg border p-3">
                <legend className="px-1 text-sm font-semibold">
                    Referensi sumber milik akun
                </legend>
                <p className="text-xs text-muted-foreground">
                    Hanya kontrak hak dataset existing yang memenuhi syarat
                    dapat dipilih. Asosiasi tidak memberi grant pengambilan,
                    admission atau bukti relevansi. Collector tetap OFF.
                </p>
                {sources.length === 0 && (
                    <p className="text-sm">
                        Belum ada sumber terdaftar. Objek dapat disimpan tanpa
                        sumber.
                    </p>
                )}
                {sources.map((s) => (
                    <label
                        key={s.id}
                        className="flex items-start gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            className="mt-1"
                            checked={ids.includes(s.id)}
                            disabled={!s.rights.allowed && !ids.includes(s.id)}
                            onChange={(e) =>
                                setIds(
                                    e.target.checked
                                        ? [...ids, s.id]
                                        : ids.filter((id) => id !== s.id),
                                )
                            }
                        />
                        <span className="min-w-0 break-words">
                            {s.name}
                            <small className="block">
                                {s.rights.allowed
                                    ? 'Referensi dataset memenuhi kontrak existing'
                                    : 'Belum diizinkan sebagai referensi'}{' '}
                                · {s.rights.reason}
                            </small>
                        </span>
                    </label>
                ))}
            </fieldset>
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
                disabled={busy}
                className="rounded-lg bg-foreground px-4 py-3 font-semibold text-background disabled:opacity-40"
            >
                {o ? 'Simpan perubahan objek' : 'Simpan objek'}
            </button>
        </form>
    );
}
export default function MonitoringObjects({
    objects,
    sourceChoices,
    limit,
}: Props) {
    const [selected, setSelected] = useState<string | null>(null);
    const [newRevision, setNewRevision] = useState(0);
    const o = objects.find((x) => x.id === selected);
    return (
        <>
            <Head title="SIFI · Objek Pemantauan" />
            <main className="min-h-screen space-y-5 bg-muted/20 p-4 lg:p-8">
                <header className="flex flex-wrap justify-between gap-3">
                    <div>
                        <p className="text-xs tracking-widest text-muted-foreground uppercase">
                            SIFI / UNIVERSAL MONITORING
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold">
                            Objek pemantauan
                        </h1>
                        <p className="mt-2 text-sm">
                            Objek dapat berupa wilayah, perusahaan, industri,
                            kebijakan, teknologi, atau kategori lain.
                        </p>
                    </div>
                    <Link className="underline" href="/sifi/workbench">
                        Kembali ke Workbench
                    </Link>
                </header>
                <p className="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                    KONFIGURASI OBJEK · Menyimpan objek tidak otomatis memulai
                    crawler. Untuk pemindaian sosial terbatas gunakan tautan
                    Pantau media sosial; hanya konektor yang diizinkan secara
                    lokal dapat mengambil metadata. Tidak ada scheduler atau
                    evidence admission.
                </p>
                <div className="grid min-w-0 gap-5 xl:grid-cols-2">
                    <section className="min-w-0 space-y-3">
                        <div className="flex flex-wrap justify-between gap-2">
                            <h2 className="text-xl font-semibold">
                                Objek saya ({objects.length})
                            </h2>
                            <button
                                onClick={() => {
                                    setSelected(null);
                                    setNewRevision((n) => n + 1);
                                }}
                                className="rounded-lg border px-3 py-2 text-sm"
                            >
                                Objek baru
                            </button>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Maksimum {limit} konfigurasi terbaru ditampilkan.
                        </p>
                        {objects.length === 0 && (
                            <p>
                                Belum ada objek. Buat konfigurasi pertama Anda.
                            </p>
                        )}
                        {objects.map((x) => (
                            <article
                                key={x.id}
                                className="min-w-0 space-y-2 rounded-xl border bg-card p-4"
                            >
                                <h3 className="text-lg font-semibold break-words">
                                    {x.name}
                                </h3>
                                <p className="text-sm break-words">
                                    {x.category} · {states[x.state]} · revisi{' '}
                                    {x.revision}
                                </p>
                                <p className="text-sm">
                                    Geografi: {x.geography ?? 'Tidak dibatasi'}{' '}
                                    · Horizon: {x.horizon}
                                </p>
                                <p className="text-sm break-words text-muted-foreground">
                                    {x.objective}
                                </p>
                                {x.ae16_filter && (
                                    <p className="text-xs break-words text-muted-foreground">
                                        Filter AE-16:{' '}
                                        {x.ae16_filter.date_window.start} s.d.
                                        sebelum{' '}
                                        {
                                            x.ae16_filter.date_window
                                                .end_exclusive
                                        }{' '}
                                        · {x.ae16_filter.geography} ·{' '}
                                        {x.ae16_filter.media_types.join(', ')} ·{' '}
                                        {x.ae16_filter.sentiment}
                                    </p>
                                )}
                                <div className="flex flex-wrap gap-4">
                                    <button
                                        className="underline"
                                        onClick={() => setSelected(x.id)}
                                    >
                                        Ubah {x.name}
                                    </button>
                                    <Link
                                        className="font-semibold underline"
                                        href={`/sifi/discovery-period?object_id=${x.id}`}
                                    >
                                        Pantau periode {x.name}
                                    </Link>
                                    <Link
                                        className="underline"
                                        href={`/sifi/monitoring-objects/${x.id}/discovery`}
                                    >
                                        Temukan Sumber {x.name}
                                    </Link>
                                    <Link
                                        className="font-semibold underline"
                                        href={`/sifi/monitoring-objects/${x.id}/research-multi#social-watch`}
                                    >
                                        Pantau media sosial
                                    </Link>
                                    <Link
                                        className="underline"
                                        href={`/sifi/monitoring-objects/${x.id}/research-multi`}
                                    >
                                        Penelusuran informasi {x.name}
                                    </Link>
                                    <Link
                                        className="underline"
                                        href={`/sifi/monitoring-objects/${x.id}/research`}
                                    >
                                        Rencanakan riset {x.name}
                                    </Link>
                                    <Link
                                        className="underline"
                                        href={`/sifi/workbench?object=${x.id}`}
                                    >
                                        Buka Workbench objek
                                    </Link>
                                </div>
                                <details className="text-sm">
                                    <summary className="cursor-pointer">
                                        Riwayat revisi {x.name}
                                    </summary>
                                    {x.audit.map((a) => (
                                        <p key={a.revision}>
                                            v{a.revision} · {a.action} ·{' '}
                                            {a.created_at}
                                        </p>
                                    ))}
                                </details>
                            </article>
                        ))}
                    </section>
                    <Editor
                        key={
                            o
                                ? `${o.id}-${o.revision}`
                                : 'new-' + objects.length + '-' + newRevision
                        }
                        object={o}
                        sources={sourceChoices}
                    />
                </div>
            </main>
        </>
    );
}
