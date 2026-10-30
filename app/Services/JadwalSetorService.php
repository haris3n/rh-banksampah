<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JadwalSetorService
{
    /**
     * Mendapatkan jadwal terdekat beserta status operasional real-time WIB.
     */
    public function getStatus(): array
    {
        $db = DB::connection('proyek3');
        $now = CarbonImmutable::now('Asia/Jakarta');
        $today = $now->format('Y-m-d');
        $currentTime = $now->format('H:i:s');

        // Cari jadwal hari ini atau jadwal mendatang terdekat yang aktif
        $jadwal = $db->table('jadwal_setor')
            ->where('aktif', true)
            ->where('tanggal', '>=', $today)
            ->orderByRaw('CASE WHEN tanggal = ? AND jam_tutup <= ? THEN 1 ELSE 0 END', [$today, $currentTime])
            ->orderBy('tanggal')
            ->orderBy('jam_buka')
            ->first();

        if (! $jadwal) {
            return [
                'jadwal' => null,
                'status' => 'BELUM_DIUMUMKAN',
                'badge_class' => 'waiting',
                'status_label' => 'Belum Diumumkan',
                'status_desc' => 'Belum ada jadwal pelayanan yang ditentukan oleh petugas.',
                'is_open' => false,
                'is_emergency' => false,
                'can_edit' => false,
            ];
        }

        $isEmergency = (bool) $jadwal->ditutup_darurat;
        $isToday = $jadwal->tanggal === $today;
        $inTimeWindow = $isToday && $currentTime >= $jadwal->jam_buka && $currentTime < $jadwal->jam_tutup;
        $canEdit = CarbonImmutable::parse($jadwal->tanggal.' '.$jadwal->jam_buka, 'Asia/Jakarta')->isFuture();

        if ($isEmergency) {
            $status = 'DITUTUP_DARURAT';
            $badgeClass = 'cancelled';
            $statusLabel = 'Ditutup Darurat';
            $statusDesc = $isToday 
                ? 'Pelayanan hari ini sedang ditutup darurat oleh petugas.'
                : 'Pelayanan tanggal ' . CarbonImmutable::parse($jadwal->tanggal)->locale('id')->isoFormat('D MMMM Y') . ' ditutup darurat oleh petugas.';
            $isOpen = false;
        } elseif ($inTimeWindow) {
            $status = 'SEDANG_BUKA';
            $badgeClass = 'success';
            $statusLabel = 'Sedang Buka';
            $statusDesc = 'Pelayanan setoran sedang berlangsung sampai pukul ' . substr($jadwal->jam_tutup, 0, 5) . ' WIB.';
            $isOpen = true;
        } elseif ($isToday && $currentTime < $jadwal->jam_buka) {
            $status = 'SEGERA_BUKA';
            $badgeClass = 'waiting';
            $statusLabel = 'Buka Hari Ini';
            $statusDesc = 'Pelayanan dibuka hari ini mulai pukul ' . substr($jadwal->jam_buka, 0, 5) . ' WIB.';
            $isOpen = false;
        } elseif ($isToday && $currentTime >= $jadwal->jam_tutup) {
            $status = 'SUDAH_TUTUP';
            $badgeClass = 'cancelled';
            $statusLabel = 'Sudah Tutup';
            $statusDesc = 'Pelayanan hari ini telah selesai pada pukul ' . substr($jadwal->jam_tutup, 0, 5) . ' WIB.';
            $isOpen = false;
        } else {
            $status = 'AKAN_DATANG';
            $badgeClass = 'waiting';
            $tanggalFormatted = CarbonImmutable::parse($jadwal->tanggal)->locale('id')->isoFormat('dddd, D MMMM Y');
            $statusLabel = 'Akan Datang';
            $statusDesc = 'Jadwal pelayanan berikutnya pada hari ' . $tanggalFormatted . '.';
            $isOpen = false;
        }

        return [
            'jadwal' => $jadwal,
            'status' => $status,
            'badge_class' => $badgeClass,
            'status_label' => $statusLabel,
            'status_desc' => $statusDesc,
            'is_open' => $isOpen,
            'is_emergency' => $isEmergency,
            'can_edit' => $canEdit,
        ];
    }

    /**
     * Mendapatkan daftar jadwal pelayanan mendatang yang aktif.
     */
    public function getUpcoming(): array
    {
        $db = DB::connection('proyek3');
        $now = CarbonImmutable::now('Asia/Jakarta');
        $today = $now->format('Y-m-d');
        $currentTime = $now->format('H:i:s');

        $list = $db->table('jadwal_setor as j')
            ->join('users as u', 'u.id', '=', 'j.dibuat_oleh')
            ->where('j.aktif', true)
            ->where('j.tanggal', '>=', $today)
            ->select('j.*', 'u.nama as pembuat')
            ->orderBy('j.tanggal')
            ->orderBy('j.jam_buka')
            ->limit(10)
            ->get();

        return $list->map(function ($item) use ($now, $today, $currentTime) {
            $isToday = $item->tanggal === $today;
            $inTimeWindow = $isToday && $currentTime >= $item->jam_buka && $currentTime < $item->jam_tutup;

            if ($item->ditutup_darurat) {
                $badge = 'cancelled';
                $label = 'Ditutup Darurat';
            } elseif ($inTimeWindow) {
                $badge = 'success';
                $label = 'Sedang Buka';
            } elseif ($isToday && $currentTime < $item->jam_buka) {
                $badge = 'waiting';
                $label = 'Buka Hari Ini';
            } elseif ($isToday && $currentTime >= $item->jam_tutup) {
                $badge = 'cancelled';
                $label = 'Sudah Tutup';
            } else {
                $badge = 'waiting';
                $label = 'Akan Datang';
            }

            $canEdit = CarbonImmutable::parse($item->tanggal.' '.$item->jam_buka, 'Asia/Jakarta')->isFuture();

            return (array) $item + [
                'status_label' => $label,
                'badge_class' => $badge,
                'can_edit' => $canEdit,
            ];
        })->all();
    }

    /**
     * Menyimpan jadwal baru atau memperbarui jadwal yang ada.
     */
    public function save(int $userId, array $data, ?int $id = null): int
    {
        $db = DB::connection('proyek3');

        return $db->transaction(function () use ($db, $userId, $data, $id) {
            // Lock table agar tidak terjadi race condition saat jadwal ganda disimpan bersamaan
            $db->statement('LOCK TABLE jadwal_setor IN SHARE ROW EXCLUSIVE MODE');
            $now = CarbonImmutable::now('Asia/Jakarta');
            $fail = fn (string $field, string $message) => throw ValidationException::withMessages([$field => $message]);

            if ($data['jam_tutup'] <= $data['jam_buka']) {
                $fail('jam_tutup', 'Jam tutup harus setelah jam buka.');
            }

            if (CarbonImmutable::parse($data['tanggal'].' '.$data['jam_tutup'], 'Asia/Jakarta')->lessThanOrEqualTo($now)) {
                $fail('tanggal', 'Jadwal pelayanan harus berakhir setelah waktu sekarang.');
            }

            if ($data['tanggal'] < $now->format('Y-m-d')) {
                $fail('tanggal', 'Tanggal pelayanan tidak boleh di masa lalu.');
            }

            $record = [
                'tanggal' => $data['tanggal'],
                'jam_buka' => $data['jam_buka'],
                'jam_tutup' => $data['jam_tutup'],
                'catatan' => $data['catatan'] ?? null,
                'updated_at' => now(),
            ];

            if ($id) {
                $existing = $db->table('jadwal_setor')->find($id);
                abort_unless($existing, 404);
                abort_unless($existing->aktif, 409, 'Jadwal yang sudah dibatalkan tidak dapat diubah.');

                $startTime = CarbonImmutable::parse($existing->tanggal.' '.$existing->jam_buka, 'Asia/Jakarta');
                if ($startTime->lessThanOrEqualTo($now)) {
                    $fail('tanggal', 'Jadwal yang sudah dimulai atau selesai tidak dapat diubah.');
                }

                // Cek bentrok jam dengan jadwal aktif lain
                $overlap = $db->table('jadwal_setor')
                    ->where('aktif', true)
                    ->where('tanggal', $data['tanggal'])
                    ->where('id', '<>', $id)
                    ->where('jam_buka', '<', $data['jam_tutup'])
                    ->where('jam_tutup', '>', $data['jam_buka'])
                    ->exists();

                if ($overlap) {
                    $fail('jam_buka', 'Jam pelayanan bertumpang tindih dengan jadwal aktif lainnya pada tanggal tersebut.');
                }

                if ($existing->tanggal !== $data['tanggal']) {
                    $record['ditutup_darurat'] = false;
                }

                $db->table('jadwal_setor')->where('id', $id)->update($record);
                return $id;
            }

            // Validasi jadwal baru: tidak boleh tumpang tindih dengan jadwal aktif lain
            $overlap = $db->table('jadwal_setor')
                ->where('aktif', true)
                ->where('tanggal', $data['tanggal'])
                ->where('jam_buka', '<', $data['jam_tutup'])
                ->where('jam_tutup', '>', $data['jam_buka'])
                ->exists();

            if ($overlap) {
                $fail('jam_buka', 'Jam pelayanan bertumpang tindih dengan jadwal aktif lainnya pada tanggal tersebut.');
            }

            $record['aktif'] = true;
            $record['ditutup_darurat'] = false;
            $record['dibuat_oleh'] = $userId;
            $record['created_at'] = now();

            return $db->table('jadwal_setor')->insertGetId($record);
        });
    }

    /**
     * Set status darurat secara eksplisit (idempotent).
     */
    public function setEmergency(int $jadwalId, bool $isEmergency): bool
    {
        $db = DB::connection('proyek3');
        $jadwal = $db->table('jadwal_setor')->find($jadwalId);
        abort_unless($jadwal, 404);
        abort_unless($jadwal->aktif, 409, 'Jadwal yang sudah dibatalkan tidak dapat diubah status daruratnya.');

        $now = CarbonImmutable::now('Asia/Jakarta');
        $endTime = CarbonImmutable::parse($jadwal->tanggal.' '.$jadwal->jam_tutup, 'Asia/Jakarta');
        abort_if($endTime->isPast(), 409, 'Jadwal pelayanan yang sudah lewat tidak dapat diubah status daruratnya.');

        $db->table('jadwal_setor')->where('id', $jadwalId)->update([
            'ditutup_darurat' => $isEmergency,
            'updated_at' => now(),
        ]);

        return $isEmergency;
    }

    /**
     * Membatalkan jadwal pelayanan mendatang dengan alasan wajib (dicatat di audit_log).
     */
    public function cancel(int $userId, int $jadwalId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['alasan' => 'Alasan pembatalan jadwal wajib diisi.']);
        }

        $db = DB::connection('proyek3');
        $jadwal = $db->table('jadwal_setor')->find($jadwalId);
        abort_unless($jadwal, 404);
        abort_unless($jadwal->aktif, 409, 'Jadwal sudah tidak aktif.');

        $now = CarbonImmutable::now('Asia/Jakarta');
        $startTime = CarbonImmutable::parse($jadwal->tanggal.' '.$jadwal->jam_buka, 'Asia/Jakarta');
        abort_if($startTime->lessThanOrEqualTo($now), 409, 'Jadwal yang sudah dimulai atau selesai tidak dapat dibatalkan.');

        $db->transaction(function () use ($db, $userId, $jadwal, $jadwalId, $reason) {
            $db->table('jadwal_setor')->where('id', $jadwalId)->update([
                'aktif' => false,
                'updated_at' => now(),
            ]);

            $db->table('audit_log')->insert([
                'user_id' => $userId,
                'aksi' => 'BATAL_JADWAL',
                'entitas' => 'jadwal_setor',
                'entitas_id' => $jadwalId,
                'data_lama' => json_encode([
                    'tanggal' => $jadwal->tanggal,
                    'jam_buka' => $jadwal->jam_buka,
                    'jam_tutup' => $jadwal->jam_tutup,
                    'catatan' => $jadwal->catatan,
                ]),
                'data_baru' => json_encode([
                    'aktif' => false,
                    'alasan' => $reason,
                ]),
                'created_at' => now(),
            ]);
        });
    }
}
