<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreatePetugas extends Command
{
    protected $signature = 'petugas:create {email} {nama}';
    protected $description = 'Buat akun petugas PostgreSQL; password diminta secara tersembunyi';

    public function handle(): int
    {
        $data = ['email' => $this->argument('email'), 'nama' => $this->argument('nama'), 'password' => $this->secret('Password (minimal 8 karakter)')];
        $validator = Validator::make($data, ['email' => 'required|email|max:255', 'nama' => 'required|string|max:255', 'password' => 'required|string|min:8']);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());
            return self::FAILURE;
        }
        $db = DB::connection('proyek3');
        if ($db->table('users')->where('email', $data['email'])->exists()) {
            $this->error('Email sudah terdaftar; akun tidak diubah.');
            return self::FAILURE;
        }
        $db->transaction(function () use ($db, $data) {
            $role = $db->table('roles')->where('name', 'petugas')->where('guard_name', 'web')->first();
            if (! $role) throw new \RuntimeException('Jalankan katalog seeder backend terlebih dahulu.');
            $id = $db->table('users')->insertGetId(['nama' => $data['nama'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
            $db->table('model_has_roles')->insert(['role_id' => $role->id, 'model_type' => \App\Models\User::class, 'model_id' => $id]);
        });
        $this->info('Akun petugas berhasil dibuat.');
        return self::SUCCESS;
    }
}
