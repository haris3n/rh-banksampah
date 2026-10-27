<?php

namespace Tests\Feature;

use App\Services\JadwalSetorService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleBoundaryTest extends TestCase
{
    public function test_wib_boundaries_future_emergency_and_history(): void
    {
        $db=DB::connection('proyek3');
        $db->beginTransaction();
        try {
            $db->table('jadwal_setor')->update(['aktif'=>false]);
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2091-01-10 07:59:00','Asia/Jakarta'));
            $service=new JadwalSetorService;
            $this->assertSame('BELUM_DIUMUMKAN',$service->getStatus()['status']);
            $user=$db->table('users')->insertGetId(['nama'=>'Schedule fixture','email'=>uniqid().'@example.test']);
            $data=['tanggal'=>'2091-01-10','jam_buka'=>'08:00','jam_tutup'=>'14:00'];
            $id=$service->save($user,$data);
            $this->assertSame('SEGERA_BUKA',$service->getStatus()['status']);
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2091-01-10 08:00:00','Asia/Jakarta'));
            $this->assertTrue($service->getStatus()['is_open']);
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2091-01-10 14:00:00','Asia/Jakarta'));
            $this->assertSame('SUDAH_TUTUP',$service->getStatus()['status']);
            $next=$service->save($user,array_replace($data,['tanggal'=>'2091-02-10']),$id);
            $this->assertNotEquals($id,$next);
            $this->assertSame('2091-01-10',$db->table('jadwal_setor')->where('id',$id)->value('tanggal'));
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2091-01-11 00:00:00','Asia/Jakarta'));
            $service->toggleEmergency($next);
            $this->assertStringNotContainsString('hari ini',$service->getStatus()['status_desc']);
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2091-02-11 00:00:00','Asia/Jakarta'));
            $this->assertSame('BELUM_DIUMUMKAN',$service->getStatus()['status']);
        } finally {
            CarbonImmutable::setTestNow();
            $db->rollBack();
        }
    }
}
