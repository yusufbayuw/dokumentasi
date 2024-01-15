<?php

namespace Database\Seeders;

use App\Models\Pengaturan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PengaturanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Pengaturan::create([
            'nama' => 'Kuota IPA',
            'keterangan' => 'Jumlah kuota untuk program IPA',
            'nilai' => '78',
        ]);
        Pengaturan::create([
            'nama' => 'Kuota IPS',
            'keterangan' => 'Jumlah kuota untuk program IPS',
            'nilai' => '20',
        ]);
        Pengaturan::create([
            'nama' => 'Nama Seleksi',
            'keterangan' => 'Nama seleksi penerimaan PT',
            'nilai' => 'SNBP 2024',
        ]);
        Pengaturan::create([
            'nama' => 'Pengumuman Kuota',
            'keterangan' => '1 untuk aktif, 0 untuk non-aktif',
            'nilai' => '0',
        ]);
        Pengaturan::create([
            'nama' => 'Pengumuman Ranking Pemilihan',
            'keterangan' => '1 untuk aktif, 0 untuk non-aktif',
            'nilai' => '1',
        ]);
    }
}
