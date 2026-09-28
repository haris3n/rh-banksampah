<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PetugasPreviewController extends Controller
{
    public function __invoke(string $page = 'dashboard'): View
    {
        abort_unless(app()->environment(['local', 'testing']) && config('petugas.preview'), 404);
        $pages = [
            'dashboard' => ['Dashboard', 'Selamat datang, Petugas'],
            'setoran-baru' => ['Setoran Baru', 'Input Setoran Baru'],
            'transaksi' => ['Transaksi Hari Ini', 'Transaksi Setoran'],
            'nasabah' => ['Cari Nasabah', 'Pencarian Nasabah'],
            'pengepul' => ['Data untuk Pengepul', 'Rekap untuk Pengepul'],
        ];
        abort_unless(isset($pages[$page]), 404);

        return view('petugas.preview', compact('page', 'pages'));
    }
}
