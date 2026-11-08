<?php

namespace Tests\Feature\Sifi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SifiSourceCatalogWorkflowTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite(); Http::preventStrayRequests();
    }
    private function user(): User { return User::factory()->create(['email_verified_at'=>now()]); }
    private function sourceData(): array { return ['name'=>'Example Official Portal','source_type'=>'OFFICIAL','homepage_url'=>'https://example.org','source_geography'=>'Kota Yogyakarta','notes'=>'Unverified draft']; }
    private function profileData(array $ids=[]): array { return ['name'=>'Pilot Yogyakarta','level'=>'CITY','region_name'=>'Kota Yogyakarta','domains'=>['lingkungan','infrastruktur'],'source_ids'=>$ids]; }
    private function createSource(User $user): string
    {
        $this->actingAs($user)->post('/sifi/source-catalog/sources',$this->sourceData())->assertRedirect();
        return DB::table('sifi_source_drafts')->where('user_id',$user->id)->value('id');
    }
    public function test_guest_cannot_access_catalog(): void
    {
        $this->get('/sifi/source-catalog')->assertRedirect('/login');
        $this->post('/sifi/source-catalog/sources',$this->sourceData())->assertRedirect('/login');
    }
    public function test_source_draft_is_local_not_enabled(): void
    {
        $user=$this->user();$this->createSource($user);
        $this->assertDatabaseHas('sifi_source_drafts',['user_id'=>$user->id,'rights_state'=>'UNASSESSED','collector_state'=>'OFF','revision'=>1]);
        $this->assertDatabaseHas('sifi_catalog_audit',['subject_type'=>'SOURCE','action'=>'CREATED','revision'=>1]);
        Http::assertNothingSent();
    }
    public function test_client_cannot_promote_rights_or_collector(): void
    {
        $this->actingAs($this->user())->post('/sifi/source-catalog/sources',array_merge($this->sourceData(),['collector_state'=>'ON','rights_state'=>'CLEARED']))->assertSessionHasErrors(['collector_state','rights_state']);
        $this->assertDatabaseCount('sifi_source_drafts',0);
    }
    public function test_update_requires_current_revision_and_appends_audit(): void
    {
        $user=$this->user();$id=$this->createSource($user);
        $this->actingAs($user)->patch('/sifi/source-catalog/sources/'.$id,array_merge($this->sourceData(),['revision'=>1,'name'=>'Revised Official Source']))->assertRedirect();
        $this->assertDatabaseHas('sifi_source_drafts',['id'=>$id,'name'=>'Revised Official Source','revision'=>2]);
        $this->patch('/sifi/source-catalog/sources/'.$id,array_merge($this->sourceData(),['revision'=>1,'name'=>'Stale']))->assertSessionHasErrors('revision');
        $this->assertDatabaseCount('sifi_catalog_audit',2);
    }
    public function test_owner_only_visibility_and_update(): void
    {
        $owner=$this->user();$id=$this->createSource($owner);
        $other=$this->user();
        $this->actingAs($other)->patch('/sifi/source-catalog/sources/'.$id,array_merge($this->sourceData(),['revision'=>1]))->assertForbidden();
        $this->actingAs($other)->get('/sifi/source-catalog')->assertOk()->assertInertia(fn(\Inertia\Testing\AssertableInertia $page)=>$page->component('sifi/source-catalog')->has('sources',0)->etc());
    }
    public function test_profile_links_only_owned_sources(): void
    {
        $owner=$this->user();$id=$this->createSource($owner);
        $this->actingAs($owner)->post('/sifi/source-catalog/profiles',$this->profileData([$id]))->assertRedirect();
        $this->assertDatabaseHas('sifi_coverage_profiles',['user_id'=>$owner->id,'profile_state'=>'DRAFT','collector_state'=>'OFF','revision'=>1]);
        $this->assertDatabaseHas('sifi_coverage_source_drafts',['source_id'=>$id]);
        $this->assertDatabaseHas('sifi_catalog_audit',['subject_type'=>'PROFILE','action'=>'CREATED']);
    }
    public function test_cross_user_link_rejected(): void
    {
        $userA=$this->user();$id=$this->createSource($userA);
        $userB=$this->user();$this->actingAs($userB)->post('/sifi/source-catalog/profiles',$this->profileData([$id]))->assertSessionHasErrors('source_ids');
        $this->assertDatabaseCount('sifi_coverage_profiles',0);
    }
    public function test_profile_revision_and_no_activation(): void
    {
        $u=$this->user();$this->actingAs($u)->post('/sifi/source-catalog/profiles',$this->profileData())->assertRedirect();
        $id=DB::table('sifi_coverage_profiles')->value('id');
        $this->patch('/sifi/source-catalog/profiles/'.$id,array_merge($this->profileData(),['revision'=>1,'region_name'=>'Kabupaten Bantul']))->assertRedirect();
        $this->assertDatabaseHas('sifi_coverage_profiles',['id'=>$id,'revision'=>2,'profile_state'=>'DRAFT','collector_state'=>'OFF','region_name'=>'Kabupaten Bantul']);
        $this->patch('/sifi/source-catalog/profiles/'.$id,array_merge($this->profileData(),['revision'=>1,'region_name'=>'Stale']))->assertSessionHasErrors('revision');
        $this->assertDatabaseCount('sifi_catalog_audit',2);
        Http::assertNothingSent();
    }
    public function test_profile_cannot_be_activated_by_client(): void
    {
        $this->actingAs($this->user())->post('/sifi/source-catalog/profiles',array_merge($this->profileData(),['collector_state'=>'ON','profile_state'=>'APPROVED']))->assertSessionHasErrors(['collector_state','profile_state']);
        $this->assertDatabaseCount('sifi_coverage_profiles',0);
    }
}
