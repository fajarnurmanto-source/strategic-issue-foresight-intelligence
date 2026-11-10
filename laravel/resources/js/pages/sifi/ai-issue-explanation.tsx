import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
type Props = {
    candidate: { id: string; revision: number };
    notes: Record<string, string>;
    outcome?: {
        candidate_id?: string;
        revision?: number;
        status: string;
        reason?: string;
        reference?: string;
        model?: string;
        output?: Record<string, string>;
    };
};
export default function Explanation({ candidate, notes, outcome }: Props) {
    const form = useForm({
        rights_confirmed: false,
        revision: candidate.revision,
    });
    const result =
        outcome &&
        (!outcome.candidate_id || outcome.candidate_id === candidate.id) &&
        (!outcome.revision || outcome.revision === candidate.revision)
            ? outcome
            : null;
    return (
        <>
            <Head title="Pahami isu" />
            <div className="mx-auto max-w-3xl space-y-5 p-6">
                <h1 className="text-2xl font-semibold">
                    Bantu saya memahami isu ini
                </h1>
                <p>
                    NON_BINDING · NOT VERIFIED. Penjelasan AI dapat keliru dan
                    tidak mengesahkan bukti atau keputusan.
                </p>
                <p>
                    Catatan berikut hanya ditampilkan lokal. Pengiriman catatan
                    isu nyata diblokir dalam tahap zero-spend. Uji sintetis
                    publik tersedia melalui Pengaturan Model AI setelah
                    kelayakan gratis dan izin diperiksa.
                </p>
                <dl className="space-y-3 rounded border p-4">
                    {Object.entries(notes).map(([k, v]) => (
                        <div key={k}>
                            <dt className="font-medium">{k}</dt>
                            <dd className="break-words whitespace-pre-wrap">
                                {v || 'Belum dicatat'}
                            </dd>
                        </div>
                    ))}
                </dl>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(
                            `/sifi/issue-candidates/${candidate.id}/ai-explanation`,
                        );
                    }}
                    className="space-y-3"
                >
                    <label className="flex gap-2">
                        <input
                            type="checkbox"
                            checked={form.data.rights_confirmed}
                            onChange={(e) =>
                                form.setData(
                                    'rights_confirmed',
                                    e.target.checked,
                                )
                            }
                        />
                        Saya memahami bahwa pengiriman catatan isu nyata belum
                        diizinkan.
                    </label>
                    {Object.values(form.errors).map((v, i) => (
                        <p role="alert" key={i}>
                            {v}
                        </p>
                    ))}
                    <Button disabled={true}>
                        {form.processing
                            ? 'Menunggu provider…'
                            : 'Pengiriman isu belum diizinkan'}
                    </Button>
                </form>
                {result && (
                    <section
                        aria-label="Hasil penjelasan"
                        className="space-y-3 rounded border p-4"
                    >
                        <h2 className="font-semibold">{result.status}</h2>
                        <p>{result.reason}</p>
                        {Object.entries(result.output ?? {}).map(([k, v]) => (
                            <div key={k}>
                                <h3>{k}</h3>
                                <p className="break-words whitespace-pre-wrap">
                                    {v}
                                </p>
                            </div>
                        ))}
                        <p>
                            Referensi input server:{' '}
                            {result.reference ?? 'belum ada hasil'}
                        </p>
                        <p>Model: {result.model ?? 'tidak digunakan'}</p>
                    </section>
                )}
                <nav className="flex flex-wrap gap-4">
                    <Link href={`/sifi/issue-candidates/${candidate.id}`}>
                        Kembali ke kandidat
                    </Link>
                    <Link
                        href={`/sifi/issue-candidates/${candidate.id}/evidence`}
                    >
                        Periksa evidence
                    </Link>
                    <Link href="/sifi/workbench">Workbench</Link>
                    <Link href="/settings/ai-models">Pengaturan Model AI</Link>
                </nav>
            </div>
        </>
    );
}
Explanation.layout = {
    breadcrumbs: [{ title: 'Pahami isu', href: '/sifi/workbench' }],
};
