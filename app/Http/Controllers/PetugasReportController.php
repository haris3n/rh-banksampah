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
        $sale = $missing ? null : (int) $groups->sum('jual');
        $top = (clone $query)->join('users as u', 'u.id', '=', 's.nasabah_id')
            ->select('u.nama')->selectRaw('sum(s.total_kg) as kg, sum(s.total_liter) as liter')
            ->groupBy('u.id', 'u.nama')->orderByDesc('kg')->limit(5)->get();

        return compact('dates', 'summary', 'groups', 'missing', 'sale', 'top', 'query');
    }

    public function show(Request $request)
    {
        return view('petugas.report', $this->report($request));
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
