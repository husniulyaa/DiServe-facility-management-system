<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FacilityController extends Controller
{
    /**
     * Display a listing of the facilities (US 1, US 2).
     * Accessible by visitors without login.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Facility::query();

        // Admin can see all, public visitors see active & maintenance (and optionally inactive if specified)
        if (!$request->user() || !in_array($request->user()->role, ['admin', 'petugas'])) {
            // Visitors see facilities (active and maintenance)
            // Even maintenance facilities remain visible as required by US 12
        }

        // Search keyword (name, address, category, location)
        if ($request->filled('search')) {
            $keyword = strtolower($request->search);
            $query->where(function ($q) use ($keyword) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$keyword}%"])
                  ->orWhereRaw('LOWER(address) LIKE ?', ["%{$keyword}%"])
                  ->orWhereRaw('LOWER(category) LIKE ?', ["%{$keyword}%"])
                  ->orWhereRaw('LOWER(location) LIKE ?', ["%{$keyword}%"]);
            });
        }

        // Type / Category filter
        if ($request->filled('type') && $request->type !== '') {
            $query->whereRaw('LOWER(category) = ?', [strtolower($request->type)]);
        }

        // Location filter
        if ($request->filled('location') && $request->location !== '') {
            $query->whereRaw('LOWER(location) = ?', [strtolower($request->location)]);
        }

        // Capacity filter
        if ($request->filled('capacity') && $request->capacity !== '') {
            $cap = $request->capacity;
            if ($cap === '0-50') {
                $query->where('capacity', '<=', 50);
            } elseif ($cap === '51-100') {
                $query->whereBetween('capacity', [51, 100]);
            } elseif ($cap === '101-300') {
                $query->whereBetween('capacity', [101, 300]);
            } elseif ($cap === '301-800') {
                $query->whereBetween('capacity', [301, 800]);
            } elseif ($cap === '801-2000') {
                $query->whereBetween('capacity', [801, 2000]);
            } elseif ($cap === '2001+') {
                $query->where('capacity', '>=', 2001);
            }
        }

        $facilities = $query->orderBy('name')->get();

        $data = $facilities->map(function ($fac) {
            $statusLabel = 'Tersedia';
            $statusClass = 'available';

            if ($fac->isMaintenance()) {
                $statusLabel = 'Dalam Perbaikan';
                $statusClass = 'maintenance';
            } elseif ($fac->isInactive()) {
                $statusLabel = 'Nonaktif';
                $statusClass = 'inactive';
            }

            $rawImg = $fac->image;
            $imageUrl = null;
            if ($rawImg) {
                if (str_starts_with($rawImg, 'http')) {
                    $imageUrl = $rawImg;
                } elseif (str_starts_with($rawImg, 'assets/')) {
                    $imageUrl = asset($rawImg);
                } else {
                    $imageUrl = asset('assets/images/' . $rawImg);
                }
            }

            return [
                'id' => $fac->id,
                'name' => $fac->name,
                'slug' => $fac->slug,
                'category' => $fac->category ?? $fac->type,
                'type' => $fac->type ?? $fac->category,
                'description' => $fac->description,
                'location' => $fac->location,
                'capacity' => $fac->capacity,
                'address' => $fac->address,
                'image' => $imageUrl,
                'image_name' => $rawImg ? basename($rawImg) : null,
                'status' => $fac->status,
                'availability_label' => $statusLabel,
                'availability_class' => $statusClass,
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Display a single facility (US 1, US 2).
     */
    public function show($id): JsonResponse
    {
        $decoded = urldecode($id);
        $facility = is_numeric($id)
            ? Facility::find($id)
            : Facility::where('slug', $id)
                ->orWhere('slug', Str::slug($decoded))
                ->orWhereRaw('LOWER(name) = ?', [strtolower($decoded)])
                ->first();

        if (!$facility) {
            return response()->json([
                'message' => 'Fasilitas tidak ditemukan.',
            ], 404);
        }

        $rawImg = $facility->image;
        $imageUrl = null;
        if ($rawImg) {
            if (str_starts_with($rawImg, 'http')) {
                $imageUrl = $rawImg;
            } elseif (str_starts_with($rawImg, 'assets/')) {
                $imageUrl = asset($rawImg);
            } else {
                $imageUrl = asset('assets/images/' . $rawImg);
            }
        }

        return response()->json([
            'data' => [
                'id' => $facility->id,
                'name' => $facility->name,
                'slug' => $facility->slug,
                'category' => $facility->category ?? $facility->type,
                'type' => $facility->type ?? $facility->category,
                'description' => $facility->description,
                'location' => $facility->location,
                'capacity' => $facility->capacity,
                'address' => $facility->address,
                'image' => $imageUrl,
                'image_name' => $rawImg ? basename($rawImg) : null,
                'status' => $facility->status,
                'availability_label' => $facility->isMaintenance() ? 'Dalam Perbaikan' : ($facility->isInactive() ? 'Nonaktif' : 'Tersedia'),
                'availability_class' => $facility->isMaintenance() ? 'maintenance' : ($facility->isInactive() ? 'inactive' : 'available'),
            ],
        ]);
    }

    /**
     * Get availability slots for a facility on a specific date (US 1).
     * Privacy: Unauthenticated visitors do NOT see applicant names or purpose!
     */
    public function availability(Request $request, $id): JsonResponse
    {
        $decoded = urldecode($id);
        $facility = is_numeric($id)
            ? Facility::find($id)
            : Facility::where('slug', $id)
                ->orWhere('slug', Str::slug($decoded))
                ->orWhereRaw('LOWER(name) = ?', [strtolower($decoded)])
                ->first();

        if (!$facility) {
            return response()->json([
                'message' => 'Fasilitas tidak ditemukan.',
            ], 404);
        }

        $date = $request->query('date', now()->format('Y-m-d'));
        if (!Carbon::hasFormat($date, 'Y-m-d') || Carbon::parse($date)->isBefore(now()->startOfDay())) {
            return response()->json(['message' => 'Tanggal ketersediaan tidak boleh sebelum hari ini.'], 422);
        }
        Reservation::expirePassedPendingReservations();

        // Generate standard time slots from 06:00 to 23:00 (30-min intervals)
        $slots = [];
        $startHour = 6;
        $endHour = 22;

        for ($h = $startHour; $h <= $endHour; $h++) {
            $hStr = str_pad($h, 2, '0', STR_PAD_LEFT);
            $slots[] = [
                'start' => "{$hStr}:00",
                'end' => "{$hStr}:30",
                'label' => "{$hStr}.00 - {$hStr}.30",
            ];
            $nextH = $h + 1;
            $nextHStr = str_pad($nextH, 2, '0', STR_PAD_LEFT);
            $slots[] = [
                'start' => "{$hStr}:30",
                'end' => "{$nextHStr}:00",
                'label' => "{$hStr}.30 - {$nextHStr}.00",
            ];
        }

        // Fetch active reservations on that date
        $targetDate = Carbon::parse($date)->format('Y-m-d');
        $reservations = Reservation::where('facility_id', $facility->id)
            ->whereIn('status', ['approved', 'disetujui', 'pending', 'menunggu'])
            ->where(function ($q) use ($targetDate) {
                $q->where(function ($sq) use ($targetDate) {
                    $sq->whereNotNull('start_date')
                       ->where('start_date', '<=', $targetDate)
                       ->where('end_date', '>=', $targetDate);
                })->orWhere('reservation_date', $targetDate);
            })
            ->get();

        $slotResults = [];
        $isMaintenance = $facility->isMaintenance();
        $isInactive = $facility->isInactive();

        foreach ($slots as $slot) {
            $slotStartDateTime = Carbon::parse("{$targetDate} {$slot['start']}:00");
            $slotEndDateTime = Carbon::parse("{$targetDate} {$slot['end']}:00");

            if ($isMaintenance || $isInactive) {
                $status = 'unavailable';
                $statusLabel = 'Tidak tersedia';
            } else {
                $status = 'available';
                $statusLabel = 'Tersedia';

                foreach ($reservations as $res) {
                    // Conflict check overlap
                    if ($res->start_at && $res->end_at && $res->start_at < $slotEndDateTime && $res->end_at > $slotStartDateTime) {
                        if ($res->isApproved()) {
                            $status = 'unavailable';
                            $statusLabel = 'Tidak tersedia';
                            break;
                        } elseif ($res->isPending()) {
                            $status = 'pending';
                            $statusLabel = 'Menunggu';
                        }
                    }
                }
            }

            $slotResults[] = [
                'time' => $slot['label'],
                'start' => $slot['start'],
                'end' => $slot['end'],
                'status' => $status,
                'status_label' => $statusLabel,
            ];
        }

        return response()->json([
            'facility' => [
                'id' => $facility->id,
                'name' => $facility->name,
                'category' => $facility->category,
                'location' => $facility->location,
                'capacity' => $facility->capacity,
                'status' => $facility->status,
            ],
            'date' => $date,
            'slots' => $slotResults,
        ]);
    }

    /**
     * Store new facility (US 16 - Admin).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'location' => 'required|string|max:100',
            'capacity' => 'required|integer|min:1',
            'address' => 'nullable|string|max:500',
            'image' => 'nullable',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('assets/images'), $filename);
            $imagePath = $filename;
        } elseif ($request->filled('image')) {
            $imagePath = $request->image;
        }

        $facility = Facility::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'category' => $request->category,
            'location' => $request->location,
            'capacity' => (int) $request->capacity,
            'address' => $request->address,
            'image' => $imagePath,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => "Fasilitas '{$facility->name}' berhasil ditambahkan.",
            'facility' => $facility,
        ], 201);
    }

    /**
     * Update facility (US 16 - Admin).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $facility = Facility::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'location' => 'required|string|max:100',
            'capacity' => 'required|integer|min:1',
            'address' => 'nullable|string|max:500',
            'image' => 'nullable',
        ]);

        $imagePath = $facility->image;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('assets/images'), $filename);
            $imagePath = $filename;
        }

        $facility->update([
            'name' => $request->name,
            'category' => $request->category,
            'location' => $request->location,
            'capacity' => (int) $request->capacity,
            'address' => $request->address,
            'image' => $imagePath,
        ]);

        return response()->json([
            'message' => "Data fasilitas '{$facility->name}' berhasil diperbarui.",
            'facility' => $facility,
        ]);
    }

    /**
     * Toggle status: active <-> inactive (Admin - US 16) or active <-> maintenance (Petugas - US 12).
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        $facility = Facility::findOrFail($id);
        $user = $request->user();

        // If target status is provided
        if ($request->filled('status')) {
            $newStatus = $request->status;
        } else {
            // Context toggle:
            if ($request->is('*maintenance*') || strtolower($user->role ?? '') === 'petugas') {
                // Petugas toggles maintenance <-> active (US 12)
                $newStatus = $facility->isMaintenance() ? 'active' : 'maintenance';
            } else {
                // Admin toggles active <-> inactive (US 16)
                $newStatus = $facility->isActive() ? 'inactive' : 'active';
            }
        }

        $facility->update(['status' => $newStatus]);

        return response()->json([
            'message' => "Status fasilitas '{$facility->name}' berhasil diubah menjadi '{$newStatus}'.",
            'facility' => $facility,
        ]);
    }

    /**
     * Soft deactivation / delete check (US 16).
     * Do NOT hard delete if facility has reservation or report history.
     */
    public function destroy($id): JsonResponse
    {
        $facility = Facility::findOrFail($id);

        $hasHistory = $facility->reservations()->exists() || $facility->reports()->exists();

        if ($hasHistory) {
            // Cannot hard delete due to existing history, set to inactive
            $facility->update(['status' => 'inactive']);

            return response()->json([
                'message' => "Fasilitas '{$facility->name}' memiliki riwayat data, status diubah menjadi nonaktif.",
                'facility' => $facility,
            ]);
        }

        $facility->delete();

        return response()->json([
            'message' => "Fasilitas '{$facility->name}' berhasil dihapus.",
        ]);
    }
}
