import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { AlertCircle, ArrowRight, ClipboardList, LockKeyhole, ShieldAlert, Waypoints } from 'lucide-react';

type Investigation = {
    id: string;
    title: string;
    review_state: string;
    revision: number;
    updated_at: string;
    claim_hypothesis: string | null;
};

type Props = { investigations: Investigation[]; totalCount: number; scope: string };

const stateNames: Record<string, string> = {
    DRAFT: 'Draf', REVIEW_REQUESTED: 'Menunggu telaah', ON_HOLD: 'Ditunda',
};

function formatDate(value: string) {
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? 'Tanggal tidak tersedia' : d.toLocaleString('id-ID', {
        dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta',
    }) + ' WIB';
}

export default function InvestigationsIndex({ investigations, totalCount }: Props) {
    return <AppLayout breadcrumbs={[{ title: 'SIFI — Investigations', href: '/sifi/investigations' }]}>
        <Head title="SIFI · Investigations" />
        <div className="min-h-screen bg-muted/20 px-4 py-7 lg:px-8">
            <div className="mx-auto max-w-6xl space-y-5">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="flex items-center gap-2 text-xs font-semibold tracking-widest text-muted-foreground uppercase"><Waypoints className="h-4 w-4" /> SIFI / Analyst workspace</p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight">Investigations</h1>
                        <p className="mt-2 text-sm text-muted-foreground">Berkas investigasi berbasis publikasi, draf analis, dan jejak revisi. Bukan Issue Object yang telah diverifikasi.</p>
                    </div>
                    <Link href="/sifi/signals" className="rounded-lg bg-foreground px-4 py-2.5 text-sm font-semibold text-background hover:opacity-90">Buka Signal Explorer →</Link>
                </header>
                <div className="flex gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="alert">
                    <ShieldAlert className="h-5 w-5 shrink-0 text-amber-600" />
                    <div><p className="font-semibold">OFFLINE_FIXTURE_ONLY · Konstruksi isu otomatis nonaktif</p>
                        <p className="mt-1 text-xs text-muted-foreground">Penilaian manusia disimpan di Laravel untuk pengguna yang sedang masuk. Tidak ada pengakuan bukti, perubahan klasifikasi Z1/Z2, atau keputusan kebijakan.</p></div>
                </div>
                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-xl border bg-card p-4"><ClipboardList className="mb-2 h-5 w-5 text-muted-foreground" /><p className="text-3xl font-semibold">{totalCount}</p><p className="text-xs text-muted-foreground">Berkas investigasi pribadi</p></div>
                    <div className="rounded-xl border bg-card p-4"><LockKeyhole className="mb-2 h-5 w-5 text-muted-foreground" /><p className="text-xl font-semibold">Draft-only</p><p className="text-xs text-muted-foreground">Tidak membuat evidence/issue</p></div>
                    <div className="rounded-xl border bg-card p-4"><AlertCircle className="mb-2 h-5 w-5 text-muted-foreground" /><p className="text-xl font-semibold">Human review</p><p className="text-xs text-muted-foreground">Permintaan telaah bukan persetujuan</p></div>
                </div>
                <section className="overflow-hidden rounded-xl border bg-card shadow-sm" aria-label="Daftar investigasi">
                    <div className="flex items-center justify-between border-b p-5"><h2 className="text-lg font-semibold">Berkas investigasi</h2><span className="text-xs text-muted-foreground">100 terbaru ditampilkan</span></div>
                    {investigations.length === 0 ? <div className="p-12 text-center"><ClipboardList className="mx-auto mb-4 h-9 w-9 text-muted-foreground" /><p className="font-semibold">Belum ada investigasi</p><p className="mt-2 text-sm text-muted-foreground">Pilih sinyal di Signal Explorer dan klik “Buat investigasi”.</p><Link href="/sifi/signals" className="mt-4 inline-block rounded-lg border px-4 py-2 text-sm font-semibold hover:bg-muted">Pilih publikasi</Link></div>
                        : <div className="divide-y">{investigations.map((item) => <Link key={item.id} href={`/sifi/investigations/${item.id}`} className="flex items-center justify-between gap-4 p-5 hover:bg-muted/40">
                            <div className="min-w-0"><div className="mb-2 flex flex-wrap items-center gap-2"><span className="rounded-md border bg-muted px-2 py-1 text-[11px] font-semibold">{stateNames[item.review_state] ?? item.review_state}</span><span className="text-xs text-muted-foreground">Revisi {item.revision}</span></div>
                                <h3 className="truncate text-base font-semibold">{item.title}</h3><p className="mt-1 line-clamp-2 text-sm text-muted-foreground">{item.claim_hypothesis || 'Hipotesis awal belum ditulis'}</p><p className="mt-2 text-xs text-muted-foreground">Diperbarui {formatDate(item.updated_at)}</p></div><ArrowRight className="h-5 w-5 shrink-0 text-muted-foreground" /></Link>)}</div>}
                </section>
                <p className="text-xs text-muted-foreground">SIFI AQ01 · POC lokal · 0 evidence admitted · Penilaian manusia tidak membuktikan fakta.</p>
            </div>
        </div>
    </AppLayout>;
}
