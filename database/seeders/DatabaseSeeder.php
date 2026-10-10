<?php

namespace Database\Seeders;

use App\Models\DamageReport;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Facilities (Extracted from GitHub database-setup schema.sql and seed.go)
        $facilities = [
            [
                'name' => 'Muladi Dome',
                'slug' => 'muladi-dome',
                'type' => 'Gedung/Aula',
                'category' => 'Gedung/Aula',
                'location' => 'Lainnya',
                'capacity' => 5000,
                'description' => 'Gedung serbaguna kapasitas besar untuk kegiatan kampus.',
                'address' => 'Jl. Prof. Soedarto No.50239, Tembalang',
                'image' => 'muladi-dome.png',
                'status' => 'aktif',
            ],
            [
                'name' => 'Polytron Stadium',
                'slug' => 'polytron',
                'type' => 'Stadion',
                'category' => 'Stadion',
                'location' => 'Lainnya',
                'capacity' => 300,
                'description' => 'Fasilitas olahraga indoor untuk berbagai cabang olahraga.',
                'address' => 'Jl. Prof. Soedarto SH, Tembalang',
                'image' => 'polytron-stadium.png',
                'status' => 'aktif',
            ],
            [
                'name' => 'Gedung Auditorium Prof. Soedarto, S.H.',
                'slug' => 'auditorium',
                'type' => 'Gedung/Aula',
                'category' => 'Gedung/Aula',
                'location' => 'Lainnya',
                'capacity' => 800,
                'description' => 'Auditorium utama universitas.',
                'address' => 'Jl. Prof. Soedarto, S.H., Tembalang',
                'image' => 'Gd-Auditorium-Prof.-Soedarto.png',
                'status' => 'Dalam Perbaikan',
            ],
            [
                'name' => 'Laboratorium Komputer Terintegrasi - Gedung Acintya Prasada',
                'slug' => 'lab-komputer',
                'type' => 'Laboratorium',
                'category' => 'Laboratorium',
                'location' => 'FSM',
                'capacity' => 50,
                'description' => 'Laboratorium praktikum dan tes berbasis komputer.',
                'address' => 'Jl. Prof. Jacob Rais, Tembalang',
                'image' => 'Lab-Komputer-Gd-AP.png',
                'status' => 'aktif',
            ],
            [
                'name' => 'Gedung Laboratorium Terpadu',
                'slug' => 'gedung-laboratorium',
                'type' => 'Laboratorium',
                'category' => 'Laboratorium',
                'location' => 'Lainnya',
                'capacity' => 50,
                'description' => 'Gedung laboratorium riset bersama.',
                'address' => 'Jl. Prof. Soedarto, S.H., Tembalang',
                'image' => 'gd-lab-bersama.png',
                'status' => 'nonaktif',
            ],
            [
                'name' => 'Laboratorium Sentral FK',
                'slug' => 'laboratorium-sentral',
                'type' => 'Laboratorium',
                'category' => 'Laboratorium',
                'location' => 'Lainnya',
                'capacity' => 50,
                'description' => 'Fasilitas laboratorium Fakultas Kedokteran.',
                'address' => 'Jl. Prof. Moeljono S. Trastotenojo',
                'image' => 'lab-sentral-fk.png',
                'status' => 'aktif',
            ],
        ];

        $facilityModels = [];
        foreach ($facilities as $fac) {
            $facilityModels[$fac['slug']] = Facility::create($fac);
        }

        // 2. Seed Users (Extracted from GitHub database-setup seed.go)
        // Demo accounts use one password; do not use this seeder to provision production accounts.
        $defaultPassword = Hash::make('Password123!');

        // Admin: Ira Kusumadewi
        $admin = User::create([
            'name' => 'Ira Kusumadewi',
            'identity_number' => '198001012005012001',
            'email' => 'ira.admin@admin.undip.ac.id',
            'phone' => '081112223334',
            'password' => $defaultPassword,
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        // Secondary admin alias for test compatibility
        User::create([
            'name' => 'Administrator DiServe',
            'identity_number' => '198501012010122001',
            'email' => 'admin@admin.undip.ac.id',
            'phone' => '081112223335',
            'password' => $defaultPassword,
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        // Petugas: Arif Pratama
        $petugas = User::create([
            'name' => 'Arif Pratama',
            'identity_number' => '198803112014021004',
            'email' => 'arifpratama35@facility.undip.ac.id',
            'phone' => '081298761234',
            'password' => $defaultPassword,
            'role' => 'petugas',
            'status' => 'aktif',
        ]);

        // Pengguna: Husni Ulyaa Khanifah
        $husni = User::create([
            'name' => 'Husni Ulyaa Khanifah',
            'identity_number' => '24060124120021',
            'email' => 'husni@students.undip.ac.id',
            'phone' => '0812-3456-7890',
            'password' => $defaultPassword,
            'role' => 'pengguna',
            'status' => 'aktif',
        ]);

        // Pengguna: Lintang Aulia Nuraini
        $lintang = User::create([
            'name' => 'Lintang Aulia Nuraini',
            'identity_number' => '24060124120017',
            'email' => 'lintang@students.undip.ac.id',
            'phone' => '0821-9876-5432',
            'password' => $defaultPassword,
            'role' => 'pengguna',
            'status' => 'aktif',
        ]);

        // Pengguna: Hana Nafi'atul Haq
        $hana = User::create([
            'name' => "Hana Nafi'atul Haq",
            'identity_number' => '24060124130081',
            'email' => 'hana@students.undip.ac.id',
            'phone' => '0857-1234-5678',
            'password' => $defaultPassword,
            'role' => 'pengguna',
            'status' => 'aktif',
        ]);

        // Pengguna: Birela Miadeta Purita
        $birela = User::create([
            'name' => 'Birela Miadeta Purita',
            'identity_number' => '24060124120002',
            'email' => 'birela@students.undip.ac.id',
            'phone' => '0838-8765-4321',
            'password' => $defaultPassword,
            'role' => 'pengguna',
            'status' => 'aktif',
        ]);

        // Pengguna: Nabila Kayla Rafa (Pending / Menunggu Verifikasi)
        User::create([
            'name' => 'Nabila Kayla Rafa',
            'identity_number' => '24060124120022',
            'email' => 'nabilakay@students.undip.ac.id',
            'phone' => '081233445566',
            'password' => $defaultPassword,
            'role' => 'pengguna',
            'status' => 'pending',
        ]);

        // Additional lecturer and staff accounts for admin dashboard demo
        User::create([
            'name' => 'Dr. Budi Susanto, S.T., M.T.',
            'identity_number' => '197505122001121002',
            'email' => 'budisusanto@lectures.undip.ac.id',
            'phone' => '081299887766',
            'password' => $defaultPassword,
            'role' => 'pengguna',
            'status' => 'pending',
        ]);

        User::create([
            'name' => 'Siti Aminah, S.A.P.',
            'identity_number' => '198207192008102001',
            'email' => 'sitiaminah@staff.undip.ac.id',
            'phone' => '081377665544',
            'password' => $defaultPassword,
            'role' => 'pengguna',
            'status' => 'pending',
        ]);

        // 3. Seed Reservations (Extracted from GitHub database-setup seed.go)
        Reservation::create([
            'user_id' => $husni->id,
            'facility_id' => $facilityModels['lab-komputer']->id,
            'reservation_date' => '2026-10-25',
            'start_date' => '2026-10-25',
            'end_date' => '2026-10-25',
            'start_time' => '08:00',
            'end_time' => '11:30',
            'start_at' => Carbon::parse('2026-10-25 08:00:00'),
            'end_at' => Carbon::parse('2026-10-25 11:30:00'),
            'purpose' => 'Pelatihan UI/UX Design',
            'supporting_file' => 'Proposal_Pelatihan_UIUX.pdf',
            'status' => 'menunggu',
            'cancellation_deadline' => Carbon::parse('2026-10-24 08:00:00'),
        ]);

        Reservation::create([
            'user_id' => $lintang->id,
            'facility_id' => $facilityModels['muladi-dome']->id,
            'reservation_date' => '2026-10-28',
            'start_date' => '2026-10-28',
            'end_date' => '2026-10-28',
            'start_time' => '13:00',
            'end_time' => '16:00',
            'start_at' => Carbon::parse('2026-10-28 13:00:00'),
            'end_at' => Carbon::parse('2026-10-28 16:00:00'),
            'purpose' => 'Seminar Generative AI untuk Mahasiswa Informatika',
            'supporting_file' => 'Izin_Acara_Seminar_AI.pdf',
            'status' => 'disetujui',
            'cancellation_deadline' => Carbon::parse('2026-10-27 13:00:00'),
        ]);

        Reservation::create([
            'user_id' => $hana->id,
            'facility_id' => $facilityModels['auditorium']->id,
            'reservation_date' => '2026-10-30',
            'start_date' => '2026-10-30',
            'end_date' => '2026-10-30',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'start_at' => Carbon::parse('2026-10-30 09:00:00'),
            'end_at' => Carbon::parse('2026-10-30 12:00:00'),
            'purpose' => 'Gathering Maba Informatika',
            'supporting_file' => 'Proposal_Makrab_Informatika.pdf',
            'status' => 'ditolak',
            'cancellation_reason' => 'Gedung sedang dalam perbaikan.',
            'rejection_reason' => 'Gedung sedang dalam perbaikan.',
            'cancellation_deadline' => Carbon::parse('2026-10-29 09:00:00'),
        ]);

        Reservation::create([
            'user_id' => $birela->id,
            'facility_id' => $facilityModels['polytron']->id,
            'reservation_date' => '2026-11-02',
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-02',
            'start_time' => '10:00',
            'end_time' => '14:00',
            'start_at' => Carbon::parse('2026-11-02 10:00:00'),
            'end_at' => Carbon::parse('2026-11-02 14:00:00'),
            'purpose' => 'Pertandingan Badminton Tingkat Fakultas',
            'supporting_file' => 'Jadwal_Tanding_BEM.pdf',
            'status' => 'dibatalkan',
            'cancellation_reason' => 'Dialihkan untuk acara universitas.',
            'cancellation_deadline' => Carbon::parse('2026-11-01 10:00:00'),
        ]);

        // 4. Seed Reports (Extracted from GitHub database-setup seed.go)
        DamageReport::create([
            'user_id' => $husni->id,
            'facility_id' => $facilityModels['laboratorium-sentral']->id,
            'category' => 'Kelistrikan',
            'location_detail' => 'Ruang 203, Lantai 2 (Area Praktikum)',
            'description' => 'Kerusakan pada instalasi listrik utama, teknisi sedang melakukan pengecekan.',
            'photo' => 'IMG_Konslet_Lab.jpg',
            'status' => 'Baru',
        ]);

        DamageReport::create([
            'user_id' => $lintang->id,
            'facility_id' => $facilityModels['auditorium']->id,
            'category' => 'Infrastruktur Bangunan',
            'location_detail' => 'Atap sayap kiri auditorium',
            'description' => 'Ditemukan kebocoran pada beberapa bagian atap gedung yang menyebabkan rembesan air ke area dalam.',
            'photo' => 'IMG_Bocor_Atap.jpg',
            'status' => 'Diproses',
        ]);

        DamageReport::create([
            'user_id' => $hana->id,
            'facility_id' => $facilityModels['lab-komputer']->id,
            'category' => 'Inventaris Ruangan',
            'location_detail' => 'Lab Komputer A, Baris ke-2',
            'description' => 'Beberapa kursi dan meja komputer mengalami kerusakan dan perlu diperbaiki.',
            'photo' => 'IMG_Kursi_Patah.jpg',
            'status' => 'Selesai',
            'resolution_note' => 'Penggantian dan perbaikan unit kursi',
        ]);

        DamageReport::create([
            'user_id' => $birela->id,
            'facility_id' => $facilityModels['polytron']->id,
            'category' => 'Infrastruktur Bangunan',
            'location_detail' => 'Tribun Penonton VIP',
            'description' => 'Lampu sorot lapangan mati satu di bagian sudut kanan.',
            'photo' => 'IMG_Lampu_Mati.jpg',
            'status' => 'Ditolak',
            'reject_reason' => 'Laporan duplikat. Kerusakan sudah dilaporkan sebelumnya.',
        ]);
    }
}
