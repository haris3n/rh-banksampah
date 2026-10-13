<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoNasabah extends Command
{
    protected $signature = 'petugas:demo-nasabah';
    protected $description = 'Buat tiga nasabah uji lokal tanpa password login';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Hanya tersedia di lingkungan local.');
            return self::FAILURE;
        }
        $db=DB::connection('proyek3');
        $db->transaction(function() use($db){
            $role=$db->table('roles')->where('name','nasabah')->where('guard_name','web')->first();
            if(!$role)throw new \RuntimeException('Role nasabah belum tersedia.');
            foreach(['A','B','C'] as $name){
                $email='nasabah-uji-'.strtolower($name).'@example.test';
                $user=$db->table('users')->where('email',$email)->first();
                if(!$user){
                    $id=$db->table('users')->insertGetId(['nama'=>'Nasabah Uji '.$name,'email'=>$email,'alamat'=>'Alamat contoh untuk pengujian lokal','aktif'=>true,'kode_qr'=>(string) Str::uuid(),'created_at'=>now(),'updated_at'=>now()]);
                    $db->table('model_has_roles')->insert(['role_id'=>$role->id,'model_type'=>\App\Models\User::class,'model_id'=>$id]);
                    $db->table('saldo_nasabah')->insert(['user_id'=>$id,'tersedia'=>0,'ditahan'=>0]);
                    $user=$db->table('users')->find($id);
                }
                $this->line($user->nama.' | QR: '.$user->kode_qr);
            }
        });
        return self::SUCCESS;
    }
}
