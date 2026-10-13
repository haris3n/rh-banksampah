<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SetoranService
{
    private function actor(User $actor): void
    {
        abort_unless($actor->fresh()?->aktif && $actor->hasRole('petugas'), 403);
    }

    public function save(User $actor, array $data, ?int $id = null): int
    {
        $this->actor($actor);
        $db = DB::connection('proyek3');

        return $db->transaction(function () use ($db, $actor, $data, $id) {
            $old = collect();
            if ($id) {
                $record = $db->table('setoran')->where('id', $id)->lockForUpdate()->first();
                abort_unless($record, 404);
                abort_unless($record->petugas_id === $actor->id, 403);
                abort_unless($record->status === 'MENUNGGU_VERIFIKASI', 409);
                $old = $db->table('setoran_detail')->where('setoran_id', $id)->get()->keyBy('jenis_sampah_id');
            }
            $customer = User::find($data['nasabah_id']);
            if (! $customer?->aktif || ! $customer->hasRole('nasabah')) {
                throw ValidationException::withMessages(['nasabah_id' => 'Pilih nasabah aktif.']);
            }
            $details = []; $total = 0; $units = ['kg' => 0, 'liter' => 0];
            foreach ($data['items'] as $item) {
                $type = $db->table('jenis_sampah')->where('id', $item['jenis_sampah_id'])->first();
                $snapshot = $old->get($item['jenis_sampah_id']);
                if (! $type || (! $type->aktif && ! $snapshot)) {
                    throw ValidationException::withMessages(['items' => 'Jenis sampah tidak tersedia.']);
                }
                // Decimal input -> integer hundredths; avoid binary-float money arithmetic.
                $parts = explode('.', (string) $item['jumlah']);
                $amount = ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
                $buy = $snapshot?->harga_beli_snapshot ?? $type->harga_beli;
                $sell = $snapshot ? $snapshot->harga_jual_snapshot : $type->harga_jual;
                $unit = $snapshot?->satuan ?? $type->satuan;
                $subtotal = intdiv($amount * $buy + 50, 100);
                $units[$unit] += $amount; $total += $subtotal;
                $details[] = ['jenis_sampah_id' => $type->id, 'nama_jenis' => $snapshot?->nama_jenis ?? $type->nama,
                    'satuan' => $unit, 'jumlah' => $item['jumlah'], 'harga_beli_snapshot' => $buy,
                    'harga_jual_snapshot' => $sell, 'subtotal' => $subtotal, 'created_at' => now(), 'updated_at' => now()];
            }
            $header = ['nasabah_id' => $customer->id, 'total_nilai' => $total, 'total_kg' => $units['kg'] / 100,
                'total_liter' => $units['liter'] / 100, 'updated_at' => now()];
            if ($id) {
                $db->table('setoran')->where('id', $id)->update($header);
                $db->table('setoran_detail')->where('setoran_id', $id)->delete();
            } else {
                $id = $db->table('setoran')->insertGetId($header + ['kode' => 'RH-'.Str::upper(Str::random(12)),
                    'petugas_id' => $actor->id, 'status' => 'MENUNGGU_VERIFIKASI', 'created_at' => now()]);
            }
            $db->table('setoran_detail')->insert(array_map(fn ($d) => $d + ['setoran_id' => $id], $details));

            return $id;
        });
    }

    public function finish(User $actor, int $id, ?string $reason = null): void
    {
        $this->actor($actor);
        $db = DB::connection('proyek3');
        $db->transaction(function () use ($db, $actor, $id, $reason) {
            $record = $db->table('setoran')->where('id', $id)->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_unless($record->petugas_id === $actor->id, 403);
            abort_unless($record->status === 'MENUNGGU_VERIFIKASI', 409);
            if ($reason !== null) {
                abort_if(trim($reason) === '', 422);
                $db->table('setoran')->where('id', $id)->update(['status' => 'DIBATALKAN', 'alasan_batal' => trim($reason), 'dibatalkan_at' => now(), 'updated_at' => now()]);
                $db->table('audit_log')->insert(['user_id'=>$actor->id,'aksi'=>'BATAL_SETORAN','entitas'=>'setoran','entitas_id'=>$id,
                    'data_lama'=>json_encode(['status'=>$record->status]),'data_baru'=>json_encode(['status'=>'DIBATALKAN','alasan'=>trim($reason)]),'created_at'=>now()]);
                return;
            }
            $db->table('saldo_nasabah')->insertOrIgnore(['user_id' => $record->nasabah_id, 'tersedia' => 0, 'ditahan' => 0]);
            $saldo = $db->table('saldo_nasabah')->where('user_id', $record->nasabah_id)->lockForUpdate()->first();
            $available = $saldo->tersedia + $record->total_nilai;
            $db->table('saldo_nasabah')->where('user_id', $record->nasabah_id)->update(['tersedia' => $available, 'updated_at' => now()]);
            $db->table('mutasi_saldo')->insert(['user_id' => $record->nasabah_id, 'tipe' => 'SETORAN', 'jumlah' => $record->total_nilai,
                'tersedia_setelah' => $available, 'ditahan_setelah' => $saldo->ditahan, 'referensi_tipe' => 'setoran', 'referensi_id' => $id,
                'created_at' => now(), 'updated_at' => now()]);
            $db->table('setoran')->where('id', $id)->update(['status' => 'SELESAI', 'diverifikasi_at' => now(), 'updated_at' => now()]);
            $db->table('notifikasi')->insert(['user_id' => $record->nasabah_id, 'tipe' => 'SETORAN', 'judul' => 'Setoran selesai',
                'isi' => 'Setoran '.$record->kode.' telah diverifikasi.', 'data' => json_encode(['setoran_id' => $id]), 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}
