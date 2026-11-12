<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Services\Sifi\Aq01SignalProjection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Local, analyst-authored draft investigations, NOT qualified evidence or IssueObjects. */
final class InvestigationController extends Controller
{
    private const REVIEW_STATES = ['DRAFT', 'REVIEW_REQUESTED', 'ON_HOLD'];

    private const SIGNAL_LIMIT = 10;

    public function index(Request $request): Response
    {
        $userId = (int) $request->user()->id;
        $rows = DB::table('sifi_investigations')
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        $items = $rows->map(static fn ($row): array => [
            'id' => $row->id,
            'title' => $row->title,
            'review_state' => $row->review_state,
            'revision' => (int) $row->revision,
            'updated_at' => $row->updated_at,
            'claim_hypothesis' => $row->claim_hypothesis,
        ])->all();

        return Inertia::render('sifi/investigations-index', [
            'investigations' => $items,
            'totalCount' => DB::table('sifi_investigations')->where('user_id', $userId)->count(),
            'scope' => 'PERSONAL_LOCAL_DRAFT',
        ]);
    }

    public function store(Request $request, Aq01SignalProjection $projection): RedirectResponse
    {
        $data = $request->validate([
            'signal_id' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/D'],
        ]);
        $signal = $projection->find($data['signal_id']);
        if ($signal === null) {
            throw ValidationException::withMessages([
                'signal_id' => 'Sinyal fixture tidak tersedia atau API offline. Tidak ada investigasi dibuat.',
            ]);
        }
        $id = (string) Str::uuid();
        $now = now();
        $userId = (int) $request->user()->id;
        $title = mb_substr('Investigasi: '.$signal['title'], 0, 180);

        DB::transaction(function () use ($id, $now, $userId, $title, $data): void {
            DB::table('sifi_investigations')->insert([
                'id' => $id, 'user_id' => $userId, 'title' => $title,
                'review_state' => 'DRAFT', 'revision' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('sifi_investigation_signals')->insert([
                'investigation_id' => $id, 'signal_id' => $data['signal_id'],
                'added_by_user_id' => $userId, 'created_at' => $now,
            ]);
            $this->audit($id, $userId, 1, 'CREATED', [
                'title' => $title, 'review_state' => 'DRAFT', 'signal_ids' => [$data['signal_id']],
            ], $now);
        });

        return redirect()->route('sifi.investigations.show', $id);
    }

    public function show(Request $request, string $investigation, Aq01SignalProjection $projection): Response
    {
        $row = $this->owned($request, $investigation);
        $ids = DB::table('sifi_investigation_signals')
            ->where('investigation_id', $row->id)->orderBy('id')->pluck('signal_id')->all();
        $signals = [];
        foreach ($ids as $id) {
            $signals[] = [
                'signal_id' => $id,
                'projection' => $projection->find($id, (int) $request->user()->id),
            ];
        }
        $revisions = DB::table('sifi_investigation_audit')
            ->where('investigation_id', $row->id)
            ->orderByDesc('revision')->limit(25)
            ->get(['revision', 'action', 'created_at'])
            ->map(static fn ($entry): array => [
                'revision' => (int) $entry->revision,
                'action' => $entry->action,
                'created_at' => $entry->created_at,
            ])->all();

        return Inertia::render('sifi/investigation-detail', [
            'investigation' => [
                'id' => $row->id, 'title' => $row->title,
                'claim_hypothesis' => $row->claim_hypothesis ?? '',
                'evidence_gaps' => $row->evidence_gaps ?? '',
                'next_steps' => $row->next_steps ?? '',
                'review_state' => $row->review_state,
                'revision' => (int) $row->revision,
                'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
            ],
            'signals' => $signals,
            'revisions' => $revisions,
            'maxSignals' => self::SIGNAL_LIMIT,
            'scope' => 'PERSONAL_LOCAL_DRAFT',
        ]);
    }

    public function update(Request $request, string $investigation): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'claim_hypothesis' => ['nullable', 'string', 'max:4000'],
            'evidence_gaps' => ['nullable', 'string', 'max:4000'],
            'next_steps' => ['nullable', 'string', 'max:4000'],
            'review_state' => ['required', Rule::in(self::REVIEW_STATES)],
            'revision' => ['required', 'integer', 'min:1'],
        ]);
        $id = $this->owned($request, $investigation)->id;
        $userId = (int) $request->user()->id;
        DB::transaction(function () use ($id, $userId, $data): void {
            $current = DB::table('sifi_investigations')
                ->where('id', $id)->where('user_id', $userId)->lockForUpdate()->first();
            abort_unless($current !== null, 404);
            if ((int) $current->revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'Investigasi diperbarui di sesi lain. Muat ulang sebelum menyimpan.']);
            }
            $fields = [
                'title' => trim($data['title']),
                'claim_hypothesis' => $data['claim_hypothesis'] ?? null,
                'evidence_gaps' => $data['evidence_gaps'] ?? null,
                'next_steps' => $data['next_steps'] ?? null,
                'review_state' => $data['review_state'],
            ];
            $changed = false;
            foreach ($fields as $key => $value) {
                if ($current->{$key} !== $value) {
                    $changed = true;
                    break;
                }
            }
            if (! $changed) {
                return;
            }
            $revision = (int) $current->revision + 1;
            DB::table('sifi_investigations')->where('id', $id)->update([
                ...$fields, 'revision' => $revision, 'updated_at' => now(),
            ]);
            $this->audit($id, $userId, $revision, 'ASSESSMENT_EDITED', $fields, now());
        });

        return redirect()->route('sifi.investigations.show', $id);
    }

    public function attachSignal(Request $request, string $investigation, Aq01SignalProjection $projection): RedirectResponse
    {
        $data = $request->validate(['signal_id' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/D']]);
        $row = $this->owned($request, $investigation);
        if ($projection->find($data['signal_id']) === null) {
            throw ValidationException::withMessages(['signal_id' => 'Sinyal fixture tidak tersedia; lampiran dibatalkan.']);
        }
        $userId = (int) $request->user()->id;
        DB::transaction(function () use ($row, $userId, $data): void {
            $current = DB::table('sifi_investigations')->where('id', $row->id)
                ->where('user_id', $userId)->lockForUpdate()->first();
            abort_unless($current !== null, 404);
            $already = DB::table('sifi_investigation_signals')
                ->where('investigation_id', $row->id)->where('signal_id', $data['signal_id'])->exists();
            if ($already) {
                return;
            }
            $count = DB::table('sifi_investigation_signals')
                ->where('investigation_id', $row->id)->count();
            if ($count >= self::SIGNAL_LIMIT) {
                throw ValidationException::withMessages(['signal_id' => 'Maksimum 10 referensi sinyal per investigasi.']);
            }
            DB::table('sifi_investigation_signals')->insert([
                'investigation_id' => $row->id, 'signal_id' => $data['signal_id'],
                'added_by_user_id' => $userId, 'created_at' => now(),
            ]);
            $revision = (int) $current->revision + 1;
            DB::table('sifi_investigations')->where('id', $row->id)->update([
                'revision' => $revision, 'updated_at' => now(),
            ]);
            $this->audit($row->id, $userId, $revision, 'SIGNAL_ATTACHED', ['signal_id' => $data['signal_id']], now());
        });

        return redirect()->route('sifi.investigations.show', $row->id);
    }

    private function owned(Request $request, string $id): \stdClass
    {
        $row = DB::table('sifi_investigations')
            ->where('id', $id)->where('user_id', (int) $request->user()->id)->first();
        abort_unless($row !== null, 404);

        return $row;
    }

    /** @param array<string, mixed> $snapshot */
    private function audit(string $id, int $actorId, int $revision, string $action, array $snapshot, mixed $time): void
    {
        DB::table('sifi_investigation_audit')->insert([
            'investigation_id' => $id, 'actor_user_id' => $actorId,
            'revision' => $revision, 'action' => $action,
            'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => $time,
        ]);
    }
}
