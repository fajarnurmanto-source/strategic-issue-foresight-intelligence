import CollectorObservations, {
    type CollectorObservation,
} from './collector-observations';
import type { MonitoringObject } from './monitoring-objects';
import CandidateWorkContext, {
    type WorkContext,
} from '@/components/sifi/candidate-work-context';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    BookOpen,
    ClipboardList,
    FileQuestion,
    FileText,
    GitBranch,
    LibraryBig,
    ScanSearch,
    ShieldAlert,
    ShieldCheck,
    Telescope,
    Workflow,
} from 'lucide-react';

type CaseRow = {
    candidate_id: string;
    title: string;
    review_state: string;
    revision: number;
    has_case: boolean;
    has_trajectory: boolean;
    has_foresight: boolean;
    has_brief: boolean;
    updated_at: string | null;
};
type Props = {
    collectorObservations?: CollectorObservation[];
    monitoringObject?: MonitoringObject;
    objectSources?: {
        id: string;
        name: string;
        rights: { allowed: boolean; reason: string };
    }[];
    objectSourceIds?: string[];
    focus: WorkContext | null;
    cases: CaseRow[];
    total: number;
    showing: number;
    mode:
        | 'OFFLINE_FIXTURE_ONLY'
        | 'REAL_PUBLIC_ARCHIVE'
        | 'MOCK_ACQUISITION_ONLY';
    evidence_state: 'NOT_EVALUATED';
    production_authorized: false;
};
const card = 'rounded-xl border bg-card p-5';
const modules = [
    {
        title: 'Signal Explorer',
        note: 'Publikasi fixture dan provenance',
        href: '/sifi/signals',
        icon: ScanSearch,
    },
    {
        title: 'Investigations',
        note: 'Berkas analisis dan hipotesis',
        href: '/sifi/investigations',
        icon: ClipboardList,
    },
    {
        title: 'Issue Candidates',
        note: 'Draf isu dan penilaian analis',
        href: '/sifi/issue-candidates',
        icon: FileQuestion,
    },
    {
        title: 'Evidence Reviews',
        note: 'Antrean pemeriksaan manusia',
        href: '/sifi/evidence-reviews',
        icon: ShieldCheck,
    },
    {
        title: 'Source & Coverage',
        note: 'Usulan sumber dan profil wilayah',
        href: '/sifi/source-catalog',
        icon: LibraryBig,
    },
    {
        title: 'E4 Discovery',
        note: 'Pilih objek, periode, dan cakupan sumber',
        href: '/sifi/discovery-period',
        icon: Telescope,
    },
];

