<?php

namespace App\Http\Controllers\Sifi;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only projection of the repository-owned canonical roadmap.
 * A milestone in a document is never evidence that a feature has shipped.
 */
final class ProductRoadmapController extends Controller
{
    public function index(): Response
    {
        $file = base_path('docs/canonical/SIFI_MASTER_PRODUCT_ROADMAP_v1_2_20261109.md');
        $markdown = File::isFile($file) && File::size($file) <= 131072
            ? File::get($file) : null;

        $milestones = [];
        $journey = [];
        if (is_string($markdown)) {
            foreach (preg_split('/\R/u', $markdown) ?: [] as $line) {
                if (preg_match('/^\|\s*\*\*(E\d+)\s*\/\s*(.*?)\*\*\s*\|\s*(.*?)\s*\|\s*(.*?)\s*\|\s*$/u', $line, $match)) {
                    $milestones[] = [
                        'id' => $match[1],
                        'priority' => trim($match[2]),
                        'deliverable' => trim(str_replace(['**', '`'], '', $match[3])),
                        'acceptance' => trim(str_replace(['**', '`'], '', $match[4])),
                        'implementation_state' => 'NOT_ASSERTED_BY_ROADMAP',
                    ];
                }
                if (str_starts_with($line, '`REGISTER/SELECT MONITORING OBJECT')) {
                    $journey = array_values(array_filter(array_map('trim',
                        explode('→', trim($line, "`\r\n ")))));
                }
            }
        }

        return Inertia::render('sifi/product-roadmap', [
            'version' => 'v1.2',
            'source_path' => 'docs/canonical/SIFI_MASTER_PRODUCT_ROADMAP_v1_2_20261109.md',
            'source_state' => $markdown === null ? 'NOT_DEPLOYED' : 'AVAILABLE',
            'sha256' => $markdown === null ? null : hash('sha256', $markdown),
            'milestones' => $milestones,
            'journey' => $journey,
            'markdown' => $markdown,
            'network_requests' => 0,
            'database_writes' => 0,
            'production_admission' => 'DENIED',
        ]);
    }
}
