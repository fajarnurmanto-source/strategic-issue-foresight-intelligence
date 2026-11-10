import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import {
    ArrowLeft,
    ClipboardCheck,
    History,
    Save,
    ShieldAlert,
    Waypoints,
    FlaskConical,
    RefreshCw,
    FileCheck2,
} from 'lucide-react';

type Candidate = {
    id: string;
    investigation_id: string;
    title: string;
    issue_hypothesis: string;
    exposure_notes: string;
    evidence_gaps: string;
    next_checks: string;
    review_state: 'DRAFT' | 'REVIEW_REQUESTED' | 'ON_HOLD';
    revision: number;
};
type Audit = { revision: number; action: string; created_at: string };
type Advisory = {
    schema_version: string;
    mode: string;
    kind: string;
    status: string;
    evidence_admission: string;
    independence_verified: boolean;
    python_issue_created: boolean;
    signal_count: number;
    distinct_source_count: number;
    possible_syndication: boolean;
    signal_ids: string[];
    observations: string[];
    verification_checks: string[];
};
type Assessment = {
    id: number;
    candidate_revision: number;
    status: string;
    result: Advisory;
    created_at: string;
};
type Props = {
    candidate: Candidate;
    audit: Audit[];
    intelligence?: Assessment[];
    attachedSignalIds?: string[];
    scope: string;
};
const labels: Record<string, string> = {
    DRAFT: 'Draf',
    REVIEW_REQUESTED: 'Permintaan telaah',
    ON_HOLD: 'Ditunda',
};
const fmt = (s: string) =>
    new Date(s).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Jakarta',
    }) + ' WIB';
