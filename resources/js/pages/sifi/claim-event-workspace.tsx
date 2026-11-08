import AppLayout from '@/layouts/app-layout';
import {Head,Link,router} from '@inertiajs/react';
import {useState,type FormEvent} from 'react';
import {ArrowLeft,FileQuestion,Network,ShieldAlert,RefreshCw,History} from 'lucide-react';

type Claim={id:string;evidence_id:string;signal_id:string;statement:string;attribution:string;modality:string;revision:number};
type Event={id:string;summary:string;time_note:string;place_note:string;revision:number};
type Relation={claim_id:string;event_id:string;relation:string};
type Evidence={id:string;signal_id:string;claim_text:string;relationship:string};
type Result={status:string;claim_count:number;event_count:number;link_count:number;distinct_source_count:number;
 claim_verified:boolean;event_verified:boolean;evidence_admission:string;verification_checks:string[];
 signal_observations:{signal_id:string;source_id:string;signal_version:number}[]};
type Snapshot={id:number;is_current:boolean;result:Result;created_at:string};
type Audit={action:string;object_id:string;created_at:string};
type Props={candidate:{id:string;title:string;revision:number};claims:Claim[];events:Event[];links:Relation[];
 evidence:Evidence[];assessments:Snapshot[];audit:Audit[];scope:string};
