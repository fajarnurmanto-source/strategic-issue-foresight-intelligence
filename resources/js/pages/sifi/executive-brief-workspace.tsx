import AppLayout from '@/layouts/app-layout';
import {Head, Link, router} from '@inertiajs/react';
import {useState, type FormEvent} from 'react';
import {ArrowLeft, AlertTriangle, FileText, Save, Download, History, ShieldAlert, Layers} from 'lucide-react';

type Src={
 source_lineage?:{connector?:string;mode:string;packet_hash:string;candidate_reason:string;source:{publisher:string;limitation:string;documentation_url:string;fetched_at?:string;source_updated_at?:string|null;freshness?:string;endpoint?:string};conflicts:unknown[];changes:unknown[];duplicates:unknown[];records:{observation:{id:string;issued_at:string|null;valid_at:string|null;period_label?:string;description:string;provenance:{pointer?:string;raw_hash?:string;feed_item?:{updated_at:string|null;lineage:{pointer:string;raw_record_sha256:string}[]}}};draft_links:{evidence_id:string;claim_id:string;statement:string;verification_state:string}[]}[]};
 candidate:{id:string;revision:number;title:string;hypothesis:string|null;evidence_gaps:string|null;next_checks:string|null};
 issue_case:{id:string;revision:number;title:string;domain:string;geography:string;lifecycle:string;verification_state:string};
 trajectory:{id:string;revision:number;horizon:string;direction:string;risk:string;opportunity:string;watch_condition:string;uncertainty:string};
 foresight:{id:string;revision:number;baseline:string;alternative:string;stress:string;drivers:string;falsification:string;options:string;uncertainty:string};
 draft_counts:Record<string,number>;
 foresight_advisory:{snapshot_id:number;draft_revision:number;marker:string;created_at:string;input_revision_matches:boolean}|null;
 data_mode:string;claim_verified:boolean;event_verified:boolean;evidence_admission:string;decision_authorized:boolean;forecast_available:boolean;
};
type Brief={id:string;revision:number;title:string;audience:string;executive_summary:string;decision_questions:string;verification_priorities:string;limitations:string;review_state:string;input_digest:string};
type Props={candidate:{id:string;revision:number;title:string};source:Src;sourceDigest:string;brief:Brief|null;sourceCurrent:boolean;audit:{revision:number;action:string;created_at:string}[]};
const card='rounded-xl border bg-card p-5';const field='mt-2 w-full rounded-lg border bg-background px-3 py-2.5 text-sm';
const root=(id:string)=>`/sifi/issue-candidates/${id}/executive-brief`;
export default function ExecutiveBriefWorkspace({candidate,source,sourceDigest,brief,sourceCurrent,audit=[]}:Props){
 const [title,setTitle]=useState(brief?.title||`Briefing: ${source.issue_case.title}`);
 const [audience,setAudience]=useState(brief?.audience||'Pengambil keputusan — draf internal');
 const [summary,setSummary]=useState(brief?.executive_summary||(source.source_lineage ? source.trajectory.direction+' Ini bahan telaah, bukan rekomendasi operasional.' : 'Sumber masih berupa fixture offline dan belum tersedia evidence terverifikasi. Uraian ini adalah draf analitis, bukan temuan faktual.'));
 const [questions,setQuestions]=useState(brief?.decision_questions||'Bukti primer apa yang diperlukan untuk menguji hipotesis isu, dampak, serta pilihan respons?');
 const [checks,setChecks]=useState(brief?.verification_priorities||(['yk_pad','wb_gdp','wb_acquisition'].includes(source.source_lineage?.connector??'')?'Periksa angka primer, satuan, definisi, periode, versi, freshness, izin reuse dan kesesuaian schema sebelum keputusan.':'Identifikasi data primer, izin sumber, kemungkinan sindikasi, lokasi dan waktu kejadian sebelum penetapan keputusan.'));
 const [limits,setLimits]=useState(brief?.limitations||(['wb_gdp','wb_acquisition'].includes(source.source_lineage?.connector??'')?(source.source_lineage?.source.limitation??'Keterbatasan sumber belum tersedia.'):'Hanya fixture offline; tidak ada verified temporal series, probabilitas foresight, evidence admitted, atau keputusan yang disahkan.'));
 const [review,setReview]=useState(brief?.review_state||'DRAFT');
 const [saving,setSaving]=useState(false);const [error,setError]=useState('');
 const url=root(candidate.id);
 const values={title,audience,executive_summary:summary,decision_questions:questions,verification_priorities:checks,limitations:limits,review_state:review};
 const dirty=!!brief&&Object.entries(values).some(([k,v])=>v!==brief[k as keyof Brief]);
 const revisions={candidate_revision:source.candidate.revision,case_revision:source.issue_case.revision,
  trajectory_revision:source.trajectory.revision,foresight_revision:source.foresight.revision,
  ...(brief?{brief_revision:brief.revision}:{}),input_digest:sourceDigest};
 function save(event:FormEvent){event.preventDefault();setError('');router.post(url,{...revisions,...values},{preserveScroll:true,
   onStart:()=>setSaving(true),onFinish:()=>setSaving(false),onError:errs=>setError(Object.values(errs).join(' · '))});}
 const fields=[
  {label:source.source_lineage?`Ringkasan analis (${['wb_gdp','wb_acquisition'].includes(source.source_lineage.connector??'')?(source.source_lineage.mode==='MOCK_ACQUISITION_ONLY'?'mock/simulasi':'arsip nyata'):'template simulasi'}; telaah sebelum disimpan)`:'Executive summary (human-authored)',help:'Ringkasan secara eksplisit membedakan indikasi dan fakta.',value:summary,set:setSummary,rows:5},
  {label:'Decision questions',help:'Pertanyaan yang perlu dijawab, bukan keputusan yang telah ditetapkan.',value:questions,set:setQuestions,rows:3},
  {label:'Verification priorities',help:'Daftar tindakan verifikasi yang diperlukan sebelum penggunaan resmi.',value:checks,set:setChecks,rows:3},
  {label:'Limitations and uncertainties',help:'Keterbatasan data, status rights, dan ketidakpastian wajib terlihat.',value:limits,set:setLimits,rows:3},
 ];
 return <AppLayout breadcrumbs={[{title:'Issue Candidates',href:'/sifi/issue-candidates'},
  {title:'Foresight Studio',href:`/sifi/issue-candidates/${candidate.id}/foresight`},{title:'Executive Brief',href:url}]}>
  <Head title="SIFI · Executive Intelligence"/>
  <main className="min-h-screen bg-muted/20 px-4 py-7 lg:px-8"><div className="mx-auto max-w-[1380px] space-y-5">
   <header><Link href={`/sifi/issue-candidates/${candidate.id}/foresight`} className="inline-flex items-center gap-2 text-sm text-muted-foreground"><ArrowLeft className="h-4 w-4"/> Kembali ke Foresight Studio</Link>
    <p className="mt-4 text-xs font-semibold uppercase tracking-widest text-muted-foreground">SIFI / EXECUTIVE DECISION SUPPORT</p>
    <h1 className="mt-2 text-3xl font-semibold">Executive Intelligence & Briefing</h1>
    <p className="mt-2 text-sm text-muted-foreground">{source.issue_case.title} · Case v{source.issue_case.revision} · Foresight v{source.foresight.revision}</p></header>
   <div role="alert" className="flex gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
    <ShieldAlert className="h-5 w-5 shrink-0 text-amber-600"/><p><b>UNAPPROVED · {['wb_gdp','wb_acquisition'].includes(source.source_lineage?.connector??'')?(source.source_lineage?.mode==='MOCK_ACQUISITION_ONLY'?'MOCK/SYNTHETIC ACQUISITION':'REAL PUBLIC ARCHIVE'):'OFFLINE FIXTURE ONLY'}.</b> Briefing ini bukan laporan resmi, bukan findings terverifikasi, tidak membuktikan peristiwa atau forecast, dan tidak mengizinkan keputusan otomatis. Evidence: NOT_EVALUATED.</p>
   </div>
   {!sourceCurrent&&<div role="alert" className="flex gap-3 rounded-xl border border-destructive/50 bg-destructive/10 p-4 text-sm"><AlertTriangle className="h-5 w-5"/> Sumber berubah sejak briefing terakhir. Simpan ulang dengan sumber terbaru sebelum mengunduh.</div>}
   <div className="grid gap-3 sm:grid-cols-4">{[
     {l:'Claim drafts',n:source.draft_counts.claim_drafts},{l:'Event hypotheses',n:source.draft_counts.event_hypotheses},
     {l:'Possible links',n:source.draft_counts.possible_links},{l:'Evidence drafts',n:source.draft_counts.evidence_drafts}
    ].map(x=><div key={x.l} className={`${card} !p-4`}><p className="text-xs text-muted-foreground">{x.l}</p><b className="mt-1 block text-2xl">{x.n??0}</b></div>)}</div>
   {error&&<p role="alert" className="rounded-lg border border-destructive/50 p-3 text-sm text-destructive">{error}</p>}
   <div className="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,1fr)]">
    <form onSubmit={save} className={`${card} space-y-4`}>
     <h2 className="flex items-center gap-2 border-b pb-4 text-lg font-semibold"><FileText className="h-5 w-5"/> Draft executive brief</h2>
     <p className="text-xs text-muted-foreground">{brief?`Draft v${brief.revision} · ${brief.review_state}`:'Belum ada brief tersimpan'} · {source.source_lineage?`${['wb_gdp','wb_acquisition'].includes(source.source_lineage.connector??'')?(source.source_lineage.mode==='MOCK_ACQUISITION_ONLY'?'Mock/simulasi':'Arsip nyata'):'Template simulasi'}; memerlukan telaah dan simpan manual.`:'Disusun manusia; tidak diterbitkan otomatis.'}</p>
     <label className="block text-sm font-semibold">Judul briefing<input className={field} value={title} onChange={e=>setTitle(e.target.value)} minLength={5} maxLength={180} required/></label>
     <label className="block text-sm font-semibold">Audiens<input className={field} value={audience} onChange={e=>setAudience(e.target.value)} minLength={3} maxLength={120} required/></label>
     {fields.map(f=><label key={f.label} className="block text-sm font-semibold">{f.label}<p className="my-1 text-xs font-normal text-muted-foreground">{f.help}</p><textarea className={field} rows={f.rows} value={f.value} onChange={e=>f.set(e.target.value)} maxLength={4000} required/></label>)}
     <label className="block text-sm font-semibold">Status workflow<select className={field} value={review} onChange={e=>setReview(e.target.value)}>
      <option value="DRAFT">DRAFT — konsep internal</option><option value="REVIEW_REQUESTED">REVIEW_REQUESTED — permintaan telaah, bukan approval</option></select></label>
     <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4"><p className="text-xs text-muted-foreground">Eloquent · optimistic revision · append-only audit</p>
      <button disabled={saving} className="inline-flex items-center gap-2 rounded-lg bg-foreground px-4 py-2.5 text-sm font-semibold text-background disabled:opacity-40"><Save className="h-4 w-4"/> Simpan briefing</button></div>
    </form>
    <aside className="space-y-5">
     <section className={`${card} space-y-3`}><h2 className="flex items-center gap-2 font-semibold"><Layers className="h-4 w-4"/> Evidence & source lineage</h2>
      <p className="text-sm"><b>Issue Case:</b> {source.issue_case.title}</p>
      <div className="grid grid-cols-2 gap-2 text-xs"><p className="rounded bg-muted p-3">Domain: {source.issue_case.domain}</p><p className="rounded bg-muted p-3">Scope: {source.issue_case.geography}</p></div>
      <p className="text-xs text-muted-foreground">Keduanya label draf, bukan konfirmasi lokasi kejadian atau relevansi faktual.</p>
      <div className="space-y-2 rounded-lg border p-3 text-sm"><b>Trajectory hypothesis</b><p className="whitespace-pre-wrap">{source.trajectory.direction}</p><b>Risk hypothesis</b><p className="whitespace-pre-wrap">{source.trajectory.risk}</p><b>Opportunity hypothesis</b><p className="whitespace-pre-wrap">{source.trajectory.opportunity}</p></div>
      <div className="space-y-2 rounded-lg border p-3 text-sm"><b>Baseline</b><p>{source.foresight.baseline}</p><b>Alternative</b><p>{source.foresight.alternative}</p><b>Stress</b><p>{source.foresight.stress}</p></div>
      <p className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-xs">Foresight Advisory: {source.foresight_advisory?.marker||'BELUM ADA SNAPSHOT'} · Claim NOT VERIFIED · Evidence NOT_EVALUATED</p>
      <p className="break-all text-[11px] text-muted-foreground">Source fingerprint: {sourceDigest}</p>
     </section>
     {source.source_lineage&&<section className={`${card} min-w-0 space-y-3 text-sm`} aria-label="Jejak sumber ke brief">
      <h2 className="font-semibold">Jejak sumber ke brief · {['wb_gdp','wb_acquisition'].includes(source.source_lineage.connector??'')?(source.source_lineage.mode==='MOCK_ACQUISITION_ONLY'?'MOCK/SIMULASI':'ARSIP NYATA'):'SIMULASI'}</h2>
      <p><b>Informasi teramati:</b> {['wb_gdp','wb_acquisition'].includes(source.source_lineage.connector??'')?(source.source_lineage.mode==='MOCK_ACQUISITION_ONLY'?'payload mock lokal sintetis, bukan respons World Bank aktual.':'tiga record nasional World Bank, bukan kondisi terkini atau lokal.'):source.source_lineage.connector==='yk_pad'?'isi fixture agregat PAD; bukan angka resmi atau kesimpulan fiskal.':source.source_lineage.connector==='feed_fixture'?'metadata RSS/Atom/agregator sintetis; ringkasan feed bukan artikel lengkap atau kondisi nyata.':source.source_lineage.connector==='research_fixture'?'isi dokumen sintetis ber-hash dan byte-span; bukan publikasi atau kondisi nyata.':'isi fixture prakiraan; bukan cuaca yang teramati.'}</p>
      <p><b>Alasan kandidat:</b> {source.source_lineage.candidate_reason}</p>
      <p><b>Klaim:</b> NOT VERIFIED. <b>Inferensi:</b> hipotesis trajectory di atas.</p>
      <p><b>Evidence belum tersedia:</b> {source.candidate.evidence_gaps}</p>
      <p><b>Keterbatasan sumber:</b> {source.source_lineage.source.limitation}</p>
      <p>{['wb_gdp','wb_acquisition'].includes(source.source_lineage.connector??'')?(source.source_lineage.mode==='MOCK_ACQUISITION_ONLY'?'SIMULASI SIFI; rujukan schema World Bank, bukan nilai/response World Bank aktual.':'World Bank: World Development Indicators, pertumbuhan PDB Indonesia 2022–2024; sumber statistik nasional/bank sentral, OECD dan estimasi World Bank. CC BY 4.0. Transformasi dan interpretasi oleh SIFI; tanpa endorsement.'):source.source_lineage.connector==='yk_pad'?'Rujukan katalog Open Data Kota Yogyakarta; format pertukaran dan angka dibuat SIFI. Schema API resmi/hak komersial belum terbukti.':source.source_lineage.connector==='feed_fixture'?'SIFI self-authored SYNTHETIC feed fixtures; sumber nyata belum diambil atau disetujui.':source.source_lineage.connector==='research_fixture'?'SIFI self-authored document fixture; tidak ada publikasi eksternal.':'BMKG sebagai rujukan format; nilai dibuat SIFI.'} Collector OFF. Tidak ada custody AQ01 atau admission.</p>
      {['wb_gdp','wb_acquisition'].includes(source.source_lineage.connector??'')&&<p className="text-xs"><a className="underline" href="https://data.worldbank.org/indicator/NY.GDP.MKTP.KD.ZG?locations=ID">Metadata indikator</a> · <a className="underline" href="https://creativecommons.org/licenses/by/4.0/">Lisensi</a> · <a className="underline" href="https://www.worldbank.org/ext/en/legal/terms-conditions/datasets">Ketentuan reuse</a></p>}
       {['yk_pad','wb_gdp','wb_acquisition'].includes(source.source_lineage.connector??'')&&<p className="break-words text-xs">Waktu impor: {source.source_lineage.source.fetched_at} · Pembaruan sumber: {source.source_lineage.source.source_updated_at??'UNKNOWN'} · Freshness saat impor: {source.source_lineage.source.freshness}</p>}
      <p>Duplikat {source.source_lineage.duplicates.length} · Konflik {source.source_lineage.conflicts.length} · {['wb_gdp','wb_acquisition'].includes(source.source_lineage.connector??'')?'Perbandingan antarperiode':'Perubahan versi'} {source.source_lineage.changes.length}</p>
      <p className="break-all text-xs">Packet SHA-256: {source.source_lineage.packet_hash}</p>
      {source.source_lineage.records.map(({observation:o,draft_links:links})=><details key={o.id} className="min-w-0 rounded-lg border p-3">
       <summary className="cursor-pointer break-words">{o.description} · {o.period_label??o.valid_at}</summary>
       <div className="mt-2 space-y-2 break-all text-xs"><p>{source.source_lineage?.connector==='feed_fixture'?'Publikasi':'Pembaruan'} {o.issued_at??'UNKNOWN'} · Periode {o.period_label??o.valid_at}</p>{source.source_lineage?.connector==='feed_fixture'&&<p>Pembaruan item {o.provenance.feed_item?.updated_at??'UNKNOWN'} · tanggal peristiwa UNKNOWN</p>}<p>Record {o.id}</p><p>Pointer {o.provenance.pointer??o.provenance.feed_item?.lineage[0]?.pointer??'UNKNOWN'}</p><p>Raw SHA-256 {o.provenance.raw_hash??o.provenance.feed_item?.lineage[0]?.raw_record_sha256??'UNKNOWN'}</p>
        {links.map(l=><p key={l.claim_id??l.evidence_id}>Evidence draft {l.evidence_id} → Claim draft {l.claim_id??'belum ada'} · {l.verification_state??'NOT VERIFIED'}<br/>{l.statement}</p>)}
       </div>
      </details>)}
      <p className="text-xs text-muted-foreground">Tinjau isi dan simpan brief sebagai draf. Tindakan ini tidak mengesahkan evidence, sumber, atau keputusan.</p>
     </section>}
     <section className={`${card} space-y-3`}><h2 className="flex items-center gap-2 font-semibold"><Download className="h-4 w-4"/> Ekspor internal</h2>
      <p className="text-sm text-muted-foreground">Unduhan Markdown selalu memuat watermark tidak terverifikasi, ID sumber, batasan, dan jejak versi.</p>
      {brief&&sourceCurrent&&!dirty?<a href={`${url}/download`} className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-foreground p-3 text-sm font-semibold text-background"><Download className="h-4 w-4"/> Unduh draf (.md)</a>:
       <p className="rounded-lg bg-muted p-3 text-xs text-muted-foreground">Simpan terlebih dahulu, atau perbarui briefing setelah perubahan sumber, sebelum mengunduh.</p>}
     </section>
     <section className={card}><h2 className="flex items-center gap-2 font-semibold"><History className="h-4 w-4"/> Histori revisi</h2>
      <ul className="mt-3 space-y-2 text-xs">{audit.map(a=><li key={a.revision} className="rounded-lg border p-3">v{a.revision} · {a.action}<p className="text-muted-foreground">{a.created_at}</p></li>)}</ul>
     </section>
    </aside>
   </div>
  </div></main>
 </AppLayout>;
}
