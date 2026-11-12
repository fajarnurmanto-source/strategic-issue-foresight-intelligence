<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use App\Models\SifiMonitoringObject;
use App\Services\Sifi\DiscoveryCatalog;
use App\Services\Sifi\SourceDiscoveryWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class SourceDiscoveryController extends Controller
{
    /** @return array<string, list<mixed>> */
    private function filterRules(): array
    {
        return ['q' => ['nullable', 'string', 'max:200'], 'type' => ['nullable', Rule::in(DiscoveryCatalog::TYPES)], 'language' => ['nullable', Rule::in(['id', 'en'])], 'geography' => ['nullable', 'string', 'max:180'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:20'], 'candidate_url' => ['nullable', 'string', 'max:500']];
    }

    /** @return array<string, list<string>> */
    private function forbidden(): array
    {
        return array_fill_keys(['user_id', 'rights', 'rights_state', 'collector_state', 'grant', 'permit', 'receipt', 'source_draft_id', 'descriptor', 'url', 'evidence_admitted', 'verified', 'schedule'], ['prohibited']);
    }

    public function index(Request $r, SifiMonitoringObject $object, DiscoveryCatalog $catalog, SourceDiscoveryWorkspace $workspace): Response
    {
        Gate::authorize('view', $object);
        $v = $r->validate($this->filterRules() + $this->forbidden());

        return Inertia::render('sifi/source-discovery', ['object' => $object->only(['id', 'name', 'category', 'geography', 'revision', 'state']), 'filters' => (object) $v, 'discovery' => $catalog->discover($object, $v), 'selections' => $workspace->selections($r->user(), $object), 'selection_limit' => 50]);
    }

    public function store(Request $r, SifiMonitoringObject $object, SourceDiscoveryWorkspace $w): RedirectResponse
    {
        Gate::authorize('update', $object);
        $v = $r->validate($this->filterRules() + $this->forbidden() + ['key' => ['required', 'string', 'max:80', 'regex:/\A[a-z0-9-]+\z/'], 'object_revision' => ['required', 'integer', 'min:1']]);
        $filters = array_intersect_key($v, $this->filterRules());
        $w->save($r->user(), $object->id, (int) $v['object_revision'], $v['key'], $filters);

        return redirect()->route('sifi.source-discovery.index', ['object' => $object->id] + $filters);
    }

    public function update(Request $r, SifiMonitoringObject $object, string $selection, SourceDiscoveryWorkspace $w): RedirectResponse
    {
        return $this->change($r, $object, $selection, $w, false);
    }

    public function register(Request $r, SifiMonitoringObject $object, string $selection, SourceDiscoveryWorkspace $w): RedirectResponse
    {
        return $this->change($r, $object, $selection, $w, true);
    }

    private function change(Request $r, SifiMonitoringObject $o, string $id, SourceDiscoveryWorkspace $w, bool $register): RedirectResponse
    {
        Gate::authorize('update', $o);
        $v = $r->validate($this->forbidden() + ['object_revision' => ['required', 'integer', 'min:1'], 'revision' => ['required', 'integer', 'min:1']]);
        $w->change($r->user(), $o->id, (int) $v['object_revision'], $id, (int) $v['revision'], $register);

        return redirect()->route('sifi.source-discovery.index',['object' => $o->id]);
    }
}
