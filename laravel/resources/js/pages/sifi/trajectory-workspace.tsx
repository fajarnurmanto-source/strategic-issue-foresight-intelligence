import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import {
    ArrowLeft,
    ShieldAlert,
    Save,
    RefreshCw,
    History,
    Activity,
    Scale,
    Eye,
} from 'lucide-react';
type Draft = {
    id: string;
    revision: number;
    horizon: string;
    trajectory_hypothesis: string;
    risk_hypothesis: string;
    opportunity_hypothesis: string;
    watch_condition: string;
    uncertainty_note: string;
    verification_state: string;
};
type Result = {
    trajectory_marker: string;
    risk_marker: string;
    opportunity_marker: string;
    watch_marker: string;
    observed_metadata: Record<string, number>;
    temporal_observations_verified: number;
    verification_checks: string[];
    verification_state: string;
    evidence_admission: string;
};
type FreshnessChange = {
    code: string;
    label: string;
    snapshot: number | null;
    current: number | null;
};
type Freshness = {
    status: 'CURRENT' | 'STALE';
    reason_codes: string[];
    changes: FreshnessChange[];
};
type Props = {
    candidate: { id: string; title: string; revision: number };
    issueCase: {
        id: string;
        revision: number;
        title: string;
        issue_domain: string;
        geography_label: string;
        lifecycle_state: string;
    };
    draft: Draft | null;
    counts: Record<string, number>;
    advisories?: {
        id: number;
        created_at: string;
        is_current: boolean;
        freshness: Freshness;
        result: Result;
    }[];
    audit?: { revision: number; action: string; created_at: string }[];
};
const base = (id: string) => `/sifi/issue-candidates/${id}/trajectory`;
export default function TrajectoryWorkspace({
    candidate,
    issueCase,
    draft,
    counts,
    advisories = [],
    audit = [],
}: Props) {
    const [horizon, setHorizon] = useState(draft?.horizon || 'UNSPECIFIED');
    const [trajectory, setTrajectory] = useState(
        draft?.trajectory_hypothesis ||
            'Arah perkembangan belum diketahui; perlu deret waktu terverifikasi.',
    );
    const [risk, setRisk] = useState(
        draft?.risk_hypothesis ||
            'Hipotesis risiko masih memerlukan mekanisme dampak dan evidence primer.',
    );
    const [opportunity, setOpportunity] = useState(
        draft?.opportunity_hypothesis ||
            'Hipotesis peluang tindakan masih perlu diuji manfaat dan konsekuensinya.',
    );
    const [watch, setWatch] = useState(
        draft?.watch_condition ||
            'Identifikasi indikator dan ambang perubahan yang perlu diamati.',
    );
    const [uncertainty, setUncertainty] = useState(
        draft?.uncertainty_note ||
            'Belum tersedia observasi temporal dan evidence yang memenuhi syarat.',
    );
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const url = base(candidate.id);
    const common = {
        candidate_revision: candidate.revision,
        case_revision: issueCase.revision,
        ...(draft ? { draft_revision: draft.revision } : {}),
    };
    const dirty =
        !!draft &&
        (horizon !== draft.horizon ||
            trajectory !== draft.trajectory_hypothesis ||
            risk !== draft.risk_hypothesis ||
            opportunity !== draft.opportunity_hypothesis ||
            watch !== draft.watch_condition ||
            uncertainty !== draft.uncertainty_note);
    function submit(e: FormEvent) {
        e.preventDefault();
        setError('');
        router.post(
            url,
            {
                ...common,
                horizon,
                trajectory_hypothesis: trajectory,
                risk_hypothesis: risk,
                opportunity_hypothesis: opportunity,
                watch_condition: watch,
                uncertainty_note: uncertainty,
            },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: (err) => setError(Object.values(err).join(' · ')),
            },
        );
    }
    function assess() {
        if (!draft) return;
        setError('');
        router.post(url + '/assess', common, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onError: (err) => setError(Object.values(err).join(' · ')),
        });
    }
    const fields = [
        {
            title: 'Trajectory hypothesis',
            description:
                'Bagaimana arah perkembangan mungkin berubah? Hindari menyatakan tren yang belum diamati.',
            value: trajectory,
            set: setTrajectory,
        },
        {
            title: 'Risk hypothesis',
            description:
                'Mekanisme risiko dan pihak terdampak yang perlu diuji, tanpa skor.',
            value: risk,
            set: setRisk,
        },
        {
            title: 'Opportunity hypothesis',
            description:
                'Peluang intervensi yang masih harus dinilai manfaat/biayanya.',
            value: opportunity,
            set: setOpportunity,
        },
        {
            title: 'Watch condition',
            description:
                'Sinyal, ambang, dan jangka pemeriksaan yang direncanakan (belum dimonitor).',
            value: watch,
            set: setWatch,
        },
        {
            title: 'Uncertainty / evidence gaps',
            description:
                'Keterbatasan data, kontradiksi, dan kebutuhan verifikasi.',
            value: uncertainty,
            set: setUncertainty,
        },
    ];
    const card = 'rounded-xl border bg-card p-5';
    const field =
        'mt-2 w-full rounded-lg border bg-background px-3 py-2.5 text-sm';
    const latest = advisories[0];
    return (
        <AppLayout
            breadcrumbs={[
                {
                    title: 'SIFI — Issue Candidates',
                    href: '/sifi/issue-candidates',
                },
                {
                    title: 'Issue Case',
                    href: `/sifi/issue-candidates/${candidate.id}/issue-case`,
                },
                { title: 'Trajectory & Risk', href: url },
            ]}
        >
            <Head title="SIFI · Trajectory, Risk & Opportunity" />
            <main className="min-h-screen bg-muted/20 px-4 py-7 lg:px-8">
                <div className="mx-auto max-w-[1360px] space-y-5">
                    <header>
                        <Link
                            href={`/sifi/issue-candidates/${candidate.id}/issue-case`}
                            className="inline-flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <ArrowLeft className="h-4 w-4" /> Kembali ke Issue
                            Case
                        </Link>
                        <p className="mt-4 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                            SIFI / CONDITIONAL INTELLIGENCE
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold">
                            Trajectory, Risk & Opportunity
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {issueCase.title} · Issue Case revisi{' '}
                            {issueCase.revision} · {issueCase.lifecycle_state}
                        </p>
                    </header>
                    <div
                        role="alert"
                        className="flex gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm"
                    >
                        <ShieldAlert className="h-5 w-5 shrink-0 text-amber-600" />
                        <p>
                            <b>OFFLINE FIXTURE ONLY · HYPOTHESIS ONLY.</b> Tidak
                            ada bukti deret waktu nyata. Status WATCHLIST,
                            jumlah publikasi, atau revisi draf bukan tren
                            faktual. Risiko/peluang tidak dihitung sebagai skor.
                            Evidence tetap NOT_EVALUATED.
                        </p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-4">
                        {[
                            { t: 'Claim drafts', v: counts.claim_count },
                            {
                                t: 'Event hypotheses',
                                v: counts.event_hypothesis_count,
                            },
                            {
                                t: 'Possible links',
                                v: counts.possible_link_count,
                            },
                            {
                                t: 'Evidence drafts',
                                v: counts.evidence_candidate_count,
                            },
                        ].map((x) => (
                            <div key={x.t} className={`${card} !p-4`}>
                                <p className="text-xs text-muted-foreground">
                                    {x.t}
                                </p>
                                <b className="mt-1 block text-2xl">
                                    {x.v ?? 0}
                                </b>
                            </div>
                        ))}
                    </div>
                    {error && (
                        <p
                            role="alert"
                            className="rounded-lg border border-destructive/40 bg-card p-3 text-sm text-destructive"
                        >
                            {error}
                        </p>
                    )}
                    <div className="grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,1fr)]">
                        <form onSubmit={submit} className={`${card} space-y-4`}>
                            <h2 className="flex items-center gap-2 border-b pb-4 text-lg font-semibold">
                                <Activity className="h-5 w-5" /> Hipotesis
                                analis (belum terbukti)
                            </h2>
                            {draft && (
                                <p className="text-xs text-muted-foreground">
                                    Draf v{draft.revision} ·{' '}
                                    {draft.verification_state}
                                </p>
                            )}
                            <label className="block text-sm font-semibold">
                                Horizon analisis (label asumsi, bukan perkiraan
                                otomatis)
                                <select
                                    className={field}
                                    value={horizon}
                                    onChange={(e) => setHorizon(e.target.value)}
                                >
                                    <option value="UNSPECIFIED">
                                        Belum ditentukan
                                    </option>
                                    <option value="SHORT_TERM">
                                        Jangka pendek (hipotesis)
                                    </option>
                                    <option value="MEDIUM_TERM">
                                        Jangka menengah (hipotesis)
                                    </option>
                                    <option value="LONG_TERM">
                                        Jangka panjang (hipotesis)
                                    </option>
                                </select>
                            </label>
                            {fields.map((f) => (
                                <label
                                    key={f.title}
                                    className="block text-sm font-semibold"
                                >
                                    {f.title}
                                    <p className="my-1 text-xs font-normal text-muted-foreground">
                                        {f.description}
                                    </p>
                                    <textarea
                                        className={field}
                                        rows={3}
                                        required
                                        minLength={3}
                                        maxLength={3000}
                                        value={f.value}
                                        onChange={(e) => f.set(e.target.value)}
                                    />
                                </label>
                            ))}
                            <div className="flex items-center justify-between gap-3 border-t pt-4">
                                <p className="text-xs text-muted-foreground">
                                    Simpan manual · revisi dilindungi · audit
                                    append-only
                                </p>
                                <button
                                    disabled={busy}
                                    className="inline-flex items-center gap-2 rounded-lg bg-foreground px-4 py-2.5 text-sm font-semibold text-background disabled:opacity-40"
                                >
                                    <Save className="h-4 w-4" /> Simpan
                                    hipotesis
                                </button>
                            </div>
                        </form>
                        <aside className="space-y-5">
                            <section className={`${card} space-y-3`}>
                                <h2 className="flex items-center gap-2 font-semibold">
                                    <Scale className="h-4 w-4" /> Python
                                    Trajectory Advisory
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Menilai batas kemampuan data, bukan
                                    memprediksi risiko atau peluang. Pemeriksaan
                                    tidak mengambil berita atau membuat Issue
                                    Object.
                                </p>
                                <button
                                    type="button"
                                    onClick={assess}
                                    disabled={busy || !draft || dirty}
                                    className="flex w-full items-center justify-center gap-2 rounded-lg bg-foreground p-3 text-sm font-semibold text-background disabled:opacity-40"
                                >
                                    <RefreshCw className="h-4 w-4" /> Jalankan
                                    pemeriksaan offline
                                </button>
                                {!draft && (
                                    <p className="text-xs text-muted-foreground">
                                        Simpan hipotesis terlebih dahulu.
                                    </p>
                                )}
                                {dirty && (
                                    <p className="text-xs text-muted-foreground">
                                        Simpan perubahan sebelum menjalankan
                                        analisis.
                                    </p>
                                )}
                                {latest && (
                                    <div className="space-y-3 rounded-lg border p-3 text-sm">
                                        <p className="font-semibold">
                                            Snapshot #{latest.id} ·{' '}
                                            {latest.result.trajectory_marker}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {latest.is_current
                                                ? 'Input terkini'
                                                : 'Input sudah berubah — jalankan ulang'}{' '}
                                            · {latest.created_at}
                                        </p>
                                        {latest.freshness.status ===
                                            'STALE' && (
                                            <div
                                                role="status"
                                                className="rounded-lg border border-blue-500/30 bg-blue-500/10 p-3"
                                            >
                                                <p className="font-semibold text-blue-800 dark:text-blue-200">
                                                    Mengapa snapshot perlu
                                                    diperiksa ulang
                                                </p>
                                                <ul className="mt-2 space-y-1.5 text-xs">
                                                    {latest.freshness.changes.map(
                                                        (change) => (
                                                            <li
                                                                key={
                                                                    change.code
                                                                }
                                                                className="flex items-center justify-between gap-3"
                                                            >
                                                                <span>
                                                                    {
                                                                        change.label
                                                                    }
                                                                </span>
                                                                <span className="font-mono text-muted-foreground">
                                                                    {change.snapshot ===
                                                                        null ||
                                                                    change.current ===
                                                                        null
                                                                        ? 'berubah'
                                                                        : `${change.snapshot} → ${change.current}`}
                                                                </span>
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    Freshness hanya
                                                    membandingkan input
                                                    snapshot; bukan verifikasi
                                                    trajectory, risiko, atau
                                                    evidence.
                                                </p>
                                            </div>
                                        )}
                                        {latest.freshness.status ===
                                            'CURRENT' && (
                                            <p className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-800 dark:text-emerald-200">
                                                Snapshot masih memakai revisi
                                                dan jumlah input yang sama.
                                                Status ini tidak memverifikasi
                                                isi hipotesis.
                                            </p>
                                        )}
                                        <p className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-xs">
                                            NOT VERIFIED · Risk:{' '}
                                            {latest.result.risk_marker} ·
                                            Opportunity:{' '}
                                            {latest.result.opportunity_marker} ·
                                            Evidence: NOT_EVALUATED
                                        </p>
                                        <div className="rounded-lg bg-muted p-3">
                                            <b>
                                                {
                                                    latest.result
                                                        .temporal_observations_verified
                                                }
                                            </b>
                                            <p className="text-xs text-muted-foreground">
                                                Observasi temporal terverifikasi
                                            </p>
                                        </div>
                                        <ul className="list-disc space-y-2 pl-4 text-sm">
                                            {latest.result.verification_checks.map(
                                                (x, i) => (
                                                    <li key={i}>{x}</li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}
                            </section>
                            <section className={card}>
                                <h2 className="flex items-center gap-2 font-semibold">
                                    <History className="h-4 w-4" /> Audit revisi
                                </h2>
                                <ul className="mt-3 space-y-2 text-xs">
                                    {audit.map((a) => (
                                        <li
                                            key={a.revision}
                                            className="rounded-lg border p-3"
                                        >
                                            v{a.revision} · {a.action}
                                            <p className="text-muted-foreground">
                                                {a.created_at}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                            {draft && (
                                <Link
                                    href={`/sifi/issue-candidates/${candidate.id}/foresight`}
                                    className={`${card} flex items-center gap-2 text-sm font-semibold hover:bg-muted`}
                                >
                                    Buka Foresight Studio →
                                </Link>
                            )}
                            <Link
                                href={`/sifi/issue-candidates/${candidate.id}/issue-case`}
                                className={`${card} flex items-center gap-2 text-sm font-semibold`}
                            >
                                <Eye className="h-4 w-4" /> Kembali ke Issue
                                Case
                            </Link>
                        </aside>
                    </div>
                </div>
            </main>
        </AppLayout>
    );
}
