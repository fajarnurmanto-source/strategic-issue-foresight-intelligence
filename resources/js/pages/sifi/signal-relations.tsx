import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Activity, ArrowRight, CircleAlert, Fingerprint, GitCompareArrows, ShieldAlert } from 'lucide-react';

type Signal = { signal_id: string; source_id: string; title: string; signal_version: number };
type Edge = {
    left_signal_id: string; right_signal_id: string;
    left_source_id: string; right_source_id: string;
    relation: 'SAME_CONTENT_FINGERPRINT' | 'SAME_TITLE_TOKENS' | 'HIGH_TITLE_OVERLAP';
    same_source_id: boolean; independence_verified: false; corroboration_verified: false;
};
type Projection = {
    schema_version: string;
    status: 'BOUNDED_COMPLETE' | 'BOUNDED_PARTIAL';
    total_in_projection: number; analyzed_signals: number; distinct_source_ids: number;
    relationships: Edge[]; exact_fingerprint_groups: string[][]; signals: Signal[];
    human_checks: string[];
};

type Props = { apiStatus: string; projection: Projection | null };
const label: Record<Edge['relation'], string> = {
    SAME_CONTENT_FINGERPRINT: 'Fingerprint konten sama',
    SAME_TITLE_TOKENS: 'Token judul sama',
    HIGH_TITLE_OVERLAP: 'Kemiripan judul tinggi',
};

function MiniStat({ name, value }: { name: string; value: number | string }) {
    return <div className="rounded-xl border bg-card p-4 shadow-sm"><p className="text-xs text-muted-foreground">{name}</p>
        <p className="mt-2 text-2xl font-semibold tabular-nums">{value}</p></div>;
}

export default function SignalRelations({ apiStatus, projection }: Props) {
    const ready = apiStatus === 'OFFLINE_FIXTURE_ONLY' && projection !== null;
    const byId = new Map(projection?.signals.map(s => [s.signal_id, s]) ?? []);
    return <AppLayout breadcrumbs={[{ title: 'SIFI — Signal Relations', href: '/sifi/signal-relations' }]}>
        <Head title="SIFI · Signal Relationship Intelligence" />
        <div className="min-h-full space-y-6 bg-background p-5 text-foreground md:p-8">
            <header className="flex flex-wrap items-start justify-between gap-4">
                <div><p className="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase">SIFI / PROVENANCE INTELLIGENCE</p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight">Signal Relationship Intelligence</h1>
                    <p className="mt-2 max-w-3xl text-sm text-muted-foreground">Pengelompokan sinyal yang mungkin mengulang konten. Bukan verifikasi klaim, dukungan independen, atau pengukuran prevalensi.</p>
                </div>
                <Link className="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-muted" href="/sifi/signals">Signal Explorer <ArrowRight className="h-4 w-4" /></Link>
            </header>
            <div className="flex gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="status">
                <ShieldAlert className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div><strong>OFFLINE FIXTURE ONLY · INDEPENDENCE NOT VERIFIED.</strong><p className="mt-1 text-muted-foreground">Kesamaan judul atau fingerprint hanya petunjuk penelusuran. Tidak ada evidence admitted, Issue Object, atau akuisisi nyata.</p></div>
            </div>
            {!ready ? <section className="rounded-xl border p-6" role="alert"><div className="flex items-center gap-2 font-medium"><CircleAlert className="h-5 w-5"/>Analisis belum dapat ditampilkan</div>
                <p className="mt-2 text-sm text-muted-foreground">Status Python: {apiStatus}. Periksa layanan lokal SIFI; tidak ada hasil yang direkayasa saat API tidak tersedia.</p></section>
            : <>
                {projection.status === 'BOUNDED_PARTIAL' && <p className="rounded-lg border border-amber-500/40 p-3 text-sm" role="alert">Analisis terbatas pada 100 sinyal; jangan menganggapnya statistik seluruh database.</p>}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <MiniStat name="Sinyal diperiksa" value={projection.analyzed_signals} />
                    <MiniStat name="Identitas sumber teramati" value={projection.distinct_source_ids} />
                    <MiniStat name="Pasangan perlu ditinjau" value={projection.relationships.length} />
                    <MiniStat name="Grup fingerprint berulang" value={projection.exact_fingerprint_groups.length} />
                </div>
                <section className="rounded-xl border bg-card shadow-sm">
                    <div className="flex items-center gap-2 border-b px-5 py-4"><GitCompareArrows className="h-4 w-4"/><h2 className="text-lg font-semibold">Hubungan publikasi yang teramati</h2></div>
                    {projection.relationships.length === 0 ? <p className="p-6 text-sm text-muted-foreground">Tidak ditemukan kesamaan menurut aturan terbatas yang digunakan. Ini tidak membuktikan sumber independen.</p>
                    : <div className="divide-y">{projection.relationships.map((edge, i) => <article className="space-y-3 p-5" key={`${edge.left_signal_id}:${edge.right_signal_id}:${i}`}>
                        <div className="flex flex-wrap items-center gap-2 text-xs"><span className="rounded-md border px-2 py-1 font-medium">{label[edge.relation]}</span>
                            <span className="text-muted-foreground">{edge.same_source_id ? 'Satu ID sumber' : 'Dua ID sumber (independensi belum terbukti)'}</span></div>
                        <div className="grid gap-3 md:grid-cols-2">{([edge.left_signal_id, edge.right_signal_id] as const).map(id => <div key={id} className="rounded-lg bg-muted/50 p-3">
                            <div className="break-words font-medium">{byId.get(id)?.title ?? 'Judul tidak tersedia'}</div>
                            <div className="mt-1 text-xs text-muted-foreground">{byId.get(id)?.source_id} · versi {byId.get(id)?.signal_version}</div>
                            <div className="mt-1 break-all font-mono text-[10px] text-muted-foreground">{id}</div>
                        </div>)}</div>
                        <p className="text-xs text-amber-700 dark:text-amber-300">Perlu pemeriksaan asal publikasi. Hubungan ini bukan corroboration atau bukti kebenaran.</p>
                    </article>)}</div>}
                </section>
                <section className="rounded-xl border bg-card p-5 shadow-sm"><div className="mb-3 flex items-center gap-2"><Fingerprint className="h-4 w-4"/><h2 className="font-semibold">Prosedur pemeriksaan analis</h2></div>
                    <ol className="list-decimal space-y-2 pl-5 text-sm text-muted-foreground">{projection.human_checks.map((x,i)=><li key={i}>{x}</li>)}</ol>
                </section>
                <p className="flex items-center gap-2 text-xs text-muted-foreground"><Activity className="h-4 w-4"/>Analisis deterministik Python · read-only · {projection.total_in_projection} total sinyal fixture · Tidak ada keputusan otomatis.</p>
            </>}
        </div>
    </AppLayout>;
}
