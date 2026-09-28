<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PetugasPreviewTest extends TestCase
{
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
