import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    ChevronLeft,
    ChevronRight,
    Database,
    Fingerprint,
    History,
    Layers3,
    Search,
    ShieldAlert,
    Waypoints,
    FileSearch,
    Server,
    Globe2,
} from 'lucide-react';

type Source = {
    source_id: string;
    name: string;
    family: string;
    zone: string;
    verification_state: string;
    live_acquisition_enabled: boolean;
    production_evidence_admitted: boolean;
};

type Signal = {
    signal_id: string;
    source_id: string;
    source_name: string;
    publisher_or_owner?: string | null;
    upstream_attribution?: string | null;
    title: string;
    canonical_url: string | null;
    published_at: string | null;
    first_seen_at?: string;
    last_seen_at: string;
    fetch_count: number;
    version?: number;
    content_fingerprint?: string;
    evidence_admission: string;
    provenance_mode: string;
    evidence_ids: string[];
    issue_ids: string[];
};

type SyndicationGroup = {
    content_fingerprint: string;
    signals: { source_id: string; signal_id: string }[];
    independence_verified: boolean;
    status: string;
};

type Props = {
    apiStatus: string;
    sources: Source[];
    signals: Signal[];
    signalCount: number;
    sourceFilter: string | null;
    page: number;
    pageSize: number;
    selectedSignalId: string | null;
    selectedHistory: (Signal & { version: number; observed_at: string })[];
    syndicationGroups: SyndicationGroup[];
};

function href(query: {
    source?: string | null;
    page?: number;
    signal?: string | null;
}): string {
    const params = new URLSearchParams();
    if (query.source) params.set('source_id', query.source);
    if (query.page && query.page !== 1) params.set('page', String(query.page));
    if (query.signal) params.set('signal', query.signal);
    const suffix = params.toString();
    return '/sifi/signals' + (suffix ? '?' + suffix : '');
}

function dateLabel(value?: string | null): string {
    if (!value) return 'Tanggal tidak tersedia';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'Tanggal tidak valid';
    return (
        date.toLocaleString('id-ID', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: 'Asia/Jakarta',
        }) + ' WIB'
    );
}

function Tag({
    children,
    subtle = false,
}: {
    children: ReactNode;
    subtle?: boolean;
}) {
    return (
        <span
            className={
                'inline-flex items-center rounded-md border px-2 py-1 text-[11px] font-semibold ' +
                (subtle
                    ? 'border-border bg-muted/50 text-muted-foreground'
                    : 'border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300')
            }
        >
            {children}
        </span>
    );
}

function Kpi({
    label,
    value,
    icon: Icon,
    hint,
}: {
    label: string;
    value: string;
    icon: typeof Activity;
    hint: string;
}) {
    return (
        <div className="rounded-xl border bg-card p-4 shadow-sm">
            <div className="flex items-center justify-between text-muted-foreground">
                <span className="text-xs font-medium">{label}</span>
                <Icon className="h-4 w-4" />
            </div>
            <div className="mt-2 text-3xl font-semibold tracking-tight tabular-nums">
                {value}
            </div>
            <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
        </div>
    );
}

