import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import {
    ArrowLeft,
    ClipboardCheck,
    History,
    ShieldAlert,
    Send,
    Save,
} from 'lucide-react';

type SourceProvenance = {
    session_id: string;
    source_url: string;
    source_sha256: string;
    image_sha256: string;
    brief_sha256: string;
    provenance_mode: string;
    review_state: string;
    rights_status: string;
    evidence_admission: string;
};
type ReviewReadiness = {
    state:
        | 'READY_TO_REQUEST'
        | 'NEEDS_CONTEXT'
        | 'WAITING_REVIEW'
        | 'REVIEW_RECORDED'
        | 'UNAVAILABLE';
    blockers: Record<string, string> | [];
};
type AuditEntry = { action: string; revision: number; created_at: string };
type Evidence = {
    review_readiness: ReviewReadiness;
    history: AuditEntry[];
    id: string;
    candidate_id: string;
    signal_id: string;
    claim_text: string;
    rationale: string | null;
    known_gaps: string | null;
    relationship: string;
    workflow_state: string;
    revision: number;
    reviewer_email: string | null;
    reviews: Review[];
    source_provenance: SourceProvenance | null;
};
type Review = {
    id: number;
    evidence_revision: number;
    review_finding: string;
    review_notes: string;
    created_at: string;
};
type Props = {
    candidate: { id: string; title: string; review_state: string };
    linkedSignalIds: string[];
    investigationUrl: string;
    evidence: Evidence[];
    scope: string;
};
const input = 'mt-1 w-full rounded-lg border bg-background px-3 py-2.5 text-sm';
const relationships = [
    ['UNKNOWN', 'Belum diketahui'],
    ['SUPPORTS', 'Diduga mendukung'],
    ['CONTRADICTS', 'Diduga bertentangan'],
    ['CONTEXT', 'Konteks saja'],
];
const names: Record<string, string> = {
    DRAFT: 'Draf (belum dinilai)',
    REVIEW_REQUESTED: 'Menunggu telaah',
    REVIEW_RECORDED: 'Catatan telaah tersedia',
};
const readinessText: Record<ReviewReadiness['state'], string> = {
    READY_TO_REQUEST: 'Siap diajukan untuk telaah.',
    NEEDS_CONTEXT: 'Lengkapi prasyarat sebelum meminta telaah.',
    WAITING_REVIEW: 'Menunggu catatan dari peninjau yang ditugaskan.',
    REVIEW_RECORDED: 'Catatan telaah tersimpan.',
    UNAVAILABLE: 'Kesiapan telaah belum tersedia.',
};
const auditLabels: Record<string, string> = {
    CREATED: 'Draf dibuat',
    EDITED: 'Draf disunting',
    REVIEW_REQUESTED: 'Telaah diminta',
    REVIEW_RECORDED: 'Telaah dicatat',
    REVISED_AFTER_REVIEW: 'Draf direvisi setelah telaah',
    REVIEW_RESUBMITTED: 'Telaah diajukan ulang',
};
function EvidenceForm({
    candidateId,
    item,
    linkedSignalIds,
}: {
    candidateId: string;
    item?: Evidence;
    linkedSignalIds: string[];
}) {
    const [signal, setSignal] = useState(
        item?.signal_id ?? linkedSignalIds[0] ?? '',
    );
    const [claim, setClaim] = useState(item?.claim_text ?? '');
    const [rationale, setRationale] = useState(item?.rationale ?? '');
    const [gaps, setGaps] = useState(item?.known_gaps ?? '');
    const [relationship, setRelationship] = useState(
        item?.relationship ?? 'UNKNOWN',
    );
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    function submit(e: FormEvent) {
        e.preventDefault();
        setErrors({});
        const payload = {
            claim_text: claim,
            rationale,
            known_gaps: gaps,
            relationship,
            ...(item ? { revision: item.revision } : { signal_id: signal }),
        };
        const opts = {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onError: (e: Record<string, string>) => setErrors(e),
        };
        if (item) router.patch(`/sifi/evidence/${item.id}`, payload, opts);
        else
            router.post(
                `/sifi/issue-candidates/${candidateId}/evidence`,
                payload,
                opts,
            );
    }
    return (
        <form
            onSubmit={submit}
            className="space-y-3 rounded-xl border bg-card p-4"
        >
            <h3 className="font-semibold">
                {item
                    ? 'Sunting draf kandidat bukti'
                    : 'Buat kandidat bukti dari referensi'}
            </h3>
            <label className="block text-sm font-medium">
                Referensi signal (fixture atau snapshot nyata)
                {item ? (
                    <p className="mt-1 rounded-md bg-muted p-2 font-mono text-xs break-all">
                        {item.signal_id}
                    </p>
                ) : (
                    <select
                        required
                        className={input}
                        value={signal}
                        onChange={(e) => setSignal(e.target.value)}
                    >
                        {linkedSignalIds.map((id) => (
                            <option key={id} value={id}>
                                {id.slice(0, 20)}…{id.slice(-8)}
                            </option>
                        ))}
                    </select>
                )}
                {errors.signal_id && (
                    <span role="alert" className="text-xs text-destructive">
                        {errors.signal_id}
                    </span>
                )}
            </label>
            <label className="block text-sm font-medium">
                Klaim yang perlu diuji (bukan fakta)
                <textarea
                    className={input}
                    rows={3}
                    required
                    minLength={5}
                    maxLength={3000}
                    value={claim}
                    onChange={(e) => setClaim(e.target.value)}
                    placeholder="Tuliskan klaim/hipotesis, bukan hasil verifikasi."
                />
                {errors.claim_text && (
                    <span className="text-xs text-destructive">
                        {errors.claim_text}
                    </span>
                )}
            </label>
            <label className="block text-sm font-medium">
                Hubungan publikasi dengan klaim
                <select
                    className={input}
                    value={relationship}
                    onChange={(e) => setRelationship(e.target.value)}
                >
                    {relationships.map(([val, label]) => (
                        <option key={val} value={val}>
                            {label}
                        </option>
                    ))}
                </select>
            </label>
            <label className="block text-sm font-medium">
                Alasan hubungan (opsional)
                <textarea
                    className={input}
                    rows={2}
                    maxLength={4000}
                    value={rationale}
                    onChange={(e) => setRationale(e.target.value)}
                    placeholder="Mengapa sinyal ini relevan? Apa asumsi Anda?"
                />
            </label>
            <label className="block text-sm font-medium">
                Kekurangan dan kontradiksi bukti
                <textarea
                    className={input}
                    rows={2}
                    maxLength={4000}
                    value={gaps}
                    onChange={(e) => setGaps(e.target.value)}
                    placeholder="Misalnya asal data, izin, independensi, wilayah kejadian, waktu peristiwa."
                />
            </label>
            {errors.follow_up && (
                <p role="alert" className="text-sm text-destructive">
                    {errors.follow_up}
                </p>
            )}
            {errors.revision && (
                <p role="alert" className="text-sm text-destructive">
                    {errors.revision}
                </p>
            )}
            <button
                disabled={busy || (!item && !signal)}
                className="inline-flex items-center gap-2 rounded-lg bg-foreground px-4 py-2.5 text-sm font-semibold text-background disabled:opacity-50"
            >
                <Save className="h-4 w-4" />
                {busy ? 'Menyimpan…' : 'Simpan draf'}
            </button>
        </form>
    );
}
function EvidenceCard({
    item,
    candidateId,
    investigationUrl,
}: {
    item: Evidence;
    candidateId: string;
    investigationUrl: string;
}) {
    const [email, setEmail] = useState('');
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [edit, setEdit] = useState(false);
    function requestReview(e: FormEvent) {
        e.preventDefault();
        setErrors({});
        router.post(
            `/sifi/evidence/${item.id}/request-review`,
            { revision: item.revision, reviewer_email: email },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: (e: Record<string, string>) => setErrors(e),
            },
        );
    }
    return (
        <article className="min-w-0 space-y-3 rounded-xl border bg-card p-4 [overflow-wrap:anywhere] shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="font-semibold">{item.claim_text}</p>
                    <p className="mt-1 font-mono text-xs break-all text-muted-foreground">
                        Signal: {item.signal_id}
                    </p>
                </div>
                <span className="rounded-md border px-2 py-1 text-xs">
                    {names[item.workflow_state] ?? item.workflow_state} · v
                    {item.revision}
                </span>
            </div>
            {item.source_provenance && (
                <div className="space-y-2 rounded-lg border p-3 text-xs">
                    <p className="font-semibold">
                        Asal dokumen nyata · BUKAN evidence terverifikasi
                    </p>
                    <a
                        className="break-all underline"
                        href={item.source_provenance.source_url}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {item.source_provenance.source_url}
                    </a>
                    <p className="font-mono break-all">
                        SHA-256 dokumen: {item.source_provenance.source_sha256}
                    </p>
                    <p className="font-mono break-all">
                        SHA-256 gambar: {item.source_provenance.image_sha256}
                    </p>
                    <p>
                        Sumber berasal dari Research Session{' '}
                        {item.source_provenance.session_id.slice(0, 8)}; hak
                        UNASSESSED; admission NONE. Review tidak membuktikan
                        kebenaran claim.
                    </p>
                </div>
            )}
            <p className="text-sm">
                <b>Relasi:</b>{' '}
                {relationships.find((x) => x[0] === item.relationship)?.[1] ??
                    item.relationship}
            </p>
            <p className="text-sm">
                <b>Alasan:</b> {item.rationale || 'Belum ditulis'}
            </p>
            <p className="text-sm">
                <b>Kesenjangan:</b> {item.known_gaps || 'Belum ditulis'}
            </p>
            {item.reviewer_email && (
                <p className="text-xs text-muted-foreground">
                    Reviewer: {item.reviewer_email}
                </p>
            )}
            <section
                aria-label="Kesiapan telaah"
                className="space-y-2 rounded-lg border p-3 text-sm"
            >
                <p className="font-semibold">Kesiapan telaah</p>
                <p>{readinessText[item.review_readiness.state]}</p>
                {item.review_readiness.state === 'READY_TO_REQUEST' && (
                    <p className="text-muted-foreground">
                        Pilih akun Laravel terverifikasi yang berbeda dari
                        pemilik draf.
                    </p>
                )}
                {item.review_readiness.state === 'NEEDS_CONTEXT' && (
                    <>
                        <ul className="list-disc space-y-1 pl-5">
                            {Object.entries(item.review_readiness.blockers).map(
                                ([field, message]) => (
                                    <li key={field}>{message}</li>
                                ),
                            )}
                        </ul>
                        {'signal_id' in item.review_readiness.blockers && (
                            <Link href={investigationUrl} className="underline">
                                Buka investigasi untuk memeriksa referensi
                            </Link>
                        )}
                    </>
                )}
            </section>
            {item.workflow_state === 'REVIEW_RECORDED' &&
                item.reviews[0]?.evidence_revision === item.revision && (
                    <>
                        <p className="text-sm text-muted-foreground">
                            Gunakan catatan peninjau di bawah untuk merevisi
                            draf sebelum mengajukan telaah lagi.
                        </p>
                        <button
                            type="button"
                            onClick={() => setEdit((v) => !v)}
                            className="rounded-md border px-3 py-2 text-xs font-medium"
                        >
                            {edit ? 'Tutup editor' : 'Revisi setelah telaah'}
                        </button>
                        {edit && (
                            <EvidenceForm
                                key={`${item.id}-${item.revision}`}
                                item={item}
                                linkedSignalIds={[]}
                                candidateId={candidateId}
                            />
                        )}
                    </>
                )}
            {item.workflow_state === 'DRAFT' && (
                <>
                    <button
                        type="button"
                        onClick={() => setEdit((v) => !v)}
                        className="rounded-md border px-3 py-2 text-xs font-medium"
                    >
                        {edit ? 'Tutup editor' : 'Sunting draf'}
                    </button>
                    {edit && (
                        <EvidenceForm
                            key={`${item.id}-${item.revision}`}
                            item={item}
                            linkedSignalIds={[]}
                            candidateId={candidateId}
                        />
                    )}
                    {item.review_readiness.state === 'READY_TO_REQUEST' && (
                        <form
                            onSubmit={requestReview}
                            className="flex flex-wrap items-end gap-3 rounded-lg border bg-muted/30 p-3"
                        >
                            <label className="min-w-[210px] flex-1 text-xs font-medium">
                                Email reviewer (akun Laravel terverifikasi lain)
                                <input
                                    className={input}
                                    type="email"
                                    required
                                    maxLength={255}
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    placeholder="reviewer@example.org"
                                />
                            </label>
                            <button
                                disabled={busy}
                                className="inline-flex items-center gap-2 rounded-md bg-foreground px-3 py-2.5 text-xs font-semibold text-background"
                            >
                                <Send className="h-3.5 w-3.5" />{' '}
                                {item.reviews.length
                                    ? 'Ajukan telaah ulang'
                                    : 'Minta telaah'}
                            </button>
                            {Object.entries(errors).map(([k, v]) => (
                                <p
                                    role="alert"
                                    key={k}
                                    className="basis-full text-xs text-destructive"
                                >
                                    {v}
                                </p>
                            ))}
                        </form>
                    )}
                </>
            )}
            <details className="rounded-lg border p-3 text-sm">
                <summary className="cursor-pointer font-semibold">
                    Riwayat perubahan
                </summary>
                {item.history.length === 0 ? (
                    <p className="mt-2 text-muted-foreground">
                        Belum ada catatan perubahan.
                    </p>
                ) : (
                    <ol className="mt-2 space-y-2">
                        {item.history.map((entry) => (
                            <li
                                key={entry.revision}
                                className="flex flex-wrap gap-x-2"
                            >
                                <span>
                                    v{entry.revision} ·{' '}
                                    {auditLabels[entry.action] ??
                                        entry.action.replaceAll('_', ' ')}
                                </span>
                                <span className="text-muted-foreground">
                                    {entry.created_at}
                                </span>
                            </li>
                        ))}
                    </ol>
                )}
            </details>
            {item.reviews.map((r) => (
                <div
                    key={r.id}
                    className="rounded-lg border bg-muted/30 p-3 text-sm"
                >
                    <span className="flex items-center gap-2 font-semibold">
                        <History className="h-4 w-4" /> Catatan reviewer · v
                        {r.evidence_revision} ·{' '}
                        {r.review_finding.replaceAll('_', ' ')}
                    </span>
                    <p className="mt-1 whitespace-pre-wrap">{r.review_notes}</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {r.created_at}
                    </p>
                </div>
            ))}
        </article>
    );
}
export default function EvidenceIntake({
    candidate,
    linkedSignalIds,
    investigationUrl,
    evidence,
}: Props) {
    const unused = linkedSignalIds.filter(
        (id) => !evidence.some((e) => e.signal_id === id),
    );
    return (
        <AppLayout
            breadcrumbs={[
                {
                    title: 'SIFI — Issue Candidates',
                    href: '/sifi/issue-candidates',
                },
                {
                    title: 'Evidence Intake',
                    href: `/sifi/issue-candidates/${candidate.id}/evidence`,
                },
            ]}
        >
            <Head title="SIFI · Evidence Intake" />
            <main className="min-h-screen bg-muted/20 px-4 py-7 lg:px-8">
                <div className="mx-auto max-w-[1200px] space-y-5">
                    <header>
                        <Link
                            href={`/sifi/issue-candidates/${candidate.id}`}
                            className="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <ArrowLeft className="h-4 w-4" /> Kembali ke Issue
                            Candidate
                        </Link>
                        <p className="mt-4 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                            SIFI / Evidence Intake
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold">
                            Evidence Candidate Workspace
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {candidate.title}
                        </p>
                    </header>
                    <div
                        role="alert"
                        className="flex gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm"
                    >
                        <ShieldAlert className="h-5 w-5 shrink-0 text-amber-600" />
                        <p>
                            <b>LOCAL TRIAGE ONLY — 0 ADMITTED EVIDENCE.</b>{' '}
                            Signal fixture maupun snapshot nyata belum
                            membuktikan klaim. Review hanya mencatat
                            keterbatasan atau tindak lanjut; tidak mengesahkan
                            klaim, hak sumber, atau Python Issue Object.
                        </p>
                    </div>
                    <div className="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(300px,1fr)]">
                        <section className="min-w-0 space-y-4">
                            <h2 className="flex items-center gap-2 text-lg font-semibold">
                                <ClipboardCheck className="h-5 w-5" /> Kandidat
                                bukti ({evidence.length})
                            </h2>
                            {evidence.length === 0 ? (
                                <p className="rounded-xl border bg-card p-5 text-sm text-muted-foreground">
                                    Belum ada kandidat bukti.
                                </p>
                            ) : (
                                evidence.map((item) => (
                                    <EvidenceCard
                                        key={`${item.id}-${item.revision}`}
                                        item={item}
                                        candidateId={candidate.id}
                                        investigationUrl={investigationUrl}
                                    />
                                ))
                            )}
                        </section>
                        <aside className="space-y-4">
                            <h2 className="text-lg font-semibold">
                                Tambahkan referensi
                            </h2>
                            {unused.length > 0 ? (
                                <EvidenceForm
                                    key={unused.join('')}
                                    candidateId={candidate.id}
                                    linkedSignalIds={unused}
                                />
                            ) : (
                                <div className="rounded-xl border bg-card p-5 text-sm text-muted-foreground">
                                    Tidak ada referensi sinyal yang belum
                                    digunakan. Tambahkan sinyal di Investigation
                                    Workspace jika diperlukan.
                                </div>
                            )}
                            <p className="rounded-xl border p-4 text-sm text-muted-foreground">
                                Pelimpahan review hanya untuk pengguna Laravel
                                lain yang sudah memiliki akun terverifikasi.
                                Tidak ada otoritas penerimaan bukti pada tahap
                                ini.
                            </p>
                            <Link
                                href="/sifi/evidence-reviews"
                                className="inline-block text-sm font-semibold underline"
                            >
                                Buka antrean reviewer →
                            </Link>
                        </aside>
                    </div>
                </div>
            </main>
        </AppLayout>
    );
}
