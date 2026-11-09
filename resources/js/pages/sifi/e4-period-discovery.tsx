import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { ArrowRight, CalendarRange, DatabaseZap, Link2, Search, ShieldAlert } from 'lucide-react';

type ObjectRow = { id: string; name: string; revision: number; state: string; keywords: string[] };
type SourceRow = {
    id: string; class: string; platform: string; state: string; coverage: string; dispatchable: boolean;
};
type Plan = {
    schema: string; object_id: string; object_name: string; object_revision: number;
    keyword_count: number; keyword_scope_sha256: string; query: string;
    start_date: string; end_date: string; sources: SourceRow[];
    eligible_dispatch_count: number; live_enabled: boolean; source_coverage: string;
};
type ResultSource = SourceRow & { result_state: string; session_id: string | null; result_count: number; error_code?: string | null };
type Item = {
    provider: string; source_class: string; platform: string; title: string;
    url: string; published_at: string; rights_state: string; session_id: string;
};
type Report = {
    object_id: string; start_date: string; end_date: string; result_state: string;
    sources: ResultSource[]; items: Item[]; coverage: string;
    evidence_admission: string; production_admission: string;
};
type Props = { objects: ObjectRow[]; plan: Plan | null; report: Report | null; live_enabled: boolean; production_admission: string };

const inputClass = 'mt-1 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm';
const statusLabel: Record<string, string> = {
    ELIGIBLE: 'Izin sumber memenuhi syarat', ELIGIBLE_LATEST_ONLY: 'Hanya feed terbaru',
    PERMISSION_DISABLED: 'Belum diizinkan', HISTORICAL_COVERAGE_UNAVAILABLE: 'Arsip tanggal ini tidak tersedia',
    PERIOD_BACKFILL_NOT_IN_E4: 'Belum tersedia untuk backfill', HISTORICAL_COVERAGE_NOT_ESTABLISHED: 'Belum ada cakupan historis',
    ATTENTION_ONLY_NO_HISTORIC_POSTS: 'Sinyal perhatian saja', NO_REVIEWED_PERIOD_ADAPTER: 'Belum ada adapter periode',
};
const today = () => new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit', timeZone: 'Asia/Jakarta' }).format(new Date());

