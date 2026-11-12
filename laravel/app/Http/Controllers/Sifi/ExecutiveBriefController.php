<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sifi\SaveExecutiveBriefRequest;
use App\Models\SifiExecutiveBrief;
use App\Models\SifiIssueCandidate;
use App\Services\Sifi\ExecutiveBriefSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Human-owned brief composer; never certified output or automated decision. */
final class ExecutiveBriefController extends Controller
{
    private function brief(SifiIssueCandidate $c): ?SifiExecutiveBrief
    {
        return SifiExecutiveBrief::query()->where('candidate_id', $c->id)->first();
    }

    /** @return array<string, string|int> */
    private function viewBrief(SifiExecutiveBrief $b): array
    {
        return ['id' => $b->id, 'revision' => (int) $b->revision, 'title' => $b->title,
            'audience' => $b->audience, 'executive_summary' => $b->executive_summary,
            'decision_questions' => $b->decision_questions, 'verification_priorities' => $b->verification_priorities,
            'limitations' => $b->limitations, 'review_state' => $b->review_state, 'input_digest' => $b->input_digest];
    }

    public function show(Request $request, SifiIssueCandidate $candidate, ExecutiveBriefSource $service): Response
    {
        Gate::authorize('view', $candidate);
        [$case,$trajectory,$foresight] = $service->parents($candidate, (int) $request->user()->id);
        $source = $service->assemble($candidate, $case, $trajectory, $foresight);
        $digest = $service->digest($source);
        $brief = $this->brief($candidate);
        if ($brief) {
            abort_unless((int) $brief->owner_user_id === (int) $request->user()->id, 403);
        }
        $audit = $brief ? DB::table('sifi_executive_brief_audit')->where('brief_id', $brief->id)->orderByDesc('revision')
            ->limit(20)->get(['revision', 'action', 'created_at'])->all() : [];

        return Inertia::render('sifi/executive-brief-workspace', [
            'candidate' => ['id' => $candidate->id, 'revision' => (int) $candidate->revision, 'title' => $candidate->title],
            'source' => $source, 'sourceDigest' => $digest, 'brief' => $brief ? $this->viewBrief($brief) : null,
            'sourceCurrent' => $brief === null || hash_equals($brief->input_digest, $digest),
            'audit' => $audit, 'mode' => $source['data_mode'].'_UNAPPROVED_BRIEF']);
    }

