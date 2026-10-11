# DiServe - Facility Management System 🏛️

**DiServe** adalah sistem informasi manajemen fasilitas kampus terpadu yang dirancang untuk mempermudah civitas akademika Universitas Diponegoro (UNDIP) dalam melihat ketersediaan gedung/ruangan, mengajukan reservasi fasilitas, serta melaporkan kerusakan fasilitas secara transparan dan efisien.

Aplikasi ini dibangun menggunakan framework **Laravel** dengan basis data cloud berbasis **Supabase (PostgreSQL)**.

---

## 📌 Daftar Isi
- [Fitur Utama](#-fitur-utama)
- [Peran Pengguna (User Roles)](#-peran-pengguna-user-roles)
- [Teknologi yang Digunakan](#-teknologi-yang-digunakan)
- [Struktur Proyek](#-struktur-proyek)
- [Prasyarat Sistem](#-prasyarat-sistem)
- [Konfigurasi Database Supabase](#-konfigurasi-database-supabase)
- [Panduan Instalasi & Menjalankan Aplikasi](#-panduan-instalasi--menjalankan-aplikasi)
- [Akun Pengujian (Demo Accounts)](#-akun-pengujian-demo-accounts)
- [Endpoint API Utama](#-endpoint-api-utama)
- [Menjalankan Pengujian (Testing)](#-menjalankan-pengujian-testing)
- [Lisensi](#-lisensi)

---

## ✨ Fitur Utama

1. **Katalog Fasilitas & Pengecekan Ketersediaan (Availability Checker)**
   - Eksplorasi katalog gedung, aula, stadion, dan laboratorium.
   - Pengecekan jadwal penggunaan fasilitas secara real-time untuk mencegah bentrok jadwal.
2. **Manajemen Reservasi Fasilitas**
   - Pengajuan peminjaman fasilitas secara daring dilengkapi dokumen/proposal pendukung (PDF/dokumen).
   - Validasi batas waktu pembatalan (*cancellation deadline*).
   - Penanganan pembatalan mandiri oleh pengguna dan pembatalan darurat (*emergency cancel*) oleh petugas.
3. **Pelaporan & Monitoring Kerusakan Fasilitas**
   - Pelaporan kerusakan fasilitas lengkap dengan kategori, rincian lokasi, deskripsi masalah, dan bukti foto.
   - Pelacakan alur status laporan (*Baru*, *Diproses*, *Selesai*, *Ditolak*).
4. **Verifikasi Akun & Autentikasi Keamanan**
   - Registrasi civitas akademika dengan alur verifikasi dan persetujuan oleh Admin.
   - Autentikasi berbasis token aman dengan **Laravel Sanctum** serta proteksi sesi idle (*idle token refresh middleware*).
   - Fitur Lupa Password & Reset Password berbasis token email.
5. **Panel Admin & Rekapitulasi Data**
   - Manajemen master data fasilitas (Tambah, Edit, Toggle Status Operasional/Maintenance, Hapus).
   - Manajemen data pengguna (Verifikasi, Penolakan, Pengaktifan/Penonaktifan akun).
   - Laporan dan rekapitulasi data peminjaman serta fasilitas.

---

## 👥 Peran Pengguna (User Roles)

| Peran | Hak Akses & Tanggung Jawab |
|---|---|
| **Pengunjung (Visitor)** | Melihat katalog fasilitas, detail fasilitas, serta jadwal ketersediaan umum tanpa login. |
| **Pengguna (Civitas/Mahasiswa)** | Mengajukan reservasi, melacak status pengajuan, membatalkan reservasi, dan mengirim laporan kerusakan fasilitas. |
| **Petugas Operasional** | Meninjau antrean reservasi (setujui/tolak/batal darurat), menangani laporan kerusakan, serta mengubah status pemeliharaan (*maintenance*) fasilitas. |
| **Administrator** | Memverifikasi akun baru, mengelola master data pengguna & fasilitas, serta melihat rekapitulasi dan laporan sistem. |

---

## 🛠️ Teknologi yang Digunakan

- **Backend**: [Laravel 11/12](https://laravel.com/) (PHP ^8.3), [Laravel Sanctum](https://laravel.com/docs/sanctum)
- **Database Utama**: **[Supabase](https://supabase.com/)** (Hosted PostgreSQL)
- **Database Testing**: SQLite in-memory (untuk kecepatan & isolasi unit/feature test)
- **Frontend**: Vanilla JavaScript (ES6+), HTML5, CSS3 kustom responsif
- **Asset Bundler / Tools**: Vite, Composer, NPM
- **Testing**: PHPUnit

---

## 📂 Struktur Proyek

```text
DiServe-facility-management-system/
├── app/
│   ├── Http/Controllers/     # Controller API & Bisnis Logika (Admin, Auth, Facility, dll.)
│   ├── Http/Middleware/      # Middleware peran (role) & idle token
│   ├── Mail/                 # Mailable untuk notifikasi email
│   └── Models/               # Model Eloquent (User, Facility, Reservation, DamageReport)
├── config/
│   └── database.php          # Konfigurasi koneksi pgsql (Supabase)
├── database/
│   ├── migrations/           # Skema tabel PostgreSQL
│   └── seeders/              # Data seeder fasilitas, user demo, reservasi, & laporan
├── public/
│   ├── assets/
│   │   ├── css/              # Stylesheet antarmuka
│   │   ├── js/               # Logika antarmuka klien & fetch API
│   │   └── images/           # Asset gambar fasilitas & logo UNDIP/DiServe
│   └── *.html                # Halaman frontend (index, login, dashboard, admin, petugas, dll.)
├── resources/                # View email & template Blade
├── routes/
│   ├── api.php               # Rute RESTful API (Sanctum protected)
│   └── web.php               # Rute web untuk navigasi antarhalaman
└── tests/
    └── Feature/              # Pengujian otomatis skenario aplikasi
```

---

## 💻 Prasyarat Sistem

Sebelum menjalankan proyek, pastikan sistem Anda telah terpasang:
- **PHP** minimal versi 8.3 dengan ekstensi aktif:
  - `pdo_pgsql` dan `pgsql` (⚠️ **Wajib** untuk koneksi ke database Supabase)
  - `pdo_sqlite` / `sqlite3` (untuk menjalankan automated testing)
  - `mbstring`, `xml`, `curl`, `openssl`
- **Composer** (versi 2.x)
- **Node.js** (versi 18+) & **NPM**

---

## 🗄️ Konfigurasi Database Supabase

Proyek ini terhubung langsung ke **Supabase** menggunakan driver PostgreSQL (`pgsql`).

Dapatkan parameter koneksi dari dashboard Supabase Anda:
👉 **Project Settings** > **Database** > **Connection parameters** (atau via **Connection Pooling**).

Atur konfigurasi pada file `.env` sebagai berikut:

```env
DB_CONNECTION=pgsql
DB_HOST=aws-0-ap-northeast-1.pooler.supabase.com   # Sesuaikan dengan host pooler project Supabase Anda
DB_PORT=5432                                      # Port pooler (5432 untuk session mode atau 6543 untuk transaction mode)
DB_DATABASE=postgres
DB_USERNAME=postgres.YOUR_PROJECT_REF            # Format username Supabase pooler
DB_PASSWORD=YOUR_SUPABASE_DB_PASSWORD
DB_SSLMODE=prefer
```

---

## 🚀 Panduan Instalasi & Menjalankan Aplikasi

Ikuti langkah-langkah berikut untuk menginisialisasi dan menjalankan aplikasi:

### 1. Salin Environment Configuration
Buat salinan file `.env`:
```bash
cp .env.example .env
```
*(Di Windows PowerShell: `Copy-Item .env.example .env`)*

Buka file `.env` dan pastikan konfigurasi `DB_*` telah sesuai dengan akun **Supabase** Anda.

### 2. Pasang Dependensi
Pasang paket dependensi PHP dan Node.js:
```bash
composer install
npm install
```

### 3. Generate Application Key
```bash
php artisan key:generate
```

### 4. Jalankan Migrasi & Seeder ke Supabase
Jalankan migrasi tabel ke database Supabase beserta data seeder awal:
```bash
php artisan migrate --seed
```

### 5. Jalankan Server Pengembangan
Jalankan server aplikasi Laravel:
```bash
php artisan serve
```

---

## 🔑 Akun Pengujian (Demo Accounts)

Untuk keperluan pengujian dan demonstrasi sistem, berikut adalah kredensial akun demo untuk setiap peran (aktor):

### 1. Aktor 1: Pengguna (User)
- **Email**: `test@students.undip.ac.id`
- **Password**: `Password123!`
- **Hak Akses**: Mengajukan reservasi fasilitas, melihat ketersediaan, dan melaporkan kerusakan fasilitas.

### 2. Aktor 2: Petugas (Staff/Operator)
- **Email**: `petugas@officer.undip.ac.id`
- **Password**: `Petugas123!`
- **Hak Akses**: Memeriksa permohonan reservasi, memperbarui status pengaduan/laporan kerusakan, dan memvalidasi ketersediaan lokasi.

### 3. Aktor 3: Administrator (Admin)
- **Email**: `admin@admin.undip.ac.id`
- **Password**: `Admin123!`
- **Hak Akses**: Manajemen data master fasilitas (tambah/edit/hapus), kelola pengguna, dan rekapitulasi laporan terpusat.

| Aktor | Role | Email | Password | Ringkasan Hak Akses |
|---|---|---|---|---|
| **Aktor 1** | Pengguna (*User*) | `test@students.undip.ac.id` | `Password123!` | Reservasi fasilitas, cek ketersediaan, & laporan kerusakan |
| **Aktor 2** | Petugas (*Staff/Operator*) | `petugas@officer.undip.ac.id` | `Petugas123!` | Verifikasi reservasi, perbarui status laporan, & validasi lokasi |
| **Aktor 3** | Administrator (*Admin*) | `admin@admin.undip.ac.id` | `Admin123!` | Kelola master fasilitas, kelola pengguna, & rekapitulasi laporan |

---

## 📡 Endpoint API Utama

Sistem menyediakan RESTful API berbasis token Sanctum:

### Autentikasi & Akun
- `POST /api/auth/register` : Registrasi akun pengguna baru.
- `POST /api/auth/login` : Masuk sistem dan memperoleh token akses.
- `GET /api/auth/me` : Mendapatkan data profil pengguna yang sedang login.
- `POST /api/auth/logout` : Logout dan invalidasi token.
- `POST /api/auth/forgot-password` & `POST /api/auth/reset-password` : Pengaturan ulang kata sandi.

### Fasilitas (Publik & Terautentikasi)
- `GET /api/facilities` : Daftar seluruh fasilitas kampus.
- `GET /api/facilities/{id}` : Detail informasi fasilitas tertentu.
- `GET /api/facilities/{id}/availability` : Jadwal ketersediaan fasilitas.

### Reservasi & Pelaporan (Pengguna)
- `GET /api/user/reservations` : Riwayat reservasi milik pengguna login.
- `POST /api/reservations` : Mengajukan peminjaman fasilitas baru.
- `POST /api/reservations/{id}/cancel` : Membatalkan reservasi mandiri (sebelum deadline).
- `GET /api/user/reports` : Riwayat laporan kerusakan fasilitas milik pengguna.
- `POST /api/reports` : Mengirim laporan kerusakan fasilitas baru.

### Operasional & Verifikasi (Petugas & Admin)
- `GET /api/petugas/dashboard` : Statistik ringkasan petugas.
- `GET /api/petugas/queue` : Antrean verifikasi pengajuan reservasi.
- `POST /api/petugas/reservations/{id}/approve` : Menyetujui pengajuan reservasi.
- `POST /api/petugas/reservations/{id}/reject` : Menolak pengajuan reservasi.
- `POST /api/petugas/reservations/{id}/emergency-cancel` : Pembatalan darurat oleh petugas.
- `GET /api/petugas/damage-reports` : Daftar seluruh laporan kerusakan fasilitas.
- `PATCH /api/petugas/damage-reports/{id}/status` : Memperbarui status penanganan laporan.

### Administrasi Sistem (Admin)
- `GET /api/admin/users` : Daftar seluruh akun pengguna.
- `POST /api/admin/users/{id}/verify` : Menyetujui dan mengaktifkan akun baru.
- `POST /api/admin/users/{id}/reject` : Menolak pendaftaran akun baru.
- `POST /api/admin/facilities` : Menambahkan master data fasilitas baru.
- `PUT /api/admin/facilities/{id}` : Memperbarui master data fasilitas.
- `DELETE /api/admin/facilities/{id}` : Menghapus fasilitas.
- `GET /api/admin/rekap` : Rekapitulasi data penggunaan fasilitas dan laporan.
- `GET /api/admin/export/{format}` : Ekspor data rekapitulasi.

---

## 🧪 Menjalankan Pengujian (Testing)

Pengujian otomatis (*Feature* & *Unit Test*) dikonfigurasi menggunakan SQLite in-memory untuk eksekusi yang cepat, aman, dan terisolasi tanpa mempengaruhi basis data Supabase:

```bash
php artisan test
```

---

## 📄 Lisensi

Proyek ini dikembangkan untuk kebutuhan akademik mata kuliah Pengembangan Platform Khusus (PPK) Universitas Diponegoro dan berlisensi di bawah [MIT License](LICENSE).

