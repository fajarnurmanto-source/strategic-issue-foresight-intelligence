import AcquisitionReceipts, {
    type AcquisitionWorkspace,
} from '@/components/sifi/acquisition-receipts';
import AppLayout from '@/layouts/app-layout';
import SourcePipelinePanel, {
    type Pipeline,
} from '@/components/sifi/source-pipeline-panel';
import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import {
    Database,
    Layers,
    MapPinned,
    ShieldAlert,
    Save,
    Plus,
    RefreshCw,
} from 'lucide-react';

type Source = {
    id: string;
    name: string;
    source_type: string;
    homepage_url: string | null;
    source_geography: string | null;
    notes: string | null;
    rights_state: string;
    collector_state: string;
    revision: number;
};
type Profile = {
    id: string;
    name: string;
    level: string;
    region_name: string;
    domains: string[];
    source_ids: string[];
    profile_state: string;
    collector_state: string;
    revision: number;
};
type Props = {
    sources: Source[];
    profiles: Profile[];
    scope: string;
    pipeline: Pipeline;
    acquisitionWorkspace?: AcquisitionWorkspace | null;
};
const kinds = ['OFFICIAL', 'MEDIA', 'RESEARCH', 'OPEN_DATA', 'OTHER'];
const levels = [
    { v: 'CITY', t: 'Kabupaten / Kota' },
    { v: 'PROVINCE', t: 'Provinsi' },
    { v: 'NATIONAL', t: 'Nasional' },
];
const control =
    'mt-1.5 w-full rounded-lg border bg-background px-3 py-2.5 text-sm';
const label = 'block text-sm font-medium';
const btn =
    'inline-flex items-center justify-center gap-2 rounded-lg bg-foreground px-4 py-2.5 text-sm font-semibold text-background disabled:opacity-50';

function SourceEditor({ item, done }: { item?: Source; done: () => void }) {
    const [name, setName] = useState(item?.name ?? '');
    const [type, setType] = useState(item?.source_type ?? 'OFFICIAL');
    const [url, setUrl] = useState(item?.homepage_url ?? '');
    const [geo, setGeo] = useState(item?.source_geography ?? '');
    const [notes, setNotes] = useState(item?.notes ?? '');
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    function save(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        setErrors({});
        const payload = {
            name,
            source_type: type,
            homepage_url: url || null,
            source_geography: geo || null,
            notes: notes || null,
            ...(item ? { revision: item.revision } : {}),
        };
        const opts = {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onError: (err: Record<string, string>) => setErrors(err),
            onSuccess: done,
        };
        if (item) {
            router.patch(
                `/sifi/source-catalog/sources/${item.id}`,
                payload,
                opts,
            );
        } else {
            router.post('/sifi/source-catalog/sources', payload, opts);
        }
    }
    return (
        <form onSubmit={save} className="space-y-4">
            <div className="flex items-center justify-between gap-2">
                <h3 className="font-semibold">
                    {item ? 'Ubah usulan sumber' : 'Tambahkan usulan sumber'}
                </h3>
                <span className="rounded-md border px-2 py-1 text-xs">
                    {item ? `Revisi ${item.revision}` : 'Draf baru'}
                </span>
            </div>
            <label className={label}>
                Nama sumber
                <input
                    required
                    minLength={3}
                    maxLength={180}
                    className={control}
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="Nama sumber atau penerbit"
                />
                {errors.name && (
                    <span className="text-xs text-destructive">
                        {errors.name}
                    </span>
                )}
            </label>
            <label className={label}>
                Jenis sumber
                <select
                    className={control}
                    value={type}
                    onChange={(e) => setType(e.target.value)}
                >
                    {kinds.map((k) => (
                        <option key={k} value={k}>
                            {k.replace('_', ' ')}
                        </option>
                    ))}
                </select>
            </label>
            <label className={label}>
                URL situs (hanya disimpan; tidak diakses)
                <input
                    className={control}
                    type="url"
                    maxLength={500}
                    value={url}
                    onChange={(e) => setUrl(e.target.value)}
                    placeholder="https://..."
                />
                {errors.homepage_url && (
                    <span className="text-xs text-destructive">
                        {errors.homepage_url}
                    </span>
                )}
            </label>
            <label className={label}>
                Geografi sumber (bukan geografi peristiwa)
                <input
                    className={control}
                    maxLength={180}
                    value={geo}
                    onChange={(e) => setGeo(e.target.value)}
                    placeholder="Contoh: Kota Yogyakarta"
                />
            </label>
            <label className={label}>
                Catatan hak akses / verifikasi
                <textarea
                    className={control}
                    maxLength={4000}
                    rows={3}
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    placeholder="Status izin, keterbatasan, sumber primer, hal yang perlu dicek"
                />
            </label>
            <p className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-xs">
                Rights: <b>UNASSESSED</b> · Collector: <b>OFF</b>. Tidak ada
                kontrol aktivasi pada halaman ini.
            </p>
            {errors.revision && (
                <p role="alert" className="text-sm text-destructive">
                    {errors.revision}
                </p>
            )}
            <button disabled={busy} className={btn}>
                <Save className="h-4 w-4" />
                {busy
                    ? 'Menyimpan…'
                    : item
                      ? 'Simpan revisi'
                      : 'Simpan draf sumber'}
            </button>
        </form>
    );
}

