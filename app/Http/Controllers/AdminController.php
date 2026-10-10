<?php

namespace App\Http\Controllers;

use App\Models\DamageReport;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AdminController extends Controller
{
    /**
     * List all accounts for Admin (US 15).
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::query()->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $keyword = strtolower($request->search);
            $query->where(function ($q) use ($keyword) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$keyword}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$keyword}%"])
                  ->orWhereRaw('LOWER(identity_number) LIKE ?', ["%{$keyword}%"]);
            });
        }

        $users = $query->get();

        $data = $users->map(function ($u) {
            $roleLabel = 'Mahasiswa';
            $email = strtolower($u->email);

            if (str_contains($email, '@lectures.undip.ac.id')) {
                $roleLabel = 'Dosen';
            } elseif (str_contains($email, '@staff.undip.ac.id')) {
                $roleLabel = 'Staf Akademik';
            } elseif ($u->role === 'petugas' || str_contains($email, 'facility') || str_contains($email, 'officer')) {
                $roleLabel = 'Petugas';
            } elseif ($u->role === 'admin' || str_contains($email, '@admin.undip.ac.id')) {
                $roleLabel = 'Administrator';
            }

            $rawStatus = strtolower($u->status);
            $statusText = match ($rawStatus) {
                'aktif', 'active' => 'Aktif',
                'pending', 'menunggu verifikasi' => 'Menunggu Verifikasi',
                'nonaktif', 'suspended' => 'Nonaktif',
                'ditolak', 'rejected' => 'Ditolak',
                default => ucfirst($u->status),
            };

            $statusBadge = match ($rawStatus) {
                'aktif', 'active' => 'success',
                'pending', 'menunggu verifikasi' => 'warning',
                'nonaktif', 'suspended' => 'neutral',
                'ditolak', 'rejected' => 'danger',
                default => 'neutral',
            };

            return [
                'id' => $u->id,
                'row_id' => 'acc-' . $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'identity_number' => $u->identity_number ?: '-',
                'role' => $u->role,
                'role_label' => $roleLabel,
                'status' => $u->status,
                'status_text' => $statusText,
                'status_badge' => $statusBadge,
                'rejection_reason' => $u->rejection_reason,
            ];
        });

        $pendingCount = User::whereIn('status', ['pending', 'menunggu verifikasi', 'Menunggu Verifikasi'])->count();

        return response()->json([
            'data' => $data,
            'pending_count' => $pendingCount,
        ]);
    }

    /**
     * Admin registers user / officer account directly (US 13, US 14).
     */
    public function createUser(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'identity_number' => 'required|string|max:50',
            'email' => 'required|string|email|max:150|unique:users,email',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/[a-z]/', $value)
                        || !preg_match('/[A-Z]/', $value)
                        || !preg_match('/[0-9]/', $value)
                        || !preg_match('/[@$!%*?&#_]/', $value)) {
                        $fail('Password harus memiliki huruf kecil, huruf besar, angka, dan karakter khusus.');
                    }
                },
            ],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'identity_number.required' => 'NIM / NIP wajib diisi.',
            'email.required' => 'Email kampus wajib diisi.',
            'email.unique' => 'Email sudah terdaftar di sistem.',
        ]);

        $email = strtolower($request->email);
        $role = 'pengguna';

        if (str_contains($email, '@admin.undip.ac.id')) {
            $role = 'admin';
        } elseif (str_contains($email, '@facility.undip.ac.id') || str_contains($email, '@facillity.undip.ac.id') || str_contains($email, '@officer.undip.ac.id')) {
            $role = 'petugas'; // US 13
        } elseif (str_contains($email, '@students.undip.ac.id') || str_contains($email, '@lectures.undip.ac.id') || str_contains($email, '@staff.undip.ac.id')) {
            $role = 'pengguna'; // US 14
        } else {
            return response()->json([
                'message' => 'Pendaftaran ditolak! Harap gunakan format email resmi institusi yang valid.',
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'identity_number' => $request->identity_number,
            'email' => $email,
            'password' => Hash::make($request->password),
            'role' => $role,
            'status' => 'aktif',
        ]);

        return response()->json([
            'message' => "Akun berhasil didaftarkan!\n\nNama: {$user->name}\nIdentitas: {$user->identity_number}\nEmail: {$user->email}\nRole: {$user->role}",
            'user' => $user,
        ], 201);
    }

    /**
     * Verify self-registered account (US 15).
     */
    public function verifyUser(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);
        if (strtolower($user->status) !== 'pending') {
            return response()->json(['message' => 'Hanya akun yang menunggu verifikasi yang dapat disetujui.'], 422);
        }

        try {
            if (! $user->update(['status' => 'aktif'])) {
                return response()->json([
                    'message' => 'Status akun gagal disimpan. Silakan coba lagi atau hubungi administrator.',
                    'email_sent' => false,
                ], 503);
            }
        } catch (QueryException $exception) {
            Log::error('Account approval status could not be saved.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Status akun gagal disimpan. Silakan coba lagi atau hubungi administrator.',
                'email_sent' => false,
            ], 503);
        }

        $emailSent = false;
        if (config('mail.default') !== 'log') {
            try {
                Mail::to($user->email)->send(new \App\Mail\AccountApprovedMail($user));
                $emailSent = true;
            } catch (\Throwable $exception) {
                Log::error('Account approval notification could not be sent.', [
                    'user_id' => $user->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return response()->json([
            'message' => $emailSent
                ? "Akun pengguna {$user->name} berhasil diverifikasi dan aktif."
                : "Akun pengguna {$user->name} berhasil diaktifkan, tetapi notifikasi email tidak terkirim.",
            'email_sent' => $emailSent,
            'user' => $user,
        ]);
    }

    /**
     * Reject account registration (US 15).
     */
    public function rejectUser(Request $request, $id): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $user = User::findOrFail($id);
        if (strtolower($user->status) !== 'pending') {
            return response()->json(['message' => 'Hanya akun yang menunggu verifikasi yang dapat ditolak.'], 422);
        }

        if (! Schema::hasColumn('users', 'rejection_reason')) {
            return response()->json([
                'message' => 'Penyimpanan alasan penolakan belum tersedia. Minta administrator menyiapkan pembaruan database sebelum menolak akun.',
            ], 503);
        }

        try {
            if (! $user->update([
                'status' => 'ditolak',
                'rejection_reason' => $request->reason,
            ])) {
                return response()->json([
                    'message' => 'Status akun gagal disimpan. Silakan coba lagi atau hubungi administrator.',
                ], 503);
            }
        } catch (QueryException $exception) {
            Log::error('Account rejection status could not be saved.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Status akun gagal disimpan. Silakan coba lagi atau hubungi administrator.',
            ], 503);
        }

        return response()->json([
            'message' => "Pendaftaran akun {$user->name} ditolak.",
            'user' => $user,
        ]);
    }

    /**
     * Toggle account access status between active and revoked/inactive (US 15).
     */
    public function toggleUserStatus(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->isActive()) {
            $user->update(['status' => 'nonaktif']);
            // Revoke current tokens
            $user->tokens()->delete();

            return response()->json([
                'message' => "Akses login untuk \"{$user->name}\" telah DICABUT.",
                'user' => $user,
                'status' => 'nonaktif',
            ]);
        } else {
            $user->update(['status' => 'aktif']);

            return response()->json([
                'message' => "Akses login untuk \"{$user->name}\" telah DIAKTIFKAN KEMBALI.",
                'user' => $user,
                'status' => 'aktif',
            ]);
        }
    }

    /**
     * Rekapitulasi: occupancy rate and damage frequency per facility (US 17).
     */
    public function rekap(): JsonResponse
    {
        $facilities = Facility::all();
        $totalApprovedReservations = Reservation::whereIn('status', ['approved', 'disetujui'])->get();

        $totalHours = 0;
        foreach ($totalApprovedReservations as $res) {
            $start = Carbon::parse($res->start_at ?: $res->reservation_date);
            $end = Carbon::parse($res->end_at ?: $res->reservation_date);
            if ($end->greaterThan($start)) {
                $totalHours += $start->diffInMinutes($end) / 60;
            }
        }

        $totalDamageCount = DamageReport::count();

        $facilityStats = [];

        foreach ($facilities as $fac) {
            $resCount = Reservation::where('facility_id', $fac->id)->count();
            $dmgCount = DamageReport::where('facility_id', $fac->id)->count();

            $facilityStats[] = [
                'id' => $fac->id,
                'name' => $fac->name,
                'location' => $fac->location,
                'total_bookings' => $resCount,
                'total_bookings_label' => "{$resCount} Kali",
                'occupancy_rate' => null,
                'occupancy_label' => 'Belum tersedia',
                'damage_count' => $dmgCount,
                'damage_label' => "{$dmgCount} Laporan",
            ];
        }

        return response()->json([
            'summary' => [
                'average_occupancy' => null,
                'occupancy_note' => 'Belum tersedia: jadwal operasional dan definisi perhitungan okupansi perlu ditetapkan.',
                'total_hours' => number_format($totalHours, 1, ',', '.') . ' Jam',
                'total_damages' => $totalDamageCount . ' Laporan',
            ],
            'facilities' => $facilityStats,
        ]);
    }

    /**
     * Export rekap data in CSV, Excel, or PDF format (US 17).
     */
    public function exportRekap(string $format): Response
    {
        $format = strtolower($format);
        if (!in_array($format, ['csv', 'excel'], true)) {
            abort(422, 'Format ekspor tidak didukung. Gunakan CSV atau Excel.');
        }

        $rekapData = $this->rekap()->getData(true);
        $facilities = $rekapData['facilities'];

        // CSV or Excel format
        $delimiter = ($format === 'excel') ? "\t" : ",";
        $ext = ($format === 'excel') ? 'xls' : 'csv';

        $output = "Fasilitas{$delimiter}Lokasi{$delimiter}Total Peminjaman{$delimiter}Tingkat Okupansi{$delimiter}Frekuensi Kerusakan\n";
        foreach ($facilities as $fac) {
            $output .= "\"{$fac['name']}\"{$delimiter}\"{$fac['location']}\"{$delimiter}\"{$fac['total_bookings_label']}\"{$delimiter}\"{$fac['occupancy_label']}\"{$delimiter}\"{$fac['damage_label']}\"\n";
        }

        return response($output, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"rekapitulasi_fasilitas_diserve.{$ext}\"",
        ]);
    }
}