export default function Workbench({
    cases,
    total,
    showing,
    mode,
    evidence_state,
    production_authorized,
    focus,
    monitoringObject,
    objectSources = [],
    objectSourceIds = [],
    collectorObservations = [],
}: Props) {
    const selected =
        (focus
            ? cases.find((item) => item.candidate_id === focus.candidate.id)
            : null) ??
        cases[0] ??
        null;
    const selectedRoot = selected
        ? `/sifi/issue-candidates/${selected.candidate_id}`
        : null;
    const comparisonRoot = focus
        ? `/sifi/issue-candidates/${focus.candidate.id}`
        : selectedRoot;
    const guidedPaths = [
        {
            title: 'Temukan isu penting',
            benefit:
                'Mulai dari informasi yang tersedia, lalu pilih hal yang perlu diperiksa lebih lanjut.',
            status: 'Tersedia untuk data fixture lokal. SIFI belum mengambil berita dari internet secara otomatis.',
            next: 'Lihat informasi yang tersedia',
            href: '/sifi/signals',
            icon: ScanSearch,
        },
        {
            title: 'Periksa informasi',
            benefit:
                'Bandingkan pernyataan dan lihat alasan jika informasinya belum cukup untuk menyimpulkan.',
            status: comparisonRoot
                ? 'Dugaan isu yang Anda pilih siap dibuka. Hasil tetap NON_BINDING dan NOT VERIFIED.'
                : 'Belum ada dugaan isu. Pilih atau buat draf kandidat lebih dahulu.',
            next: comparisonRoot ? 'Bandingkan pernyataan' : 'Pilih dugaan isu',
            href: comparisonRoot
                ? `${comparisonRoot}/claim-comparison-preview`
                : '/sifi/issue-candidates',
            icon: GitBranch,
        },
        {
            title: 'Lihat perkembangan isu',
            benefit:
                'Tinjau arah perkembangan, risiko, peluang, dan informasi yang masih perlu dilengkapi.',
            status: selected?.has_case
                ? 'Draf perkembangan tersedia. CURRENT berarti input belum berubah, bukan berarti informasinya benar.'
                : 'Perkembangan belum tersedia. Lengkapi Issue Case terlebih dahulu.',
            next: selected?.has_case
                ? 'Buka perkembangan isu'
                : selectedRoot
                  ? 'Lengkapi Issue Case'
                  : 'Pilih dugaan isu',
            href: selected?.has_case
                ? `${selectedRoot}/trajectory`
                : selectedRoot
                  ? `${selectedRoot}/issue-case`
                  : '/sifi/issue-candidates',
            icon: Activity,
        },
        {
            title: 'Siapkan ringkasan atau laporan',
            benefit:
                'Susun hasil pemeriksaan menjadi ringkasan yang tetap memperlihatkan batas dan ketidakpastian.',
            status: selected?.has_foresight
                ? 'Bahan ringkasan tersedia untuk ditinjau. Ringkasan tidak mengesahkan bukti atau keputusan.'
                : selected?.has_trajectory
                  ? 'Lengkapi Foresight sebelum menyiapkan Executive Brief.'
                  : 'Tahap perkembangan dan foresight belum lengkap.',
            next: selected?.has_foresight
                ? 'Buka ringkasan'
                : selected?.has_trajectory
                  ? 'Lanjutkan ke Foresight'
                  : selected?.has_case
                    ? 'Lanjutkan perkembangan'
                    : selectedRoot
                      ? 'Lengkapi Issue Case'
                      : 'Pilih dugaan isu',
            href: selected?.has_foresight
                ? `${selectedRoot}/executive-brief`
                : selected?.has_trajectory
                  ? `${selectedRoot}/foresight`
                  : selected?.has_case
                    ? `${selectedRoot}/trajectory`
                    : selectedRoot
                      ? `${selectedRoot}/issue-case`
                      : '/sifi/issue-candidates',
            icon: FileText,
        },
    ];

    return (
        <AppLayout
            breadcrumbs={[{ title: 'SIFI Workbench', href: '/sifi/workbench' }]}
        >
            <Head title="SIFI · Intelligence Workbench" />
            <main className="min-h-screen bg-muted/20 px-4 py-7 lg:px-8">
                <div className="mx-auto max-w-[1360px] space-y-6">
                    <Link
                        href="/sifi/research"
                        className="inline-block rounded-lg border bg-card px-4 py-2 text-sm font-semibold"
                    >
                        Penelusuran informasi
                    </Link>
                    <Link
                        href="/sifi/monitoring-objects"
                        className="inline-block rounded-lg border bg-card px-4 py-2 text-sm font-semibold"
                    >
                        Kelola objek pemantauan
                    </Link>
                    <Link
                        href="/sifi/product-roadmap"
                        className="inline-block rounded-lg border bg-card px-4 py-2 text-sm font-semibold"
                    >
                        Roadmap Produk
                    </Link>
                    {monitoringObject && (
                        <section
                            aria-label="Konteks objek pemantauan"
                            className="min-w-0 space-y-3 rounded-xl border bg-card p-5"
                        >
                            <h1 className="text-2xl font-semibold">
                                {monitoringObject.name}
                            </h1>
                            <p className="break-words">
                                {monitoringObject.category} ·{' '}
                                {monitoringObject.state} · revisi{' '}
                                {monitoringObject.revision}
                            </p>
                            <p>
                                Geografi:{' '}
                                {monitoringObject.geography ?? 'Tidak dibatasi'}{' '}
                                · Horizon: {monitoringObject.horizon}
                            </p>
                            <p className="break-words">
                                Tujuan: {monitoringObject.objective}
                            </p>
                            <p className="break-words">
                                Alias:{' '}
                                {monitoringObject.aliases_json.join(', ') ||
                                    'Belum ditetapkan'}{' '}
                                · Keyword:{' '}
                                {monitoringObject.keywords_json.join(', ') ||
                                    'Belum ditetapkan'}{' '}
                                · Exclusion:{' '}
                                {monitoringObject.exclusions_json.join(', ') ||
                                    'Belum ditetapkan'}
                            </p>
                            <p className="text-sm">
                                Sumber terasosiasi:{' '}
                                {objectSources
                                    .filter((s) =>
                                        objectSourceIds.includes(s.id),
                                    )
                                    .map(
                                        (s) => `${s.name} (${s.rights.reason})`,
                                    )
                                    .join('; ') || 'Belum ada'}
                            </p>
                            <p className="rounded-lg bg-muted p-3 text-sm">
                                Konteks konfigurasi objek. Observasi collector
                                di bawah berasal dari riwayat riset milik objek
                                ini. Halaman ini tidak menjalankan pengambilan;
                                kandidat global tidak ditampilkan sebagai hasil
                                objek. Tautan modul di bawah membuka workspace
                                global dengan batas existing.
                            </p>
                        </section>
                    )}
                    {monitoringObject && (
                        <CollectorObservations
                            objectId={monitoringObject.id}
                            items={collectorObservations}
                        />
                    )}

                    <header>
                        <p className="flex items-center gap-2 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                            <Workflow className="h-4 w-4" /> SIFI / PANDUAN
                            KERJA
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                            Apa yang ingin Anda kerjakan?
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                            Pilih tujuan yang paling dekat dengan kebutuhan
                            Anda. SIFI akan membuka kemampuan yang sudah
                            tersedia dan menjelaskan langkah yang masih perlu
                            diselesaikan.
                        </p>
                    </header>
                    <div
                        role="alert"
                        className="flex gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm"
                    >
                        <ShieldAlert className="h-5 w-5 shrink-0 text-amber-600" />
                        <p>
                            <b>
                                {mode} · {evidence_state}.
                            </b>{' '}
                            {mode === 'REAL_PUBLIC_ARCHIVE'
                                ? 'Kandidat terpilih memakai arsip publik nyata historis; tetap draf NOT VERIFIED, bukan data real-time.'
                                : 'Semua entri masih berupa draf atau fixture, bukan temuan terverifikasi.'}{' '}
                            Production authorized:{' '}
                            {production_authorized ? 'YES' : 'NO'}. Tidak ada
                            pengambilan berita nyata dari halaman ini.
                        </p>
                    </div>
                    <section
                        aria-labelledby="guided-paths-title"
                        className="space-y-3"
                    >
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h2
                                    id="guided-paths-title"
                                    className="text-xl font-semibold"
                                >
                                    Mulai dari tujuan Anda
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Setiap pilihan membuka halaman yang sudah
                                    berfungsi. Tidak ada analisis otomatis atau
                                    tombol semu.
                                </p>
                            </div>
                            <a
                                href="#mode-analis"
                                className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                            >
                                Gunakan mode analis
                            </a>
                        </div>
                        <div className="grid gap-3 md:grid-cols-2">
                            {guidedPaths.map(
                                (
                                    {
                                        title,
                                        benefit,
                                        status,
                                        next,
                                        href,
                                        icon: Icon,
                                    },
                                    index,
                                ) => (
                                    <Link
                                        key={title}
                                        href={href}
                                        className={`${card} group block transition-colors hover:border-primary/40 hover:bg-muted/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2`}
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex items-center gap-3">
                                                <span className="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                                    {index + 1}
                                                </span>
                                                <Icon className="h-5 w-5 text-primary" />
                                            </div>
                                            <ArrowRight className="h-4 w-4 text-muted-foreground transition-transform group-hover:translate-x-1" />
                                        </div>
                                        <h3 className="mt-4 text-lg font-semibold">
                                            {title}
                                        </h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {benefit}
                                        </p>
                                        <div className="mt-4 rounded-lg bg-muted/60 p-3 text-sm">
                                            <p>
                                                <span className="font-semibold">
                                                    Kondisi sekarang:
                                                </span>{' '}
                                                {status}
                                            </p>
                                            <p className="mt-2 font-medium text-primary">
                                                Langkah berikut: {next}
                                            </p>
                                        </div>
                                    </Link>
                                ),
                            )}
                        </div>
                    </section>
                    <details className={`${card} group`}>
                        <summary className="cursor-pointer font-semibold focus-visible:outline focus-visible:outline-2">
                            Istilah yang akan Anda temui
                        </summary>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Istilah teknis tetap tersedia untuk audit, dengan
                            arti sederhana berikut.
                        </p>
                        <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            {[
                                [
                                    'Dugaan isu',
                                    'Kandidat isu yang masih perlu diperiksa.',
                                ],
                                [
                                    'Bandingkan pernyataan',
                                    'Membaca kesamaan atau perbedaan klaim tanpa menetapkan kebenaran.',
                                ],
                                [
                                    'Belum cukup informasi',
                                    'ABSTAIN; SIFI tidak memiliki dasar yang memadai untuk menyimpulkan.',
                                ],
                                [
                                    'Informasi yang masih kurang',
                                    'Evidence gap yang perlu dicari atau diverifikasi.',
                                ],
                                [
                                    'Analisis perlu diperbarui',
                                    'STALE; input berubah sejak snapshot dibuat.',
                                ],
                                [
                                    'Asal dan riwayat informasi',
                                    'Provenance yang membantu menelusuri sumber dan perubahan.',
                                ],
                            ].map(([term, description]) => (
                                <div
                                    key={term}
                                    className="rounded-lg border p-3"
                                >
                                    <dt className="font-semibold">{term}</dt>
                                    <dd className="mt-1 text-muted-foreground">
                                        {description}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </details>
                    {focus && (
                        <CandidateWorkContext
                            key={focus.candidate.id}
                            context={focus}
                        />
                    )}
                    <section
                        id="mode-analis"
                        aria-label="Mode analis"
                        className="scroll-mt-6 space-y-3"
                    >
                        <div>
                            <h2 className="text-lg font-semibold">
                                Mode analis
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Akses langsung dengan istilah teknis untuk
                                pengguna yang sudah mengenal struktur SIFI.
                            </p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {modules.map(
                                ({ title, note, href, icon: Icon }) => (
                                    <Link
                                        key={href}
                                        href={href}
                                        className={`${card} group block transition-colors hover:bg-muted/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2`}
                                    >
                                        <div className="flex items-center justify-between">
                                            <Icon className="h-5 w-5" />
                                            <ArrowRight className="h-4 w-4 text-muted-foreground group-hover:translate-x-1" />
                                        </div>
                                        <h3 className="mt-4 text-base font-semibold">
                                            {title}
                                        </h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {note}
                                        </p>
                                    </Link>
                                ),
                            )}
                        </div>
                    </section>
                    <section
                        aria-label="Kasus milik pengguna"
                        className={`${card} space-y-4`}
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    Alur isu saya
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {showing} dari {total} kandidat isu milik
                                    akun ini ditampilkan, terbaru terlebih
                                    dahulu.
                                </p>
                            </div>
                            <Link
                                href="/sifi/issue-candidates"
                                className="rounded-lg border px-3 py-2 text-sm font-medium hover:bg-muted"
                            >
                                Semua kandidat{' '}
                                <ArrowRight className="ml-1 inline h-4 w-4" />
                            </Link>
                        </div>
                        {cases.length === 0 && (
                            <div className="rounded-lg border border-dashed p-8 text-center">
                                <BookOpen className="mx-auto h-7 w-7 text-muted-foreground" />
                                <p className="mt-3 font-semibold">
                                    Belum ada kandidat isu
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Mulai dari Signal Explorer atau
                                    Investigations untuk membuat berkas
                                    analisis.
                                </p>
                            </div>
                        )}
                        {cases.map((c) => {
                            const root = `/sifi/issue-candidates/${c.candidate_id}`;
                            const links = [
                                {
                                    title: 'Konteks & tindak lanjut',
                                    href: `/sifi/workbench?candidate=${c.candidate_id}`,
                                    available: true,
                                    icon: Workflow,
                                },
                                {
                                    title: 'Claim Comparison',
                                    href: `${root}/claim-comparison-preview`,
                                    available: true,
                                    icon: GitBranch,
                                },
                                {
                                    title: 'Kandidat',
                                    href: root,
                                    available: true,
                                    icon: FileQuestion,
                                },
                                {
                                    title: 'Evidence',
                                    href: `${root}/evidence`,
                                    available: true,
                                    icon: ShieldCheck,
                                },
                                {
                                    title: 'Claim & Event',
                                    href: `${root}/claim-events`,
                                    available: true,
                                    icon: GitBranch,
                                },
                                {
                                    title: 'Issue Case',
                                    href: `${root}/issue-case`,
                                    available: true,
                                    icon: BookOpen,
                                },
                                {
                                    title: 'Trajectory',
                                    href: `${root}/trajectory`,
                                    available: c.has_case,
                                    icon: Activity,
                                },
                                {
                                    title: 'Foresight',
                                    href: `${root}/foresight`,
                                    available: c.has_trajectory,
                                    icon: Telescope,
                                },
                                {
                                    title: 'Executive Brief',
                                    href: `${root}/executive-brief`,
                                    available: c.has_foresight,
                                    icon: FileText,
                                },
                            ];
                            return (
                                <article
                                    key={c.candidate_id}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h3 className="font-semibold">
                                                {c.title}
                                            </h3>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Revisi {c.revision} · Status
                                                kerja: {c.review_state} · Bukan
                                                Issue Object terverifikasi
                                            </p>
                                        </div>
                                        <p className="rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground">
                                            {c.has_brief
                                                ? 'Brief tersedia'
                                                : c.has_foresight
                                                  ? 'Foresight tersedia'
                                                  : c.has_trajectory
                                                    ? 'Trajectory tersedia'
                                                    : c.has_case
                                                      ? 'Issue Case tersedia'
                                                      : 'Kandidat draf'}
                                        </p>
                                    </div>
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {links.map(
                                            ({
                                                title,
                                                href,
                                                available,
                                                icon: Icon,
                                            }) =>
                                                available ? (
                                                    <Link
                                                        key={title}
                                                        href={href}
                                                        className="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-xs font-medium hover:bg-muted focus-visible:outline focus-visible:outline-2"
                                                    >
                                                        <Icon className="h-3.5 w-3.5" />
                                                        {title}{' '}
                                                        <ArrowRight className="h-3 w-3" />
                                                    </Link>
                                                ) : (
                                                    <span
                                                        key={title}
                                                        title="Simpan tahap sebelumnya terlebih dahulu"
                                                        className="inline-flex cursor-not-allowed items-center gap-1.5 rounded-md border border-dashed px-3 py-2 text-xs text-muted-foreground/70"
                                                    >
                                                        <Icon className="h-3.5 w-3.5" />
                                                        {title} · belum tersedia
                                                    </span>
                                                ),
                                        )}
                                    </div>
                                </article>
                            );
                        })}
                    </section>
                    <p className="text-xs text-muted-foreground">
                        SIFI AQ01 ·{' '}
                        {mode === 'REAL_PUBLIC_ARCHIVE'
                            ? 'Arsip publik historis di Laravel; corpus AQ01 tetap fixture'
                            : 'Fixture-only workbench'}{' '}
                        · Catatan manual disimpan di Laravel dengan revisi/audit
                        · Tidak mengubah evidence atau Python API
                    </p>
                </div>
            </main>
        </AppLayout>
    );
}