export default function E4PeriodDiscovery({ objects, plan, report, live_enabled }: Props) {
    const params = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search);
    const [objectId, setObjectId] = useState(plan?.object_id ?? params.get('object_id') ?? '');
    const [startDate, setStartDate] = useState(plan?.start_date ?? params.get('start_date') ?? '');
    const [endDate, setEndDate] = useState(plan?.end_date ?? params.get('end_date') ?? today());
    const [consent, setConsent] = useState(false);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const selected = objects.find((o) => o.id === objectId);
    const matchesPlan = !!plan && !!selected && plan.object_id === selected.id
        && plan.object_revision === selected.revision && plan.start_date === startDate && plan.end_date === endDate;
    const canCollect = matchesPlan && !!plan?.live_enabled && (plan?.eligible_dispatch_count ?? 0) > 0 && consent && !busy;
    const keywords = selected?.keywords?.filter((k) => typeof k === 'string' && k.trim().length > 0) ?? [];

    function prepare(event: FormEvent) {
        event.preventDefault();
        setErrors({});
        if (!selected || keywords.length === 0) { setErrors({ object_id: 'Registrasikan objek dengan sedikitnya satu keyword terlebih dahulu.' }); return; }
        router.get('/sifi/discovery-period', { object_id: objectId, start_date: startDate, end_date: endDate }, {
            preserveState: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onError: setErrors,
        });
    }
    function collect() {
        if (!canCollect || !plan) return;
        setErrors({});
        router.post('/sifi/discovery-period/collect', {
            object_id: objectId, object_revision: plan.object_revision,
            start_date: startDate, end_date: endDate,
            consent: true, submission_key: crypto.randomUUID(),
        }, {
            preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onError: setErrors,
        });
    }

    return <AppLayout>
        <Head title="SIFI · Penelusuran Objek dan Periode (E4)" />
        <main className="mx-auto max-w-6xl space-y-6 p-4 pb-12 md:p-8">
            <div>
                <p className="text-xs font-bold uppercase tracking-widest text-muted-foreground">SIFI / E4 / Discovery</p>
                <h1 className="mt-2 text-3xl font-semibold tracking-tight">Telusuri informasi berdasarkan objek dan periode</h1>
                <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                    Daftarkan objek beserta keyword, pilih tanggal publikasi, lalu SIFI menunjukkan cakupan penyedia yang benar-benar tersedia.
                    Penelusuran ini hanya mengambil metadata dari sumber yang sudah mendapat izin khusus.
                </p>
            </div>

            <section className="grid gap-4 lg:grid-cols-2">
                <form onSubmit={prepare} className="space-y-4 rounded-xl border bg-card p-5">
                    <div className="flex items-center gap-2"><Search className="size-5 text-primary" /><h2 className="text-lg font-semibold">1 · Objek pemantauan</h2></div>
                    <label className="block text-sm font-medium">Objek yang telah diregistrasi
                        <select className={inputClass} value={objectId} onChange={(e) => { setObjectId(e.target.value); setConsent(false); }} required>
                            <option value="">Pilih objek...</option>
                            {objects.map((o) => <option key={o.id} value={o.id}>{o.name} · v{o.revision} · {o.state}</option>)}
                        </select>
                    </label>
                    <div className="rounded-lg bg-muted/60 p-3 text-sm">
                        <p className="font-medium">Keyword terdaftar</p>
                        {keywords.length > 0
                            ? <p className="mt-1 break-words text-muted-foreground">{keywords.join(' · ')}</p>
                            : <p className="mt-1 text-amber-700">Belum ada objek dengan keyword yang dipilih.</p>}
                    </div>
                    <Link href="/sifi/monitoring-objects" className="inline-flex items-center gap-1 text-sm font-medium text-primary underline">
                        Registrasi / ubah keyword objek <ArrowRight className="size-4" />
                    </Link>
                    <div className="flex items-center gap-2"><CalendarRange className="size-5 text-primary" /><h2 className="text-lg font-semibold">2 · Periode pemantauan</h2></div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="block text-sm font-medium">Mulai (inklusif)
                            <input className={inputClass} type="date" max={today()} value={startDate} onChange={(e) => setStartDate(e.target.value)} required />
                        </label>
                        <label className="block text-sm font-medium">Sampai (inklusif)
                            <input className={inputClass} type="date" min={startDate} max={today()} value={endDate} onChange={(e) => setEndDate(e.target.value)} required />
                        </label>
                    </div>
                    <p className="text-xs text-muted-foreground">Maksimal 366 hari inklusif; tanggal publikasi, bukan tanggal SIFI mengambil data. Sumber mungkin tidak memiliki arsip untuk seluruh rentang.</p>
                    {(errors.object_id || errors.start_date || errors.end_date) && <p role="alert" className="text-sm text-destructive">{errors.object_id || errors.start_date || errors.end_date}</p>}
                    <button type="submit" disabled={busy || !selected || keywords.length === 0}
                        className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50">
                        Periksa cakupan sumber
                    </button>
                </form>

                <section className="space-y-3 rounded-xl border bg-card p-5">
                    <div className="flex items-center gap-2"><DatabaseZap className="size-5 text-primary" /><h2 className="text-lg font-semibold">3 · Ketersediaan lintas sumber</h2></div>
                    {!matchesPlan || !plan
                        ? <p className="text-sm text-muted-foreground">Pilih objek dan periode, lalu klik “Periksa cakupan sumber”. Belum ada koneksi eksternal yang dijalankan.</p>
                        : <>
                            <p className="text-sm">Objek <strong>{plan.object_name}</strong>, v{plan.object_revision}, {plan.start_date} sampai {plan.end_date}.</p>
                            <p className="text-sm text-muted-foreground">Istilah pencarian: {plan.query}</p>
                            <div className="max-h-[430px] space-y-2 overflow-auto">
                                {plan.sources.map((s) => <div key={s.id} className="rounded-lg border p-3 text-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-2"><strong>{s.platform}</strong>
                                        <span className={s.dispatchable ? 'text-emerald-700' : 'text-amber-700'}>{statusLabel[s.state] ?? s.state}</span>
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">{s.class} · {s.coverage}</p>
                                </div>)}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {plan.eligible_dispatch_count} sumber siap untuk satu permintaan terbatas pada lingkungan validasi terisolasi.
                            </p>
                            <label className="flex items-start gap-2 text-sm">
                                <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)}
                                    className="mt-1 size-4" />
                                <span>Saya menyetujui hingga satu permintaan per sumber yang memenuhi izin, dengan pembatasan kuota dan metadata saja.</span>
                            </label>
                            {!live_enabled && <p className="rounded-lg bg-amber-50 p-2 text-sm text-amber-900">
                                Akuisisi live E4 dinonaktifkan. Aktifkan hanya pada SQLite validasi terisolasi dan izin provider yang berlaku.</p>}
                            {errors.source && <p role="alert" className="text-sm text-destructive">{errors.source}</p>}
                            <button disabled={!canCollect} onClick={collect} type="button"
                                className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50">
                                {busy ? 'Memproses...' : 'Jalankan akuisisi yang diizinkan'}
                            </button>
                        </>}
                </section>
            </section>

            <section className="rounded-xl border bg-card p-5">
                <h2 className="flex items-center gap-2 text-lg font-semibold"><Link2 className="size-5 text-primary" />Hasil metadata dan asal platform</h2>
                {!report || report.object_id !== objectId || report.start_date !== startDate || report.end_date !== endDate
                    ? <p className="mt-3 text-sm text-muted-foreground">Belum ada hasil untuk pilihan objek dan periode saat ini. Cakupan sumber yang tidak tersedia tetap ditampilkan di atas.</p>
                    : <>
                        <p className="mt-2 text-sm font-medium">{report.result_state}</p>
                        <p className="mt-1 text-sm text-muted-foreground">{report.coverage}</p>
                        <div className="mt-4 grid gap-2 md:grid-cols-2">
                            {report.sources.map((s) => <div key={s.id} className="rounded-lg border p-3 text-sm">
                                <strong>{s.platform}</strong> · {s.result_state} · {s.result_count} hasil
                                {s.error_code && <p className="text-xs text-amber-700">{s.error_code}</p>}
                                {s.session_id && <Link className="block text-xs text-primary underline"
                                    href={'/sifi/monitoring-objects/' + objectId + '/research-multi?research=' + s.session_id}>
                                    Riwayat riset / receipt
                                </Link>}
                            </div>)}
                        </div>
                        {report.items.length > 0 && <ul className="mt-4 space-y-2">{report.items.map((item, i) =>
                            <li key={i} className="rounded-lg border p-3">
                                <p className="text-xs text-muted-foreground">{item.source_class} / {item.platform} · {item.published_at} · {item.rights_state}</p>
                                <a href={item.url} target="_blank" rel="noopener noreferrer" className="font-medium text-primary underline">{item.title || item.url}</a>
                            </li>)}</ul>}
                    </>}
            </section>
            <aside className="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                <ShieldAlert className="mt-0.5 size-5 shrink-0" />
                <p>Ini discovery berbasis objek dan periode (E4), bukan bukti kejadian, pengukuran prevalensi, atau jaminan seluruh platform tercakup.
                    Sumber historis terbatas atau tidak berizin akan dilabeli eksplisit. Ekstraksi mendalam, hierarki isu, dan analisis tren lintas waktu merupakan tahap berikutnya sesuai roadmap. Produksi: DENIED.</p>
            </aside>
            <Link href="/sifi/product-roadmap" className="inline-flex text-sm font-medium text-primary underline">Lihat roadmap pengembangan selanjutnya</Link>
        </main>
    </AppLayout>;
}
