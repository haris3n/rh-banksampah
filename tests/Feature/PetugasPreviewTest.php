<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PetugasPreviewTest extends TestCase
{
    public function test_operational_form_uses_reference_sidebar_and_real_submission(): void
    {
        $html = view('petugas.operational', [
            'screen'=>'form','record'=>null,'selected'=>null,'details'=>collect(),
            'customers'=>collect(), 'types'=>collect(),
            'errors'=>new \Illuminate\Support\ViewErrorBag,
        ])->render();
        $this->assertStringContainsString('Navigasi petugas', $html);
        $this->assertStringContainsString('Data untuk Pengepul', $html);
        $this->assertStringContainsString('petugas-operational.css', $html);
        $this->assertStringContainsString('action="/petugas/setoran"', $html);
        $this->assertStringContainsString('id="identity-card" hidden', $html);
        $this->assertStringContainsString('id="stop-camera" class="button" hidden', $html);
    }
    public function test_preview_is_opt_in(): void
    {
        config(['petugas.preview' => false]);
        $this->get('/preview/petugas')->assertNotFound();
    }

    public function test_all_pages_render_without_database_queries(): void
    {
        config(['petugas.preview' => true]);
        DB::listen(function () {
            $this->fail('Preview must not query a database.');
        });
        foreach (['dashboard', 'setoran-baru', 'transaksi', 'nasabah', 'pengepul'] as $page) {
            $this->get('/preview/petugas/'.$page)
                ->assertOk()->assertSee('Mode demo')->assertSee('Rumah Hijau');
        }
        $this->get('/preview/petugas/unknown')->assertNotFound();
    }

    public function test_preview_remains_unavailable_in_production_even_when_enabled(): void
    {
        config(['petugas.preview' => true]);
        $this->app->instance('env', 'production');
        $this->get('/preview/petugas')->assertNotFound();
    }
}
