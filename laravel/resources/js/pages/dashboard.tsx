import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import {
    Activity, ArrowRight, BookOpen, ClipboardList, FileQuestion, FileText,
    GitBranch, LibraryBig, LockKeyhole, Radar, ScanSearch, ShieldAlert,
    ShieldCheck, Telescope, Workflow,
} from 'lucide-react';

type Metrics = {
    investigations: number;
    candidates: number;
    evidence_drafts: number;
    review_requested: number;
    source_proposals: number;
    coverage_profiles: number;
    brief_drafts: number;
};
type CaseRow = {
    id: string;
    title: string;
    review_state: string;
    updated_at: string | null;
    has_case: boolean;
    has_trajectory: boolean;
    has_foresight: boolean;
    has_brief: boolean;
};
type Props = {
    metrics: Metrics;
    cases: CaseRow[];
    mode: 'OFFLINE_FIXTURE_ONLY';
    evidence_state: 'NOT_EVALUATED';
    production_authorized: false;
};
const panel = 'rounded-2xl border border-border bg-card p-5 shadow-sm';
const metricsDefinition = [
    { key: 'candidates', label: 'Kandidat isu', caption: 'Usulan analis, belum verified', icon: FileQuestion, href: '/sifi/issue-candidates' },
    { key: 'investigations', label: 'Investigasi', caption: 'Berkas kerja milik Anda', icon: ClipboardList, href: '/sifi/investigations' },
    { key: 'evidence_drafts', label: 'Draf bukti', caption: 'Belum admitted', icon: ShieldCheck, href: '/sifi/evidence-reviews' },
    { key: 'review_requested', label: 'Menunggu telaah', caption: 'Kandidat dengan review requested', icon: ShieldAlert, href: '/sifi/issue-candidates' },
] as const;

