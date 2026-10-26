<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class PetugasReportController extends Controller
{
    private function report(Request $request): array
    {
        $today = CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d');
        $request->merge(['from' => $request->input('from', $today), 'to' => $request->input('to', $today)]);
        $dates = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        $start = CarbonImmutable::parse($dates['from'], 'Asia/Jakarta')->startOfDay()->utc();
        $end = CarbonImmutable::parse($dates['to'], 'Asia/Jakarta')->addDay()->startOfDay()->utc();
        $query = DB::connection('proyek3')->table('setoran as s')->where('s.status', 'SELESAI')
            ->where('s.diverifikasi_at', '>=', $start)->where('s.diverifikasi_at', '<', $end);
        $summary = (clone $query)->selectRaw('count(*) as transaksi, coalesce(sum(total_kg),0) as kg, coalesce(sum(total_liter),0) as liter, coalesce(sum(total_nilai),0) as beli')->first();
        $groups = (clone $query)->join('setoran_detail as d', 'd.setoran_id', '=', 's.id')
            ->select('d.jenis_sampah_id', 'd.nama_jenis', 'd.satuan')
            ->selectRaw('sum(d.jumlah) as jumlah, sum(d.subtotal) as beli, count(*) filter (where d.harga_jual_snapshot is null) as missing, sum(round(d.jumlah * d.harga_jual_snapshot)) as jual')
            ->groupBy('d.jenis_sampah_id', 'd.nama_jenis', 'd.satuan')->orderBy('d.nama_jenis')->get();
        $missing = $groups->where('missing', '>', 0)->count();
        $breakdown = (clone $query)->join('setoran_detail as d','d.setoran_id','=','s.id')
            ->select('d.jenis_sampah_id','d.nama_jenis','d.satuan','d.harga_beli_snapshot','d.harga_jual_snapshot')
            ->selectRaw('sum(d.jumlah) as jumlah, sum(d.subtotal) as beli, sum(round(d.jumlah*d.harga_jual_snapshot)) as jual')
            ->groupBy('d.jenis_sampah_id','d.nama_jenis','d.satuan','d.harga_beli_snapshot','d.harga_jual_snapshot')
            ->orderBy('d.nama_jenis')->orderBy('d.harga_beli_snapshot')->get();
        $sale = $missing ? null : (int) $groups->sum('jual');
        $top = (clone $query)->join('users as u', 'u.id', '=', 's.nasabah_id')
            ->select('u.nama')->selectRaw('sum(s.total_kg) as kg, sum(s.total_liter) as liter')
            ->groupBy('u.id', 'u.nama')->orderByDesc('kg')->limit(5)->get();

        $jadwalInfo = app(\App\Services\JadwalSetorService::class)->getStatus();

        return compact('dates', 'summary', 'groups', 'missing', 'sale', 'top', 'query','breakdown', 'jadwalInfo');
    }

    public function show(Request $request)
    {
        return view('petugas.report', $this->report($request));
    }

    public function saveJadwal(Request $request, \App\Services\JadwalSetorService $service)
    {
        $data = $request->validate([
            'id' => 'nullable|integer',
            'tanggal' => 'required|date_format:Y-m-d',
            'jam_buka' => 'required|date_format:H:i',
            'jam_tutup' => 'required|date_format:H:i|after:jam_buka',
            'catatan' => 'nullable|string|max:1000',
        ]);

        $service->save($request->user()->id, $data, $data['id'] ?? null);

        return redirect('/petugas/dashboard')->with('success', 'Jadwal pelayanan berhasil disimpan.');
    }

    public function toggleEmergency(Request $request, \App\Services\JadwalSetorService $service, int $id)
    {
        $status = $service->toggleEmergency($id);
        $message = $status ? 'Pelayanan ditutup darurat.' : 'Tutup darurat dibatalkan. Pelayanan dibuka kembali.';

        return redirect('/petugas/dashboard')->with('success', $message);
    }

    public function export(Request $request)
    {
        $data = $this->report($request);
        $kind = $request->validate(['kind' => 'required|in:transaksi,pengepul'])['kind'];
        $filename = 'setorin-'.$kind.'-'.str_replace('-', '', $data['dates']['from']).'-'.str_replace('-', '', $data['dates']['to']).'.xlsx';
        // OpenSpout is already installed through Filament; numeric cells stay numeric.
        return response()->streamDownload(function () use ($data, $kind) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            try {
                if ($kind === 'pengepul') {
                    $writer->addRow(Row::fromValues(['Jenis sampah', 'Satuan', 'Jumlah', 'Nilai beli (Rp)', 'Estimasi jual (Rp)', 'Estimasi selisih (Rp)']));
                    foreach ($data['groups'] as $g) {
                        $writer->addRow(Row::fromValues([$g->nama_jenis, $g->satuan, (float) $g->jumlah, (int) $g->beli,
                            $g->missing ? 'Belum tersedia' : (int) $g->jual, $g->missing ? 'Belum tersedia' : (int) $g->jual - (int) $g->beli]));
                    }
                    $writer->addRow(Row::fromValues(['TOTAL NILAI', '', null, (int)$data['summary']->beli,
                        $data['sale'] ?? 'Belum tersedia', $data['sale'] === null ? 'Belum tersedia' : $data['sale']-(int)$data['summary']->beli]));
                    $writer->addNewSheetAndMakeItCurrent()->setName('Rincian harga snapshot');
                    $writer->addRow(Row::fromValues(['Jenis','Satuan','Jumlah','Harga beli/satuan','Harga jual/satuan','Nilai beli','Estimasi jual']));
                    foreach ($data['breakdown'] as $d) {
                        $writer->addRow(Row::fromValues([$d->nama_jenis,$d->satuan,(float)$d->jumlah,(int)$d->harga_beli_snapshot,
                            $d->harga_jual_snapshot === null ? 'Belum tersedia' : (int)$d->harga_jual_snapshot,(int)$d->beli,
                            $d->jual === null ? 'Belum tersedia' : (int)$d->jual]));
                    }
                } else {
                    $writer->addRow(Row::fromValues(['Kode', 'Nasabah', 'No HP', 'Alamat', 'Waktu verifikasi WIB', 'Kg', 'Liter', 'Nilai (Rp)', 'Status']));
                    $rows = (clone $data['query'])->join('users as u', 'u.id', '=', 's.nasabah_id')
                        ->select('s.*', 'u.nama', 'u.no_hp', 'u.alamat')->orderBy('s.id')->cursor();
                    foreach ($rows as $r) {
                        $writer->addRow(Row::fromValues([$r->kode, $r->nama, $r->no_hp, $r->alamat,
                            CarbonImmutable::parse($r->diverifikasi_at)->timezone('Asia/Jakarta')->format('d M Y H:i'),
                            (float) $r->total_kg, (float) $r->total_liter, (int) $r->total_nilai, $r->status]));
                    }
                }
            } finally {
                $writer->close();
            }
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