const base=(id:string)=>`/sifi/issue-candidates/${id}/claim-events`;
function short(id:string){return id.slice(0,8)+'…';}
export default function ClaimEventWorkspace({candidate,claims,events,links,evidence,assessments=[],audit=[]}:Props){
 const [evidenceId,setEvidenceId]=useState(evidence[0]?.id||'');
 const [statement,setStatement]=useState('');const [attribution,setAttribution]=useState('');
 const [modality,setModality]=useState('UNDETERMINED');
 const [summary,setSummary]=useState('');const [time,setTime]=useState('');const [place,setPlace]=useState('');
 const [claimId,setClaimId]=useState(claims[0]?.id||'');const [eventId,setEventId]=useState(events[0]?.id||'');
 const [relation,setRelation]=useState('UNKNOWN');const [error,setError]=useState('');
 const [busy,setBusy]=useState(false);
 const url=base(candidate.id);const latest=assessments[0];
 function submit(endpoint:string,payload:Record<string,string|number>,after?:()=>void){
  setError('');router.post(`${url}/${endpoint}`,{candidate_revision:candidate.revision,...payload},{preserveScroll:true,
   onStart:()=>setBusy(true),onFinish:()=>setBusy(false),
   onError:errors=>setError(Object.values(errors).join(' · ')||'Permintaan ditolak.'),
   onSuccess:()=>{after?.();}});
 }
 function createClaim(e:FormEvent){e.preventDefault();submit('claims',{evidence_id:evidenceId,statement,attribution,modality},()=>setStatement(''));}
 function createEvent(e:FormEvent){e.preventDefault();submit('events',{summary,time_note:time,place_note:place},()=>{setSummary('');setTime('');setPlace('');});}
 function createLink(e:FormEvent){e.preventDefault();submit('links',{claim_id:claimId||claims[0]?.id||'',event_id:eventId||events[0]?.id||'',relation});}
 return <AppLayout breadcrumbs={[{title:'SIFI — Issue Candidates',href:'/sifi/issue-candidates'},{title:'Claim & Event',href:url}]}>
  <Head title="SIFI · Claim & Event Intelligence"/>
  <main className="min-h-screen bg-muted/20 px-4 py-7 lg:px-8"><div className="mx-auto max-w-[1360px] space-y-5">
   <header><Link href={`/sifi/issue-candidates/${candidate.id}`} className="inline-flex items-center gap-2 text-sm text-muted-foreground"><ArrowLeft className="h-4 w-4"/> Kembali ke kandidat isu</Link>
    <p className="mt-4 text-xs font-semibold uppercase tracking-widest text-muted-foreground">SIFI / STRUCTURAL INTELLIGENCE</p>
    <h1 className="mt-2 text-3xl font-semibold">Claim & Event Workspace</h1>
    <p className="mt-2 text-sm text-muted-foreground">{candidate.title} · Revisi kandidat {candidate.revision} · Pemisahan klaim dan dugaan peristiwa</p>
   </header>
   <div role="alert" className="flex gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm"><ShieldAlert className="h-5 w-5 shrink-0 text-amber-600"/>
     <p><b>OFFLINE FIXTURE ONLY · NOT VERIFIED.</b> Publikasi, klaim, dan dugaan peristiwa adalah objek berbeda. Relasi dibuat manusia; tidak ada kebenaran klaim, kejadian, status evidence, atau Python Issue Object yang disahkan otomatis.</p></div>
   <div className="grid gap-3 sm:grid-cols-3">{[{label:'Claim Drafts',count:claims.length},{label:'Event Hypotheses',count:events.length},{label:'Unverified Links',count:links.length}].map(m=><div key={m.label} className="rounded-xl border bg-card p-4"><span className="text-xs text-muted-foreground">{m.label}</span><b className="mt-2 block text-2xl">{m.count}</b></div>)}</div>
   {error&&<p role="alert" className="rounded-lg border border-destructive/30 bg-card p-3 text-sm text-destructive">{error}</p>}
   <div className="grid gap-5 xl:grid-cols-2">
    <section className="space-y-4 rounded-xl border bg-card p-5 shadow-sm"><h2 className="flex items-center gap-2 text-lg font-semibold"><FileQuestion className="h-5 w-5"/> Claim Drafts (pernyataan yang perlu diuji)</h2>
     {claims.length===0?<p className="text-sm text-muted-foreground">Belum ada klaim draf.</p>:<div className="space-y-2">{claims.map(c=><div key={c.id} className="rounded-lg border p-3 text-sm"><div className="flex justify-between gap-2"><b>{c.modality} · NOT VERIFIED</b><span className="text-xs text-muted-foreground">{short(c.id)}</span></div><p className="mt-2">{c.statement}</p><p className="mt-2 text-xs text-muted-foreground">Atribusi: {c.attribution||'UNKNOWN'} · evidence draft {short(c.evidence_id)}</p></div>)}</div>}
     <form onSubmit={createClaim} className="space-y-3 border-t pt-4"><h3 className="font-semibold">Tambahkan klaim dari kandidat bukti</h3>
      <label className="block text-sm">Kandidat bukti<select required value={evidenceId} onChange={e=>setEvidenceId(e.target.value)} className="mt-1 w-full rounded-md border bg-background p-2" disabled={!evidence.length}>{evidence.length===0?<option value="">Buat Evidence Candidate dahulu</option>:evidence.map(x=><option key={x.id} value={x.id}>{short(x.id)} · {x.relationship} · {x.claim_text.slice(0,60)}</option>)}</select></label>
      <label className="block text-sm">Pernyataan/claim<textarea required minLength={3} maxLength={2000} rows={3} value={statement} onChange={e=>setStatement(e.target.value)} className="mt-1 w-full rounded-md border bg-background p-2" placeholder="Tuliskan pernyataan yang diklaim, tanpa menganggapnya fakta."/></label>
      <label className="block text-sm">Atribusi yang disebutkan<input maxLength={255} value={attribution} onChange={e=>setAttribution(e.target.value)} className="mt-1 w-full rounded-md border bg-background p-2" placeholder="Sumber klaim / belum diketahui"/></label>
      <label className="block text-sm">Modalitas pelaporan<select value={modality} onChange={e=>setModality(e.target.value)} className="mt-1 w-full rounded-md border bg-background p-2"><option value="UNDETERMINED">UNDETERMINED</option><option value="REPORTED">REPORTED</option><option value="ALLEGED">ALLEGED</option></select></label>
      <button disabled={busy||!evidence.length||claims.length>=20} className="rounded-lg bg-foreground px-4 py-2 text-sm font-semibold text-background disabled:opacity-40">Simpan claim draft</button>
      {!evidence.length&&<p className="text-xs text-muted-foreground"><Link href={`${url.replace('/claim-events','')}/evidence`} className="underline">Buat kandidat bukti di Evidence Intake</Link> terlebih dahulu.</p>}
     </form></section>
    <section className="space-y-4 rounded-xl border bg-card p-5 shadow-sm"><h2 className="flex items-center gap-2 text-lg font-semibold"><Network className="h-5 w-5"/> Event Hypotheses (belum terbukti)</h2>
     {events.length===0?<p className="text-sm text-muted-foreground">Belum ada hipotesis peristiwa.</p>:<div className="space-y-2">{events.map(ev=><div key={ev.id} className="rounded-lg border p-3 text-sm"><div className="flex justify-between gap-2"><b>NOT VERIFIED</b><span className="text-xs text-muted-foreground">{short(ev.id)}</span></div><p className="mt-2">{ev.summary}</p><p className="mt-2 text-xs text-muted-foreground">Waktu: {ev.time_note||'UNKNOWN'} · Tempat kejadian: {ev.place_note||'UNKNOWN'}</p></div>)}</div>}
     <form onSubmit={createEvent} className="space-y-3 border-t pt-4"><h3 className="font-semibold">Tambahkan dugaan peristiwa</h3>
      <label className="block text-sm">Rumusan dugaan peristiwa<textarea required minLength={3} maxLength={1000} rows={3} value={summary} onChange={e=>setSummary(e.target.value)} className="mt-1 w-full rounded-md border bg-background p-2" placeholder="Hipotesis tentang sesuatu yang mungkin terjadi; belum diverifikasi."/></label>
      <label className="block text-sm">Konteks waktu (opsional)<input maxLength={255} value={time} onChange={e=>setTime(e.target.value)} className="mt-1 w-full rounded-md border bg-background p-2" placeholder="Belum diketahui"/></label>
      <label className="block text-sm">Lokasi peristiwa (bukan wilayah penerbit)<input maxLength={255} value={place} onChange={e=>setPlace(e.target.value)} className="mt-1 w-full rounded-md border bg-background p-2" placeholder="Belum diketahui"/></label>
      <button disabled={busy||events.length>=20} className="rounded-lg bg-foreground px-4 py-2 text-sm font-semibold text-background disabled:opacity-40">Simpan event hypothesis</button>
     </form></section>
   </div>
   <div className="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,1fr)]">
    <section className="space-y-4 rounded-xl border bg-card p-5"><h2 className="font-semibold">Pemetaan hubungan klaim–peristiwa</h2>
      {links.length===0?<p className="text-sm text-muted-foreground">Belum ada hubungan. Tidak boleh menyimpulkan kedua objek menggambarkan kejadian yang sama.</p>:<ul className="space-y-2 text-sm">{links.map(l=><li key={l.claim_id+l.event_id} className="rounded-lg border p-3">Claim {short(l.claim_id)} → Event {short(l.event_id)} · <b>{l.relation}</b></li>)}</ul>}
      <form onSubmit={createLink} className="space-y-3 border-t pt-4"><label className="block text-sm">Klaim<select className="mt-1 w-full rounded-md border bg-background p-2" value={claimId||claims[0]?.id||''} onChange={e=>setClaimId(e.target.value)}>{claims.map(c=><option key={c.id} value={c.id}>{c.statement.slice(0,100)}</option>)}</select></label>
       <label className="block text-sm">Hipotesis peristiwa<select className="mt-1 w-full rounded-md border bg-background p-2" value={eventId||events[0]?.id||''} onChange={e=>setEventId(e.target.value)}>{events.map(ev=><option key={ev.id} value={ev.id}>{ev.summary.slice(0,100)}</option>)}</select></label>
       <label className="block text-sm">Hubungan draf<select className="mt-1 w-full rounded-md border bg-background p-2" value={relation} onChange={e=>setRelation(e.target.value)}><option value="UNKNOWN">UNKNOWN</option><option value="POSSIBLE_ASSOCIATION">POSSIBLE_ASSOCIATION (belum diverifikasi)</option></select></label>
       <button disabled={busy||claims.length===0||events.length===0||links.length>=50} className="rounded-lg border px-4 py-2 text-sm font-semibold disabled:opacity-40">Hubungkan sebagai draf</button></form>
    </section>
    <aside className="space-y-4"><section className="space-y-3 rounded-xl border bg-card p-5"><h2 className="flex items-center gap-2 font-semibold"><RefreshCw className="h-4 w-4"/> Python Structural Advisory</h2>
      <p className="text-sm text-muted-foreground">Hanya memeriksa struktur metadata fixture, sumber yang teramati, dan kekosongan konteks. Bukan verifikasi kejadian.</p>
      <button disabled={busy||claims.length<1} onClick={()=>submit('assess',{})} className="w-full rounded-lg bg-foreground p-3 text-sm font-semibold text-background disabled:opacity-40">Jalankan pemeriksaan offline</button>
      {latest&&<div className="space-y-3 rounded-lg border p-3 text-sm"><div className="font-semibold">Snapshot #{latest.id} · {latest.result.status}</div><div className="text-xs text-muted-foreground">{latest.is_current?'Input masih sama':'Snapshot lama'} · {latest.created_at}</div>
        <div className="grid grid-cols-3 gap-2">{[{label:'Klaim',num:latest.result.claim_count},{label:'Peristiwa',num:latest.result.event_count},{label:'Sumber teramati',num:latest.result.distinct_source_count}].map(m=><div key={m.label} className="rounded bg-muted p-2"><b>{m.num}</b><p className="text-xs">{m.label}</p></div>)}</div>
        <p className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-2 text-xs">Claim verified: NO · Event verified: NO · Evidence: NOT EVALUATED</p>
        <ul className="list-disc space-y-2 pl-4 text-sm">{latest.result.verification_checks.map((check,i)=><li key={i}>{check}</li>)}</ul></div>}
     </section><section className="rounded-xl border bg-card p-5"><h2 className="flex items-center gap-2 font-semibold"><History className="h-4 w-4"/> Audit</h2>
       <ul className="mt-3 space-y-2 text-xs">{audit.map((a,i)=><li key={i} className="rounded-lg border p-3">{a.action} · {short(a.object_id)}<p className="mt-1 text-muted-foreground">{a.created_at}</p></li>)}</ul>
     </section></aside>
   </div>
  </div></main>
 </AppLayout>;
}