export default function Dashboard({ metrics, cases, mode, evidence_state, production_authorized }: Props) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }]}>
            <Head title="SIFI | Intelligence Dashboard" />
            <main className="min-h-screen bg-background px-4 py-7 lg:px-8">
                <div className="mx-auto max-w-[1480px] space-y-6">
                    <section className="rounded-2xl border border-slate-800 bg-slate-950 px-6 py-7 text-white shadow-sm dark:border-slate-700 sm:px-8">
                        <div className="flex flex-wrap items-start justify-between gap-5">
                            <div>
                                <p className="flex items-center gap-2 text-xs font-bold uppercase tracking-[.17em] text-teal-300"><Radar className="size-4" /> SIFI / INTELLIGENCE OVERVIEW</p>
                                <h1 className="mt-3 text-3xl font-semibold tracking-tight">Intelligence Dashboard</h1>
                                <p className="mt-2 max-w-2xl text-sm leading-relaxed text-slate-300">Ringkasan pekerjaan intelijen dari database Laravel lokal. Semua angka bersumber dari catatan akun Anda, bukan ukuran intensitas pemberitaan nyata.</p>
                            </div>
                            <Link href="/sifi/workbench" className="inline-flex items-center gap-2 rounded-lg bg-teal-400 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-teal-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-300"><Workflow className="size-4" /> Buka Workbench <ArrowRight className="size-4" /></Link>
                        </div>
                        <div className="mt-6 flex flex-wrap gap-2 text-xs">
                            <span className="rounded-md border border-teal-600/60 bg-teal-500/10 px-3 py-1.5 text-teal-100">Mode: {mode}</span>
                            <span className="rounded-md border border-slate-600 px-3 py-1.5 text-slate-200">Evidence: {evidence_state}</span>
                            <span className="rounded-md border border-slate-600 px-3 py-1.5 text-slate-200">Production: {production_authorized ? 'AUTHORIZED' : 'NOT AUTHORIZED'}</span>
                        </div>
                    </section>

                    <section aria-label="Ringkasan kerja" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {metricsDefinition.map(({ key, label, caption, icon: Icon, href }) => (
                            <Link key={key} href={href} className={`${panel} group block transition-colors hover:border-teal-500/40 focus-visible:outline-2 focus-visible:outline-offset-2`}>
                                <div className="flex items-center justify-between text-muted-foreground"><span className="text-sm font-medium">{label}</span><Icon className="size-5" /></div>
                                <div className="mt-3 text-3xl font-semibold tabular-nums tracking-tight">{metrics[key]}</div>
                                <div className="mt-1 flex items-center justify-between text-xs text-muted-foreground"><span>{caption}</span><ArrowRight className="size-4 opacity-0 transition-opacity group-hover:opacity-100" /></div>
                            </Link>
                        ))}
                    </section>

                    <div className="grid gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(300px,1fr)]">
                        <section className={`${panel} min-w-0 space-y-4`} aria-label="Alur isu terkini">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div><h2 className="text-lg font-semibold">Alur isu terkini</h2><p className="mt-1 text-sm text-muted-foreground">Status penyusunan dan ketersediaan draf; bukan urutan risiko.</p></div>
                                <Link href="/sifi/issue-candidates" className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">Semua kandidat <ArrowRight className="size-4" /></Link>
                            </div>
                            {cases.length === 0 && (
                                <div className="rounded-xl border border-dashed p-7 text-center">
                                    <BookOpen className="mx-auto size-8 text-muted-foreground" />
                                    <p className="mt-2 font-semibold">Belum ada kandidat isu</p>
                                    <p className="mt-1 text-sm text-muted-foreground">Mulai dari Signal Explorer atau buat berkas investigasi.</p>
                                </div>
                            )}
                            <div className="space-y-3">
                                {cases.map((c) => {
                                    const root = `/sifi/issue-candidates/${c.id}`;
                                    const stages = [
                                        { name: 'Kandidat', active: true },
                                        { name: 'Issue Case', active: c.has_case },
                                        { name: 'Trajectory', active: c.has_trajectory },
                                        { name: 'Foresight', active: c.has_foresight },
                                        { name: 'Brief', active: c.has_brief },
                                    ];
                                    return <article key={c.id} className="rounded-xl border border-border p-4">
                                        <div className="flex items-start justify-between gap-3"><div className="min-w-0"><Link href={root} className="font-semibold hover:text-primary hover:underline">{c.title}</Link><p className="mt-1 text-xs text-muted-foreground">Status kerja: {c.review_state} · Bukan Issue Object terverifikasi</p></div><ArrowRight className="mt-1 size-4 shrink-0 text-muted-foreground" /></div>
                                        <div className="mt-4 flex flex-wrap gap-1.5">
                                            {stages.map((s) => <span key={s.name} className={`rounded-md border px-2 py-1 text-[11px] font-medium ${s.active ? 'border-teal-600/30 bg-teal-500/10 text-foreground' : 'border-dashed text-muted-foreground'}`}>{s.name}: {s.active ? 'Draf tersedia' : 'Belum ada'}</span>)}
                                        </div>
                                    </article>;
                                })}
                            </div>
                        </section>
                        <div className="min-w-0 space-y-4">
                            <section className={`${panel} space-y-4`} aria-label="Kualitas data">
                                <h2 className="flex items-center gap-2 text-lg font-semibold"><LockKeyhole className="size-5" /> Data & evidence readiness</h2>
                                <div className="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm leading-relaxed"><b>Belum layak disimpulkan.</b> Kegiatan lokal ini menggunakan fixture dan draf. Evidence admission serta verifikasi klaim tidak dilakukan oleh Dashboard.</div>
                                <div className="grid grid-cols-2 gap-3 text-sm">
                                    <div className="rounded-lg bg-muted/60 p-3"><p className="text-xs text-muted-foreground">Usulan sumber</p><p className="mt-1 text-2xl font-semibold tabular-nums">{metrics.source_proposals}</p></div>
                                    <div className="rounded-lg bg-muted/60 p-3"><p className="text-xs text-muted-foreground">Coverage profiles</p><p className="mt-1 text-2xl font-semibold tabular-nums">{metrics.coverage_profiles}</p></div>
                                </div>
                                <Link href="/sifi/source-catalog" className="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"><LibraryBig className="size-4" /> Kelola Source & Coverage <ArrowRight className="size-4" /></Link>
                            </section>
                            <section className={`${panel} space-y-3`} aria-label="Kesiapan trajectory">
                                <h2 className="flex items-center gap-2 text-lg font-semibold"><Activity className="size-5" /> Trajectory & foresight watch</h2>
                                <p className="text-sm leading-relaxed text-muted-foreground">Belum tersedia deret waktu terverifikasi untuk menampilkan tren, risiko terukur, atau probabilitas foresight. Tidak ada grafik yang dibentuk dari revisi draf atau fixture.</p>
                                <Link href="/sifi/workbench" className="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"><Telescope className="size-4" /> Lihat hipotesis dan skenario <ArrowRight className="size-4" /></Link>
                            </section>
                            <section className={`${panel} flex items-center justify-between gap-3`}>
                                <div><p className="text-sm font-semibold">Executive briefing drafts</p><p className="mt-1 text-xs text-muted-foreground">Dokumen internal yang belum disahkan</p></div><span className="text-2xl font-semibold tabular-nums">{metrics.brief_drafts}</span>
                            </section>
                        </div>
                    </div>
                    <p className="text-xs text-muted-foreground">SIFI Local · Dashboard read-only · Tidak mengambil data eksternal, memodifikasi workflow, atau menetapkan evidence.</p>
                </div>
            </main>
        </AppLayout>
    );
}
