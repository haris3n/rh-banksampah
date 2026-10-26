<?php

namespace Tests\Feature;

use Tests\TestCase;

class PetugasLoginPageTest extends TestCase
{
    public function test_login_has_its_own_layout_and_preserves_email_but_never_password(): void
    {
        $this->withSession(['_old_input'=>['email'=>'petugas@example.test','password'=>'never-render-this']])
            ->get('/petugas/login')->assertOk()->assertSee('petugas-login.css')
            ->assertSee('petugas@example.test')->assertDontSee('never-render-this')
            ->assertSee('autocomplete="current-password"', false)->assertSee('name="_token"', false)
            ->assertDontSee('Navigasi petugas')->assertDontSee('petugas.sidebar');
    }
}
