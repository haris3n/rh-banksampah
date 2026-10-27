<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SetoranService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PetugasController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($data + ['aktif' => true])) {
            throw ValidationException::withMessages(['email' => 'Email atau password tidak sesuai.']);
        }
        if (! $request->user()->hasRole('petugas')) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Akun tidak memiliki akses petugas.']);
        }
        $request->session()->regenerate();
        return redirect('/petugas/dashboard');
    }

    public function index(Request $request)
    {
        $db = DB::connection('proyek3');
        $today = now('Asia/Jakarta')->format('Y-m-d');
        $request->merge(['from'=>$request->input('from') ?: $today, 'to'=>$request->input('to') ?: $today]);
        $filters = $request->validate(['q'=>'nullable|string|max:100', 'status'=>'nullable|in:MENUNGGU_VERIFIKASI,SELESAI,DIBATALKAN', 'from'=>'nullable|date_format:Y-m-d', 'to'=>'nullable|date_format:Y-m-d|after_or_equal:from']);
        $query = $db->table('setoran as s')->join('users as u', 'u.id', '=', 's.nasabah_id')
            ->when($filters['q'] ?? null, fn($q,$v)=>$q->where(fn($q)=>$q->where('u.nama','ilike','%'.$v.'%')->orWhere('s.kode','ilike','%'.$v.'%')->orWhere('u.no_hp','like','%'.$v.'%')))
            ->when($filters['status'] ?? null, fn($q,$v)=>$q->where('s.status',$v))
            ->when($filters['from'] ?? null, fn($q,$v)=>$q->whereRaw("(coalesce(s.diverifikasi_at,s.created_at) AT TIME ZONE 'Asia/Jakarta')::date >= ?",[$v]))
            ->when($filters['to'] ?? null, fn($q,$v)=>$q->whereRaw("(coalesce(s.diverifikasi_at,s.created_at) AT TIME ZONE 'Asia/Jakarta')::date <= ?",[$v]))
            ;
        $summary = (clone $query)->selectRaw("count(*) as transaksi, coalesce(sum(s.total_kg) filter (where s.status='SELESAI'),0) as kg, coalesce(sum(s.total_liter) filter (where s.status='SELESAI'),0) as liter, coalesce(sum(s.total_nilai) filter (where s.status='SELESAI'),0) as nilai")->first();
        $records = $query->select('s.*', 'u.nama', 'u.no_hp', 'u.alamat')->orderByDesc('s.id')->paginate(20)->withQueryString();
        $items = $db->table('setoran_detail')->whereIn('setoran_id', $records->pluck('id'))->orderBy('id')->get()->groupBy('setoran_id');
        return view('petugas.operational', ['screen' => 'index', 'records' => $records, 'summary'=>$summary, 'items'=>$items, 'filters'=>$filters]);
    }

    public function form(Request $request, ?int $id = null)
    {
        $db = DB::connection('proyek3');
        $record = $id ? $db->table('setoran')->find($id) : null;
        if ($id) {
            abort_unless($record, 404);
            abort_unless($record->petugas_id === $request->user()->id, 403);
            abort_unless($record->status === 'MENUNGGU_VERIFIKASI', 409);
        }
        $search = $request->validate(['q' => 'nullable|string|max:100', 'nasabah_id'=>'nullable|integer'])['q'] ?? '';
        $selected = old('nasabah_id', $record?->nasabah_id ?? $request->input('nasabah_id'));
        $customers = $db->table('users as u')->join('model_has_roles as m', 'm.model_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'm.role_id')->where('m.model_type', User::class)
            ->where('r.name', 'nasabah')->where('r.guard_name', 'web')->where('u.aktif', true)
            ->where(function ($q) use ($search, $selected) {
                $q->where('u.nama', 'ilike', '%'.$search.'%')->orWhere('u.no_hp', 'like', '%'.$search.'%')->orWhereRaw('u.kode_qr::text = ?', [$search])->orWhereRaw('u.id::text = ?', [$search]);
                if ($selected) $q->orWhere('u.id', $selected);
            })->select('u.id', 'u.nama', 'u.no_hp', 'u.alamat')->orderBy('u.nama')->limit(50)->get();
        $details = $id ? $db->table('setoran_detail')->where('setoran_id', $id)->get() : collect();
        $types = $db->table('jenis_sampah')->where(fn($q)=>$q->where('aktif',true)->orWhereIn('id',$details->pluck('jenis_sampah_id')))->orderBy('nama')->get();
        foreach ($types as $type) {
            $snapshot = $details->firstWhere('jenis_sampah_id',$type->id);
            if ($snapshot) {
                $type->harga_beli = $snapshot->harga_beli_snapshot;
                $type->satuan = $snapshot->satuan;
                $type->nama = $snapshot->nama_jenis;
            }
        }
        return view('petugas.operational', compact('record','customers','types','details','selected') + ['screen'=>'form']);
    }

    public function customers(Request $request, ?int $id = null)
    {
        $data=$request->validate(['q'=>'nullable|string|max:100']);
        $query=User::query()->whereExists(function($q){
            $q->selectRaw('1')->from('model_has_roles as m')->join('roles as r','r.id','=','m.role_id')
                ->whereColumn('m.model_id','users.id')->where('m.model_type',User::class)->where('r.name','nasabah')->where('r.guard_name','web');
        });
        $customer=$id?(clone $query)->findOrFail($id):null;
        $search=$data['q']??'';
        $customers=$query->where(fn($q)=>$q->where('nama','ilike','%'.$search.'%')->orWhere('no_hp','like','%'.$search.'%')->orWhereRaw('kode_qr::text = ?',[$search])->orWhereRaw('users.id::text = ?',[$search]))->orderBy('nama')->orderBy('id')->paginate(20)->withQueryString();
        $db=DB::connection('proyek3');

        $totalKg = 0;
        $totalLiter = 0;
        if ($customer) {
            $aggregates = $db->table('setoran')
                ->where('nasabah_id', $id)
                ->where('status', 'SELESAI')
                ->selectRaw('coalesce(sum(total_kg), 0) as total_kg, coalesce(sum(total_liter), 0) as total_liter')
                ->first();
            $totalKg = $aggregates->total_kg ?? 0;
            $totalLiter = $aggregates->total_liter ?? 0;
        }

        return view('petugas.operational',[
            'screen'=>'customers',
            'customers'=>$customers,
            'customer'=>$customer,
            'totalKg'=>$totalKg,
            'totalLiter'=>$totalLiter,
            'saldo'=>$customer?$db->table('saldo_nasabah')->where('user_id',$id)->first():null,
            'history'=>$customer?$db->table('setoran')->where('nasabah_id',$id)->orderByDesc('id')->paginate(10, ['*'], 'history_page'):null
        ]);
    }

    public function search(Request $request)
    {
        $q=$request->validate(['q'=>'required|string|max:100'])['q'];
        return DB::connection('proyek3')->table('users as u')->where('u.aktif',true)
            ->whereExists(fn($b)=>$b->selectRaw('1')->from('model_has_roles as m')->join('roles as r','r.id','=','m.role_id')->whereColumn('m.model_id','u.id')->where('m.model_type',User::class)->where('r.name','nasabah')->where('r.guard_name','web'))
            ->where(fn($b)=>$b->where('u.nama','ilike','%'.$q.'%')->orWhere('u.no_hp','like','%'.$q.'%')->orWhereRaw('u.kode_qr::text = ?',[$q])->orWhereRaw('u.id::text = ?',[$q]))
            ->select('u.id','u.nama','u.no_hp','u.alamat')->orderBy('u.nama')->limit(50)->get();
    }

    public function save(Request $request, SetoranService $service, ?int $id = null)
    {
        $data = $request->validate(['nasabah_id' => 'required|integer', 'items' => 'required|array|min:1|max:50',
            'items.*.jenis_sampah_id' => 'required|integer|distinct',
            'items.*.jumlah' => ['required', 'numeric', 'min:0.01', 'max:10000', 'regex:/^\d+(\.\d{1,2})?$/']]);
        return redirect('/petugas/setoran/'.$service->save($request->user(), $data, $id));
    }

    public function show(int $id)
    {
        $db = DB::connection('proyek3');
        $record = $db->table('setoran')->find($id);
        abort_unless($record, 404);

        $saldoNasabah = $db->table('saldo_nasabah')->where('user_id', $record->nasabah_id)->first();

        return view('petugas.operational', [
            'screen' => 'show',
            'record' => $record,
            'customer' => User::findOrFail($record->nasabah_id),
            'recorder' => User::findOrFail($record->petugas_id),
            'saldoNasabah' => $saldoNasabah,
            'details' => $db->table('setoran_detail')->where('setoran_id', $id)->get()
        ]);
    }

    public function finish(Request $request, SetoranService $service, int $id)
    {
        $data = $request->validate(['action' => 'required|in:verify,cancel', 'alasan' => 'required_if:action,cancel|nullable|string|max:2000']);
        $service->finish($request->user(), $id, $data['action'] === 'cancel' ? $data['alasan'] : null);

        $redirect = redirect('/petugas/setoran/'.$id);
        if ($data['action'] === 'verify') {
            $redirect->with('just_verified', true);
        }

        return $redirect;
    }
}
