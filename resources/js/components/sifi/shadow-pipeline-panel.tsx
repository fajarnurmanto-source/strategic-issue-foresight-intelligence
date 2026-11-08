import { Link } from '@inertiajs/react';

export type ShadowTimeline = {
    stages: Record<string, string>;
    warnings: string[];
    source_sha256: string | null;
    review_state: string | null;
    review_url: string | null;
    investigation_url: string | null;
    candidate_url: string | null;
    brief_url: string | null;
    issue_case_state: 'MISSING' | 'DRAFT_AVAILABLE' | 'UNAVAILABLE' | null;
    issue_case_url: string | null;
    brief_readiness: keyof typeof briefStatus | null;
    next_context_url: string | null;
};

const briefStatus = {
    ISSUE_CASE_REQUIRED: 'Buat dan simpan draf Issue Case untuk kandidat isu ini.',
    TRAJECTORY_REQUIRED: 'Simpan draf Trajectory untuk melanjutkan.',
    FORESIGHT_REQUIRED: 'Simpan draf Foresight untuk membuka Executive Brief.',
    CONTEXT_UNAVAILABLE: 'Konteks briefing tidak tersedia untuk sesi ini.',
    READY: 'Executive Brief siap dibuka.',
} as const;

export default function ShadowPipelinePanel({ timeline }: { timeline: ShadowTimeline }) {
    const steps = [
        ['Sumber tersimpan', timeline.stages.real_acquisition === 'SOURCE_METADATA_RECORDED' ? 'Metadata tercatat' : 'Belum tercatat'],
        ['Signal, investigasi, dan kandidat isu', timeline.review_url ? 'Rantai terhubung' : 'Rantai belum dikonfirmasi'],
        ['Review evidence', timeline.review_state === 'REVIEW_RECORDED' ? 'Penilaian tercatat' :
            timeline.review_state === 'REVIEW_REQUESTED' ? 'Menunggu peninjau' :
            timeline.review_state === 'DRAFT' ? 'Draf lokal' : 'Belum dikonfirmasi'],
        ['Intelligence Brief', timeline.stages.intelligence_brief === 'DRAFT_RECORDED' ? 'Draf tersimpan' :
            timeline.stages.intelligence_brief === 'INTEGRITY_WARNING' ? 'Perlu cek integritas' : 'Belum tercatat'],
    ];

    return (
        <section aria-labelledby="core-v2-timeline" className="space-y-3 rounded-lg border p-4 text-sm">
            <div>
                <p className="text-xs text-muted-foreground">Core V2 · Pratinjau hanya baca</p>
                <h2 id="core-v2-timeline" className="text-lg font-semibold">Alur riset dan review</h2>
            </div>
            <ol className="grid gap-2 sm:grid-cols-2">
                {steps.map(([label, status]) => (
                    <li key={label} className="min-w-0 rounded-md border p-3">
                        <p className="font-medium">{label}</p>
                        <p className="text-muted-foreground">{status}</p>
                    </li>
                ))}
            </ol>
            {timeline.source_sha256 && (
                <p>SHA-256 sumber <code className="break-all">{timeline.source_sha256}</code></p>
            )}
            {timeline.warnings.length > 0 && (
                <details>
                    <summary className="cursor-pointer font-medium">Referensi perlu diperiksa</summary>
                    <ul className="mt-2 space-y-1">
                        {timeline.warnings.map((warning) => <li key={warning} className="break-all">{warning}</li>)}
                    </ul>
                </details>
            )}
            <p>Status penerimaan evidence resmi belum ditetapkan.</p>
            {timeline.review_url && (
                <nav aria-label="Tindak lanjut sesi" className="flex flex-wrap gap-x-4 gap-y-2">
                    {timeline.investigation_url && <Link href={timeline.investigation_url} className="underline">Buka investigasi</Link>}
                    {timeline.candidate_url && <Link href={timeline.candidate_url} className="underline">Buka kandidat isu</Link>}
                    <Link href={timeline.review_url} className="underline">Buka Evidence Intake</Link>
                    {timeline.issue_case_url && <Link href={timeline.issue_case_url} className="underline">Buka Issue Case</Link>}
                    {timeline.next_context_url && (
                        <Link href={timeline.next_context_url} className="underline">
                            {timeline.brief_readiness === 'TRAJECTORY_REQUIRED' ? 'Buka Trajectory' : 'Buka Foresight'}
                        </Link>
                    )}
                    {timeline.brief_url && <Link href={timeline.brief_url} className="underline">Buka Executive Brief</Link>}
                </nav>
            )}
            {timeline.issue_case_state && (
                <p className="text-muted-foreground">
                    Issue Case {timeline.issue_case_state === 'DRAFT_AVAILABLE' ? 'tersedia sebagai draf.' :
                        timeline.issue_case_state === 'MISSING' ? 'belum dibuat.' : 'tidak tersedia untuk sesi ini.'}
                </p>
            )}
            {timeline.brief_readiness && (
                <p className="text-muted-foreground">{briefStatus[timeline.brief_readiness]}</p>
            )}
        </section>
    );
}