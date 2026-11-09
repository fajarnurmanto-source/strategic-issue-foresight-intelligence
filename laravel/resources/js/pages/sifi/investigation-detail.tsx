import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { AlertTriangle, ArrowLeft, ClipboardCheck, FileSearch, History, Layers3, Link2, LockKeyhole, Save, ShieldAlert } from 'lucide-react';

type Assessment = {
    id: string; title: string; claim_hypothesis: string; evidence_gaps: string; next_steps: string;
    review_state: 'DRAFT' | 'REVIEW_REQUESTED' | 'ON_HOLD'; revision: number;
    created_at: string; updated_at: string;
};
type SignalProjection = { signal_id: string; title: string; source_id: string; publisher: string | null; published_at: string | null; evidence_admission: string; provenance_mode: string; independence_verified: boolean };
type Associated = { signal_id: string; projection: SignalProjection | null };
type Audit = { revision: number; action: string; created_at: string };
type Props = { investigation: Assessment; signals: Associated[]; revisions: Audit[]; maxSignals: number; scope: string };
type FormErrors = Record<string, string>;
const states: Record<string, string> = { DRAFT: 'Draf', REVIEW_REQUESTED: 'Permintaan telaah manusia', ON_HOLD: 'Ditunda' };

export default function InvestigationDetail({ investigation, signals, revisions, maxSignals }: Props) {
    const [title, setTitle] = useState(investigation.title);
    const [hypothesis, setHypothesis] = useState(investigation.claim_hypothesis);
    const [gaps, setGaps] = useState(investigation.evidence_gaps);
    const [nextSteps, setNextSteps] = useState(investigation.next_steps);
    const [state, setState] = useState(investigation.review_state);
    const [newSignal, setNewSignal] = useState('');
    const [errors, setErrors] = useState<FormErrors>({});
    const [working, setWorking] = useState(false);
    const [adding, setAdding] = useState(false);

    const url = `/sifi/investigations/${investigation.id}`;
    const save = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault(); setErrors({});
        router.patch(url, { title, claim_hypothesis: hypothesis, evidence_gaps: gaps, next_steps: nextSteps, review_state: state, revision: investigation.revision }, {
            preserveScroll: true, onStart: () => setWorking(true), onFinish: () => setWorking(false), onError: (e) => setErrors(e),
        });
    };
    const attach = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault(); setErrors({});
        router.post(`${url}/signals`, { signal_id: newSignal.trim().toLowerCase() }, {
            preserveScroll: true, onStart: () => setAdding(true), onFinish: () => setAdding(false), onSuccess: () => setNewSignal(''), onError: (e) => setErrors(e),
        });
    };
    const date = (value: string | null) => value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' }) + ' WIB' : 'Tidak tersedia';

    return <AppLayout breadcrumbs={[{ title: 'SIFI — Investigations', href: '/sifi/investigations' }, { title: 'Investigation detail', href: url }]}>
        <Head title="SIFI · Investigation" />
        <div className="min-h-screen bg-muted/20 px-4 py-6 lg:px-8"><div className="mx-auto max-w-[1480px] space-y-5">
            <header><Link href="/sifi/investigations" className="mb-3 inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"><ArrowLeft className="h-4 w-4" /> Semua investigasi</Link>
                <div className="flex flex-wrap items-end justify-between gap-3"><div><p className="text-xs font-semibold tracking-widest text-muted-foreground uppercase">SIFI / Investigation Workspace</p><h1 className="mt-2 text-3xl font-semibold tracking-tight">Assessment Workspace</h1><p className="mt-2 text-sm text-muted-foreground">Kandidat klaim, kekurangan bukti, dan langkah verifikasi — bukan temuan resmi.</p></div><span className="rounded-full border bg-card px-3 py-2 text-xs font-semibold">Revisi {investigation.revision} · {states[investigation.review_state]}</span></div>
            </header>
            <div role="alert" className="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4"><ShieldAlert className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" /><p className="text-sm"><strong>OFFLINE FIXTURE ONLY.</strong> Berkas ini disimpan di database Laravel lokal. Semua sinyal masih NOT_EVALUATED. Review requested adalah permintaan telaah, bukan evidence admitted atau Issue Object terverifikasi.</p></div>
            <div className="grid gap-5 xl:grid-cols-[minmax(0,1.65fr)_minmax(320px,1fr)]">
                <form onSubmit={save} className="space-y-5 rounded-xl border bg-card p-5 shadow-sm" aria-label="Form penilaian analis">
                    <div className="flex items-center gap-2 border-b pb-4"><ClipboardCheck className="h-5 w-5" /><h2 className="text-lg font-semibold">Analyst Assessment</h2><span className="ml-auto rounded-md border px-2 py-1 text-xs text-muted-foreground">Human-authored · draft</span></div>
                    <label className="block text-sm font-semibold">Judul investigasi<input maxLength={180} minLength={3} required value={title} onChange={e => setTitle(e.target.value)} className="mt-2 w-full rounded-lg border bg-background px-3 py-2.5 text-sm font-normal" />{errors.title && <span className="mt-1 block text-xs text-destructive">{errors.title}</span>}</label>
                    <label className="block text-sm font-semibold">Hipotesis / kandidat klaim<textarea rows={5} maxLength={4000} value={hypothesis} onChange={e => setHypothesis(e.target.value)} placeholder="Apa dugaan awal yang masih harus diuji? Jelaskan ketidakpastian, bukan kesimpulan faktual." className="mt-2 w-full resize-y rounded-lg border bg-background px-3 py-2.5 text-sm font-normal" />{errors.claim_hypothesis && <span className="mt-1 block text-xs text-destructive">{errors.claim_hypothesis}</span>}</label>
                    <label className="block text-sm font-semibold">Kesenjangan bukti / kontradiksi<textarea rows={4} maxLength={4000} value={gaps} onChange={e => setGaps(e.target.value)} placeholder="Sumber primer yang belum ada, klaim yang belum terkonfirmasi, asal sindikasi, dan hal yang belum diketahui." className="mt-2 w-full resize-y rounded-lg border bg-background px-3 py-2.5 text-sm font-normal" />{errors.evidence_gaps && <span className="mt-1 block text-xs text-destructive">{errors.evidence_gaps}</span>}</label>
                    <label className="block text-sm font-semibold">Langkah verifikasi berikutnya<textarea rows={4} maxLength={4000} value={nextSteps} onChange={e => setNextSteps(e.target.value)} placeholder="Verifikasi lapangan, silang referensi sumber resmi, cek metadata dan urutan kejadian." className="mt-2 w-full resize-y rounded-lg border bg-background px-3 py-2.5 text-sm font-normal" />{errors.next_steps && <span className="mt-1 block text-xs text-destructive">{errors.next_steps}</span>}</label>
                    <label className="block text-sm font-semibold">Status alur kerja<select value={state} onChange={e => setState(e.target.value as Assessment['review_state'])} className="mt-2 w-full rounded-lg border bg-background px-3 py-2.5 text-sm font-normal"><option value="DRAFT">Draf · masih ditulis</option><option value="REVIEW_REQUESTED">Permintaan telaah · bukan disetujui</option><option value="ON_HOLD">Ditunda · bukti belum cukup</option></select>{errors.review_state && <span className="text-xs text-destructive">{errors.review_state}</span>}</label>
                    {errors.revision && <p className="rounded-lg border border-destructive/30 p-3 text-sm text-destructive" role="alert">{errors.revision}</p>}
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4"><p className="text-xs text-muted-foreground">Penyimpanan manual · setiap perubahan dicatat sebagai revisi</p><button disabled={working} className="inline-flex items-center gap-2 rounded-lg bg-foreground px-4 py-2.5 text-sm font-semibold text-background disabled:opacity-50"><Save className="h-4 w-4" />{working ? 'Menyimpan…' : 'Simpan assessment'}</button></div>
                </form>
                <div className="space-y-5">
                    <section className="rounded-xl border bg-card p-5 shadow-sm"><div className="flex items-center justify-between gap-2"><h2 className="flex items-center gap-2 text-lg font-semibold"><Layers3 className="h-5 w-5" /> Referensi sinyal</h2><span className="text-xs text-muted-foreground">{signals.length}/{maxSignals}</span></div><p className="mt-2 text-xs text-muted-foreground">Publikasi adalah petunjuk investigasi, bukan bukti independen. Data tetap dimiliki API Python.</p>
                        <div className="mt-4 space-y-3">{signals.map(({signal_id, projection}) => <div key={signal_id} className="rounded-lg border p-3"><p className="break-all font-mono text-[11px] text-muted-foreground">{signal_id}</p>{projection ? <><h3 className="mt-2 text-sm font-semibold">{projection.title}</h3><p className="mt-1 text-xs text-muted-foreground">{projection.source_id} · {projection.publisher || 'Penerbit tidak diketahui'}</p><p className="mt-2 text-xs font-semibold text-amber-700 dark:text-amber-300">NOT_EVALUATED · independensi belum diverifikasi</p></> : <p className="mt-2 flex items-center gap-2 text-xs text-amber-700"><AlertTriangle className="h-4 w-4" /> API/proyeksi tidak tersedia; metadata tidak ditebak.</p>}</div>)}</div>
                        <form onSubmit={attach} className="mt-5 border-t pt-4"><label className="text-sm font-semibold">Lampirkan sinyal fixture lain<input pattern="[0-9a-fA-F]{64}" maxLength={64} required value={newSignal} onChange={e => setNewSignal(e.target.value)} placeholder="Tempel Signal ID 64 karakter" className="mt-2 w-full rounded-lg border bg-background p-2.5 font-mono text-xs font-normal" /></label>{errors.signal_id && <p role="alert" className="mt-2 text-xs text-destructive">{errors.signal_id}</p>}<button disabled={adding || signals.length >= maxSignals} className="mt-3 inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold hover:bg-muted disabled:opacity-50"><Link2 className="h-4 w-4" />{adding ? 'Memeriksa…' : 'Tambahkan referensi'}</button><p className="mt-2 text-xs text-muted-foreground">Hanya Signal ID yang terdapat pada API offline. Tidak ada URL baru yang di-fetch.</p></form>
                    </section>
                    <section className="rounded-xl border bg-card p-5 shadow-sm"><h2 className="text-lg font-semibold">Issue Candidate</h2><p className="mt-2 text-sm text-muted-foreground">Susun kandidat isu dari assessment ini menggunakan workflow native Laravel. Membuat draf, bukan bukti atau Issue Object.</p><button type="button" onClick={() => router.post('/sifi/issue-candidates', { investigation_id: investigation.id })} className="mt-3 rounded-lg bg-foreground px-4 py-2.5 text-sm font-semibold text-background">Buat / buka kandidat isu →</button><p className="mt-2 text-xs text-muted-foreground">Satu kandidat per investigasi; klik ulang tidak membuat duplikat.</p></section>
                    <section className="rounded-xl border bg-card p-5 shadow-sm"><h2 className="mb-3 flex items-center gap-2 text-lg font-semibold"><History className="h-5 w-5" /> Audit revisions</h2><div className="space-y-2">{revisions.map(r => <div key={r.revision} className="flex justify-between gap-2 rounded-lg border px-3 py-2 text-xs"><span className="font-semibold">v{r.revision} · {r.action.replaceAll('_', ' ')}</span><span className="text-muted-foreground">{date(r.created_at)}</span></div>)}</div></section>
                    <div className="flex gap-2 rounded-lg border bg-muted/50 p-3 text-xs text-muted-foreground"><LockKeyhole className="h-4 w-4 shrink-0" /><p>Hanya pengguna Laravel pemilik yang dapat membaca/mengubah berkas. Belum ada sistem penugasan multi-reviewer.</p></div>
                    <Link href="/sifi/signals" className="inline-flex items-center gap-2 text-sm font-semibold hover:underline"><FileSearch className="h-4 w-4" /> Kembali ke Signal Explorer</Link>
                </div>
            </div>
        </div></div>
    </AppLayout>;
}