    public function save(SaveExecutiveBriefRequest $request, SifiIssueCandidate $candidate, ExecutiveBriefSource $service): RedirectResponse
    {
        $v = $request->validated();
        DB::transaction(function () use ($request, $candidate, $service, $v): void {
            $c = SifiIssueCandidate::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $c->user_id === (int) $request->user()->id, 403);
            [$case,$trajectory,$foresight] = $service->parents($c, (int) $request->user()->id, true);
            if ((int) $c->revision !== (int) $v['candidate_revision'] || (int) $case->revision !== (int) $v['case_revision'] ||
              (int) $trajectory->revision !== (int) $v['trajectory_revision'] ||
              (int) $foresight->revision !== (int) $v['foresight_revision']) {
                throw ValidationException::withMessages(['input_digest' => 'Sumber berubah; muat ulang halaman.']);
            }
            $source = $service->assemble($c, $case, $trajectory, $foresight);
            $digest = $service->digest($source);
            if (! hash_equals($digest, $v['input_digest'])) {
                throw ValidationException::withMessages(['input_digest' => 'Sumber berubah selama penyusunan; muat ulang.']);
            }
            $brief = SifiExecutiveBrief::query()->where('candidate_id', $c->id)->lockForUpdate()->first();
            if (($brief === null && isset($v['brief_revision'])) ||
               ($brief !== null && (! isset($v['brief_revision']) || (int) $brief->revision !== (int) $v['brief_revision']))) {
                throw ValidationException::withMessages(['brief_revision' => 'Brief diperbarui di tempat lain; muat ulang.']);
            }
            $fields = [];
            foreach (['title', 'audience', 'executive_summary', 'decision_questions', 'verification_priorities', 'limitations', 'review_state'] as $field) {
                $fields[$field] = trim($v[$field]);
            }
            $fields['input_digest'] = $digest;
            $fields['input_snapshot_json'] = json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            if ($brief === null) {
                $brief = SifiExecutiveBrief::query()->create($fields + [
                    'id' => (string) Str::uuid(), 'candidate_id' => $c->id,
                    'owner_user_id' => (int) $request->user()->id, 'revision' => 1]);
                $action = 'EXECUTIVE_BRIEF_CREATED';
            } else {
                $brief->fill($fields);
                if (! $brief->isDirty()) {
                    return;
                }
                $brief->revision++;
                $brief->save();
                $action = 'EXECUTIVE_BRIEF_REVISED';
            }
            DB::table('sifi_executive_brief_audit')->insert([
                'brief_id' => $brief->id, 'actor_user_id' => (int) $request->user()->id,
                'revision' => $brief->revision, 'action' => $action,
                'snapshot_json' => json_encode($this->viewBrief($brief), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_at' => now()]);
        });

        return redirect()->route('sifi.executive-brief.show', $candidate->id);
    }

    public function download(Request $request, SifiIssueCandidate $candidate, ExecutiveBriefSource $service): HttpResponse
    {
        Gate::authorize('view', $candidate);
        [$case,$trajectory,$foresight] = $service->parents($candidate, (int) $request->user()->id);
        $source = $service->assemble($candidate, $case, $trajectory, $foresight);
        $brief = $this->brief($candidate);
        abort_unless($brief !== null && (int) $brief->owner_user_id === (int) $request->user()->id, 404);
        if (! hash_equals($brief->input_digest, $service->digest($source))) {
            abort(409, 'Brief stale; synchronize first.');
        }
        $quote = static fn (?string $s): string => implode("\n", array_map(static fn ($l) => '> '.($l === '' ? ' ' : $l), explode("\n", trim((string) $s))));
        $sections = [
            ['Ringkasan analis', $brief->executive_summary], ['Pertanyaan keputusan', $brief->decision_questions],
            ['Prioritas verifikasi', $brief->verification_priorities], ['Batasan dan ketidakpastian', $brief->limitations],
            ['Kandidat isu (belum diverifikasi)', $source['candidate']['hypothesis']],
            ['Hipotesis trajectory', $source['trajectory']['direction']],
            ['Hipotesis risiko', $source['trajectory']['risk']],
            ['Hipotesis peluang', $source['trajectory']['opportunity']],
            ['Foresight: baseline', $source['foresight']['baseline']],
            ['Foresight: alternatif', $source['foresight']['alternative']],
            ['Foresight: stres', $source['foresight']['stress']],
            ['Falsification checks', $source['foresight']['falsification']],
        ];
        $out = "# SIFI — EXECUTIVE BRIEF (UNAPPROVED OFFLINE DRAFT)\n\n";
        $out .= "NOT VERIFIED | EVIDENCE NOT_EVALUATED | NO FORECAST | NO DECISION APPROVAL\n\n";
        $out .= "Brief ID: {$brief->id} | Revision: {$brief->revision} | State: {$brief->review_state}\n";
        $out .= "Issue Case: {$case->id} (v{$case->revision}) | Source fingerprint: {$brief->input_digest}\n\n";
        $out .= "## Judul briefing\n\n".$quote($brief->title)."\n\n";
        $out .= "Audience: {$brief->audience}\n\n";
        foreach ($sections as [$name,$body]) {
            $out .= "## {$name}\n\n".$quote($body)."\n\n";
        }
        $out .= "## Status sumber\n\n";
        $out .= "Fixture offline; claim/event not verified; evidence NOT_EVALUATED.\n";
        $out .= 'Observed draft counts: '.json_encode($source['draft_counts'], JSON_THROW_ON_ERROR)."\n";
        $out .= 'Past Python foresight advisory: '.($source['foresight_advisory']['marker'] ?? 'NONE')." (not a forecast)\n";
        if (isset($source['source_lineage'])) {
            $lineage = $source['source_lineage'];
            $out .= "\n## Jejak sumber ke brief — ".(($lineage['mode'] ?? null) === 'MOCK_ACQUISITION_ONLY' ? 'MOCK/SYNTHETIC ACQUISITION' : (in_array($lineage['connector'] ?? null, ['wb_gdp', 'wb_acquisition'], true) ? 'REAL PUBLIC ARCHIVE' : 'SYNTHETIC FIXTURE ONLY'))."\n\n";
            $out .= $quote(in_array($lineage['connector'] ?? null, ['wb_gdp', 'wb_acquisition'], true) ? $lineage['source']['attribution'].' Lisensi: https://creativecommons.org/licenses/by/4.0/ Ketentuan: https://www.worldbank.org/ext/en/legal/terms-conditions/datasets Metadata: '.$lineage['source']['documentation_url'].'; transformasi SIFI, tanpa endorsement.' : (($lineage['connector'] ?? null) === 'yk_pad' ? 'Rujukan katalog Open Data Kota Yogyakarta; format pertukaran dan angka dibuat SIFI. Bukan angka resmi. Hak komersial/schema API belum terbukti.' : (($lineage['connector'] ?? null) === 'feed_fixture' ? 'SIFI self-authored SYNTHETIC feed fixtures; summaries are not full articles; rights UNASSESSED, collector OFF.' : (($lineage['connector'] ?? null) === 'research_fixture' ? 'SIFI self-authored document fixture; no external publication.' : 'BMKG sebagai rujukan format; seluruh nilai dibuat SIFI. Bukan data BMKG aktual.'))))."\n\n";
            $out .= $quote('Informasi teramati: '.(in_array($lineage['connector'] ?? null, ['wb_gdp', 'wb_acquisition'], true) ? (($lineage['mode'] ?? null) === 'MOCK_ACQUISITION_ONLY' ? 'payload mock lokal sintetis, bukan respons World Bank aktual.' : 'tiga record nasional historis World Bank, bukan kondisi terkini/lokal.') : (($lineage['connector'] ?? null) === 'yk_pad' ? 'isi fixture agregat PAD, bukan angka resmi atau kesimpulan fiskal.' : (($lineage['connector'] ?? null) === 'feed_fixture' ? 'metadata RSS/Atom/agregator sintetis, bukan artikel lengkap atau kondisi nyata.' : (($lineage['connector'] ?? null) === 'research_fixture' ? 'isi dokumen sintetis ber-hash dan byte-span, bukan publikasi nyata.' : 'isi fixture prakiraan, bukan cuaca teramati.')))).' Klaim: NOT VERIFIED. Inferensi: hipotesis analis. Kekurangan evidence: '.$source['candidate']['evidence_gaps'])."\n\n";
            $out .= $quote('Keterbatasan sumber: '.$lineage['source']['limitation'])."\n\n";
            $out .= $quote('Record, raw snapshot, pointer, checksum, issue/valid time, claim/evidence links, duplikat, konflik dan perubahan versi:')."\n\n";
            $out .= $quote(json_encode($lineage, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))."\n";
        }
        $out .= "\nDO NOT USE AS AN OFFICIAL OR VERIFIED INTELLIGENCE FINDING.\n";

        return response($out, 200, ['Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sifi-executive-brief-draft.md"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
