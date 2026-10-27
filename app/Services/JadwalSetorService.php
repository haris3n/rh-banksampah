<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

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
            ];
        }

        $isEmergency = (bool) $jadwal->ditutup_darurat;
        $isToday = $jadwal->tanggal === $today;
        $inTimeWindow = $isToday && $currentTime >= $jadwal->jam_buka && $currentTime < $jadwal->jam_tutup;

        if ($isEmergency) {
            $status = 'DITUTUP_DARURAT';
            $badgeClass = 'cancelled';
            $statusLabel = 'Ditutup Darurat';
            $statusDesc = 'Pelayanan tanggal ' . $jadwal->tanggal . ' ditutup darurat oleh petugas.';
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
            $tanggalFormatted = CarbonImmutable::parse($jadwal->tanggal)->locale('id')->isoFormat('D MMMM Y');
            $statusLabel = 'Akan Datang';
            $statusDesc = 'Jadwal pelayanan berikutnya pada tanggal ' . $tanggalFormatted . '.';
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
        ];
    }

    /**
     * Menyimpan jadwal baru atau memperbarui jadwal yang ada.
     */
    public function save(int $userId, array $data, ?int $id = null): int
    {
        $db = DB::connection('proyek3');

        $record = [
            'tanggal' => $data['tanggal'],
            'jam_buka' => $data['jam_buka'],
            'jam_tutup' => $data['jam_tutup'],
            'catatan' => $data['catatan'] ?? null,
            'dibuat_oleh' => $userId,
            'updated_at' => now(),
        ];

        if ($id) {
            $existing = $db->table('jadwal_setor')->find($id);
            abort_unless($existing, 404);
            // A different service date is a new event, never overwrite history.
            if ($existing->tanggal === $data['tanggal']) {
                abort_if(CarbonImmutable::parse($existing->tanggal.' '.$existing->jam_tutup, 'Asia/Jakarta')->isPast(), 409, 'Jadwal yang telah selesai tidak dapat diubah.');
                unset($record['dibuat_oleh']);
                $db->table('jadwal_setor')->where('id', $id)->update($record);
                return $id;
            }
        }

        abort_if($data['tanggal'] < CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d'), 422, 'Tanggal pelayanan tidak boleh di masa lalu.');

        $record['aktif'] = true;
        $record['ditutup_darurat'] = false;
        $record['created_at'] = now();

        return $db->table('jadwal_setor')->insertGetId($record);
    }

    /**
     * Toggle status darurat (buka kembali atau tutup darurat).
     */
    public function toggleEmergency(int $jadwalId): bool
    {
        $db = DB::connection('proyek3');
        $jadwal = $db->table('jadwal_setor')->find($jadwalId);
        abort_unless($jadwal, 404);

        $newStatus = ! (bool) $jadwal->ditutup_darurat;
        $db->table('jadwal_setor')->where('id', $jadwalId)->update([
            'ditutup_darurat' => $newStatus,
            'updated_at' => now(),
        ]);

        return $newStatus;
    }
}
