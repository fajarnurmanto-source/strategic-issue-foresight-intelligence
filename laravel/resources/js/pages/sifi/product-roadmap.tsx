import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpen,
    CalendarRange,
    Layers,
    ShieldAlert,
} from 'lucide-react';

type Milestone = {
    id: string;
    priority: string;
    deliverable: string;
    acceptance: string;
    implementation_state: 'NOT_ASSERTED_BY_ROADMAP';
};

type Props = {
    version: string;
    source_path: string;
    source_state: 'AVAILABLE' | 'NOT_DEPLOYED';
    sha256: string | null;
    milestones: Milestone[];
    journey: string[];
    markdown: string | null;
    network_requests: number;
    database_writes: number;
    production_admission: 'DENIED';
};

export default function ProductRoadmap({
    version,
    source_path,
    source_state,
    sha256,
    milestones,
    journey,
    markdown,
    network_requests,
    database_writes,
    production_admission,
}: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'SIFI Workbench', href: '/sifi/workbench' },
                { title: 'Roadmap Produk', href: '/sifi/product-roadmap' },
            ]}
        >
            <Head title="SIFI · Master Product Roadmap" />
            <main className="min-h-screen bg-muted/20 px-4 py-7 lg:px-8">
                <div className="mx-auto max-w-[1200px] space-y-6">
                    <header className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="flex items-center gap-2 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                                <BookOpen className="h-4 w-4" /> Product
                                governance / single roadmap
                            </p>
                            <h1 className="mt-2 text-3xl font-semibold">
                                Roadmap Pengembangan SIFI
                            </h1>
                            <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                                Dokumen kanonis {version}. Roadmap menetapkan
                                pekerjaan dan kriteria penerimaan; status
                                implementasi hanya dapat dibuktikan melalui
                                hasil aplikasi dan pengujian.
                            </p>
                        </div>
                        <Link
                            className="inline-flex items-center gap-2 rounded-lg border bg-card px-4 py-3 text-sm font-medium"
                            href="/sifi/monitoring-objects"
                        >
                            Kelola objek pemantauan{' '}
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                    </header>

                    <section
                        aria-label="Status dan integritas roadmap"
                        className="grid gap-3 md:grid-cols-3"
                    >
                        <div className="rounded-xl border bg-card p-4">
                            <p className="text-xs text-muted-foreground">
                                Versi dokumen
                            </p>
                            <p className="mt-1 text-xl font-semibold">
                                {version}
                            </p>
                            <p className="mt-2 text-xs break-all text-muted-foreground">
                                {source_path}
                            </p>
                        </div>
                        <div className="rounded-xl border bg-card p-4">
                            <p className="text-xs text-muted-foreground">
                                Dokumen pada runtime
                            </p>
                            <p className="mt-1 text-xl font-semibold">
                                {source_state === 'AVAILABLE'
                                    ? 'Tersedia'
                                    : 'Belum tersinkron'}
                            </p>
                            <p className="mt-2 text-xs break-all text-muted-foreground">
                                {sha256
                                    ? `SHA-256: ${sha256}`
                                    : 'Sinkronkan berkas docs/canonical melalui bridge.'}
                            </p>
                        </div>
                        <div className="rounded-xl border bg-card p-4">
                            <p className="text-xs text-muted-foreground">
                                Batas keamanan halaman
                            </p>
                            <p className="mt-1 text-xl font-semibold">
                                Baca saja
                            </p>
                            <p className="mt-2 text-xs text-muted-foreground">
                                {network_requests} request provider ·{' '}
                                {database_writes} penulisan database ·
                                Production {production_admission}
                            </p>
                        </div>
                    </section>

                    <section
                        className="space-y-3 rounded-xl border bg-card p-5"
                        aria-label="Alur pemantauan wajib"
                    >
                        <div className="flex items-center gap-2">
                            <CalendarRange className="h-5 w-5" />
                            <h2 className="text-xl font-semibold">
                                Alur produk yang dituju
                            </h2>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Registrasi objek dengan keyword lebih dahulu,
                            kemudian pilih periode; sumber, ekstraksi, klaster,
                            dan tren mengikuti ruang lingkup tersebut.
                        </p>
                        {journey.length ? (
                            <ol className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {journey.map((step, index) => (
                                    <li
                                        key={step}
                                        className="flex gap-3 rounded-lg border bg-muted/30 p-3 text-sm"
                                    >
                                        <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 font-semibold text-primary">
                                            {index + 1}
                                        </span>
                                        <span className="min-w-0 break-words">
                                            {step.replaceAll('/', ' / ')}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <p className="text-sm text-amber-700">
                                Urutan langkah belum dapat dibaca dari dokumen
                                yang terpasang.
                            </p>
                        )}
                    </section>

                    <section
                        className="space-y-4"
                        aria-label="Milestone mandat"
                    >
                        <div className="flex items-center gap-2">
                            <Layers className="h-5 w-5" />
                            <h2 className="text-xl font-semibold">
                                Milestone prioritas
                            </h2>
                        </div>
                        {milestones.length ? (
                            <div className="grid gap-3 md:grid-cols-2">
                                {milestones.map((row) => (
                                    <article
                                        key={row.id}
                                        className="space-y-3 rounded-xl border bg-card p-5"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <h3 className="text-lg font-semibold">
                                                {row.id}
                                            </h3>
                                            <span className="rounded-full border px-3 py-1 text-xs font-medium">
                                                {row.priority}
                                            </span>
                                        </div>
                                        <p className="text-sm font-medium">
                                            {row.deliverable}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            Syarat selesai: {row.acceptance}
                                        </p>
                                        <p className="text-xs text-amber-700">
                                            Status pemasangan: belum dinilai
                                            dari bukti runtime
                                        </p>
                                    </article>
                                ))}
                            </div>
                        ) : (
                            <p className="rounded-lg border p-4 text-sm">
                                Milestone belum tersedia pada runtime aplikasi.
                            </p>
                        )}
                    </section>

                    <aside className="flex gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                        <ShieldAlert className="h-5 w-5 shrink-0" />
                        <p>
                            Roadmap bukan bukti kemampuan telah berfungsi.
                            Aktivasi API, crawler, evidence admission, dan rilis
                            produksi tetap mengikuti gerbang izin masing-masing.
                        </p>
                    </aside>

                    {markdown && (
                        <details className="rounded-xl border bg-card p-5">
                            <summary className="cursor-pointer text-lg font-semibold">
                                Baca sumber Markdown lengkap
                            </summary>
                            <pre className="mt-4 max-h-[600px] overflow-auto rounded-lg bg-muted p-4 text-xs leading-relaxed break-words whitespace-pre-wrap">
                                {markdown}
                            </pre>
                        </details>
                    )}
                </div>
            </main>
        </AppLayout>
    );
}
