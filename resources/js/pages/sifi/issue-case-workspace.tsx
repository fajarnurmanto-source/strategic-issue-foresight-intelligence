import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { ArrowLeft, ShieldAlert, Save, RefreshCw, History, Waypoints, BookOpen } from 'lucide-react';

type Case = {id:string;candidate_id:string;revision:number;title:string;issue_domain:string;geography_label:string;
 scope_note:string;lifecycle_state:'DRAFT'|'WATCHLIST'|'ON_HOLD'|'CLOSED_DRAFT';coverage_profile_id:string|null;verification_state:string};
type Profile={id:string;name:string;region_name:string;domains:string[];revision:number};
type Result={status:string;scope_marker:string;domain_label_match:boolean;region_label_match:boolean;claim_count:number;event_count:number;link_count:number;
 evidence_admission:string;relevance_verified:boolean;issue_object_created:boolean;verification_checks:string[]};
type Advisory={id:number;created_at:string;is_current:boolean;result:Result};
type Props={candidate:{id:string;title:string;revision:number};case:Case|null;profiles:Profile[];counts:{claims:number;events:number;links:number};
 advisories:Advisory[];audit:{revision:number;action:string;created_at:string}[];mode:string};
const base=(id:string)=>`/sifi/issue-candidates/${id}/issue-case`;
export default function IssueCaseWorkspace({candidate,case:issueCase,profiles,counts,advisories=[],audit=[]}:Props){
 const [title,setTitle]=useState(issueCase?.title||candidate.title);
 const [domain,setDomain]=useState(issueCase?.issue_domain||'belum ditentukan');
 const [geography,setGeography]=useState(issueCase?.geography_label||'UNKNOWN');
 const [note,setNote]=useState(issueCase?.scope_note||'Cakupan dan batas isu belum ditentukan; memerlukan pemeriksaan.');
 const [state,setState]=useState<Case['lifecycle_state']>(issueCase?.lifecycle_state||'DRAFT');
 const [profile,setProfile]=useState(issueCase?.coverage_profile_id||'');
 const [busy,setBusy]=useState(false);const [error,setError]=useState('');
 const url=base(candidate.id);
 const common={candidate_revision:candidate.revision,...(issueCase?{case_revision:issueCase.revision}:{})};
 function save(e:FormEvent){e.preventDefault();setError('');router.post(url,{...common,title,issue_domain:domain,geography_label:geography,
  scope_note:note,lifecycle_state:state,coverage_profile_id:profile||null},{preserveScroll:true,onStart:()=>setBusy(true),onFinish:()=>setBusy(false),
  onError:e=>setError(Object.values(e).join(' · '))});}
 function assess(){if(!issueCase)return;setError('');router.post(url+'/assess',common,{preserveScroll:true,
  onStart:()=>setBusy(true),onFinish:()=>setBusy(false),onError:e=>setError(Object.values(e).join(' · '))});}
 const latest=advisories[0];const dirty=!!issueCase && (title!==issueCase.title||domain!==issueCase.issue_domain||geography!==issueCase.geography_label||
 note!==issueCase.scope_note||state!==issueCase.lifecycle_state||profile!==(issueCase.coverage_profile_id||''));
 const field='mt-1 w-full rounded-lg border bg-background px-3 py-2.5 text-sm';
 return <AppLayout breadcrumbs={[{title:'SIFI — Issue Candidates',href:'/sifi/issue-candidates'},{title:'Issue Case',href:url}]}>
  <Head title="SIFI · Issue Identity & Lifecycle"/>
  <main className="min-h-screen bg-muted/20 px-4 py-7 lg:px-8"><div className="mx-auto max-w-[1360px] space-y-5">
   <header><Link href={`/sifi/issue-candidates/${candidate.id}`} className="inline-flex items-center gap-2 text-sm text-muted-foreground"><ArrowLeft className="h-4 w-4"/> Kembali ke kandidat isu</Link>
    <p className="mt-4 text-xs font-semibold uppercase tracking-widest text-muted-foreground">SIFI / ISSUE IDENTITY</p>
    <h1 className="mt-2 text-3xl font-semibold">Issue Case & Lifecycle</h1>
    <p className="mt-2 text-sm text-muted-foreground">Identitas kerja analis, cakupan wilayah, domain, dan catatan relevansi — bukan Python Issue Object.</p></header>
   <div role="alert" className="flex gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm"><ShieldAlert className="h-5 w-5 shrink-0 text-amber-600"/>
    <p><b>OFFLINE FIXTURE ONLY · NOT VERIFIED.</b> WATCHLIST adalah status pengelolaan draf, bukan perkembangan isu nyata. Kecocokan label wilayah/domain bukan validasi relevansi atau dampak faktual. Evidence tetap NOT_EVALUATED.</p></div>
   <div className="grid gap-3 sm:grid-cols-3">{[{t:'Claim Drafts',v:counts.claims},{t:'Event Hypotheses',v:counts.events},{t:'Possible Links',v:counts.links}].map(m=><div key={m.t} className="rounded-xl border bg-card p-4"><p className="text-xs text-muted-foreground">{m.t}</p><b className="mt-2 block text-2xl">{m.v}</b></div>)}</div>
   {error&&<p role="alert" className="rounded-lg border border-destructive/30 bg-card p-3 text-sm text-destructive">{error}</p>}
   <div className="grid gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(310px,1fr)]">
    <form onSubmit={save} className="space-y-4 rounded-xl border bg-card p-5 shadow-sm">
     <h2 className="flex items-center gap-2 border-b pb-4 text-lg font-semibold"><BookOpen className="h-5 w-5"/> Identitas isu (draf)</h2>
     {issueCase&&<p className="text-xs text-muted-foreground">ID draf: {issueCase.id} · Revisi {issueCase.revision} · {issueCase.verification_state}</p>}
     <label className="block text-sm font-semibold">Judul isu<input className={field} required minLength={3} maxLength={180} value={title} onChange={e=>setTitle(e.target.value)}/></label>
     <label className="block text-sm font-semibold">Domain isu (label analis)<input className={field} required minLength={2} maxLength={120} value={domain} onChange={e=>setDomain(e.target.value)} placeholder="contoh: lingkungan"/></label>
     <label className="block text-sm font-semibold">Geografi relevansi yang diusulkan<input className={field} required minLength={2} maxLength={180} value={geography} onChange={e=>setGeography(e.target.value)} placeholder="UNKNOWN jika belum tersedia informasi"/><p className="mt-1 text-xs text-muted-foreground">Bukan lokasi kejadian yang telah diverifikasi dan bukan wilayah penerbit.</p></label>
     <label className="block text-sm font-semibold">Coverage Profile (opsional)<select className={field} value={profile} onChange={e=>setProfile(e.target.value)}><option value="">Belum dikaitkan</option>{profiles.map(p=><option value={p.id} key={p.id}>{p.name} · {p.region_name}</option>)}</select></label>
     <label className="block text-sm font-semibold">Batas isu dan alasan penelusuran<textarea rows={4} className={field} required minLength={3} maxLength={4000} value={note} onChange={e=>setNote(e.target.value)} placeholder="Jelaskan batas masalah, siapa yang mungkin terdampak, dan hal yang belum diketahui."/></label>
     <label className="block text-sm font-semibold">Status lifecycle DRAF<select className={field} value={state} onChange={e=>setState(e.target.value as Case['lifecycle_state'])}>
      <option value="DRAFT">DRAFT · Konstruksi isu</option><option value="WATCHLIST">WATCHLIST · Pantau draf (bukan isu terverifikasi)</option><option value="ON_HOLD">ON_HOLD · Menunggu klarifikasi</option><option value="CLOSED_DRAFT">CLOSED_DRAFT · Arsip draf</option></select></label>
     <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4"><p className="text-xs text-muted-foreground">Eloquent + Policy · revisi optimistis · audit append-only</p>
      <button disabled={busy} className="inline-flex items-center gap-2 rounded-lg bg-foreground px-4 py-2.5 text-sm font-semibold text-background disabled:opacity-40"><Save className="h-4 w-4"/> Simpan Issue Case</button></div>
    </form>
    <aside className="space-y-5"><section className="space-y-3 rounded-xl border bg-card p-5"><h2 className="flex items-center gap-2 font-semibold"><Waypoints className="h-4 w-4"/> Python Scope Advisory</h2>
     <p className="text-sm text-muted-foreground">Hanya membandingkan label domain/wilayah pada draf dengan Coverage Profile. Tidak ada perhitungan risiko, verifikasi kejadian, atau skor kebenaran.</p>
     <button type="button" onClick={assess} disabled={busy||!issueCase||dirty} className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-foreground p-3 text-sm font-semibold text-background disabled:opacity-40"><RefreshCw className="h-4 w-4"/> Jalankan pemeriksaan offline</button>
     {!issueCase&&<p className="text-xs text-muted-foreground">Simpan draf dahulu.</p>}{dirty&&<p className="text-xs text-muted-foreground">Simpan perubahan sebelum memeriksa.</p>}
     {latest&&<div className="space-y-3 rounded-lg border p-3 text-sm"><p className="font-semibold">Snapshot #{latest.id} · {latest.result.scope_marker}</p><p className="text-xs text-muted-foreground">{latest.is_current?'Input masih sama':'Input sudah berubah'} · {latest.created_at}</p>
      <p className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-2 text-xs">NOT VERIFIED · Relevansi faktual: NO · Evidence: NOT_EVALUATED</p>
      <div className="grid grid-cols-2 gap-2"><div className="rounded bg-muted p-3"><b>{latest.result.domain_label_match?'YA':'TIDAK'}</b><p className="text-xs">Label domain sama</p></div><div className="rounded bg-muted p-3"><b>{latest.result.region_label_match?'YA':'TIDAK'}</b><p className="text-xs">Label wilayah sama</p></div></div>
      <ul className="list-disc space-y-2 pl-4 text-sm">{latest.result.verification_checks.map((x,i)=><li key={i}>{x}</li>)}</ul></div>}
    </section>
    <section className="rounded-xl border bg-card p-5"><h2 className="flex items-center gap-2 font-semibold"><History className="h-4 w-4"/> Histori revisi</h2><ul className="mt-3 space-y-2 text-xs">{audit.map(a=><li key={a.revision} className="rounded-lg border p-3">v{a.revision} · {a.action}<p className="mt-1 text-muted-foreground">{a.created_at}</p></li>)}</ul></section>
    {issueCase&&<Link href={`/sifi/issue-candidates/${candidate.id}/trajectory`} className="block rounded-xl border bg-card p-4 text-sm font-semibold hover:bg-muted">Buka Trajectory, Risk & Opportunity →</Link>}
    <Link href={`/sifi/issue-candidates/${candidate.id}/claim-events`} className="block rounded-xl border bg-card p-4 text-sm font-semibold hover:bg-muted">Buka Claim & Event Intelligence →</Link>
    <p className="rounded-xl border bg-muted/60 p-4 text-xs text-muted-foreground">Seluruh data draf berada di Laravel lokal. Python tidak menulis objek isu atau mengubah database.</p>
    </aside></div></div></main>
 </AppLayout>;
}
