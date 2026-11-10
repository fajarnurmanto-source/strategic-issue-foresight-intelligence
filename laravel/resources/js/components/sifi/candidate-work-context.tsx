import { Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export type WorkContext = {
    source_mode?:
        | 'REAL_PUBLIC_ARCHIVE'
        | 'MOCK_ACQUISITION_ONLY'
        | 'OFFLINE_FIXTURE_ONLY';
    candidate: {
        id: string;
        revision: number;
        title: string;
        issue_hypothesis: string | null;
        exposure_notes: string | null;
        evidence_gaps: string | null;
        next_checks: string | null;
        review_state: 'DRAFT' | 'REVIEW_REQUESTED' | 'ON_HOLD';
    };
    claim_input_digest: string;
    signal_reference_count: number;
    diagnostics: {
        candidate_id: string;
        candidate_revision: number;
        claim_count: number;
        displayed_claim_count: number;
        event_count: number;
        link_count: number;
        claims_truncated: boolean;
        comparison_eligible: false;
        review_authorized: false;
        evidence_admission: 'NOT_EVALUATED';
        structured_context: 'NOT_ESTABLISHED';
        historical_custody: 'NOT_ATTESTED';
        claims: { reason_codes: string[] }[];
    };
};

const reasonLabels: Record<string, string> = {
    STRUCTURED_PROPOSITION_NOT_CAPTURED: 'Proposisi terstruktur belum dicatat.',
    COMPARISON_SCOPE_NOT_CAPTURED:
        'Scope perbandingan belum terikat sebagai konteks yang sah.',
    HISTORICAL_CUSTODY_NOT_ATTESTED:
        'Custody historis belum memiliki attestation.',
    DRAFT_EVENT_LINK_MISSING:
        'Ada draf klaim tanpa hubungan ke hipotesis peristiwa.',
    NON_ASSERTED_DRAFT_MODALITY:
        'Ada modalitas laporan, dugaan, atau belum ditentukan.',
};

function candidatePayload(
    candidate: WorkContext['candidate'],
    next = candidate.next_checks ?? '',
) {
    return {
        title: candidate.title,
        issue_hypothesis: candidate.issue_hypothesis,
        exposure_notes: candidate.exposure_notes,
        evidence_gaps: candidate.evidence_gaps,
        next_checks: next,
        review_state: candidate.review_state,
        revision: candidate.revision,
    };
}

export default function CandidateWorkContext({
    context,
}: {
    context: WorkContext;
}) {
    const { candidate, diagnostics } = context;
    // Reuse the complete native update contract so no other analyst field is cleared.
    const form = useForm(candidatePayload(candidate));
    const stale = form.data.revision !== candidate.revision;
    const root = `/sifi/issue-candidates/${candidate.id}`;
    const coherent =
        diagnostics.candidate_id === candidate.id &&
        diagnostics.candidate_revision === candidate.revision &&
        diagnostics.comparison_eligible === false &&
        diagnostics.review_authorized === false &&
        diagnostics.historical_custody === 'NOT_ATTESTED' &&
        diagnostics.structured_context === 'NOT_ESTABLISHED' &&
        diagnostics.evidence_admission === 'NOT_EVALUATED' &&
        /^[a-f0-9]{64}$/.test(context.claim_input_digest);
    if (!coherent) {
        return (
            <section role="alert" className="rounded-xl border bg-card p-5">
                Konteks tidak konsisten. Muat ulang Workbench; hasil tidak dapat
                digunakan.
            </section>
        );
    }
    const reasons = [
        ...new Set(diagnostics.claims.flatMap((claim) => claim.reason_codes)),
    ];
    function save(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.patch(root, {
            preserveScroll: true,
            onSuccess: () =>
                router.visit(`/sifi/workbench?candidate=${candidate.id}`, {
                    preserveScroll: true,
                }),
        });
    }
    const text = (value: string | null) =>
        value?.trim() || 'Belum dicatat oleh analis.';
    const linkClass =
        'rounded-lg border px-3 py-2 text-sm font-medium hover:bg-muted';
    return (
        <section
            aria-label="Konteks dan tindak lanjut kandidat"
            className="space-y-5 rounded-xl border bg-card p-5"
        >
            <header className="space-y-2">
                <p className="text-xs font-semibold tracking-wider text-primary uppercase dark:text-blue-200">
                    Kandidat aktif · NON_BINDING · NOT VERIFIED
                </p>
                <h2 className="text-xl font-semibold break-words">
                    {candidate.title}
                </h2>
                <p className="text-xs text-muted-foreground">
                    Revisi {candidate.revision} · {candidate.review_state} ·
                    Snapshot saat halaman dimuat. Muat ulang setelah perubahan
                    dari sesi lain.
                </p>
            </header>
            <dl className="grid gap-4 text-sm lg:grid-cols-3">
                {[
                    ['Hipotesis isu', candidate.issue_hypothesis],
                    [
                        'Alasan perhatian — catatan analis',
                        candidate.exposure_notes,
                    ],
                    [
                        'Kekurangan bukti — catatan analis',
                        candidate.evidence_gaps,
                    ],
                ].map(([label, value]) => (
                    <div
                        key={label}
                        className="min-w-0 rounded-lg bg-muted/50 p-4"
                    >
                        <dt className="font-semibold">{label}</dt>
                        <dd className="mt-2 break-words whitespace-pre-wrap text-muted-foreground">
                            {text(value)}
                        </dd>
                    </div>
                ))}
            </dl>
            <div className="grid gap-4 lg:grid-cols-2">
                <section
                    aria-label="Batas analisis"
                    className="space-y-3 rounded-lg border p-4 text-sm"
                >
                    <h3 className="font-semibold">
                        Perbandingan klaim: ABSTAIN
                    </h3>
                    <p>
                        {context.signal_reference_count}{' '}
                        {context.source_mode === 'MOCK_ACQUISITION_ONLY'
                            ? 'referensi MOCK/SIMULASI'
                            : context.source_mode === 'REAL_PUBLIC_ARCHIVE'
                              ? 'referensi arsip publik nyata'
                              : 'referensi publikasi fixture'}{' '}
                        · {diagnostics.claim_count} draf klaim ·{' '}
                        {diagnostics.event_count} hipotesis peristiwa ·{' '}
                        {diagnostics.link_count} hubungan draf.
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Jumlah referensi bukan jumlah sumber independen,
                        kejadian, atau ukuran kepentingan isu. Konteks
                        terstruktur NOT_ESTABLISHED; custody historis
                        NOT_ATTESTED.
                    </p>
                    {diagnostics.claim_count === 0 && (
                        <p>
                            Belum ada draf klaim. Susun konteks melalui
                            Claim/Event.
                        </p>
                    )}
                    <ul className="list-disc space-y-1 pl-5">
                        {reasons.map((reason) => (
                            <li key={reason}>
                                {reasonLabels[reason] ??
                                    'Rincian belum dikenali; periksa melalui Claim Comparison Preview.'}
                            </li>
                        ))}
                    </ul>
                    {diagnostics.claims_truncated && (
                        <p className="text-xs text-muted-foreground">
                            Alasan di atas mencakup maksimal{' '}
                            {diagnostics.displayed_claim_count} dari{' '}
                            {diagnostics.claim_count} draf; bukan diagnosis
                            seluruh kandidat.
                        </p>
                    )}
                    <p className="text-xs break-all text-muted-foreground">
                        Fingerprint konteks klaim: {context.claim_input_digest}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={`${root}/claim-events`}
                            className={linkClass}
                        >
                            Buka Claim/Event
                        </Link>
                        <Link
                            href={`${root}/claim-comparison-preview`}
                            className={linkClass}
                        >
                            Buka Claim Comparison Preview
                        </Link>
                        <Link
                            href="/sifi/signal-relations"
                            className={linkClass}
                        >
                            Periksa Signal Relations
                        </Link>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Signal Relations mencakup corpus fixture, bukan otomatis
                        hanya referensi kandidat ini. Periksa asal publikasi
                        sebelum menyimpulkan independensi.
                    </p>
                </section>
                <form
                    onSubmit={save}
                    className="space-y-3 rounded-lg border p-4"
                >
                    <h3 className="font-semibold">
                        Prioritas pemeriksaan — catatan analis
                    </h3>
                    <p className="text-xs text-muted-foreground">
                        Bukan skor prioritas isu atau peringkat kondisi publik.
                        Catat pemeriksaan berikutnya dan dasar penelusurannya;
                        tidak menjalankan tindakan otomatis.
                    </p>
                    <label
                        htmlFor={`next-checks-${candidate.id}`}
                        className="block text-sm font-medium"
                    >
                        Tindak lanjut analis
                    </label>
                    <textarea
                        id={`next-checks-${candidate.id}`}
                        value={form.data.next_checks}
                        onChange={(event) =>
                            form.setData('next_checks', event.target.value)
                        }
                        rows={5}
                        maxLength={4000}
                        className="w-full rounded-lg border bg-background p-3 text-sm"
                    />
                    <p className="text-xs text-muted-foreground">
                        Catatan lokal disusun dari revisi {form.data.revision}.
                    </p>
                    {Object.keys(form.errors).length > 0 && (
                        <div role="alert" className="text-sm text-destructive">
                            {Object.values(form.errors).join(' · ')} Muat ulang
                            bila revisi kandidat berubah; catatan belum
                            disimpan.
                        </div>
                    )}
                    {stale && (
                        <div
                            role="alert"
                            className="space-y-2 rounded-lg border border-amber-500/40 p-3 text-sm"
                        >
                            <p>
                                Kandidat berubah ke revisi {candidate.revision}.
                                Catatan lokal Anda tetap di kolom; belum
                                disimpan.
                            </p>
                            <p className="break-words whitespace-pre-wrap">
                                Catatan terbaru tersimpan:{' '}
                                {text(candidate.next_checks)}
                            </p>
                            <button
                                type="button"
                                onClick={() => {
                                    form.setData(
                                        candidatePayload(
                                            candidate,
                                            form.data.next_checks,
                                        ),
                                    );
                                    form.clearErrors();
                                }}
                                className="rounded-md border px-3 py-2 text-sm"
                            >
                                Gunakan revisi terbaru untuk catatan lokal
                            </button>
                            <p className="text-xs text-muted-foreground">
                                Bandingkan kedua catatan dahulu. Tombol ini
                                tidak menyimpan; tinjau lalu tekan Simpan secara
                                manual.
                            </p>
                        </div>
                    )}
                    <button
                        type="submit"
                        disabled={form.processing || !form.isDirty || stale}
                        className="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                    >
                        {form.processing
                            ? 'Menyimpan…'
                            : 'Simpan tindak lanjut'}
                    </button>
                    <p className="text-xs text-muted-foreground">
                        Memakai revision check dan audit kandidat existing.
                        Status review serta field lain dipertahankan. Tidak
                        memberi evidence admission atau izin keputusan.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Link href={root} className={linkClass}>
                            Buka assessment & riwayat
                        </Link>
                        <Link href={`${root}/issue-case`} className={linkClass}>
                            Lanjutkan Issue Case
                        </Link>
                    </div>
                </form>
            </div>
        </section>
    );
}