function ProfileEditor({
    item,
    sources,
    done,
}: {
    item?: Profile;
    sources: Source[];
    done: () => void;
}) {
    const [name, setName] = useState(item?.name ?? '');
    const [level, setLevel] = useState(item?.level ?? 'CITY');
    const [region, setRegion] = useState(item?.region_name ?? '');
    const [domains, setDomains] = useState(item?.domains.join(', ') ?? '');
    const [selected, setSelected] = useState<string[]>(item?.source_ids ?? []);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    function save(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        setErrors({});
        const domainList = [
            ...new Set(
                domains
                    .split(/[,\n]+/)
                    .map((s) => s.trim())
                    .filter(Boolean),
            ),
        ];
        const payload = {
            name,
            level,
            region_name: region,
            domains: domainList,
            source_ids: selected,
            ...(item ? { revision: item.revision } : {}),
        };
        const opts = {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onError: (err: Record<string, string>) => setErrors(err),
            onSuccess: done,
        };
        if (item) {
            router.patch(
                `/sifi/source-catalog/profiles/${item.id}`,
                payload,
                opts,
            );
        } else {
            router.post('/sifi/source-catalog/profiles', payload, opts);
        }
    }
    return (
        <form onSubmit={save} className="space-y-4">
            <div className="flex items-center justify-between gap-2">
                <h3 className="font-semibold">
                    {item ? 'Ubah Coverage Profile' : 'Buat Coverage Profile'}
                </h3>
                <span className="rounded-md border px-2 py-1 text-xs">
                    {item ? `Revisi ${item.revision}` : 'Draf baru'}
                </span>
            </div>
            <label className={label}>
                Nama profil
                <input
                    required
                    minLength={3}
                    maxLength={180}
                    className={control}
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="Misalnya: Pilot Yogyakarta"
                />
                {errors.name && (
                    <span className="text-xs text-destructive">
                        {errors.name}
                    </span>
                )}
            </label>
            <label className={label}>
                Tingkat geografi
                <select
                    className={control}
                    value={level}
                    onChange={(e) => setLevel(e.target.value)}
                >
                    {levels.map((l) => (
                        <option key={l.v} value={l.v}>
                            {l.t}
                        </option>
                    ))}
                </select>
            </label>
            <label className={label}>
                Wilayah relevansi
                <input
                    required
                    maxLength={180}
                    className={control}
                    value={region}
                    onChange={(e) => setRegion(e.target.value)}
                    placeholder="Kota Yogyakarta"
                />
                {errors.region_name && (
                    <span className="text-xs text-destructive">
                        {errors.region_name}
                    </span>
                )}
            </label>
            <label className={label}>
                Domain isu (pisahkan dengan koma)
                <textarea
                    rows={2}
                    required
                    className={control}
                    value={domains}
                    onChange={(e) => setDomains(e.target.value)}
                    placeholder="lingkungan, infrastruktur, kebijakan publik"
                />
                {errors.domains && (
                    <span className="text-xs text-destructive">
                        {errors.domains}
                    </span>
                )}
            </label>
            <div className="space-y-2">
                <p className="text-sm font-medium">
                    Usulan sumber yang dicakup (opsional; bukan hak aktivasi)
                </p>
                <div className="max-h-44 space-y-1 overflow-y-auto rounded-lg border p-3">
                    {sources.length === 0 ? (
                        <p className="text-xs text-muted-foreground">
                            Tambahkan usulan sumber lebih dulu.
                        </p>
                    ) : (
                        sources.map((s) => (
                            <label
                                key={s.id}
                                className="flex items-center gap-2 py-1 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    checked={selected.includes(s.id)}
                                    onChange={(e) =>
                                        setSelected((cur) =>
                                            e.target.checked
                                                ? [...cur, s.id]
                                                : cur.filter(
                                                      (id) => id !== s.id,
                                                  ),
                                        )
                                    }
                                />
                                <span>{s.name}</span>
                            </label>
                        ))
                    )}
                </div>
                {errors.source_ids && (
                    <span className="text-xs text-destructive">
                        {errors.source_ids}
                    </span>
                )}
            </div>
            <p className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-xs">
                Profil: <b>DRAFT</b> · Collector: <b>OFF</b>. Wilayah relevansi
                tidak membuktikan lokasi kejadian atau yurisdiksi kebijakan.
            </p>
            {errors.revision && (
                <p role="alert" className="text-sm text-destructive">
                    {errors.revision}
                </p>
            )}
            <button disabled={busy} className={btn}>
                <Save className="h-4 w-4" />
                {busy
                    ? 'Menyimpan…'
                    : item
                      ? 'Simpan revisi'
                      : 'Simpan draf profil'}
            </button>
        </form>
    );
}

