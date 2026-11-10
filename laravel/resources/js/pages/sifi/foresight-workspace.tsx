import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import {
    ArrowLeft,
    ShieldAlert,
    Save,
    RefreshCw,
    History,
    GitBranch,
    Telescope,
    Eye,
} from 'lucide-react';

type Draft = {
    id: string;
    revision: number;
    verification_state: string;
    baseline_scenario: string;
    alternative_scenario: string;
    stress_scenario: string;
    critical_drivers: string;
    falsification_checks: string;
    decision_options: string;
    uncertainty_note: string;
};
type Result = {
    foresight_marker: string;
    scenario_labels: string[];
    forecast_available: boolean;
    scenario_probability_available: boolean;
    decision_authorized: boolean;
    verification_checks: string[];
    temporal_observations_verified: number;
};
type Props = {
    candidate: { id: string; title: string; revision: number };
    issueCase: {
        id: string;
        title: string;
        revision: number;
        issue_domain: string;
        geography_label: string;
    };
    trajectory: { id: string; revision: number; horizon: string };
    draft: Draft | null;
    counts: Record<string, number>;
    advisories?: {
        id: number;
        created_at: string;
        is_current: boolean;
        result: Result;
    }[];
    audit?: { revision: number; action: string; created_at: string }[];
};
const urlFor = (id: string) => `/sifi/issue-candidates/${id}/foresight`;
const fieldClass =
    'mt-2 w-full rounded-lg border bg-background px-3 py-2.5 text-sm';
