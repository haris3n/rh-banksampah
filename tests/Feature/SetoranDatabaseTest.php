<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetoranDatabaseTest extends TestCase
{
    public function test_real_workflow_and_ownership(): void
    {
        if (! extension_loaded('pdo_pgsql')) $this->markTestSkipped('Run with -d extension=pdo_pgsql');
        $db = DB::connection('proyek3');
        $db->beginTransaction();
        try {
            $users = [];
            foreach (['petugas','petugas','nasabah'] as $i=>$role) {
                $id=$db->table('users')->insertGetId(['nama'=>'Test '.$i,'email'=>uniqid().'@example.test','password'=>Hash::make('test-password'),'aktif'=>true]);
                $db->table('model_has_roles')->insert(['role_id'=>$db->table('roles')->where('name',$role)->value('id'),'model_type'=>User::class,'model_id'=>$id]);
                $users[]=User::find($id);
            }
            $this->post('/petugas/login',['email'=>$users[0]->email,'password'=>'test-password'])->assertRedirect('/petugas');
            $this->get('/petugas/setoran-baru')->assertOk();
            $this->get('/petugas/nasabah?q='.urlencode($users[2]->nama))->assertOk()->assertSee($users[2]->nama);
            $this->get('/petugas/nasabah/'.$users[2]->id)->assertOk()->assertSee('Saldo tersedia');
            $this->getJson('/petugas/nasabah-search?q='.urlencode($users[2]->nama))->assertOk()->assertJsonFragment(['id'=>$users[2]->id]);
            $payload=['nasabah_id'=>$users[2]->id,'items'=>[
                ['jenis_sampah_id'=>$db->table('jenis_sampah')->where('nama','Emberan')->value('id'),'jumlah'=>'1.25'],
                ['jenis_sampah_id'=>$db->table('jenis_sampah')->where('nama','Minyak Jelantah Bersih')->value('id'),'jumlah'=>'2.50'],
            ]];
            $this->post('/petugas/setoran',$payload)->assertRedirect();
            $record=$db->table('setoran')->where('petugas_id',$users[0]->id)->first();
            $this->assertEquals(12000,$record->total_nilai);
            $this->assertEquals('1.25',$record->total_kg);
            $this->assertEquals('2.50',$record->total_liter);
            $this->assertFalse($db->table('mutasi_saldo')->where('referensi_id',$record->id)->exists());
            $this->actingAs($users[1])->post('/petugas/setoran/'.$record->id.'/finish',['action'=>'verify'])->assertForbidden();
            $db->table('jenis_sampah')->where('nama','Emberan')->update(['harga_beli'=>9999]);
            $db->table('jenis_sampah')->where('nama','Emberan')->update(['aktif'=>false]);
            $this->actingAs($users[0])->get('/petugas/setoran/'.$record->id.'/edit')->assertOk()->assertSee('data-price="1600"',false)->assertSee('snapshot nonaktif');
            $this->get('/petugas?status=MENUNGGU_VERIFIKASI&q='.$record->kode)->assertOk()->assertSee($record->kode);
            $db->table('jenis_sampah')->where('nama','Emberan')->update(['aktif'=>true]);
            $this->actingAs($users[0])->put('/petugas/setoran/'.$record->id,$payload)->assertRedirect();
            $this->assertEquals(12000,$db->table('setoran')->where('id',$record->id)->value('total_nilai'));
            $this->post('/petugas/setoran/'.$record->id.'/finish',['action'=>'verify'])->assertRedirect();
            $this->post('/petugas/setoran/'.$record->id.'/finish',['action'=>'verify'])->assertStatus(409);
            $this->put('/petugas/setoran/'.$record->id,$payload)->assertStatus(409);
            $this->get('/petugas/setoran/'.$record->id)->assertOk()->assertSee('Struk Setoran')->assertSee('12.000');
            $this->assertEquals(12000,$db->table('saldo_nasabah')->where('user_id',$users[2]->id)->value('tersedia'));
            $this->assertEquals(1,$db->table('mutasi_saldo')->where('referensi_id',$record->id)->count());
            $this->get('/petugas/dashboard')->assertOk()->assertSee('Belum tersedia')->assertSee('12.000');
            $this->get('/petugas/dashboard')->assertOk()->assertSee('Top 5 nasabah')->assertSee('Periode ringkasan: Hari ini')->assertSee('Pilih rentang tanggal')->assertSee('petugas-operational.css')->assertSee('aria-current="page"', false)->assertDontSee('Export XLSX Transaksi Selesai');
            $this->get('/petugas/pengepul')->assertOk()->assertDontSee('Top 5 nasabah')->assertSee('Export XLSX Pengepul')->assertSee('petugas-operational.css')->assertSee('Cari Nasabah')->assertSee('aria-current="page"', false);
            $this->get('/petugas/pengepul?from=2000-01-01&to=2000-01-01')->assertOk()->assertSee('Belum ada setoran')->assertDontSee('Top 5 nasabah');
            $db->table('setoran')->where('id',$record->id)->update(['diverifikasi_at'=>'2026-10-06 17:00:00+00']);
            $this->get('/petugas/dashboard?from=2026-10-06&to=2026-10-06')->assertOk()->assertDontSee('Rp12.000');
            $this->get('/petugas/dashboard?from=2026-10-07&to=2026-10-07')->assertOk()->assertSee('Rp12.000');
            $db->table('setoran')->where('id',$record->id)->update(['diverifikasi_at'=>now()]);
            $this->get('/petugas/pengepul?from=2026-10-10&to=2026-10-01')->assertSessionHasErrors('to');
            foreach (['transaksi', 'pengepul'] as $kind) {
                $export = $this->get('/petugas/export?kind='.$kind)->assertOk();
                $bytes = $export->streamedContent();
                $this->assertStringStartsWith('PK', $bytes);
                $file = tempnam(sys_get_temp_dir(), 'rh-xlsx-');
                try {
                    file_put_contents($file, $bytes);
                    $zip = new \ZipArchive;
                    $this->assertTrue($zip->open($file));
                    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
                    $this->assertStringContainsString($kind === 'transaksi' ? '12000' : '10000', $xml);
                    $this->assertStringNotContainsString('<f>', $xml);
                    $zip->close();
                } finally {
                    unlink($file);
                }
            }
            $this->post('/petugas/setoran',$payload)->assertRedirect();
            $cancelId=$db->table('setoran')->where('petugas_id',$users[0]->id)->max('id');
            $this->post('/petugas/setoran/'.$cancelId.'/finish',['action'=>'cancel'])->assertSessionHasErrors('alasan');
            $this->post('/petugas/setoran/'.$cancelId.'/finish',['action'=>'cancel','alasan'=>'Nasabah membatalkan'])->assertRedirect();
            $this->assertEquals('DIBATALKAN',$db->table('setoran')->where('id',$cancelId)->value('status'));
            $this->assertEquals(1,$db->table('audit_log')->where('entitas_id',$cancelId)->where('aksi','BATAL_SETORAN')->count());
            $this->assertEquals(12000,$db->table('saldo_nasabah')->where('user_id',$users[2]->id)->value('tersedia'));
            $db->table('users')->where('id',$users[0]->id)->update(['aktif'=>false]);
            $this->actingAs($users[0]->fresh())->get('/petugas')->assertForbidden();
        } finally {
            $db->rollBack();
        }
    }
}
