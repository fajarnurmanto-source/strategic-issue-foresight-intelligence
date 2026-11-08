import {Link} from '@inertiajs/react';
export type CollectorObservation={session_id:string;question:string;state:string;saved_at:string|null;filtered_count:number;receipt:{http_status:number|null;wire_delivery_confirmed:boolean;retrieved_at:string|null;response_sha256:string|null;response_bytes:number;failure_category:string|null};entries:{id:string;title:string;url:string;publisher:string;published_at:string|null;date_precision:string;event_date:string|null;revision_state:string;source_language:string;region:string}[]};
export default function CollectorObservations({objectId,items}:{objectId:string;items:CollectorObservation[]}){
 return <section aria-label="Observasi metadata sumber nyata" className="min-w-0 space-y-4 rounded-xl border bg-card p-5">
  <h2 className="text-xl font-semibold">Hasil sumber nyata untuk objek ini</h2>
  <p className="text-sm text-muted-foreground">Observasi bibliografi dari riwayat riset existing. Periksa wilayah, isi, hak penggunaan, dan waktu kejadian sebelum membuat claim atau menilai evidence. SIFI belum menyimpulkan fakta atau citra negatif.</p>
  <Link className="inline-block underline" href={`/sifi/monitoring-objects/${objectId}/research-multi`}>Buka penelitian dan collector</Link>
  {!items.length&&<p>Belum ada pengambilan nyata dalam riwayat objek ini.</p>}
  {items.map(s=><article key={s.session_id} className="min-w-0 space-y-3 rounded-lg border p-4">
   <h3 className="break-words font-medium">{s.question}</h3>
   <p className="text-sm">{s.state==='SAVED'?'Tersimpan':'Draf penelitian'} · HTTP {s.receipt.http_status??'UNKNOWN'} · respons diterima: {s.receipt.wire_delivery_confirmed?'Ya':'Belum terbukti'} · {s.receipt.response_bytes} byte · {s.receipt.retrieved_at??'Waktu UNKNOWN'}</p>
   <p className="break-all text-xs">SHA-256: {s.receipt.response_sha256??'UNKNOWN'}</p>
   <p className="text-sm">Kesalahan: {s.receipt.failure_category??'tidak ada'} · {s.filtered_count} hasil disaring karena judul/periode.</p>
   {s.entries.map(x=><div key={x.id} className="min-w-0 space-y-1 border-l-2 pl-3 text-sm">
    <p className="break-words font-medium">{x.title}</p><p className="break-words">Penerbit: {x.publisher}</p>
    <p>Publikasi: {x.published_at??'UNKNOWN'} ({x.date_precision}); kejadian: {x.event_date??'UNKNOWN'}</p>
    <p>Revisi: {x.revision_state} · bahasa sumber: {x.source_language} · wilayah Kota belum diverifikasi</p>
    <a className="break-all underline" href={x.url} target="_blank" rel="noopener noreferrer">Sumber asli: {x.url}</a>
   </div>)}
   <Link className="inline-block underline" href={`/sifi/monitoring-objects/${objectId}/research-multi?research=${s.session_id}`}>Buka kembali penelitian ini</Link>
  </article>)}
 </section>;
}