const card = 'rounded-xl border bg-card p-5';
export default function ForesightWorkspace({
    candidate,
    issueCase,
    trajectory,
    draft,
    counts,
    advisories = [],
    audit = [],
}: Props) {
    const [baseline, setBaseline] = useState(
        draft?.baseline_scenario ||
            'Jika kondisi belum berubah, pengujian lanjutan diperlukan sebelum menentukan arah isu.',
    );
    const [alternative, setAlternative] = useState(
        draft?.alternative_scenario ||
            'Jika ditemukan bukti baru, penafsiran awal dan pilihan respons mungkin berubah.',
    );
    const [stress, setStress] = useState(
        draft?.stress_scenario ||
            'Jika risiko yang masih hipotetis terjadi, perlu penilaian konsekuensi yang terverifikasi.',
    );
    const [drivers, setDrivers] = useState(
        draft?.critical_drivers ||
            'Identifikasi faktor pemicu dan kondisi eksternal yang perlu diuji.',
    );
    const [falsifiers, setFalsifiers] = useState(
        draft?.falsification_checks ||
            'Catat kondisi dan data yang dapat membatalkan setiap skenario.',
    );
    const [options, setOptions] = useState(
        draft?.decision_options ||
            'Susun opsi respons yang memerlukan persetujuan dan uji manfaat serta risiko.',
    );
    const [uncertainty, setUncertainty] = useState(
        draft?.uncertainty_note ||
            'Tidak tersedia deret waktu atau bukti terverifikasi; skenario hanya alat eksplorasi.',
    );
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const url = urlFor(candidate.id);
    const common = {
        candidate_revision: candidate.revision,
        case_revision: issueCase.revision,
        trajectory_revision: trajectory.revision,
        ...(draft ? { draft_revision: draft.revision } : {}),
    };
    const values = {
        baseline_scenario: baseline,
        alternative_scenario: alternative,
        stress_scenario: stress,
        critical_drivers: drivers,
        falsification_checks: falsifiers,
        decision_options: options,
        uncertainty_note: uncertainty,
    };
    const dirty =
        !!draft &&
        Object.entries(values).some(([k, v]) => v !== draft[k as keyof Draft]);
    function save(e: FormEvent) {
        e.preventDefault();
        setError('');
        router.post(
            url,
            { ...common, ...values },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: (e) => setError(Object.values(e).join(' · ')),
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
            onError: (e) => setError(Object.values(e).join(' · ')),
        });
    }
    const fields = [
        {
            title: 'Baseline scenario',
            help: 'Kemungkinan jika tidak ada perubahan material; bukan prediksi.',
            value: baseline,
            set: setBaseline,
        },
        {
            title: 'Alternative scenario',
            help: 'Skenario alternatif berdasarkan kondisi yang perlu diuji.',
            value: alternative,
            set: setAlternative,
        },
        {
            title: 'Stress scenario',
            help: 'Apa yang mungkin terjadi jika risiko bersyarat terwujud?',
            value: stress,
            set: setStress,
        },
        {
            title: 'Critical drivers',
            help: 'Faktor pendorong dan indikator yang membedakan skenario.',
            value: drivers,
            set: setDrivers,
        },
        {
            title: 'Falsification & watch tests',
            help: 'Data yang dapat membantah skenario, kondisi uji, dan frekuensi pemantauan yang diusulkan.',
            value: falsifiers,
            set: setFalsifiers,
        },
        {
            title: 'Decision options (not approved)',
            help: 'Opsi tindakan dan trade-off untuk dinilai manusia, bukan rekomendasi final.',
            value: options,
            set: setOptions,
        },
        {
            title: 'Uncertainty & evidence gaps',
            help: 'Apa yang belum diketahui, termasuk ketidakpastian sumber dan waktu?',
            value: uncertainty,
            set: setUncertainty,
        },
    ];
    const latest = advisories[0];
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Issue Candidates', href: '/sifi/issue-candidates' },
                {
                    title: 'Trajectory',
                    href: `/sifi/issue-candidates/${candidate.id}/trajectory`,
                },
                { title: 'Foresight Studio', href: url },
            ]}
        >
            <Head title="SIFI · Foresight Studio" />
            <main className="min-h-screen bg-muted/20 px-4 py-7 lg:px-8">
                <div className="mx-auto max-w-[1360px] space-y-5">
                    <header>
                        <Link
                            href={`/sifi/issue-candidates/${candidate.id}/trajectory`}
                            className="inline-flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <ArrowLeft className="h-4 w-4" /> Kembali ke
                            Trajectory
                        </Link>
                        <p className="mt-4 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                            SIFI / FORESIGHT INTELLIGENCE
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold">
                            Foresight Studio
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {issueCase.title} · Issue Case v{issueCase.revision}{' '}
                            · Trajectory v{trajectory.revision} · Horizon:{' '}
                            {trajectory.horizon}
                        </p>
                    </header>
                    <div
                        role="alert"
                        className="flex gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm"
                    >
                        <ShieldAlert className="h-5 w-5 shrink-0 text-amber-600" />
                        <p>
                            <b>OFFLINE FIXTURE ONLY · SCENARIO HYPOTHESES.</b>{' '}
                            Belum tersedia dasar prediksi terverifikasi.
                            Skenario tidak memiliki probabilitas, risiko
                            terukur, atau otorisasi keputusan. Evidence tetap
                            NOT_EVALUATED.
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
                    <div className="grid gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(320px,1fr)]">
                        <form onSubmit={save} className={`${card} space-y-4`}>
                            <h2 className="flex items-center gap-2 border-b pb-4 text-lg font-semibold">
                                <GitBranch className="h-5 w-5" /> Scenario
                                Construction
                            </h2>
                            {draft && (
                                <p className="text-xs text-muted-foreground">
                                    Draf v{draft.revision} ·{' '}
                                    {draft.verification_state}
                                </p>
                            )}
                            {fields.map((f) => (
                                <label
                                    key={f.title}
                                    className="block text-sm font-semibold"
                                >
                                    {f.title}
                                    <p className="my-1 text-xs font-normal text-muted-foreground">
                                        {f.help}
                                    </p>
                                    <textarea
                                        className={fieldClass}
                                        rows={3}
                                        required
                                        minLength={3}
                                        maxLength={2500}
                                        value={f.value}
                                        onChange={(e) => f.set(e.target.value)}
                                    />
                                </label>
                            ))}
                            <div className="flex items-center justify-between gap-3 border-t pt-4">
                                <p className="text-xs text-muted-foreground">
                                    Simpan manual · optimistic revision · audit
                                    append-only
                                </p>
                                <button
                                    disabled={busy}
                                    className="inline-flex items-center gap-2 rounded-lg bg-foreground px-4 py-2.5 text-sm font-semibold text-background disabled:opacity-40"
                                >
                                    <Save className="h-4 w-4" /> Simpan skenario
                                </button>
                            </div>
                        </form>
                        <aside className="space-y-5">
                            <section className={`${card} space-y-3`}>
                                <h2 className="flex items-center gap-2 font-semibold">
                                    <Telescope className="h-4 w-4" /> Python
                                    Foresight Advisory
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Memeriksa batas struktur skenario, bukan
                                    melakukan ramalan atau simulasi
                                    probabilistik.
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
                                        Simpan draf skenario terlebih dahulu.
                                    </p>
                                )}
                                {dirty && (
                                    <p className="text-xs text-muted-foreground">
                                        Simpan perubahan sebelum memeriksa.
                                    </p>
                                )}
                                {latest && (
                                    <div className="space-y-3 rounded-lg border p-3 text-sm">
                                        <p className="font-semibold">
                                            Snapshot #{latest.id} ·{' '}
                                            {latest.result.foresight_marker}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {latest.is_current
                                                ? 'Input terkini'
                                                : 'Input berubah — perlu pemeriksaan ulang'}{' '}
                                            · {latest.created_at}
                                        </p>
                                        <p className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-xs">
                                            NOT VERIFIED · Forecast: NO ·
                                            Probability: NO · Decision approved:
                                            NO
                                        </p>
                                        <div className="grid grid-cols-3 gap-2">
                                            {latest.result.scenario_labels.map(
                                                (x) => (
                                                    <p
                                                        key={x}
                                                        className="rounded bg-muted p-2 text-center text-xs font-semibold"
                                                    >
                                                        {x}
                                                    </p>
                                                ),
                                            )}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {
                                                latest.result
                                                    .temporal_observations_verified
                                            }{' '}
                                            observasi temporal terverifikasi
                                        </p>
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
                                    <History className="h-4 w-4" /> Histori
                                    revisi
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
                                    href={`/sifi/issue-candidates/${candidate.id}/executive-brief`}
                                    className={`${card} flex items-center gap-2 text-sm font-semibold hover:bg-muted`}
                                >
                                    Buka Executive Intelligence & Briefing →
                                </Link>
                            )}
                            <Link
                                href={`/sifi/issue-candidates/${candidate.id}/trajectory`}
                                className={`${card} flex items-center gap-2 text-sm font-semibold`}
                            >
                                <Eye className="h-4 w-4" /> Kembali ke
                                Trajectory
                            </Link>
                        </aside>
                    </div>
                </div>
            </main>
        </AppLayout>
    );
}
