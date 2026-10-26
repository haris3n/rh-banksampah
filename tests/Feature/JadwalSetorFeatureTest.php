<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JadwalSetorService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JadwalSetorFeatureTest extends TestCase
{
    public function test_jadwal_setor_lifecycle_and_emergency_override(): void
    {
        $db = DB::connection('proyek3');
        $db->beginTransaction();

        try {
            // Bersihkan sementara jadwal di dalam transaksi tes
            $db->table('jadwal_setor')->delete();

            // Setup Petugas User
            $userId = $db->table('users')->insertGetId([
                'nama' => 'Petugas Jadwal Test',
                'email' => uniqid('petugas_jadwal_') . '@example.test',
                'password' => bcrypt('password123'),
                'aktif' => true,
            ]);
            $roleId = $db->table('roles')->where('name', 'petugas')->where('guard_name', 'web')->value('id');
            $db->table('model_has_roles')->insert([
                'model_id' => $userId,
                'model_type' => User::class,
                'role_id' => $roleId,
            ]);
            $petugas = User::findOrFail($userId);

            // Test 1: Belum ada jadwal
            $service = new JadwalSetorService();
            $status1 = $service->getStatus();
            $this->assertEquals('BELUM_DIUMUMKAN', $status1['status']);
            $this->assertFalse($status1['is_open']);

            // Test 2: Input jadwal hari ini
            $today = CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d');
            $response = $this->actingAs($petugas)->post('/petugas/dashboard/jadwal', [
                'tanggal' => $today,
                'jam_buka' => '08:00',
                'jam_tutup' => '17:00',
                'catatan' => 'Bawa sampah terpilah',
            ]);
            $response->assertRedirect('/petugas/dashboard');
            $response->assertSessionHas('success');

            $jadwalId = $db->table('jadwal_setor')->where('tanggal', $today)->value('id');
            $this->assertNotNull($jadwalId);

            // Test 3: Dashboard menampilkan kartu jadwal
            $this->actingAs($petugas)->get('/petugas/dashboard')
                ->assertOk()
                ->assertSee('Jadwal Pelayanan Rumah Hijau')
                ->assertSee('Bawa sampah terpilah')
                ->assertSee('Tutup Darurat');

            // Test 4: Toggle tutup darurat
            $this->actingAs($petugas)->post('/petugas/dashboard/jadwal/' . $jadwalId . '/darurat')
                ->assertRedirect('/petugas/dashboard');

            $jadwalAfter = $db->table('jadwal_setor')->find($jadwalId);
            $this->assertTrue((bool) $jadwalAfter->ditutup_darurat);

            // Test 5: Status sekarang DITUTUP_DARURAT
            $statusEmergency = $service->getStatus();
            $this->assertEquals('DITUTUP_DARURAT', $statusEmergency['status']);
            $this->assertFalse($statusEmergency['is_open']);
            $this->assertTrue($statusEmergency['is_emergency']);

            // Dashboard sekarang menampilkan tombol "Buka Kembali"
            $this->actingAs($petugas)->get('/petugas/dashboard')
                ->assertOk()
                ->assertSee('Ditutup Darurat')
                ->assertSee('Buka Kembali');

            // Test 6: Buka kembali
            $this->actingAs($petugas)->post('/petugas/dashboard/jadwal/' . $jadwalId . '/darurat')
                ->assertRedirect('/petugas/dashboard');
            $this->assertFalse((bool) $db->table('jadwal_setor')->where('id', $jadwalId)->value('ditutup_darurat'));

        } finally {
            $db->rollBack();
        }
    }
}
