<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\Reservation;
use App\Support\SubmissionWindow;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReservationController extends Controller
{
    /**
     * User's reservation history (US 5).
     * Strictly scopes to the authenticated user.
     */
    public function myReservations(Request $request): JsonResponse
    {
        Reservation::expirePassedPendingReservations();
        $user = $request->user();

        $reservations = Reservation::with('facility')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $reservations->map(function ($res) {
            $rawStatus = strtolower($res->status);
            $statusLabel = match ($rawStatus) {
                'pending', 'menunggu' => 'Menunggu Persetujuan',
                'approved', 'disetujui' => 'Disetujui',
                'rejected', 'ditolak' => 'Ditolak',
                'cancelled', 'dibatalkan' => 'Dibatalkan',
                default => ucfirst($res->status),
            };

            $statusClass = match ($rawStatus) {
                'pending', 'menunggu' => 'pending',
                'approved', 'disetujui' => 'approved',
                'rejected', 'ditolak' => 'rejected',
                'cancelled', 'dibatalkan' => 'cancelled',
                default => $rawStatus,
            };

            $startDate = $res->start_date ?: ($res->reservation_date ?: now());
            $endDate = $res->end_date ?: $startDate;
            $dateFormatted = $startDate->equalTo($endDate)
                ? $startDate->translatedFormat('d F Y')
                : $startDate->translatedFormat('d F Y') . ' - ' . $endDate->translatedFormat('d F Y');

            return [
                'id' => $res->id,
                'facility_id' => $res->facility_id,
                'facility' => $res->facility ? $res->facility->name : 'Fasilitas Tidak Diketahui',
                'facility_category' => $res->facility ? ($res->facility->type ?? $res->facility->category) : '',
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'date' => $dateFormatted,
                'time' => "{$res->start_time} - {$res->end_time}",
                'purpose' => $res->purpose,
                'status' => $statusLabel,
                'status_class' => $statusClass,
                'file' => $res->supporting_file ? basename($res->supporting_file) : 'Tidak ada berkas',
                'file_url' => $res->supporting_file ? Storage::url($res->supporting_file) : null,
                'submitted' => $res->created_at ? $res->created_at->translatedFormat('d F Y, H:i') : '-',
                'cancellation_deadline' => ($res->cancellation_deadline ?: ($res->start_at ? $res->start_at->copy()->subDay() : null))?->toISOString(),
                'rejection_reason' => $res->rejection_reason,
                'cancellation_reason' => $res->cancellation_reason,
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Create a new reservation (US 3).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!SubmissionWindow::isOpen()) {
            return response()->json([
                'message' => 'Pengajuan reservasi hanya dapat dikirim pukul 07.00–20.00 WIB.',
                'errors' => ['submission_time' => ['Waktu pengajuan berada di luar jam layanan.']],
            ], 422);
        }

        if (!$request->has('facility') && $request->has('facility_id')) {
            $request->merge(['facility' => $request->facility_id]);
        }

        $request->validate([
            'facility' => 'required',
            'start_date' => 'required|date|after:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'purpose' => 'required|string|min:5|max:1000',
            'supporting_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ], [
            'facility.required' => 'Fasilitas wajib dipilih.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.after' => 'Tanggal mulai minimal adalah besok.',
            'end_date.required' => 'Tanggal selesai wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
            'start_time.required' => 'Jam mulai wajib dipilih.',
            'start_time.date_format' => 'Format jam mulai tidak valid.',
            'end_time.required' => 'Jam selesai wajib dipilih.',
            'end_time.date_format' => 'Format jam selesai tidak valid.',
            'purpose.required' => 'Keperluan penggunaan fasilitas wajib diisi.',
            'purpose.min' => 'Keperluan harus diisi dengan jelas (minimal 5 karakter).',
            'supporting_file.mimes' => 'Format berkas pendukung harus PDF, Word, atau Gambar (JPG, PNG).',
            'supporting_file.max' => 'Ukuran berkas pendukung maksimal 10 MB.',
        ]);

        // Find facility by ID, slug, or name
        $facilityInput = (string) $request->facility;
        $facility = is_numeric($facilityInput)
            ? Facility::find($facilityInput)
            : Facility::where('slug', $facilityInput)
                ->orWhereRaw('LOWER(name) = ?', [strtolower($facilityInput)])
                ->first();

        if (!$facility) {
            return response()->json([
                'message' => 'Fasilitas yang dipilih tidak ditemukan.',
            ], 404);
        }

        // Validate status: facility must be active
        if ($facility->isMaintenance()) {
            return response()->json([
                'message' => "Reservasi Gagal! Fasilitas \"{$facility->name}\" saat ini sedang dalam perbaikan dan tidak tersedia untuk reservasi.",
            ], 422);
        }

        if ($facility->isInactive()) {
            return response()->json([
                'message' => "Fasilitas \"{$facility->name}\" sedang dinonaktifkan.",
            ], 422);
        }

        // Format start_at and end_at
        $startAtStr = "{$request->start_date} {$request->start_time}:00";
        $endAtStr = "{$request->end_date} {$request->end_time}:00";
        $startAt = Carbon::parse($startAtStr);
        $endAt = Carbon::parse($endAtStr);

        $startMinutes = ((int) substr($request->start_time, 0, 2) * 60) + (int) substr($request->start_time, 3, 2);
        $endMinutes = ((int) substr($request->end_time, 0, 2) * 60) + (int) substr($request->end_time, 3, 2);
        if (
            $startMinutes < 360 || $startMinutes >= 1380
            || $endMinutes < 360 || $endMinutes > 1380
            || $startMinutes % 30 !== 0 || $endMinutes % 30 !== 0
        ) {
            return response()->json([
                'message' => 'Waktu pemakaian harus berada pada slot 30 menit antara pukul 06.00 dan 23.00 WIB.',
                'errors' => ['start_time' => ['Waktu pemakaian di luar slot operasional.']],
            ], 422);
        }

        // Validation: start_at < end_at
        if ($endAt->lessThanOrEqualTo($startAt)) {
            return response()->json([
                'message' => 'Waktu selesai harus lebih dari waktu mulai.',
                'errors' => [
                    'end_time' => ['Waktu selesai harus lebih dari waktu mulai.'],
                ],
            ], 422);
        }

        // Conflict check:
        // Conflict check:
        // existing.start_at < new.end_at AND existing.end_at > new.start_at
        $conflict = Reservation::where('facility_id', $facility->id)
            ->whereIn('status', ['approved', 'disetujui'])
            ->where(function ($q) use ($startAt, $endAt) {
                $q->where('start_at', '<', $endAt)
                  ->where('end_at', '>', $startAt);
            })
            ->exists();

        if ($conflict) {
            return response()->json([
                'message' => 'Fasilitas sudah memiliki reservasi yang disetujui pada rentang waktu tersebut. Silakan pilih waktu atau fasilitas lain.',
            ], 422);
        }

        // File upload
        $filePath = null;
        if ($request->hasFile('supporting_file')) {
            $filePath = $request->file('supporting_file')->store('reservations', 'public');
        }

        // Cancellation deadline: 24 hours prior to start_at
        $cancellationDeadline = $startAt->copy()->subDay();

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'facility_id' => $facility->id,
            'reservation_date' => $request->start_date,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'purpose' => $request->purpose,
            'supporting_file' => $filePath,
            'status' => 'pending',
            'cancellation_deadline' => $cancellationDeadline,
        ]);

        return response()->json([
            'message' => 'Pengajuan reservasi berhasil dikirim dan menunggu persetujuan.',
            'reservation' => [
                'id' => $reservation->id,
                'facility' => $facility->name,
                'date' => $reservation->start_date->translatedFormat('d F Y'),
                'time' => "{$reservation->start_time} - {$reservation->end_time}",
                'submitted' => $reservation->created_at->translatedFormat('d F Y, H:i') . ' WIB',
                'purpose' => $reservation->purpose,
                'file' => $filePath ? basename($filePath) : 'Tidak ada berkas',
                'status' => 'Menunggu',
                'status_class' => 'pending',
            ],
        ], 201);
    }

    /**
     * Cancel own reservation (US 4).
     */
    public function cancel(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $reservation = Reservation::findOrFail($id);

        // Ownership verification (IDOR protection)
        if ((int) $reservation->user_id !== (int) $user->id && !in_array($user->role, ['petugas', 'admin'])) {
            return response()->json([
                'message' => 'Akses ditolak. Anda hanya dapat membatalkan reservasi milik Anda sendiri.',
            ], 403);
        }

        if (in_array(strtolower($reservation->status), ['cancelled', 'dibatalkan'])) {
            return response()->json([
                'message' => 'Reservasi sudah dalam status dibatalkan.',
            ], 422);
        }

        if (!in_array(strtolower($reservation->status), ['pending', 'menunggu', 'approved', 'disetujui'])) {
            return response()->json([
                'message' => 'Reservasi ini tidak dapat dibatalkan.',
            ], 422);
        }

        // Check cancellation deadline for users
        $cancellationDeadline = $reservation->cancellation_deadline ?: ($reservation->start_at ? $reservation->start_at->copy()->subDay() : null);
        if ($user->role === 'pengguna' && $cancellationDeadline && now()->isAfter($cancellationDeadline)) {
            return response()->json([
                'message' => 'Batas waktu pembatalan telah terlewat.',
            ], 422);
        }

        $reservation->update([
            'status' => 'cancelled',
            'cancelled_by' => in_array($user->role, ['petugas', 'admin']) ? $user->role : 'user',
            'cancellation_reason' => $request->input('reason', 'Dibatalkan oleh pemohon.'),
        ]);

        return response()->json([
            'message' => 'Reservasi berhasil dibatalkan.',
            'reservation' => $reservation,
        ]);
    }

    /**
     * List all reservations queue for Petugas / Admin (US 8).
     */
    public function queue(Request $request): JsonResponse
    {
        Reservation::expirePassedPendingReservations();
        $query = Reservation::with(['facility', 'user'])->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $reservations = $query->get();

        $data = $reservations->map(function ($res) {
            $startDate = $res->start_date ?: ($res->reservation_date ?: now());
            $endDate = $res->end_date ?: $startDate;
            $dateRange = $startDate->translatedFormat('d M Y') . ($startDate->notEqualTo($endDate) ? ' - ' . $endDate->translatedFormat('d M Y') : '');

            $rawStatus = strtolower($res->status);
            $statusText = match ($rawStatus) {
                'pending', 'menunggu' => 'Menunggu',
                'approved', 'disetujui' => 'Disetujui',
                'rejected', 'ditolak' => 'Ditolak',
                'cancelled', 'dibatalkan' => 'Dibatalkan',
                default => ucfirst($res->status),
            };

            $statusClass = match ($rawStatus) {
                'pending', 'menunggu' => 'pending',
                'approved', 'disetujui' => 'approved',
                'rejected', 'ditolak' => 'rejected',
                'cancelled', 'dibatalkan' => 'cancelled',
                default => $rawStatus,
            };

            return [
                'id' => $res->id,
                'row_id' => 'row-queue-' . $res->id,
                'name' => $res->user ? $res->user->name : 'Pemohon',
                'nim' => $res->user ? $res->user->identity_number : '-',
                'role' => $res->user ? ucfirst($res->user->role) : 'Mahasiswa',
                'phone' => $res->user && $res->user->phone ? $res->user->phone : '-',
                'email' => $res->user ? $res->user->email : '-',
                'facility' => $res->facility ? $res->facility->name : '-',
                'dates' => $dateRange,
                'times' => "{$res->start_time} WIB - {$res->end_time} WIB",
                'purpose' => $res->purpose,
                'filename' => $res->supporting_file ? basename($res->supporting_file) : 'Tidak ada berkas',
                'file_url' => $res->supporting_file ? Storage::url($res->supporting_file) : null,
                'status' => $statusText,
                'status_class' => $statusClass,
                'rejection_reason' => $res->rejection_reason,
                'cancellation_reason' => $res->cancellation_reason,
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Approve reservation (US 9 - Petugas/Admin).
     * Atomic Database Transaction with double-conflict verification.
     */
    public function approve(Request $request, $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $reservation = Reservation::lockForUpdate()->findOrFail($id);

            // Check facility
            $facility = Facility::lockForUpdate()->find($reservation->facility_id);
            if (!$facility) {
                return response()->json([
                    'message' => 'Fasilitas tidak ditemukan.',
                ], 404);
            }

            if (!$facility->isActive()) {
                return response()->json([
                    'message' => "Gagal menyetujui! Fasilitas \"{$facility->name}\" sedang tidak aktif atau dalam pemeliharaan.",
                ], 422);
            }

            // Conflict check re-verification
            $conflict = Reservation::where('facility_id', $facility->id)
                ->where('id', '!=', $reservation->id)
                ->whereIn('status', ['approved', 'disetujui'])
                ->where('start_at', '<', $reservation->end_at)
                ->where('end_at', '>', $reservation->start_at)
                ->exists();

            if ($conflict) {
                return response()->json([
                    'message' => 'Terdapat bentrok jadwal dengan reservasi lain yang telah disetujui sebelumnya.',
                ], 422);
            }

            $reservation->update([
                'status' => 'approved',
            ]);

            return response()->json([
                'message' => 'Pengajuan reservasi berhasil disetujui!',
                'reservation' => $reservation,
            ]);
        });
    }

    /**
     * Reject reservation (US 9 - Petugas/Admin).
     */
    public function reject(Request $request, $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|min:3',
        ], [
            'reason.required' => 'Alasan penolakan wajib diisi!',
        ]);

        $reservation = Reservation::findOrFail($id);

        $reservation->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
        ]);

        return response()->json([
            'message' => "Pengajuan berhasil ditolak.\nAlasan: {$request->reason}",
            'reservation' => $reservation,
        ]);
    }

    /**
     * Urgent cancellation of approved reservation (US 10 - Petugas/Admin).
     */
    public function emergencyCancel(Request $request, $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|min:3',
        ], [
            'reason.required' => 'Alasan pembatalan darurat wajib diisi!',
        ]);

        $reservation = Reservation::findOrFail($id);

        $reservation->update([
            'status' => 'cancelled',
            'cancelled_by' => $request->user()->role,
            'cancellation_reason' => $request->reason,
        ]);

        return response()->json([
            'message' => "Pembatalan darurat berhasil dikirim.\nAlasan: {$request->reason}",
            'reservation' => $reservation,
        ]);
    }
}
