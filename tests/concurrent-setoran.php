<?php

// php tests/concurrent-setoran.php -- explicit local PostgreSQL concurrency check.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('local', 'testing')) throw new RuntimeException('Local/testing only.');
$db = Illuminate\Support\Facades\DB::connection('proyek3');
$service = new App\Services\SetoranService;
if (($argv[1] ?? '') === 'worker') {
    try {
        $service->finish(App\Models\User::findOrFail((int)$argv[2]), (int)$argv[3]);
        echo 'verified';
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
        if ($e->getStatusCode() !== 409) throw $e;
        echo 'conflict';
    }
    exit;
}
$check = function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };
$userIds=[]; $depositIds=[]; $workers=[];
try {
    foreach (['petugas','nasabah'] as $role) {
        $id=$db->table('users')->insertGetId(['nama'=>'Concurrency fixture','email'=>bin2hex(random_bytes(12)).'@example.test','aktif'=>true]);
        $userIds[]=$id;
        $db->table('model_has_roles')->insert(['model_id'=>$id,'model_type'=>App\Models\User::class,'role_id'=>$db->table('roles')->where('name',$role)->where('guard_name','web')->value('id')]);
    }
    $actor=App\Models\User::findOrFail($userIds[0]);
    $type=$db->table('jenis_sampah')->where('nama','Emberan')->first();
    $payload=['nasabah_id'=>$userIds[1],'items'=>[['jenis_sampah_id'=>$type->id,'jumlah'=>'1.00']]];
    $db->table('saldo_nasabah')->insert(['user_id'=>$userIds[1],'tersedia'=>0,'ditahan'=>0]);
    for($i=0;$i<3;$i++)$depositIds[]=$service->save($actor,$payload);
    foreach ([[$depositIds[0],$depositIds[0]],[$depositIds[1],$depositIds[2]]] as $round=>$ids) {
        $db->beginTransaction();
        // Hold both requests at a database boundary before releasing together.
        if($round===0)$db->table('setoran')->where('id',$ids[0])->lockForUpdate()->first();
        else $db->table('saldo_nasabah')->where('user_id',$userIds[1])->lockForUpdate()->first();
        $workers=[];
        foreach($ids as $id){
            $process=proc_open([PHP_BINARY,__FILE__,'worker',(string)$actor->id,(string)$id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
            if(!is_resource($process))throw new RuntimeException('Cannot start worker');
            fclose($pipes[0]); $workers[]=[$process,$pipes];
        }
        usleep(800000);
        $db->commit();
        $results=[];
        foreach($workers as [$process,$pipes]){
            stream_set_timeout($pipes[1],20);
            $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); $code=proc_close($process);
            $check($code===0,'Worker failed: '.$error.$output); $results[]=trim($output);
        }
        $workers=[];sort($results);
        $check($results===($round===0?['conflict','verified']:['verified','verified']),'Unexpected concurrent outcomes');
    }
    $check((int)$db->table('saldo_nasabah')->where('user_id',$userIds[1])->value('tersedia')===$type->harga_beli*3,'Lost or duplicated balance');
    $check($db->table('mutasi_saldo')->where('user_id',$userIds[1])->count()===3,'Incorrect mutation count');
    $check($db->table('notifikasi')->where('user_id',$userIds[1])->count()===3,'Incorrect notification count');
    echo "PASS: same deposit credited once; two distinct deposits to one balance both retained.\n";
} finally {
    if($db->transactionLevel())$db->rollBack();
    foreach($workers as [$process,$pipes])if(is_resource($process))proc_terminate($process);
    $db->transaction(function()use($db,$userIds,$depositIds){
        foreach(['notifikasi','mutasi_saldo','saldo_nasabah'] as $table)$db->table($table)->whereIn('user_id',$userIds)->delete();
        $db->table('setoran_detail')->whereIn('setoran_id',$depositIds)->delete();
        $db->table('setoran')->whereIn('id',$depositIds)->delete();
        $db->table('model_has_roles')->where('model_type',App\Models\User::class)->whereIn('model_id',$userIds)->delete();
        $db->table('users')->whereIn('id',$userIds)->delete();
    });
}
