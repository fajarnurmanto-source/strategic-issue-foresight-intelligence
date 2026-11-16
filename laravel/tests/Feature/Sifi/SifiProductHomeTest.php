<?php

namespace Tests\Feature\Sifi;

use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SifiProductHomeTest extends TestCase
{
    public function test_home_renders_sifi_branded_page_instead_of_laravel_starter(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('welcome'));

        $page = File::get(resource_path('js/pages/welcome.tsx'));

        $this->assertStringContainsString('Signal & Integrity Finder Initiative', $page);
        $this->assertStringContainsString('Evidence governance', $page);
        $this->assertStringNotContainsString('Deploy now', $page);
        $this->assertStringNotContainsString('Laravel has an incredibly rich ecosystem', $page);
    }
}
