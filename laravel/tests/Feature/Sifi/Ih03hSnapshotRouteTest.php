<?php

namespace Tests\Feature\Sifi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class Ih03hSnapshotRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_local_snapshot(): void
    {
        $this->get('/sifi/research-source-preview')->assertRedirect(route('login'));
    }

    public function test_verified_user_gets_not_found_for_absent_selected_preview(): void
    {
        config([
            'sifi_ih03h.preview_filename' => 'SIFI_IH03G_FUSION_UI_20990101_000000_aaaaaaaaaaaa.html',
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)
            ->get('/sifi/research-source-preview')
            ->assertNotFound();
    }

    public function test_invalid_filename_fails_closed(): void
    {
        config(['sifi_ih03h.preview_filename' => '../.env']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)
            ->get('/sifi/research-source-preview')
            ->assertNotFound();
    }
}
