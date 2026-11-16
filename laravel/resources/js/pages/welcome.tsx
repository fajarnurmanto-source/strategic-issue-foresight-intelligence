import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, ArrowRight, BookOpenCheck, Database, LockKeyhole, RadioTower, ShieldCheck } from 'lucide-react';
import { dashboard, login, register } from '@/routes';

const capabilities = [
    { title: 'Signal intelligence', detail: 'Discovery, sumber, dan sinyal yang dapat ditelusuri.', icon: RadioTower },
    { title: 'Issue workbench', detail: 'Ruang kerja analis untuk mengembangkan kandidat isu.', icon: Activity },
    { title: 'Evidence governance', detail: 'Asal data, review independen, dan jejak keputusan.', icon: ShieldCheck },
    { title: 'Executive brief', detail: 'Draf laporan dengan status dan keterbatasan yang jelas.', icon: BookOpenCheck },
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="SIFI — Signal & Integrity Finder Initiative" />
            <div className="min-h-screen bg-slate-950 text-slate-100">
                <div className="pointer-events-none absolute inset-x-0 top-0 h-[460px] bg-[radial-gradient(ellipse_at_70%_5%,rgba(13,148,136,0.25),transparent_60%)]" aria-hidden="true" />
                <div className="relative mx-auto max-w-6xl px-5 pb-20 pt-7 sm:px-8">
                    <header className="flex flex-wrap items-center justify-between gap-4">
                        <Link href="/" aria-label="SIFI, halaman utama" className="flex items-center gap-3">
                            <span className="flex size-11 items-center justify-center rounded-xl border border-teal-400/30 bg-teal-400/10">
                                <RadioTower className="size-6 text-teal-300" aria-hidden="true" />
                            </span>
                            <span className="flex flex-col">
                                <strong className="text-xl tracking-tight">SIFI</strong>
                                <span className="text-xs text-slate-400">Signal & Integrity Finder Initiative</span>
                            </span>
                        </Link>
                        <nav aria-label="Akses akun" className="flex items-center gap-3 text-sm">
                            {auth.user ? (
                                <Link href={dashboard()} className="rounded-lg bg-teal-400 px-4 py-2.5 font-semibold text-slate-950 hover:bg-teal-300">
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link href={login()} className="rounded-lg px-4 py-2.5 font-medium text-slate-200 hover:bg-white/10">Masuk</Link>
                                    <Link href={register()} className="rounded-lg border border-slate-600 px-4 py-2.5 font-medium hover:border-teal-400 hover:text-teal-200">Daftar</Link>
                                </>
                            )}
                        </nav>
                    </header>

                    <main>
                        <section className="grid items-center gap-12 py-20 lg:grid-cols-[1.35fr_0.9fr] lg:py-28">
                            <div>
                                <span className="inline-flex items-center gap-2 rounded-full border border-teal-500/30 bg-teal-500/10 px-3 py-1.5 text-xs font-medium text-teal-200">
                                    <span className="size-2 rounded-full bg-teal-400" aria-hidden="true" />
                                    Intelligence workspace · Evidence-first
                                </span>
                                <h1 className="mt-7 max-w-3xl text-4xl font-semibold leading-tight tracking-tight sm:text-5xl lg:text-6xl">
                                    Dari sinyal publik menuju
                                    <span className="block text-teal-300">pemahaman yang dapat diuji.</span>
                                </h1>
                                <p className="mt-6 max-w-xl text-base leading-8 text-slate-300">
                                    Platform penelitian isu multisumber untuk menelusuri asal informasi,
                                    mengelola bukti, memetakan perkembangan, dan menyusun analisis secara bertanggung jawab.
                                </p>
                                <div className="mt-9 flex flex-wrap gap-3">
                                    <Link href={auth.user ? dashboard() : login()} className="inline-flex items-center gap-2 rounded-lg bg-teal-400 px-6 py-3 font-semibold text-slate-950 hover:bg-teal-300">
                                        {auth.user ? 'Buka dashboard' : 'Masuk ke SIFI'}
                                        <ArrowRight className="size-4" aria-hidden="true" />
                                    </Link>
                                    <a href="#capabilities" className="inline-flex items-center rounded-lg border border-slate-600 px-6 py-3 font-medium text-slate-200 hover:border-slate-400">
                                        Jelajahi kapabilitas
                                    </a>
                                </div>
                            </div>
                            <div className="relative overflow-hidden rounded-2xl border border-slate-700/70 bg-slate-900/80 p-6 shadow-2xl shadow-black/30">
                                <div className="flex items-center justify-between border-b border-slate-700 pb-5">
                                    <span className="text-sm font-semibold">Intelligence workflow</span>
                                    <LockKeyhole className="size-4 text-teal-300" aria-label="Akses terlindungi" />
                                </div>
                                <div className="mt-7 space-y-4">
                                    {[
                                        ['01', 'Observe', 'Sinyal dan konteks sumber'],
                                        ['02', 'Corroborate', 'Bukti, provenance, dan kontradiksi'],
                                        ['03', 'Interpret', 'Hipotesis, hubungan, dan arah isu'],
                                        ['04', 'Brief', 'Draf analisis untuk pengambilan keputusan'],
                                    ].map(([step, title, detail]) => (
                                        <div key={step} className="flex gap-4 rounded-xl border border-slate-700/60 bg-slate-800/50 p-4">
                                            <span className="font-mono text-sm text-teal-300">{step}</span>
                                            <div>
                                                <p className="font-semibold">{title}</p>
                                                <p className="mt-1 text-sm text-slate-400">{detail}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                <p className="mt-6 flex items-start gap-2 text-xs leading-5 text-slate-400">
                                    <Database className="mt-0.5 size-4 shrink-0 text-teal-300" aria-hidden="true" />
                                    Aktivitas analisis tidak otomatis berarti bukti telah diverifikasi atau disahkan.
                                </p>
                            </div>
                        </section>

                        <section id="capabilities" aria-labelledby="capabilities-title" className="border-t border-slate-800 pt-10">
                            <h2 id="capabilities-title" className="text-2xl font-semibold">Satu ruang kerja. Alur yang dapat ditelusuri.</h2>
                            <div className="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {capabilities.map(({ title, detail, icon: Icon }) => (
                                    <article key={title} className="rounded-xl border border-slate-800 bg-slate-900/60 p-5">
                                        <Icon className="size-6 text-teal-300" aria-hidden="true" />
                                        <h3 className="mt-5 font-semibold">{title}</h3>
                                        <p className="mt-2 text-sm leading-6 text-slate-400">{detail}</p>
                                    </article>
                                ))}
                            </div>
                        </section>
                    </main>
                    <footer className="mt-16 border-t border-slate-800 pt-6 text-xs text-slate-500">
                        SIFI · Data provenance · Human-governed decisions
                    </footer>
                </div>
            </div>
        </>
    );
}
