<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SetoranService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfficialSalePriceTest extends TestCase
{
    public function test_fixed_oil_price_and_legacy_snapshots(): void
    {
        $db=DB::connection('proyek3');
        $db->beginTransaction();
        try {
            $oil=$db->table('jenis_sampah')->where('nama','Minyak Jelantah Bersih')->first();
            $this->assertSame('kg',$oil->satuan);
            $this->assertEquals(6000,$oil->harga_jual);
            $users=[];
            foreach(['petugas','nasabah'] as $role){
                $id=$db->table('users')->insertGetId(['nama'=>'Price test','email'=>uniqid().'@example.test','aktif'=>true]);
                $db->table('model_has_roles')->insert(['model_id'=>$id,'model_type'=>User::class,'role_id'=>$db->table('roles')->where('name',$role)->where('guard_name','web')->value('id')]);
                $users[]=User::findOrFail($id);
            }
            $service=new SetoranService;
            $data=['nasabah_id'=>$users[1]->id,'items'=>[['jenis_sampah_id'=>$oil->id,'jumlah'=>'2.50']]];
            $id=$service->save($users[0],$data);
            $db->table('jenis_sampah')->where('id',$oil->id)->update(['harga_jual'=>8000,'harga_beli'=>9000]);
            $service->save($users[0],$data,$id);
            $detail=$db->table('setoran_detail')->where('setoran_id',$id)->first();
            $this->assertEquals(6000,$detail->harga_jual_snapshot);
            $this->assertEquals(10000,$detail->subtotal);
            $service->finish($users[0],$id);
            $db->table('setoran')->where('id',$id)->update(['diverifikasi_at'=>'2090-01-01 05:00:00+00']);
            $this->actingAs($users[0])->get('/petugas/pengepul?from=2090-01-01&to=2090-01-01')->assertOk()->assertSee('Rp15.000')->assertSee('Rp5.000')->assertSee('Rp6.000/kg');
            $this->assertEquals(10000,$db->table('saldo_nasabah')->where('user_id',$users[1]->id)->value('tersedia'));
            $bytes=$this->get('/petugas/export?kind=pengepul&from=2090-01-01&to=2090-01-01')->assertOk()->streamedContent();
            $file=tempnam(sys_get_temp_dir(),'rh-prices');
            try {
                file_put_contents($file,$bytes);$zip=new \ZipArchive;$this->assertTrue($zip->open($file));
                $xml=simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
                $numeric=[];foreach($xml->sheetData->row as $row)foreach($row->c as $cell)if(isset($cell->v)&&((string)$cell['t']===''||(string)$cell['t']==='n'))$numeric[]=(float)$cell->v;
                foreach([2.5,10000,15000,5000] as $expected)$this->assertContains((float)$expected,$numeric);
                $this->assertNotFalse($zip->getFromName('xl/worksheets/sheet2.xml'));$zip->close();
            } finally {unlink($file);}
            // Simulate an older saved record: unit and unknown sale snapshot must survive edits.
            $legacy=$service->save($users[0],$data);
            $db->table('setoran_detail')->where('setoran_id',$legacy)->update(['satuan'=>'liter','harga_beli_snapshot'=>4000,'harga_jual_snapshot'=>null]);
            $service->save($users[0],$data,$legacy);
            $old=$db->table('setoran_detail')->where('setoran_id',$legacy)->first();
            $this->assertSame('liter',$old->satuan);$this->assertNull($old->harga_jual_snapshot);
            $this->assertEquals('2.50',$db->table('setoran')->where('id',$legacy)->value('total_liter'));
            $service->finish($users[0],$legacy);
            $db->table('setoran')->where('id',$legacy)->update(['diverifikasi_at'=>'2090-01-01 06:00:00+00']);
            $this->get('/petugas/pengepul?from=2090-01-01&to=2090-01-01')->assertOk()->assertSee('Belum tersedia')->assertSee('liter');
        } finally {$db->rollBack();}
    }
}