export default function IssueCandidateDetail({
    candidate,
    audit,
    intelligence = [],
    attachedSignalIds = [],
}: Props) {
    const [title, setTitle] = useState(candidate.title);
    const [hypothesis, setHypothesis] = useState(candidate.issue_hypothesis);
    const [exposure, setExposure] = useState(candidate.exposure_notes);
    const [gaps, setGaps] = useState(candidate.evidence_gaps);
    const [next, setNext] = useState(candidate.next_checks);
    const [state, setState] = useState(candidate.review_state);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [running, setRunning] = useState(false);
    const [analysisError, setAnalysisError] = useState('');
    useEffect(() => {
        setTitle(candidate.title);
        setHypothesis(candidate.issue_hypothesis);
        setExposure(candidate.exposure_notes);
        setGaps(candidate.evidence_gaps);
        setNext(candidate.next_checks);
        setState(candidate.review_state);
    }, [candidate]);
    function submit(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        setErrors({});
        router.patch(
            `/sifi/issue-candidates/${candidate.id}`,
            {
                title,
                issue_hypothesis: hypothesis,
                exposure_notes: exposure,
                evidence_gaps: gaps,
                next_checks: next,
                review_state: state,
                revision: candidate.revision,
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onError: (e) => setErrors(e),
            },
        );
    }
    function runAnalysis() {
        setAnalysisError('');
        router.post(
            `/sifi/issue-candidates/${candidate.id}/intelligence`,
            { revision: candidate.revision },
            {
                preserveScroll: true,
                onStart: () => setRunning(true),
                onFinish: () => setRunning(false),
                onError: (issues) =>
                    setAnalysisError(
                        issues.intelligence ||
                            issues.revision ||
                            'Permintaan analisis gagal.',
                    ),
            },
        );
    }
    const latest = intelligence[0];
    const outdated =
        !!latest &&
        (latest.candidate_revision !== candidate.revision ||
            latest.result.signal_ids.join(',') !== attachedSignalIds.join(','));
    return (
        <AppLayout
            breadcrumbs={[
                {
                    title: 'SIFI — Issue Candidates',
                    href: '/sifi/issue-candidates',
                },
                {
                    title: 'Candidate',
                    href: `/sifi/issue-candidates/${candidate.id}`,
                },
            ]}
        >
            <Head title="SIFI · Issue Candidate" />
            <div className="min-h-screen bg-muted/20 px-4 py-6 lg:px-8">
                <div className="mx-auto max-w-[1400px] space-y-5">
                    <header>
                        <Link
                            href="/sifi/issue-candidates"
                            className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="h-4 w-4" /> Semua kandidat
                        </Link>
                        <div className="mt-3 flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                                    SIFI / Issue Construction
                                </p>
                                <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                                    Issue Candidate Workspace
                                </h1>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Usulan analis untuk diuji, bukan klaim fakta
                                    yang telah disahkan.
                                </p>
                            </div>
                            <span className="rounded-full border bg-card px-3 py-2 text-xs font-semibold">
                                Revisi {candidate.revision} ·{' '}
                                {labels[candidate.review_state]}
                            </span>
                        </div>
                    </header>
                    <Link
                        href={`/sifi/issue-candidates/${candidate.id}/ai-explanation`}
                        className="block rounded-xl border bg-card p-4 font-semibold"
                    >
                        Bantu saya memahami isu ini →
                    </Link>
                    <div
                        role="alert"
                        className="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm"
                    >
                        <ShieldAlert className="h-5 w-5 shrink-0 text-amber-600" />
                        <p>
                            <b>LOCAL HUMAN DRAFT ONLY.</b> Tidak ada hak untuk
                            menaikkan status evidence, menjalankan ingestion,
                            atau menerbitkan Python Issue Object. Review
                            requested hanya meminta telaah.
                        </p>
                    </div>
                    <Link
                        href={`/sifi/issue-candidates/${candidate.id}/evidence`}
                        className="flex items-center justify-between rounded-xl border bg-card p-4 font-semibold hover:bg-muted"
                    >
                        <span className="flex items-center gap-2">
                            <FileCheck2 className="h-5 w-5" /> Evidence Intake &
                            Review — kelola kandidat bukti
                        </span>
                        <span className="text-xs text-muted-foreground">
                            LOCAL TRIAGE ONLY →
                        </span>
                    </Link>
                    <Link
                        href={`/sifi/issue-candidates/${candidate.id}/issue-case`}
                        className="flex items-center justify-between rounded-xl border bg-card p-4 font-semibold hover:bg-muted"
                    >
                        <span className="flex items-center gap-2">
                            <Waypoints className="h-5 w-5" /> Issue Case &
                            Lifecycle — identitas, wilayah dan domain draf
                        </span>
                        <span className="text-xs text-muted-foreground">
                            SCOPE ADVISORY →
                        </span>
                    </Link>
                    <Link
                        href={`/sifi/issue-candidates/${candidate.id}/claim-comparison-preview`}
                        className="flex items-center justify-between rounded-xl border bg-card p-4 font-semibold hover:bg-muted"
                    >
                        <span className="flex items-center gap-2">
                            <Waypoints className="h-5 w-5" /> Claim Comparison
                            Preview — konteks klaim
                        </span>
                        <span className="text-xs text-muted-foreground">
                            NON_BINDING · NOT VERIFIED →
                        </span>
                    </Link>
                    <Link
                        href={`/sifi/issue-candidates/${candidate.id}/claim-events`}
                        className="flex items-center justify-between rounded-xl border bg-card p-4 font-semibold hover:bg-muted"
                    >
                        <span className="flex items-center gap-2">
                            <Waypoints className="h-5 w-5" /> Claim & Event
                            Intelligence — pisahkan klaim dan hipotesis
                            peristiwa
                        </span>
                        <span className="text-xs text-muted-foreground">
                            NOT VERIFIED →
                        </span>
                    </Link>
                    <Link
                        href={`/sifi/issue-candidates/${candidate.id}/evidence-diagnostics`}
                        className="flex items-center justify-between rounded-xl border bg-card p-4 font-semibold hover:bg-muted"
                    >
                        <span className="flex items-center gap-2">
                            <FlaskConical className="h-5 w-5" /> Evidence
                            Diagnostics — kesiapan kualifikasi (offline)
                        </span>
                        <span className="text-xs text-muted-foreground">
                            INSUFFICIENT →
                        </span>
                    </Link>
                    <div className="grid gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(290px,1fr)]">
                        <form
                            onSubmit={submit}
                            className="space-y-5 rounded-xl border bg-card p-5 shadow-sm"
                        >
                            <div className="flex items-center gap-2 border-b pb-4">
                                <ClipboardCheck className="h-5 w-5" />
                                <h2 className="text-lg font-semibold">
                                    Konstruksi isu oleh analis
                                </h2>
                            </div>
                            <label className="block text-sm font-semibold">
                                Judul kandidat
                                <input
                                    required
                                    minLength={3}
                                    maxLength={180}
                                    value={title}
                                    onChange={(e) => setTitle(e.target.value)}
                                    className="mt-2 w-full rounded-lg border bg-background p-3 text-sm font-normal"
                                />
                                {errors.title && (
                                    <span className="block text-xs text-destructive">
                                        {errors.title}
                                    </span>
                                )}
                            </label>
                            {(
                                [
                                    {
                                        label: 'Hipotesis isu (belum diverifikasi)',
                                        value: hypothesis,
                                        set: setHypothesis,
                                        key: 'issue_hypothesis',
                                        hint: 'Apa pola atau perubahan yang dihipotesiskan? Bedakan publikasi, kejadian, dan bukti.',
                                    },
                                    {
                                        label: 'Geografi / dampak potensial',
                                        value: exposure,
                                        set: setExposure,
                                        key: 'exposure_notes',
                                        hint: 'Wilayah, populasi, atau sektor yang mungkin terdampak; tulis UNKNOWN jika belum ada bukti.',
                                    },
                                    {
                                        label: 'Kekurangan dan kontradiksi bukti',
                                        value: gaps,
                                        set: setGaps,
                                        key: 'evidence_gaps',
                                        hint: 'Apa yang belum didukung sumber primer/independen?',
                                    },
                                    {
                                        label: 'Pemeriksaan berikutnya',
                                        value: next,
                                        set: setNext,
                                        key: 'next_checks',
                                        hint: 'Data atau pemeriksaan untuk menguji, membantah, atau menguatkan hipotesis.',
                                    },
                                ] as const
                            ).map((field) => (
                                <label
                                    key={field.key}
                                    className="block text-sm font-semibold"
                                >
                                    {field.label}
                                    <textarea
                                        rows={4}
                                        maxLength={4000}
                                        value={field.value}
                                        onChange={(e) =>
                                            field.set(e.target.value)
                                        }
                                        placeholder={field.hint}
                                        className="mt-2 w-full resize-y rounded-lg border bg-background p-3 text-sm font-normal"
                                    />
                                    {errors[field.key] && (
                                        <span className="block text-xs text-destructive">
                                            {errors[field.key]}
                                        </span>
                                    )}
                                </label>
                            ))}
                            <label className="block text-sm font-semibold">
                                Status pengajuan
                                <select
                                    value={state}
                                    onChange={(e) =>
                                        setState(
                                            e.target
                                                .value as Candidate['review_state'],
                                        )
                                    }
                                    className="mt-2 w-full rounded-lg border bg-background p-3 text-sm font-normal"
                                >
                                    <option value="DRAFT">Draf</option>
                                    <option value="REVIEW_REQUESTED">
                                        Permintaan telaah manusia
                                    </option>
                                    <option value="ON_HOLD">Ditunda</option>
                                </select>
                                {errors.review_state && (
                                    <span className="block text-xs text-destructive">
                                        {errors.review_state}
                                    </span>
                                )}
                            </label>
                            {errors.revision && (
                                <p
                                    role="alert"
                                    className="rounded-lg border border-destructive/30 p-3 text-sm text-destructive"
                                >
                                    {errors.revision}
                                </p>
                            )}
                            <div className="flex flex-wrap items-center justify-between gap-2 border-t pt-4">
                                <p className="text-xs text-muted-foreground">
                                    Simpan manual · revision-check dan audit
                                    trail tersedia
                                </p>
                                <button
                                    disabled={saving}
                                    className="inline-flex items-center gap-2 rounded-lg bg-foreground px-4 py-2.5 text-sm font-semibold text-background disabled:opacity-50"
                                >
                                    <Save className="h-4 w-4" />
                                    {saving ? 'Menyimpan…' : 'Simpan kandidat'}
                                </button>
                            </div>
                        </form>
                        <aside className="space-y-5">
                            <section className="rounded-xl border bg-card p-5">
                                <h2 className="flex items-center gap-2 font-semibold">
                                    <Waypoints className="h-5 w-5" /> Asal
                                    investigasi
                                </h2>
                                <p className="mt-3 text-sm text-muted-foreground">
                                    Kandidat ini memiliki satu berkas
                                    investigasi sebagai asal. Hubungan ke sinyal
                                    tetap melalui ID dan API Python.
                                </p>
                                <Link
                                    className="mt-4 inline-block rounded-md border px-3 py-2 text-sm font-semibold hover:bg-muted"
                                    href={`/sifi/investigations/${candidate.investigation_id}`}
                                >
                                    Buka investigasi →
                                </Link>
                            </section>
                            <section className="space-y-4 rounded-xl border bg-card p-5">
                                <h2 className="flex items-center gap-2 font-semibold">
                                    <FlaskConical className="h-5 w-5" />{' '}
                                    Intelligence Bridge
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Analisis deterministik Python atas metadata
                                    fixture dan isi assessment yang sudah
                                    disimpan. Simpan perubahan formulir sebelum
                                    menjalankannya. Ini alat bantu pemeriksaan,{' '}
                                    <b>
                                        bukan penilaian kebenaran, evidence
                                        terverifikasi, atau Issue Object
                                    </b>
                                    .
                                </p>
                                <button
                                    type="button"
                                    disabled={
                                        running ||
                                        !candidate.issue_hypothesis.trim()
                                    }
                                    onClick={runAnalysis}
                                    className="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-foreground/30 bg-foreground px-3 py-2.5 text-sm font-semibold text-background disabled:opacity-40"
                                >
                                    <RefreshCw
                                        className={`h-4 w-4 ${running ? 'animate-spin' : ''}`}
                                    />
                                    {running
                                        ? 'Memeriksa…'
                                        : 'Jalankan analisis offline'}
                                </button>
                                {analysisError && (
                                    <p
                                        role="alert"
                                        className="rounded-lg border border-destructive/30 p-3 text-sm text-destructive"
                                    >
                                        {analysisError}
                                    </p>
                                )}
                                {!latest && (
                                    <p className="rounded-lg bg-muted p-3 text-xs text-muted-foreground">
                                        Belum ada hasil analisis. Simpan
                                        hipotesis terlebih dahulu, kemudian
                                        jalankan pemeriksaan.
                                    </p>
                                )}
                                {latest && (
                                    <div className="space-y-3 text-sm">
                                        <div className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3">
                                            <b>
                                                NOT VERIFIED · {latest.status}
                                            </b>
                                            <p className="mt-1 text-xs">
                                                Analisis untuk revisi{' '}
                                                {latest.candidate_revision}
                                                {outdated
                                                    ? ' — KEDALUWARSA: kandidat atau referensi berubah'
                                                    : ''}
                                                . Hanya fixture offline.
                                            </p>
                                        </div>
                                        <div className="grid grid-cols-2 gap-2">
                                            <div className="rounded-lg bg-muted p-3">
                                                <b className="block text-xl">
                                                    {latest.result.signal_count}
                                                </b>
                                                <span className="text-xs">
                                                    Referensi fixture
                                                </span>
                                            </div>
                                            <div className="rounded-lg bg-muted p-3">
                                                <b className="block text-xl">
                                                    {
                                                        latest.result
                                                            .distinct_source_count
                                                    }
                                                </b>
                                                <span className="text-xs">
                                                    Identitas sumber (bukan
                                                    sumber independen)
                                                </span>
                                            </div>
                                        </div>
                                        {latest.result.possible_syndication && (
                                            <p className="rounded-lg border border-amber-500/40 p-3 text-xs">
                                                Kemungkinan sindikasi/duplikasi
                                                ditemukan. Jangan menghitung
                                                sebagai corroboration.
                                            </p>
                                        )}
                                        <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                            Pengamatan metadata
                                        </p>
                                        <ul className="list-disc space-y-1 pl-5 text-xs">
                                            {latest.result.observations.map(
                                                (t, i) => (
                                                    <li key={i}>{t}</li>
                                                ),
                                            )}
                                        </ul>
                                        <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                            Rekomendasi pemeriksaan
                                        </p>
                                        <ul className="list-disc space-y-1 pl-5 text-xs">
                                            {latest.result.verification_checks.map(
                                                (t, i) => (
                                                    <li key={i}>{t}</li>
                                                ),
                                            )}
                                        </ul>
                                        <p className="text-xs text-muted-foreground">
                                            Snapshot #{latest.id} ·{' '}
                                            {fmt(latest.created_at)} ·{' '}
                                            {intelligence.length} riwayat
                                            terakhir
                                        </p>
                                    </div>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    Jika Python offline atau respons tidak
                                    sesuai kontrak, hasil baru tidak disimpan
                                    dan kandidat tetap utuh.
                                </p>
                            </section>
                            <section className="rounded-xl border bg-card p-5">
                                <h2 className="mb-3 flex items-center gap-2 font-semibold">
                                    <History className="h-5 w-5" /> Histori
                                    revisi
                                </h2>
                                <div className="space-y-2">
                                    {audit.map((a) => (
                                        <div
                                            key={a.revision}
                                            className="flex flex-wrap justify-between gap-2 rounded-lg border p-3 text-xs"
                                        >
                                            <b>
                                                v{a.revision} ·{' '}
                                                {a.action.replaceAll('_', ' ')}
                                            </b>
                                            <span className="text-muted-foreground">
                                                {fmt(a.created_at)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </section>
                            <p className="rounded-xl border bg-muted/60 p-4 text-sm text-muted-foreground">
                                Laravel memegang workflow, akses, dan audit.
                                Python tetap memegang provenance serta seluruh
                                definisi evidence/Issue Object.
                            </p>
                        </aside>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
