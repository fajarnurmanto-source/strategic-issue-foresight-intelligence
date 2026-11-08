import AppLayout from '@/layouts/app-layout';
import {Head, Link, router} from '@inertiajs/react';
import {useState} from 'react';
import {ArrowLeft, FlaskConical, History, RefreshCw, ShieldAlert, FileCheck2} from 'lucide-react';

type Draft = {evidence_id:string;signal_id:string;revision:number;relationship:string};
type Observation = {evidence_id:string;signal_id:string;source_id:string;signal_version:number;relationship:string};
type Result = {
  status:string;mode:string;kind:string;evidence_admission:string;
  draft_count:number;distinct_source_count:number;possible_syndication:boolean;
  independence_verified:boolean;claim_verified:boolean;python_issue_created:boolean;
  relationship_counts:Record<string,number>;duplicate_signal_groups:string[][];
  signal_observations:Observation[];verification_checks:string[];
};
type Snapshot = {id:number;candidate_revision:number;is_current:boolean;result:Result;created_at:string};
type Props = {candidate:{id:string;title:string;revision:number};drafts:Draft[];diagnostics:Snapshot[];scope:string};
export default function EvidenceDiagnostics({candidate,drafts,diagnostics=[]}:Props){
  const [running,setRunning]=useState(false);
  const [error,setError]=useState('');
  const latest=diagnostics[0];
  function run(){setError('');router.post(`/sifi/issue-candidates/${candidate.id}/evidence-diagnostics`,{revision:candidate.revision},{
    preserveScroll:true,onStart:()=>setRunning(true),onFinish:()=>setRunning(false),
    onError:(e)=>setError(e.diagnostics||e.revision||'Analisis ditolak; periksa data.')});}
  return <AppLayout breadcrumbs={[{title:'SIFI — Issue Candidates',href:'/sifi/issue-candidates'},{title:'Evidence Diagnostics',href:`/sifi/issue-candidates/${candidate.id}/evidence-diagnostics`}]}>
    <Head title="SIFI · Evidence Diagnostics"/>
    <main className="min-h-screen bg-muted/20 px-4 py-7 lg:px-8"><div className="mx-auto max-w-[1280px] space-y-5">
      <header><Link href={`/sifi/issue-candidates/${candidate.id}`} className="inline-flex items-center gap-2 text-sm text-muted-foreground"><ArrowLeft className="h-4 w-4"/> Kembali ke Issue Candidate</Link>
        <p className="mt-4 text-xs font-semibold uppercase tracking-widest text-muted-foreground">SIFI / Evidence Readiness</p>
        <h1 className="mt-2 text-3xl font-semibold">Evidence Diagnostics</h1><p className="mt-2 text-sm text-muted-foreground">{candidate.title} · Revisi kandidat {candidate.revision}</p>
      </header>
      <div role="alert" className="flex gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm"><ShieldAlert className="h-5 w-5 shrink-0 text-amber-600"/><div><b>OFFLINE FIXTURE ONLY · NOT EVALUATED.</b> Diagnostik menilai kelengkapan metadata, bukan kebenaran klaim, independensi asal, kelayakan legal, atau admisi evidence. Tidak ada Issue Object yang diterbitkan.</div></div>
      <div className="grid gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(320px,1fr)]"><section className="space-y-5">
        <div className="rounded-xl border bg-card p-5 shadow-sm"><h2 className="flex items-center gap-2 text-lg font-semibold"><FileCheck2 className="h-5 w-5"/> Pemetaan kandidat bukti ({drafts.length})</h2>
          {drafts.length===0?<p className="mt-4 text-sm text-muted-foreground">Belum ada kandidat bukti. Buat dulu di Evidence Intake.</p>:<div className="mt-4 space-y-2">{drafts.map(d=><div className="rounded-lg border p-3 text-sm" key={d.evidence_id}><div className="flex justify-between gap-2"><b>{d.relationship}</b><span className="text-xs text-muted-foreground">Revisi {d.revision}</span></div><p className="mt-1 break-all font-mono text-xs text-muted-foreground">{d.signal_id}</p><p className="mt-1 text-xs text-muted-foreground">Relasi ini merupakan penilaian awal analis, bukan hasil verifikasi.</p></div>)}</div>}
          <Link className="mt-4 inline-flex rounded-md border px-3 py-2 text-sm font-medium hover:bg-muted" href={`/sifi/issue-candidates/${candidate.id}/evidence`}>Buka Evidence Intake →</Link>
        </div>
        {latest&&<section className="space-y-4 rounded-xl border bg-card p-5 shadow-sm"><div className="flex flex-wrap justify-between gap-2"><h2 className="font-semibold">Hasil diagnosa #{latest.id}</h2><span className="text-xs text-muted-foreground">{latest.is_current?'Snapshot saat ini':'Snapshot lama — perubahan belum diperiksa'}</span></div>
          <p className="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm font-semibold">{latest.result.status} · {latest.result.evidence_admission}</p>
          <div className="grid grid-cols-2 gap-3"><div className="rounded-lg bg-muted p-4"><b className="block text-2xl">{latest.result.draft_count}</b><span className="text-xs">Kandidat bukti</span></div><div className="rounded-lg bg-muted p-4"><b className="block text-2xl">{latest.result.distinct_source_count}</b><span className="text-xs">Identitas sumber, bukan asal independen</span></div></div>
          <h3 className="font-semibold">Pemetaan hubungan (label analis)</h3><div className="grid grid-cols-2 gap-2 text-xs">{Object.entries(latest.result.relationship_counts).map(([key,v])=><div className="flex justify-between rounded border p-3" key={key}><span>{key}</span><b>{v}</b></div>)}</div>
          {latest.result.possible_syndication&&<p className="rounded-lg border border-amber-500/30 p-3 text-sm">Kemungkinan duplikasi/sindikasi terdeteksi. Jangan gunakan jumlah publikasi sebagai jumlah bukti independen.</p>}
          <h3 className="font-semibold">Metadata per sinyal</h3><div className="space-y-2">{latest.result.signal_observations.map(o=><div className="rounded-lg border p-3 text-xs" key={o.evidence_id}><b>{o.source_id} · versi sinyal {o.signal_version}</b><p className="mt-1 break-all font-mono text-muted-foreground">{o.signal_id}</p></div>)}</div>
          <h3 className="font-semibold">Langkah pemeriksaan</h3><ul className="list-disc space-y-2 pl-5 text-sm">{latest.result.verification_checks.map((check,i)=><li key={i}>{check}</li>)}</ul>
          <p className="text-xs text-muted-foreground">Claim verified: NO · Origin independence verified: NO · Evidence admission: NOT EVALUATED.</p>
        </section>}
      </section><aside className="space-y-4"><section className="rounded-xl border bg-card p-5 shadow-sm"><h2 className="flex items-center gap-2 font-semibold"><FlaskConical className="h-5 w-5"/> Python Diagnostic Bridge</h2>
        <p className="mt-3 text-sm text-muted-foreground">Menggunakan metadata fixture Python dan pemetaan draf yang tersimpan di Laravel. Hasil disimpan sebagai snapshot terpisah. Laravel Policy membatasi akses pemilik.</p>
        <button type="button" onClick={run} disabled={running||drafts.length<1||drafts.length>10} className="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-foreground px-4 py-3 text-sm font-semibold text-background disabled:opacity-40"><RefreshCw className={`h-4 w-4 ${running?'animate-spin':''}`}/>{running?'Memeriksa…':'Jalankan diagnostik offline'}</button>
        {error&&<p role="alert" className="mt-3 rounded-md border border-destructive/30 p-3 text-sm text-destructive">{error}</p>}
        {!latest&&<p className="mt-3 text-sm text-muted-foreground">Belum ada hasil. Simpan kandidat bukti terlebih dahulu.</p>}
      </section><section className="rounded-xl border bg-card p-5"><h2 className="flex items-center gap-2 font-semibold"><History className="h-5 w-5"/> Riwayat diagnostik</h2><div className="mt-3 space-y-2">{diagnostics.map(s=><div key={s.id} className="rounded-lg border p-3 text-xs"><b>Snapshot #{s.id} · kandidat v{s.candidate_revision}</b><p className="mt-1 text-muted-foreground">{s.is_current?'Input masih cocok':'Kedaluwarsa terhadap input terkini'} · {s.created_at}</p></div>)}</div></section>
        <p className="rounded-xl border bg-muted p-4 text-xs text-muted-foreground">Analisis ini tidak mengaktifkan acquisition, tidak menulis ke database Python, dan tidak mengubah workflow atau status evidence.</p>
      </aside></div>
    </div></main>
  </AppLayout>;
}
