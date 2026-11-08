import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, ClipboardList, FileQuestion, ShieldAlert } from 'lucide-react';

type Candidate = {
  id: string; title: string; investigation_id: string; review_state: string;
  revision: number; updated_at: string | null; issue_hypothesis: string | null;
};
type Props = {
  candidates: { data: Candidate[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null; total: number };
  scope: string;
};
const labels: Record<string,string> = { DRAFT: 'Draf', REVIEW_REQUESTED: 'Menunggu telaah', ON_HOLD: 'Ditunda' };

export default function IssueCandidatesIndex({ candidates }: Props) {
  return <AppLayout breadcrumbs={[{title:'SIFI — Issue Candidates',href:'/sifi/issue-candidates'}]}>
    <Head title="SIFI · Issue Candidates" />
    <div className="min-h-screen bg-muted/20 px-4 py-7 lg:px-8"><div className="mx-auto max-w-6xl space-y-5">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div><div className="flex items-center gap-2 text-xs font-semibold tracking-widest text-muted-foreground uppercase"><FileQuestion className="h-4 w-4"/> SIFI / Human-authored proposals</div>
          <h1 className="mt-2 text-3xl font-semibold tracking-tight">Issue Candidates</h1>
          <p className="mt-2 text-sm text-muted-foreground">Hipotesis isu yang disusun analis dari investigasi. Bukan Issue Object terverifikasi.</p>
        </div>
        <Link href="/sifi/investigations" className="rounded-lg border bg-card px-4 py-2.5 text-sm font-semibold hover:bg-muted">Buka investigasi →</Link>
      </header>
      <div className="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="alert"><ShieldAlert className="h-5 w-5 shrink-0 text-amber-600"/><div><p className="font-semibold">OFFLINE FIXTURE ONLY · REVIEW_REQUESTED bukan persetujuan</p><p className="mt-1 text-muted-foreground">Semua kandidat tersimpan di database Laravel lokal. Tidak ada bukti yang di-admit atau Issue Object dibuat di Python.</p></div></div>
      <section className="overflow-hidden rounded-xl border bg-card shadow-sm"><div className="flex items-center justify-between border-b p-5"><h2 className="flex items-center gap-2 text-lg font-semibold"><ClipboardList className="h-5 w-5"/> Kandidat isu</h2><span className="text-sm text-muted-foreground">{candidates.total} kandidat</span></div>
        {candidates.data.length===0 ? <div className="p-10 text-center"><p className="font-semibold">Belum ada kandidat isu</p><p className="mt-2 text-sm text-muted-foreground">Buka suatu investigasi, kemudian klik “Buat kandidat isu”.</p><Link className="mt-4 inline-block rounded-lg border px-4 py-2 text-sm font-semibold" href="/sifi/investigations">Buka Investigations</Link></div>
          : <div className="divide-y">{candidates.data.map(c => <Link key={c.id} href={`/sifi/issue-candidates/${c.id}`} className="flex items-center justify-between gap-3 p-5 hover:bg-muted/50"><div className="min-w-0"><div className="mb-2 flex items-center gap-2 text-xs text-muted-foreground"><span className="rounded-md border bg-muted px-2 py-1 font-semibold">{labels[c.review_state]??c.review_state}</span> Revisi {c.revision}</div><h3 className="truncate font-semibold">{c.title}</h3><p className="mt-1 line-clamp-2 text-sm text-muted-foreground">{c.issue_hypothesis||'Hipotesis belum ditulis'}</p></div><ArrowRight className="h-5 w-5 shrink-0 text-muted-foreground"/></Link>)}</div>}
        <div className="flex items-center justify-between border-t px-5 py-3 text-sm text-muted-foreground"><span>Halaman {candidates.current_page}/{Math.max(1,candidates.last_page)}</span><div className="flex gap-2">{candidates.prev_page_url && <Link href={candidates.prev_page_url} className="rounded-md border px-3 py-1.5 hover:bg-muted">Sebelumnya</Link>}{candidates.next_page_url && <Link href={candidates.next_page_url} className="rounded-md border px-3 py-1.5 hover:bg-muted">Berikutnya</Link>}</div></div>
      </section><p className="text-xs text-muted-foreground">SIFI AQ01 · Proposal-only, locally stored · 0 evidence admitted · Python core unchanged.</p>
    </div></div>
  </AppLayout>;
}