export default function SourceCatalog({
    sources,
    profiles,
    pipeline,
    acquisitionWorkspace,
}: Props) {
    const [sourceId, setSourceId] = useState<string | null>(null);
    const [profileId, setProfileId] = useState<string | null>(null);
    const activeSource = sources.find((s) => s.id === sourceId);
    const activeProfile = profiles.find((p) => p.id === profileId);
    return (
        <AppLayout
            breadcrumbs={[
                {
                    title: 'SIFI — Source & Coverage',
                    href: '/sifi/source-catalog',
                },
            ]}
        >
            <Head title="SIFI · Source & Coverage" />
            <main className="min-h-screen bg-muted/20 px-4 py-7 lg:px-8">
                <div className="mx-auto max-w-[1500px] space-y-5">
                    <header>
                        <p className="text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                            SIFI / Configuration Workspace
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                            Source Registry & Coverage
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Katalog usulan sumber dan cakupan isu yang
                            dikonfigurasi analis. Pengelolaan draf di Laravel;
                            otoritas provenance dan rights tetap terpisah.
                        </p>
                    </header>
                    <div
                        role="alert"
                        className="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm"
                    >
                        <ShieldAlert className="h-5 w-5 shrink-0 text-amber-600" />
                        <p>
                            <b>
                                {pipeline.scenario === 'wb_acquisition'
                                    ? 'CONTROLLED ONE-SHOT · COLLECTOR OFF.'
                                    : 'LOCAL DRAFT ONLY · NO LIVE ACQUISITION.'}
                            </b>{' '}
                            Sumber di sini belum menjadi Source Registry Python
                            yang berkualifikasi, tidak memperoleh izin
                            penggunaan, tidak mengaktifkan RSS atau kolektor,
                            dan tidak menghasilkan admitted evidence.
                        </p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                        {[
                            {
                                t: 'Usulan sumber',
                                n: sources.length,
                                icon: Database,
                            },
                            {
                                t: 'Coverage Profiles',
                                n: profiles.length,
                                icon: MapPinned,
                            },
                            { t: 'Collector aktif', n: 0, icon: Layers },
                        ].map((x) => (
                            <div
                                key={x.t}
                                className="rounded-xl border bg-card p-4"
                            >
                                <x.icon className="mb-3 h-5 w-5 text-muted-foreground" />
                                <p className="text-sm text-muted-foreground">
                                    {x.t}
                                </p>
                                <p className="text-2xl font-semibold">{x.n}</p>
                            </div>
                        ))}
                    </div>
                    {acquisitionWorkspace && (
                        <AcquisitionReceipts workspace={acquisitionWorkspace} />
                    )}
                    <SourcePipelinePanel
                        key={pipeline.scenario + pipeline.packet_hash}
                        pipeline={pipeline}
                        sources={sources}
                    />
                    <div className="grid gap-5 xl:grid-cols-2">
                        <section className="rounded-xl border bg-card p-5 shadow-sm">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="flex items-center gap-2 text-lg font-semibold">
                                    <Database className="h-5 w-5" /> Usulan
                                    sumber
                                </h2>
                                <button
                                    onClick={() => setSourceId(null)}
                                    className="inline-flex items-center gap-1 rounded-lg border px-3 py-2 text-xs font-medium"
                                >
                                    <Plus className="h-4 w-4" /> Baru
                                </button>
                            </div>
                            <div className="mb-5 max-h-48 overflow-auto rounded-lg border">
                                {sources.length === 0 ? (
                                    <p className="p-4 text-sm text-muted-foreground">
                                        Belum ada draf sumber.
                                    </p>
                                ) : (
                                    sources.map((s) => (
                                        <button
                                            key={s.id}
                                            type="button"
                                            onClick={() => setSourceId(s.id)}
                                            className={`flex w-full items-center justify-between border-b px-3 py-3 text-left text-sm last:border-b-0 hover:bg-muted ${s.id === sourceId ? 'bg-muted' : ''}`}
                                        >
                                            <span>
                                                <strong>{s.name}</strong>
                                                <small className="mt-1 block text-muted-foreground">
                                                    {s.source_type} ·{' '}
                                                    {s.rights_state} · Revisi{' '}
                                                    {s.revision}
                                                </small>
                                            </span>
                                            <span className="text-xs">
                                                Ubah →
                                            </span>
                                        </button>
                                    ))
                                )}
                            </div>
                            <SourceEditor
                                key={
                                    sourceId ??
                                    `create-source-${sources.length}`
                                }
                                item={activeSource}
                                done={() => setSourceId(null)}
                            />
                        </section>
                        <section className="rounded-xl border bg-card p-5 shadow-sm">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="flex items-center gap-2 text-lg font-semibold">
                                    <MapPinned className="h-5 w-5" /> Coverage
                                    Profiles
                                </h2>
                                <button
                                    onClick={() => setProfileId(null)}
                                    className="inline-flex items-center gap-1 rounded-lg border px-3 py-2 text-xs font-medium"
                                >
                                    <Plus className="h-4 w-4" /> Baru
                                </button>
                            </div>
                            <div className="mb-5 max-h-48 overflow-auto rounded-lg border">
                                {profiles.length === 0 ? (
                                    <p className="p-4 text-sm text-muted-foreground">
                                        Belum ada draf profil.
                                    </p>
                                ) : (
                                    profiles.map((p) => (
                                        <button
                                            key={p.id}
                                            type="button"
                                            onClick={() => setProfileId(p.id)}
                                            className={`flex w-full items-center justify-between border-b px-3 py-3 text-left text-sm last:border-b-0 hover:bg-muted ${p.id === profileId ? 'bg-muted' : ''}`}
                                        >
                                            <span>
                                                <strong>{p.name}</strong>
                                                <small className="mt-1 block text-muted-foreground">
                                                    {p.level}: {p.region_name} ·
                                                    Revisi {p.revision} ·{' '}
                                                    {p.source_ids.length} sumber
                                                    draf
                                                </small>
                                            </span>
                                            <span className="text-xs">
                                                Ubah →
                                            </span>
                                        </button>
                                    ))
                                )}
                            </div>
                            <ProfileEditor
                                key={
                                    profileId ??
                                    `create-profile-${profiles.length}`
                                }
                                item={activeProfile}
                                sources={sources}
                                done={() => setProfileId(null)}
                            />
                        </section>
                    </div>
                    <footer className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                        <span>
                            <RefreshCw className="mr-1 inline h-3.5 w-3.5" />{' '}
                            Simpan manual · revisi dilindungi · audit
                            append-only. Python API dan canonical SIFI tidak
                            dimodifikasi.
                        </span>
                        <Link
                            href="/sifi/signals"
                            className="font-semibold underline"
                        >
                            Kembali ke Signal Explorer
                        </Link>
                    </footer>
                </div>
            </main>
        </AppLayout>
    );
}
