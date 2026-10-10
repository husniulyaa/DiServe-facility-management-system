<?php

namespace App\Http\Controllers;

use App\Models\DamageReport;
use App\Models\Facility;
use App\Support\SubmissionWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DamageReportController extends Controller
{
    /**
     * User's damage reports list (US 7).
     * Strictly scopes to the authenticated user.
     */
    public function myReports(Request $request): JsonResponse
    {
        $user = $request->user();

        $reports = DamageReport::with('facility')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $reports->map(function ($rep) {
            $statusClass = match ($rep->status) {
                'baru' => 'new',
                'diproses' => 'processing',
                'selesai' => 'completed',
                'ditolak' => 'rejected',
                default => $rep->status,
            };

            $statusLabel = match ($rep->status) {
                'baru' => 'Baru',
                'diproses' => 'Diproses',
                'selesai' => 'Selesai',
                'ditolak' => 'Ditolak',
                default => ucfirst($rep->status),
            };

            return [
                'id' => $rep->id,
                'facility' => $rep->facility ? $rep->facility->name : 'Fasilitas Tidak Diketahui',
                'category' => $rep->category,
                'location' => $rep->location_detail,
                'date' => $rep->created_at ? $rep->created_at->translatedFormat('d M Y') : '-',
                'submitted' => $rep->created_at ? $rep->created_at->translatedFormat('d F Y, H:i') . ' WIB' : '-',
                'status' => $statusLabel,
                'statusClass' => $statusClass,
                'description' => $rep->description,
                'photo' => $rep->photo ? basename($rep->photo) : 'Tidak ada foto',
                'photo_url' => $rep->photo ? "/api/reports/{$rep->id}/photo" : null,
                'note' => $rep->resolution_note ?: ($rep->status === 'baru' ? 'Laporan telah diterima dan menunggu pemeriksaan petugas.' : ($rep->status === 'diproses' ? 'Petugas sedang melakukan pengecekan perangkat.' : ($rep->reject_reason ?: '-'))),
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Create a new damage report (US 6).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!SubmissionWindow::isOpen()) {
            return response()->json([
                'message' => 'Pengajuan laporan hanya dapat dikirim pukul 07.00–20.00 WIB.',
                'errors' => ['submission_time' => ['Waktu pengajuan berada di luar jam layanan.']],
            ], 422);
        }

        if (!$request->has('facility') && $request->has('facility_id')) {
            $request->merge(['facility' => $request->facility_id]);
        }

        $request->validate([
            'facility' => 'required',
            'category' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $valid = collect(DamageReport::CATEGORIES)
                        ->contains(fn ($category) => strcasecmp($category, (string) $value) === 0);

                    if (!$valid) {
                        $fail('Kategori masalah yang dipilih tidak valid.');
                    }
                },
            ],
            'location_detail' => 'required|string|max:255',
            'description' => 'required|string|min:10|max:2000',
            'photo' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
        ], [
            'facility.required' => 'Fasilitas wajib dipilih.',
            'category.required' => 'Kategori masalah wajib dipilih.',
            'location_detail.required' => 'Lokasi detail wajib diisi.',
            'description.required' => 'Deskripsi masalah wajib diisi.',
            'description.min' => 'Deskripsi masalah harus dijelaskan dengan lebih detail (minimal 10 karakter).',
            'photo.mimes' => 'Format foto harus JPG, PNG, atau WEBP.',
            'photo.max' => 'Ukuran foto maksimal 5 MB.',
        ]);

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

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('reports', 'local');
        }

        $category = collect(DamageReport::CATEGORIES)
            ->first(fn ($item) => strcasecmp($item, (string) $request->category) === 0);

        $report = DamageReport::create([
            'user_id' => $user->id,
            'facility_id' => $facility->id,
            'category' => $category,
            'location_detail' => $request->location_detail,
            'description' => $request->description,
            'photo' => $photoPath,
            'status' => 'baru',
        ]);

        return response()->json([
            'message' => 'Laporan kerusakan fasilitas berhasil dikirim.',
            'report' => [
                'id' => $report->id,
                'facility' => $facility->name,
                'category' => $report->category,
                'location' => $report->location_detail,
                'date' => $report->created_at->translatedFormat('d F Y'),
                'submitted' => $report->created_at->translatedFormat('d F Y, H:i') . ' WIB',
                'description' => $report->description,
                'photo' => $photoPath ? basename($photoPath) : 'Tidak ada foto',
                'status' => 'Baru',
                'status_class' => 'new',
            ],
        ], 201);
    }

    public function photo(Request $request, $id)
    {
        $report = DamageReport::findOrFail($id);
        $user = $request->user();

        if ((int) $report->user_id !== (int) $user->id && !$user->isPetugas()) {
            return response()->json(['message' => 'Anda tidak memiliki izin untuk mengakses foto ini.'], 403);
        }

        if (!$report->photo) {
            return response()->json(['message' => 'Laporan ini tidak memiliki foto.'], 404);
        }

        $disk = Storage::disk('local')->exists($report->photo) ? 'local' : 'public';
        abort_unless(Storage::disk($disk)->exists($report->photo), 404);

        return Storage::disk($disk)->download($report->photo, basename($report->photo));
    }

    /**
     * List all reports for Petugas / Admin (US 8).
     */
    public function index(Request $request): JsonResponse
    {
        $query = DamageReport::with(['facility', 'user'])->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $reports = $query->get();

        $data = $reports->map(function ($rep) {
            $statusText = match ($rep->status) {
                'baru' => 'Baru',
                'diproses' => 'Diproses',
                'selesai' => 'Selesai',
                'ditolak' => 'Ditolak',
                default => ucfirst($rep->status),
            };

            return [
                'id' => $rep->id,
                'row_id' => 'row-damage-' . $rep->id,
                'name' => $rep->user ? $rep->user->name : 'Pelapor',
                'category' => strtoupper($rep->category),
                'facility' => $rep->facility ? $rep->facility->name : '-',
                'location' => $rep->location_detail,
                'description' => $rep->description,
                'photo' => $rep->photo ? basename($rep->photo) : 'Tidak ada foto',
                'photo_url' => $rep->photo ? "/api/reports/{$rep->id}/photo" : null,
                'status' => $statusText,
                'status_class' => $rep->status,
                'resolution' => $rep->resolution_note ?? '',
                'rejectReason' => $rep->reject_reason ?? '',
                'date' => $rep->created_at ? $rep->created_at->translatedFormat('d M Y') : '-',
                'submitted' => $rep->created_at ? $rep->created_at->translatedFormat('d F Y, H:i') . ' WIB' : '-',
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Update damage report status (US 11 - Petugas/Admin).
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $report = DamageReport::findOrFail($id);

        $request->validate([
            'status' => 'required|in:baru,diproses,selesai,ditolak',
            'resolution_note' => 'required_if:status,selesai|nullable|string',
            'reject_reason' => 'required_if:status,ditolak|nullable|string',
        ], [
            'resolution_note.required_if' => 'Catatan resolusi teknis wajib diisi sebelum menutup laporan!',
            'reject_reason.required_if' => 'Alasan penolakan laporan wajib diisi!',
        ]);

        $updateData = ['status' => $request->status];

        if ($request->status === 'selesai') {
            $updateData['resolution_note'] = $request->resolution_note;
        } elseif ($request->status === 'ditolak') {
            $updateData['reject_reason'] = $request->reject_reason;
        }

        $report->update($updateData);

        return response()->json([
            'message' => 'Status laporan kerusakan berhasil diperbarui.',
            'report' => $report,
        ]);
    }
}