export default function SignalInbox({
    apiStatus,
    sources,
    signals,
    signalCount,
    sourceFilter,
    page,
    pageSize,
    selectedSignalId,
    selectedHistory,
    syndicationGroups,
}: Props) {
    const [search, setSearch] = useState('');
    const [tab, setTab] = useState<'signals' | 'sources'>('signals');
    const [creatingInvestigation, setCreatingInvestigation] = useState(false);
    const [investigationError, setInvestigationError] = useState('');
    const connected = apiStatus === 'OFFLINE_FIXTURE_ONLY';
    const pages = Math.max(1, Math.ceil(signalCount / pageSize));
    const selected =
        signals.find((item) => item.signal_id === selectedSignalId) ?? null;
    const syndicationIds = useMemo(
        () =>
            new Set(
                syndicationGroups.flatMap((group) =>
                    group.signals.map((item) => item.signal_id),
                ),
            ),
        [syndicationGroups],
    );
    const visible = useMemo(
        () =>
            signals.filter((signal) =>
                `${signal.title} ${signal.source_id} ${signal.publisher_or_owner ?? ''}`
                    .toLowerCase()
                    .includes(search.trim().toLowerCase()),
            ),
        [signals, search],
    );
    const demoSourceIds = [...new Set(signals.map((s) => s.source_id))].sort();
    const close = href({ source: sourceFilter, page });

    return (
        <AppLayout
            breadcrumbs={[
                {
                    title: 'SIFI — Intelligence Workspace',
                    href: '/sifi/signals',
                },
            ]}
        >
            <Head title="SIFI · Intelligence Workspace" />
            <div className="min-h-screen bg-muted/20 px-4 py-6 text-foreground lg:px-8">
                <div className="mx-auto max-w-[1600px] space-y-5">
                    <header className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <div className="mb-2 flex items-center gap-2 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                                <Layers3 className="h-4 w-4" /> SIFI / Strategic
                                Intelligence
                            </div>
                            <h1 className="text-3xl font-semibold tracking-tight lg:text-4xl">
                                Signal Intelligence Workspace
                            </h1>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Jelajahi publikasi, periksa provenance, dan
                                kenali keterbatasan bukti sebelum menilai isu.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <Link
                                href="/sifi/investigations"
                                className="rounded-lg border bg-card px-4 py-2 text-sm font-semibold hover:bg-muted"
                            >
                                Investigations →
                            </Link>
                            <div className="flex items-center gap-2 rounded-full border bg-card px-3 py-2 text-xs font-semibold">
                                <span
                                    className={
                                        'h-2 w-2 rounded-full ' +
                                        (connected
                                            ? 'bg-emerald-500'
                                            : 'bg-rose-500')
                                    }
                                />
                                {connected
                                    ? 'PYTHON API CONNECTED'
                                    : 'PYTHON API UNAVAILABLE'}
                            </div>
                        </div>
                    </header>

                    <div
                        className="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4"
                        role="alert"
                    >
                        <ShieldAlert className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                        <div>
                            <p className="text-sm font-semibold">
                                OFFLINE FIXTURE ONLY — bukan monitoring
                                pemberitaan nyata
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Sumber resmi masih dalam batas rights registry.
                                Tidak ada evidence admitted, Issue Object
                                terverifikasi, atau live collection. Kemiripan
                                publikasi bukan konfirmasi sumber independen.
                            </p>
                        </div>
                    </div>

                    {!connected && (
                        <div
                            className="rounded-xl border border-destructive/40 bg-card p-4 text-sm"
                            role="status"
                        >
                            Jalankan API melalui{' '}
                            <code>
                                cd ~/projects/sifi-aq01 &amp;&amp; bash
                                start_aq01_api.sh
                            </code>{' '}
                            di WSL, lalu refresh halaman.
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <Kpi
                            label="Signals / monitored fixture"
                            value={connected ? String(signalCount) : '—'}
                            icon={Activity}
                            hint="Publikasi, bukan peristiwa"
                        />
                        <Kpi
                            label="Source Registry"
                            value={connected ? String(sources.length) : '—'}
                            icon={Database}
                            hint="Semua live collector OFF"
                        />
                        <Kpi
                            label="Possible syndication"
                            value={
                                connected
                                    ? String(syndicationGroups.length)
                                    : '—'
                            }
                            icon={Waypoints}
                            hint="Belum verifikasi independensi"
                        />
                        <Kpi
                            label="Evidence admitted"
                            value="0"
                            icon={FileSearch}
                            hint="Abstention dipertahankan"
                        />
                    </div>

                    <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-4 border-b px-4 py-3">
                            <nav
                                aria-label="SIFI workspace views"
                                className="flex items-center gap-1"
                            >
                                <button
                                    type="button"
                                    onClick={() => setTab('signals')}
                                    className={
                                        'rounded-md px-4 py-2 text-sm font-semibold ' +
                                        (tab === 'signals'
                                            ? 'bg-foreground text-background'
                                            : 'text-muted-foreground hover:bg-muted')
                                    }
                                >
                                    Signal Explorer
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setTab('sources')}
                                    className={
                                        'rounded-md px-4 py-2 text-sm font-semibold ' +
                                        (tab === 'sources'
                                            ? 'bg-foreground text-background'
                                            : 'text-muted-foreground hover:bg-muted')
                                    }
                                >
                                    Source Registry
                                </button>
                            </nav>
                            <span className="text-xs text-muted-foreground">
                                Read-only POC · provenance-first
                            </span>
                        </div>

                        {tab === 'signals' ? (
                            <div className="grid min-h-[520px] grid-cols-1 xl:grid-cols-[minmax(0,1fr)_370px]">
                                <div className="min-w-0 xl:border-r">
                                    <div className="flex flex-wrap items-center gap-3 border-b p-4">
                                        <label className="relative min-w-[200px] flex-1">
                                            <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                                            <input
                                                aria-label="Cari sinyal pada halaman ini"
                                                value={search}
                                                onChange={(e) =>
                                                    setSearch(e.target.value)
                                                }
                                                placeholder="Cari judul atau sumber (halaman aktif)"
                                                className="w-full rounded-md border bg-background py-2 pr-3 pl-9 text-sm"
                                            />
                                        </label>
                                        <select
                                            aria-label="Filter source"
                                            className="max-w-60 rounded-md border bg-background px-3 py-2 text-sm"
                                            value={sourceFilter ?? ''}
                                            onChange={(e) => {
                                                window.location.assign(
                                                    href({
                                                        source:
                                                            e.target.value ||
                                                            null,
                                                        page: 1,
                                                    }),
                                                );
                                            }}
                                        >
                                            <option value="">
                                                Seluruh sumber demo
                                            </option>
                                            {demoSourceIds.map((sourceId) => (
                                                <option
                                                    key={sourceId}
                                                    value={sourceId}
                                                >
                                                    {sourceId}
                                                </option>
                                            ))}
                                            {sourceFilter &&
                                                !demoSourceIds.includes(
                                                    sourceFilter,
                                                ) && (
                                                    <option
                                                        value={sourceFilter}
                                                    >
                                                        {sourceFilter}
                                                    </option>
                                                )}
                                        </select>
                                    </div>

                                    {!connected ? (
                                        <p className="p-6 text-sm text-muted-foreground">
                                            API belum tersedia.
                                        </p>
                                    ) : visible.length === 0 ? (
                                        <p className="p-6 text-sm text-muted-foreground">
                                            Tidak ada sinyal pada filter atau
                                            halaman ini.
                                        </p>
                                    ) : (
                                        <div className="divide-y">
                                            {visible.map((signal) => {
                                                const isSelected =
                                                    signal.signal_id ===
                                                    selectedSignalId;
                                                return (
                                                    <a
                                                        key={signal.signal_id}
                                                        href={href({
                                                            source: sourceFilter,
                                                            page,
                                                            signal: signal.signal_id,
                                                        })}
                                                        className={
                                                            'block border-l-[3px] px-4 py-4 transition-colors hover:bg-muted/40 ' +
                                                            (isSelected
                                                                ? 'border-l-foreground bg-muted/60'
                                                                : 'border-l-transparent')
                                                        }
                                                    >
                                                        <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                                                            <span className="font-mono font-semibold">
                                                                {
                                                                    signal.source_id
                                                                }
                                                            </span>
                                                            <span className="shrink-0">
                                                                {dateLabel(
                                                                    signal.published_at,
                                                                )}
                                                            </span>
                                                        </div>
                                                        <h3 className="mt-2 text-sm font-semibold">
                                                            {signal.title}
                                                        </h3>
                                                        <div className="mt-2 flex flex-wrap items-center gap-2">
                                                            <Tag subtle>
                                                                {
                                                                    signal.provenance_mode
                                                                }
                                                            </Tag>
                                                            <Tag>
                                                                {
                                                                    signal.evidence_admission
                                                                }
                                                            </Tag>
                                                            {syndicationIds.has(
                                                                signal.signal_id,
                                                            ) && (
                                                                <Tag subtle>
                                                                    Possible
                                                                    syndication
                                                                </Tag>
                                                            )}
                                                            <span className="ml-auto flex items-center gap-1 text-xs text-muted-foreground">
                                                                Inspect{' '}
                                                                <ArrowRight className="h-3.5 w-3.5" />
                                                            </span>
                                                        </div>
                                                    </a>
                                                );
                                            })}
                                        </div>
                                    )}
                                    <div className="flex items-center justify-between gap-3 border-t p-4 text-xs text-muted-foreground">
                                        <span>
                                            Halaman {page} / {pages} ·{' '}
                                            {signalCount} sinyal
                                            {search
                                                ? ' · pencarian hanya halaman ini'
                                                : ''}
                                        </span>
                                        <div className="flex gap-2">
                                            <a
                                                aria-disabled={page <= 1}
                                                href={
                                                    page > 1
                                                        ? href({
                                                              source: sourceFilter,
                                                              page: page - 1,
                                                          })
                                                        : undefined
                                                }
                                                className={
                                                    'inline-flex items-center gap-1 rounded border px-3 py-2 ' +
                                                    (page <= 1
                                                        ? 'pointer-events-none opacity-40'
                                                        : 'hover:bg-muted')
                                                }
                                            >
                                                <ChevronLeft className="h-3.5 w-3.5" />{' '}
                                                Prev
                                            </a>
                                            <a
                                                aria-disabled={page >= pages}
                                                href={
                                                    page < pages
                                                        ? href({
                                                              source: sourceFilter,
                                                              page: page + 1,
                                                          })
                                                        : undefined
                                                }
                                                className={
                                                    'inline-flex items-center gap-1 rounded border px-3 py-2 ' +
                                                    (page >= pages
                                                        ? 'pointer-events-none opacity-40'
                                                        : 'hover:bg-muted')
                                                }
                                            >
                                                Next{' '}
                                                <ChevronRight className="h-3.5 w-3.5" />
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <aside
                                    aria-label="Detail sinyal"
                                    className="bg-muted/10 p-5"
                                >
                                    {!selected ? (
                                        <div className="flex h-full min-h-72 flex-col items-center justify-center text-center">
                                            <Fingerprint className="mb-3 h-8 w-8 text-muted-foreground" />
                                            <h2 className="font-semibold">
                                                Inspect a signal
                                            </h2>
                                            <p className="mt-2 max-w-64 text-xs text-muted-foreground">
                                                Pilih publikasi di sebelah kiri
                                                untuk melihat identitas stabil,
                                                provenance, dan histori
                                                versinya.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="space-y-5">
                                            <div className="flex items-start justify-between gap-2">
                                                <div>
                                                    <p className="text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                                                        Signal Inspector
                                                    </p>
                                                    <h2 className="mt-2 text-lg font-semibold">
                                                        {selected.title}
                                                    </h2>
                                                </div>
                                                <a
                                                    href={close}
                                                    aria-label="Tutup inspector"
                                                    className="rounded border px-2 py-1 text-xs hover:bg-muted"
                                                >
                                                    Tutup
                                                </a>
                                            </div>
                                            <Tag>
                                                NOT_EVALUATED · no evidence
                                                admitted
                                            </Tag>
                                            <div className="rounded-lg border bg-card p-3">
                                                <p className="mb-2 text-xs text-muted-foreground">
                                                    Buat berkas investigasi draf
                                                    untuk sinyal ini. Tidak
                                                    membentuk Issue Object atau
                                                    evidence.
                                                </p>
                                                <button
                                                    type="button"
                                                    disabled={
                                                        creatingInvestigation ||
                                                        !connected
                                                    }
                                                    onClick={() => {
                                                        setInvestigationError(
                                                            '',
                                                        );
                                                        router.post(
                                                            '/sifi/investigations',
                                                            {
                                                                signal_id:
                                                                    selected.signal_id,
                                                            },
                                                            {
                                                                onStart: () =>
                                                                    setCreatingInvestigation(
                                                                        true,
                                                                    ),
                                                                onFinish: () =>
                                                                    setCreatingInvestigation(
                                                                        false,
                                                                    ),
                                                                onError: (
                                                                    errors,
                                                                ) =>
                                                                    setInvestigationError(
                                                                        errors.signal_id ||
                                                                            'Tidak dapat membuat investigasi.',
                                                                    ),
                                                            },
                                                        );
                                                    }}
                                                    className="w-full rounded-lg bg-foreground px-4 py-2.5 text-sm font-semibold text-background disabled:opacity-50"
                                                >
                                                    {creatingInvestigation
                                                        ? 'Memeriksa sinyal…'
                                                        : 'Buat investigasi draf →'}
                                                </button>
                                                {investigationError && (
                                                    <p
                                                        role="alert"
                                                        className="mt-2 text-xs text-destructive"
                                                    >
                                                        {investigationError}
                                                    </p>
                                                )}
                                            </div>
                                            <dl className="space-y-3 text-xs">
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Source ID
                                                    </dt>
                                                    <dd className="mt-1 font-semibold">
                                                        {selected.source_id}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Publisher / owner (as
                                                        reported)
                                                    </dt>
                                                    <dd className="mt-1">
                                                        {selected.publisher_or_owner ||
                                                            selected.source_name ||
                                                            'Unknown'}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Published
                                                    </dt>
                                                    <dd className="mt-1">
                                                        {dateLabel(
                                                            selected.published_at,
                                                        )}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        First seen / last seen
                                                    </dt>
                                                    <dd className="mt-1">
                                                        {dateLabel(
                                                            selected.first_seen_at,
                                                        )}{' '}
                                                        /{' '}
                                                        {dateLabel(
                                                            selected.last_seen_at,
                                                        )}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Fetch count / revision
                                                    </dt>
                                                    <dd className="mt-1">
                                                        {selected.fetch_count} /{' '}
                                                        {selected.version || 1}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Signal fingerprint
                                                    </dt>
                                                    <dd className="mt-1 font-mono text-[10px] break-all">
                                                        {selected.signal_id}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Canonical reference
                                                        (fixture only; not
                                                        opened)
                                                    </dt>
                                                    <dd className="mt-1 font-mono text-[10px] break-all">
                                                        {selected.canonical_url ||
                                                            'Not available'}
                                                    </dd>
                                                </div>
                                            </dl>
                                            {syndicationIds.has(
                                                selected.signal_id,
                                            ) && (
                                                <div className="rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-xs">
                                                    <AlertTriangle className="mb-1 h-4 w-4 text-amber-600" />
                                                    Possible syndication:
                                                    matching content
                                                    fingerprint. Source
                                                    independence NOT verified.
                                                </div>
                                            )}
                                            <div className="border-t pt-3">
                                                <h3 className="mb-2 flex items-center gap-2 text-sm font-semibold">
                                                    <History className="h-4 w-4" />{' '}
                                                    Version history
                                                </h3>
                                                <div className="space-y-2">
                                                    {selectedHistory.length ===
                                                    0 ? (
                                                        <p className="text-xs text-muted-foreground">
                                                            No version history
                                                            loaded.
                                                        </p>
                                                    ) : (
                                                        selectedHistory.map(
                                                            (v) => (
                                                                <div
                                                                    key={
                                                                        v.version
                                                                    }
                                                                    className="rounded-lg border bg-card p-3 text-xs"
                                                                >
                                                                    <p className="font-semibold">
                                                                        Version{' '}
                                                                        {
                                                                            v.version
                                                                        }
                                                                    </p>
                                                                    <p className="mt-1 text-muted-foreground">
                                                                        Observed{' '}
                                                                        {dateLabel(
                                                                            v.observed_at,
                                                                        )}
                                                                    </p>
                                                                </div>
                                                            ),
                                                        )
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </aside>
                            </div>
                        ) : (
                            <div className="space-y-5 p-5">
                                <div className="flex items-center gap-3">
                                    <Server className="h-5 w-5" />
                                    <div>
                                        <h2 className="text-lg font-semibold">
                                            Registered sources
                                        </h2>
                                        <p className="text-xs text-muted-foreground">
                                            Governed candidate universe;
                                            registry presence is not access
                                            permission.
                                        </p>
                                    </div>
                                </div>
                                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    {sources.map((src) => (
                                        <div
                                            key={src.source_id}
                                            className="rounded-xl border p-4"
                                        >
                                            <div className="flex justify-between gap-2">
                                                <Globe2 className="h-5 w-5 text-muted-foreground" />
                                                <Tag subtle>{src.zone}</Tag>
                                            </div>
                                            <h3 className="mt-3 text-sm font-semibold">
                                                {src.name}
                                            </h3>
                                            <p className="mt-2 font-mono text-xs text-muted-foreground">
                                                {src.source_id}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {src.family}
                                            </p>
                                            <div className="mt-4 flex flex-wrap gap-2">
                                                <Tag subtle>
                                                    {src.verification_state}
                                                </Tag>
                                                <Tag subtle>Live OFF</Tag>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                <p className="rounded-lg border bg-muted/50 p-3 text-xs text-muted-foreground">
                                    Registry tidak memberi persetujuan untuk
                                    mengambil full text, menyimpan,
                                    mendistribusikan, atau menggunakan sumber
                                    secara komersial. Seluruh perubahan sumber
                                    tetap memerlukan rights assessment terpisah.
                                </p>
                            </div>
                        )}
                    </div>
                    <p className="flex items-center gap-2 text-xs text-muted-foreground">
                        <ShieldAlert className="h-4 w-4" /> SIFI-AQ01 · Offline
                        controlled demo · No production acquisition · No policy
                        decision automation
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
