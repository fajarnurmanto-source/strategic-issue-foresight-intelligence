import WorldBankSourcePanel from './world-bank-source-panel';
import { router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

type SourceRegistration = {id:string;homepage_url:string|null;source_type:string;revision:number;name:string};
export type Pipeline = {
  scenario:string;scenarios:Record<string,string>;mode:string;status:string;reason:string|null;connector?:string;acquisition_allowed?:boolean;receipt_id?:string;transport?:{bytes:number;attempts:number;retry_count:number;elapsed_ms:number;max_buffered_bytes:number};
  source:{id?:string;publisher?:string;limitation?:string;documentation_url?:string;endpoint?:string;rights_state?:string;collector_state?:string;freshness?:string;source_updated_at?:string|null;fetched_at?:string;source_version?:string;schema_status?:string;attribution?:string;rights_review_until?:string;wire_sha256?:string};
  packet_hash:string;candidate_reason:string|null;pages_processed?:number;
  observations:{id:string;description:string;issued_at:string|null;valid_at:string|null;period_label?:string;temperature_c?:number;humidity_percent?:number;amount_rupiah?:number;source_version?:number;provenance:{pointer:string;raw_hash:string}}[];
  duplicates:unknown[];conflicts:unknown[];changes:unknown[];retry_after_seconds?:number|null;
  import_template?:string;import_json?:string|null;history?:{new:number;already_seen:number;updated:number;conflicting:number;out_of_order:number;audit_window_limit:number;imports_examined:number};
};
const doc='https://opendata.jogjakota.go.id/data/pajak/pad_tahun_noseries';
const endpoint='https://opendata.jogjakota.go.id/data/pajak/pad_tahun_noseriesapi/index/';
export default function SourcePipelinePanel({pipeline:p,sources=[]}:{pipeline:Pipeline;sources?:SourceRegistration[]}) {
  const isPad=p.connector==='yk_pad';
  const registered=sources.filter(s=>s.source_type==='OPEN_DATA'&&[doc,endpoint].includes(s.homepage_url??''));
  const [sourceId,setSourceId]=useState(registered[0]?.id??'');
  const chosen=registered.find(s=>s.id===sourceId);
  const [note,setNote]=useState('');const [confirmed,setConfirmed]=useState(false);const [busy,setBusy]=useState(false);
  const [errors,setErrors]=useState<Record<string,string>>({});const [json,setJson]=useState(p.import_json??p.import_template??'');
  const unpreviewed=p.scenario==='pad_import'&&json!==(p.import_json??p.import_template??'');
  function create(event:FormEvent) {
    event.preventDefault();
    router.post('/sifi/source-catalog/pipeline-draft',{scenario:p.scenario,analyst_note:note,confirm_synthetic:confirmed,
      ...(isPad?{source_draft_id:sourceId||null,source_revision:chosen?.revision??null,input_digest:p.packet_hash}:{})},
      {onStart:()=>setBusy(true),onFinish:()=>setBusy(false),onError:setErrors});
  }
  function register() {
    router.post('/sifi/source-catalog/sources',{name:'Open Data Kota Yogyakarta — PAD tahunan',source_type:'OPEN_DATA',homepage_url:doc,
      source_geography:'Kota Yogyakarta',notes:`Endpoint terkunci ${endpoint}. Rights UNASSESSED / commercial reuse unconfirmed; collector OFF. Format impor SIFI_YK_PAD_IMPORT_V1 hanya sintetis; bukan schema API resmi terverifikasi.`},
      {preserveScroll:true,onStart:()=>setBusy(true),onFinish:()=>setBusy(false),onError:setErrors,onSuccess:()=>router.get('/sifi/source-catalog',{scenario:p.scenario},{preserveScroll:true})});
  }
  const ready=p.status==='READY_FOR_ANALYST_DRAFT';
  if(['wb_gdp','wb_acquisition'].includes(p.connector??'')) return <WorldBankSourcePanel pipeline={p} sources={sources}/>;
  return <section aria-labelledby="source-pipeline-title" className="min-w-0 space-y-5 rounded-xl border bg-card p-5 shadow-sm">
    <header className="flex flex-wrap items-start justify-between gap-4">
      <div><p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Sumber → Ringkasan intelligence</p>
        <h2 id="source-pipeline-title" className="mt-2 text-xl font-semibold">Uji alur dengan data simulasi</h2>
        <p className="mt-2 max-w-3xl text-sm text-muted-foreground">Periksa record, provenance, kandidat, evidence dan perubahan versi; lanjutkan ke penyusun brief yang sudah tersedia.</p></div>
      <span className="rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs font-semibold">SIMULASI · TANPA AI · COLLECTOR OFF</span>
    </header>
    <p className="rounded-lg bg-muted p-3 text-sm">{isPad?<><b>Konektor Open Data PAD — impor sintetis.</b> Hak reuse komersial dan schema API belum terbukti. Audit 25 Oktober 2026 mendapat HTTP 500; status live tidak dipantau. Format SIFI_YK_PAD_IMPORT_V1 dibuat SIFI. Tidak ada pengambilan live, retry otomatis, atau angka resmi.</>:<><b>Bukan data cuaca aktual.</b> Format mengacu pada BMKG; semua nilai dibuat SIFI. Hak pengambilan live masih menunggu izin tertulis dalam register SIFI. Tidak ada permintaan jaringan.</>}</p>
    <label className="block max-w-xl text-sm font-medium">Skenario pengujian
      <select aria-label="Skenario pengujian" className="mt-2 w-full rounded-lg border bg-background p-3" value={p.scenario}
        onChange={e=>router.get('/sifi/source-catalog',{scenario:e.target.value},{preserveScroll:true})}>
        {Object.entries(p.scenarios).map(([key,title])=><option key={key} value={key}>{title}</option>)}
      </select>
    </label>
    {isPad&&<div className="min-w-0 space-y-3 rounded-lg border p-4 text-sm" aria-label="Metadata konektor Open Data PAD">
      <h3 className="font-semibold">Open Data Kota Yogyakarta · PAD tahunan</h3>
      <p className="break-all">Source ID: SRC-OPENDATA-YK-PAD<br/>Endpoint terdaftar: {p.source.endpoint}</p>
      <p>Hak penggunaan: {p.source.rights_state} · Schema: {p.source.schema_status}</p>
      <p>Waktu impor: {p.source.fetched_at??'BELUM ADA'}<br/>Pembaruan sumber: {p.source.source_updated_at??'UNKNOWN — tidak tersedia'}<br/>Freshness saat impor: {p.source.freshness??'UNKNOWN'}</p>
      <p>Halaman impor diproses: {p.pages_processed??0}. Pagination ini milik format pertukaran SIFI; dukungan pagination API resmi belum terbukti. Batas freshness simulasi bukan jadwal resmi.</p>
      {!!p.retry_after_seconds&&<p>Retry-After: {p.retry_after_seconds} detik. Tidak dijadwalkan atau dicoba ulang otomatis.</p>}
      {registered.length===0?<button type="button" onClick={register} disabled={busy} className="rounded-lg border px-3 py-2 font-semibold disabled:opacity-40">Daftarkan usulan sumber PAD</button>:
        <label className="block">Usulan sumber milik Anda<select aria-label="Usulan sumber konektor" value={sourceId} onChange={e=>setSourceId(e.target.value)} className="mt-2 w-full rounded-lg border bg-background p-3">
          <option value="">Pilih sumber</option>{registered.map(s=><option key={s.id} value={s.id}>{s.name} · revisi {s.revision}</option>)}
        </select></label>}
      <p className="text-xs text-muted-foreground">Pendaftaran memakai Source Registry draft existing dan tidak memberi hak koleksi. Registrasi sumber diperlukan sebelum membuat draft PAD.</p>
    </div>}
    {isPad&&p.scenario==='pad_import'&&<div className="min-w-0 space-y-3 rounded-lg border p-4">
      <label className="block text-sm font-medium">JSON simulasi (maksimal 64 KiB)<textarea aria-label="JSON simulasi PAD" value={json} onChange={e=>setJson(e.target.value)} rows={10} spellCheck={false} className="mt-2 w-full rounded-lg border bg-background p-3 font-mono text-xs"/></label>
      <p className="text-xs text-muted-foreground">Hanya format pertukaran sintetis SIFI; bukan unggahan data resmi berizin atau izin untuk mengubah marker sintetis. Preview disimpan dalam sesi akun ini, bukan dikirim ke sumber.</p>
      <button type="button" disabled={busy} onClick={()=>router.post('/sifi/source-catalog/connector-preview',{import_json:json},{preserveScroll:true,onStart:()=>setBusy(true),onFinish:()=>setBusy(false),onError:setErrors})} className="rounded-lg border px-4 py-2 text-sm font-semibold">Periksa impor simulasi</button>
      {unpreviewed&&<p role="status" className="text-sm text-amber-700 dark:text-amber-300">JSON berubah. Periksa ulang sebelum membuat draft.</p>}
    </div>}
    <div className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
      {[['Record unik',p.observations.length],['Duplikat diabaikan',p.duplicates.length],['Pasangan bertentangan',p.conflicts.length],['Perubahan versi',p.changes.length]].map(([label,value])=>
        <p key={label} className="rounded-lg border p-3"><span className="block text-muted-foreground">{label}</span><b className="mt-1 block text-xl">{value}</b></p>)}
    </div>
    {isPad&&p.history&&<p className="rounded-lg bg-muted p-3 text-sm">Dibanding riwayat impor akun Anda: {p.history.new} baru · {p.history.already_seen} sudah ada · {p.history.updated} versi lebih baru · {p.history.conflicting} konflik · {p.history.out_of_order} versi lebih lama. Diperiksa maksimal {p.history.audit_window_limit} audit impor; jumlah ini bukan sumber independen.</p>}
    <p role="status" className={`rounded-lg border p-3 text-sm ${ready?'border-teal-500/40 bg-teal-500/10':'border-amber-500/40 bg-amber-500/10'}`}>
      {ready?'Siap ditelaah sebagai draft sintetis.':`Pemrosesan dihentikan: ${p.reason??p.status}. Tidak membuat kandidat atau evidence.`}
    </p>
    <div className="grid min-w-0 gap-5 lg:grid-cols-2">
      <div className="min-w-0 space-y-3"><h3 className="font-semibold">Record & provenance</h3>
        {p.observations.map(o=><details key={o.id} className="min-w-0 rounded-lg border p-3 text-sm"><summary className="cursor-pointer break-words">{o.description} · {o.period_label??o.valid_at}</summary>
          <div className="mt-3 space-y-2 break-all text-xs"><p>{isPad?`Agregat fixture: IDR ${o.amount_rupiah} · versi ${o.source_version}`:`Isi fixture: ${o.temperature_c} °C · ${o.humidity_percent}% kelembapan`}</p><p>Pembaruan record {o.issued_at??'UNKNOWN'} · Periode {o.period_label??o.valid_at}</p><p>Observation SHA-256: {o.id}</p><p>Raw SHA-256: {o.provenance.raw_hash}</p><p>JSON pointer: {o.provenance.pointer}</p></div>
        </details>)}
        <p className="break-all text-xs text-muted-foreground">Packet SHA-256: {p.packet_hash}</p>
        <p className="text-xs text-muted-foreground">Rujukan format: {p.source.publisher??'Metadata penerbit belum lengkap'}. {p.source.limitation}</p>
      </div>
      <form onSubmit={create} className="min-w-0 space-y-3 rounded-lg border p-4">
        <h3 className="font-semibold">Telaah analis → draft brief</h3>
        <p className="text-sm"><b>Alasan kandidat:</b> {p.candidate_reason??'Belum layak membentuk kandidat.'}</p>
        <p className="text-sm"><b>Klaim:</b> belum diverifikasi. <b>Inferensi:</b> memerlukan pertimbangan analis. <b>Evidence hilang:</b> data primer yang sah dan sumber independen.</p>
        <label className="block text-sm font-medium">Catatan telaah Anda<textarea aria-label="Catatan telaah Anda" required minLength={15} maxLength={2000} rows={4} value={note} onChange={e=>setNote(e.target.value)} className="mt-2 w-full rounded-lg border bg-background p-3 text-sm" placeholder="Apa yang perlu diuji, dan mengapa simulasi ini belum dapat dijadikan dasar keputusan?"/></label>
        <label className="flex items-start gap-2 text-sm"><input type="checkbox" checked={confirmed} onChange={e=>setConfirmed(e.target.checked)} className="mt-1 shrink-0"/><span>Saya memahami semua record ini sintetis. Buat draft lokal milik saya untuk ditelaah; jangan mengesahkan evidence atau keputusan.</span></label>
        <p className="text-xs text-muted-foreground">Memakai workflow existing. Pengiriman ulang membuka draft yang sama tanpa menimpa suntingan. Paket revisi berbeda membuat snapshot draft baru; tidak mengganti kandidat lama. Brief belum tersimpan sampai Anda menelaah dan menyimpannya.</p>
        {Object.entries(errors).map(([key,message])=><p key={key} role="alert" className="text-sm text-destructive">{message}</p>)}
        <button disabled={!ready||!confirmed||busy||note.trim().length<15||unpreviewed||(isPad&&!chosen)} className="rounded-lg bg-foreground px-4 py-3 text-sm font-semibold text-background disabled:opacity-40">{busy?'Menyiapkan draft…':'Buat / buka draft simulasi'}</button>
      </form>
    </div>
  </section>;
}
