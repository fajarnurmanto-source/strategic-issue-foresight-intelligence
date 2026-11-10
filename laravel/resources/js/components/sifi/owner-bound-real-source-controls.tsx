import { router } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';

type ObjectContext = {
    id: string;
    name: string;
    revision: number;
    state: string;
    keywords_json?: string[];
    aliases_json?: string[];
};
type Readiness = {
    enabled: boolean;
    crossref_allowed: boolean;
    antara_allowed: boolean;
    bluesky_allowed: boolean;
    social_autowatch_configured: boolean;
    social_contact_configured: boolean;
    object_ready: boolean;
    production_admission: 'DENIED';
    evidence_admission: 'NONE';
};

export default function OwnerBoundRealSourceControls({
    object,
}: {
    object: ObjectContext | null;
}) {
    const [status, setStatus] = useState<Readiness | null>(null);
    const [checking, setChecking] = useState(false);
    const [provider, setProvider] = useState<
        'CROSSREF' | 'ANTARA_RSS' | 'BLUESKY_PUBLIC_SEARCH'
    >('CROSSREF');
    const [socialTerm, setSocialTerm] = useState('');
    const [contact, setContact] = useState('');
    const [question, setQuestion] = useState('');
    const [submissionKey, setSubmissionKey] = useState(() =>
        crypto.randomUUID(),
    );
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        setStatus(null);
        if (!object) return;
        let active = true;
        setChecking(true);
        fetch(
            `/sifi/monitoring-objects/${object.id}/research-real-source/status`,
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        )
            .then(async (response) => {
                if (!response.ok) throw new Error('STATUS_UNAVAILABLE');
                return response.json() as Promise<Readiness>;
            })
            .then((value) => {
                if (active) {
                    setStatus(value);
                    if (value.bluesky_allowed)
                        setProvider('BLUESKY_PUBLIC_SEARCH');
                }
            })
            .catch(() => {
                if (active) setStatus(null);
            })
            .finally(() => {
                if (active) setChecking(false);
            });
        return () => {
            active = false;
        };
    }, [object?.id, object?.revision]);

    if (!object) return null;
    const terms = [
        ...new Set([
            ...(object.keywords_json ?? []),
            ...(object.aliases_json ?? []),
            object.name,
        ]),
    ].filter(
        (term) =>
            typeof term === 'string' &&
            term.trim().length >= 3 &&
            term.length <= 80,
    );
    const selectedSocialTerm = terms.includes(socialTerm)
        ? socialTerm
        : (terms[0] ?? '');
    const isSocial = provider === 'BLUESKY_PUBLIC_SEARCH';
    const allowed = Boolean(
        status?.object_ready &&
        (provider === 'CROSSREF'
            ? status?.crossref_allowed
            : provider === 'ANTARA_RSS'
              ? status?.antara_allowed
              : status?.bluesky_allowed),
    );

    function collect(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!object || !allowed || busy) return;
        setErrors({});
        router.post(
            `/sifi/monitoring-objects/${object.id}/research-real-source`,
            {
                provider,
                contact_email: contact,
                question:
                    provider === 'CROSSREF'
                        ? question
                        : isSocial
                          ? selectedSocialTerm
                          : '',
                object_revision: object.revision,
                submission_key: submissionKey,
            },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: setErrors,
                onSuccess: () => setSubmissionKey(crypto.randomUUID()),
            },
        );
    }

    return (
        <section
            id="social-watch"
            aria-label="Pengambilan metadata nyata dari akun ini"
            className="min-w-0 space-y-4 rounded-xl border border-sky-500/30 bg-card p-4 sm:p-5"
        >
            <header className="space-y-2">
                <h2 className="text-lg font-semibold">
                    Sumber nyata · akun ini
                </h2>
                <p className="text-sm text-muted-foreground">
                    Hanya objek milik akun yang login. Satu tindakan = maksimal
                    satu permintaan sumber, tanpa pengambilan artikel penuh, AI,
                    atau evidence admission.
                </p>
            </header>
            <p role="status" className="text-sm">
                {checking
                    ? 'Memeriksa izin konektor…'
                    : !status
                      ? 'Status izin konektor belum dapat dimuat.'
                      : status.crossref_allowed ||
                          status.antara_allowed ||
                          status.bluesky_allowed
                        ? `Izin lokal: Crossref ${status.crossref_allowed ? 'tersedia' : 'terkunci'} · ANTARA ${status.antara_allowed ? 'tersedia' : 'terkunci'} · Bluesky ${status.bluesky_allowed ? 'tersedia' : 'terkunci'}.`
                        : 'Pengambilan nyata terkunci. Dibutuhkan izin lokal aktif dan database SQLite terisolasi di D:\\Research\\SIFI\\05_VALIDATION.'}
            </p>
            {status?.social_autowatch_configured && (
                <p className="rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-3 text-sm">
                    Social Watch terjadwal:{' '}
                    <strong>diizinkan untuk objek akun Anda</strong>. Satu objek
                    dipilih bergiliran pada setiap tick, maksimal sesuai kuota.
                    Pengambilan otomatis hanya berjalan jika Laravel scheduler
                    lokal dijalankan; status ini bukan bukti permintaan jaringan
                    sudah terjadi.
                </p>
            )}
            <form onSubmit={collect} className="space-y-3">
                <label className="block text-sm font-medium">
                    Sumber
                    <select
                        className="mt-1 w-full rounded-lg border bg-background p-3"
                        value={provider}
                        onChange={(event) => {
                            setProvider(
                                event.target.value as
                                    | 'CROSSREF'
                                    | 'ANTARA_RSS'
                                    | 'BLUESKY_PUBLIC_SEARCH',
                            );
                            setErrors({});
                        }}
                    >
                        <option value="CROSSREF">
                            Crossref · metadata penelitian (maks. 5)
                        </option>
                        <option value="ANTARA_RSS">
                            ANTARA · judul dan tautan RSS (maks. 5)
                        </option>
                        <option value="BLUESKY_PUBLIC_SEARCH">
                            Bluesky · sinyal isu publik (maks. 10)
                        </option>
                    </select>
                </label>
                {isSocial && (
                    <label className="block text-sm font-medium">
                        Kata kunci objek pantauan
                        <select
                            className="mt-1 w-full rounded-lg border bg-background p-3"
                            value={selectedSocialTerm}
                            onChange={(event) =>
                                setSocialTerm(event.target.value)
                            }
                        >
                            {terms.map((term) => (
                                <option key={term} value={term}>
                                    {term}
                                </option>
                            ))}
                        </select>
                        <span className="mt-1 block text-xs text-muted-foreground">
                            Kata kunci diambil dari konfigurasi objek; sistem
                            memilah unggahan otomatis.
                        </span>
                    </label>
                )}
                {provider === 'CROSSREF' && (
                    <label className="block text-sm font-medium">
                        Pertanyaan penelitian
                        <textarea
                            className="mt-1 w-full rounded-lg border bg-background p-3"
                            rows={2}
                            required
                            minLength={8}
                            maxLength={600}
                            value={question}
                            onChange={(event) =>
                                setQuestion(event.target.value)
                            }
                            placeholder="Contoh: Apa kajian tentang literasi digital dan misinformasi?"
                        />
                    </label>
                )}
                {(!isSocial || !status?.social_contact_configured) && (
                    <label className="block text-sm font-medium">
                        Email kontak aktif untuk penyedia metadata (dikirim ke
                        penyedia)
                        <input
                            className="mt-1 w-full rounded-lg border bg-background p-3"
                            type="email"
                            autoComplete="email"
                            required
                            maxLength={254}
                            value={contact}
                            onChange={(event) => setContact(event.target.value)}
                            placeholder="nama@domain.org"
                        />
                    </label>
                )}
                {Object.entries(errors).map(([key, value]) => (
                    <p
                        role="alert"
                        className="text-sm break-words text-destructive"
                        key={key}
                    >
                        {value}
                    </p>
                ))}
                <button
                    type="submit"
                    className="rounded-lg border bg-foreground px-4 py-3 text-sm text-background disabled:cursor-not-allowed disabled:opacity-40"
                    disabled={
                        !allowed ||
                        busy ||
                        (!contact &&
                            (!isSocial ||
                                !status?.social_contact_configured)) ||
                        (provider === 'CROSSREF' &&
                            question.trim().length < 8) ||
                        (isSocial && !selectedSocialTerm)
                    }
                >
                    {busy
                        ? 'Mengambil sinyal…'
                        : isSocial
                          ? 'Pantau isu sosial sekarang'
                          : 'Ambil metadata nyata sekali'}
                </button>
            </form>
            <p className="text-xs text-muted-foreground">
                Batas kuota dan review masing-masing penyedia tetap berlaku.
                Hasil disimpan dalam riwayat penelitian akun ini; tidak membuat
                klaim terverifikasi, bukti, atau akses produksi. Tidak ada retry
                otomatis.
            </p>
        </section>
    );
}
