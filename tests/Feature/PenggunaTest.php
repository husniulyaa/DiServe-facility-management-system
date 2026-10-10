<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PenggunaTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private User $userB;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-09 10:00:00', 'Asia/Jakarta'));

        $this->userA = User::create([
            'name' => 'User A',
            'identity_number' => '11111111',
            'email' => 'userA@students.undip.ac.id',
            'password' => bcrypt('Password123!'),
            'role' => 'pengguna',
            'status' => 'aktif',
        ]);

        $this->userB = User::create([
            'name' => 'User B',
            'identity_number' => '22222222',
            'email' => 'userB@students.undip.ac.id',
            'password' => bcrypt('Password123!'),
            'role' => 'pengguna',
            'status' => 'aktif',
        ]);

        $this->facility = Facility::create([
            'name' => 'Muladi Dome',
            'slug' => 'muladi-dome',
            'category' => 'Gedung/Aula',
            'location' => 'Lainnya',
            'capacity' => 5000,
            'status' => 'active',
        ]);
    }

    public function test_us3_user_can_create_reservation_with_conflict_and_maintenance_checks(): void
    {
        $token = $this->userA->createToken('token')->plainTextToken;

        $targetDate = now()->addDays(5)->format('Y-m-d');

        // Valid reservation
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/reservations', [
                'facility' => $this->facility->id,
                'start_date' => $targetDate,
                'end_date' => $targetDate,
                'start_time' => '08:00',
                'end_time' => '11:00',
                'purpose' => 'Seminar Teknologi Informasi Kampus',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reservations', [
            'user_id' => $this->userA->id,
            'facility_id' => $this->facility->id,
            'status' => 'pending',
        ]);
    }

    public function test_reservation_upload_is_saved_and_downloadable_by_its_owner(): void
    {
        Storage::fake('local');
        $targetDate = now()->addDays(5)->format('Y-m-d');

        $response = $this->actingAs($this->userA, 'sanctum')
            ->post('/api/reservations', [
                'facility' => $this->facility->id,
                'start_date' => $targetDate,
                'end_date' => $targetDate,
                'start_time' => '08:00',
                'end_time' => '09:00',
                'purpose' => 'Dokumen reservasi untuk pengujian.',
                'supporting_file' => UploadedFile::fake()->create('supporting.pdf', 128, 'application/pdf'),
            ]);

        $response->assertCreated();
        $reservation = Reservation::query()->where('user_id', $this->userA->id)->firstOrFail();
        $this->assertStringStartsWith('reservations/', $reservation->supporting_file);
        Storage::disk('local')->assertExists($reservation->supporting_file);

        $this->actingAs($this->userA, 'sanctum')
            ->get("/api/reservations/{$reservation->id}/supporting-file")
            ->assertDownload(basename($reservation->supporting_file));
    }

    public function test_us3_conflict_check_rejects_overlapping_reservation_if_already_approved(): void
    {
        $targetDate = now()->addDays(5)->format('Y-m-d');

        // Existing approved reservation: 08:00 - 12:00
        Reservation::create([
            'user_id' => $this->userB->id,
            'facility_id' => $this->facility->id,
            'start_date' => $targetDate,
            'end_date' => $targetDate,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'start_at' => Carbon::parse("{$targetDate} 08:00:00"),
            'end_at' => Carbon::parse("{$targetDate} 12:00:00"),
            'purpose' => 'Kegiatan Resmi',
            'status' => 'approved',
        ]);

        $token = $this->userA->createToken('token')->plainTextToken;

        // User A tries to reserve overlapping: 10:00 - 14:00 (overlaps from 10:00 to 12:00)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/reservations', [
                'facility' => $this->facility->id,
                'start_date' => $targetDate,
                'end_date' => $targetDate,
                'start_time' => '10:00',
                'end_time' => '14:00',
                'purpose' => 'Kegiatan Bentrok',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Fasilitas sudah memiliki reservasi yang disetujui pada rentang waktu tersebut. Silakan pilih waktu atau fasilitas lain.',
            ]);
    }

    public function test_us3_cannot_reserve_facility_under_maintenance(): void
    {
        $maintFac = Facility::create([
            'name' => 'Auditorium Perbaikan',
            'slug' => 'auditorium-perbaikan',
            'category' => 'Gedung/Aula',
            'location' => 'Lainnya',
            'capacity' => 500,
            'status' => 'maintenance',
        ]);

        $token = $this->userA->createToken('token')->plainTextToken;
        $targetDate = now()->addDays(5)->format('Y-m-d');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/reservations', [
                'facility' => $maintFac->id,
                'start_date' => $targetDate,
                'end_date' => $targetDate,
                'start_time' => '08:00',
                'end_time' => '10:00',
                'purpose' => 'Mencoba reservasi gedung maintenance',
            ]);

        $response->assertStatus(422);
    }

    public function test_us4_user_can_cancel_own_reservation(): void
    {
        $targetDate = now()->addDays(5)->format('Y-m-d');

        $resA = Reservation::create([
            'user_id' => $this->userA->id,
            'facility_id' => $this->facility->id,
            'start_date' => $targetDate,
            'end_date' => $targetDate,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'start_at' => Carbon::parse("{$targetDate} 08:00:00"),
            'end_at' => Carbon::parse("{$targetDate} 10:00:00"),
            'purpose' => 'Acara User A',
            'status' => 'pending',
            'cancellation_deadline' => Carbon::parse("{$targetDate} 08:00:00")->subDay(),
        ]);

        $tokenA = $this->userA->createToken('tokenA')->plainTextToken;

        $cancelResponse = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson("/api/reservations/{$resA->id}/cancel");

        $cancelResponse->assertStatus(200);
        $this->assertEquals('cancelled', $resA->fresh()->status);
    }

    public function test_us4_idor_protection_prevents_user_from_cancelling_others_reservation(): void
    {
        $targetDate = now()->addDays(5)->format('Y-m-d');

        $resA = Reservation::create([
            'user_id' => $this->userA->id,
            'facility_id' => $this->facility->id,
            'start_date' => $targetDate,
            'end_date' => $targetDate,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'start_at' => Carbon::parse("{$targetDate} 08:00:00"),
            'end_at' => Carbon::parse("{$targetDate} 10:00:00"),
            'purpose' => 'Acara User A',
            'status' => 'pending',
            'cancellation_deadline' => Carbon::parse("{$targetDate} 08:00:00")->subDay(),
        ]);

        $tokenB = $this->userB->createToken('tokenB')->plainTextToken;

        $attackResponse = $this->withHeader('Authorization', "Bearer {$tokenB}")
            ->postJson("/api/reservations/{$resA->id}/cancel");

        $attackResponse->assertStatus(403);
        $this->assertEquals('pending', $resA->fresh()->status);
    }

    public function test_us5_user_history_scopes_strictly_to_authenticated_user(): void
    {
        $targetDate = now()->addDays(2)->format('Y-m-d');

        Reservation::create([
            'user_id' => $this->userA->id,
            'facility_id' => $this->facility->id,
            'start_date' => $targetDate,
            'end_date' => $targetDate,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'start_at' => Carbon::parse("{$targetDate} 08:00:00"),
            'end_at' => Carbon::parse("{$targetDate} 10:00:00"),
            'purpose' => 'Reservasi Milik User A',
            'status' => 'pending',
        ]);

        Reservation::create([
            'user_id' => $this->userB->id,
            'facility_id' => $this->facility->id,
            'start_date' => $targetDate,
            'end_date' => $targetDate,
            'start_time' => '13:00',
            'end_time' => '15:00',
            'start_at' => Carbon::parse("{$targetDate} 13:00:00"),
            'end_at' => Carbon::parse("{$targetDate} 15:00:00"),
            'purpose' => 'Reservasi Milik User B',
            'status' => 'approved',
        ]);

        $tokenA = $this->userA->createToken('tokenA')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson('/api/user/reservations');

        $response->assertStatus(200)
            ->assertJsonFragment(['purpose' => 'Reservasi Milik User A'])
            ->assertJsonMissing(['purpose' => 'Reservasi Milik User B']);
    }

    public function test_user_reservation_history_includes_rejection_and_cancellation_reasons(): void
    {
        $targetDate = now()->addDays(5)->format('Y-m-d');
        Reservation::create([
            'user_id' => $this->userA->id,
            'facility_id' => $this->facility->id,
            'start_date' => $targetDate,
            'end_date' => $targetDate,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'purpose' => 'Permohonan dengan alasan penolakan.',
            'status' => 'rejected',
            'rejection_reason' => 'Fasilitas sedang dalam perbaikan.',
        ]);
        Reservation::create([
            'user_id' => $this->userA->id,
            'facility_id' => $this->facility->id,
            'start_date' => $targetDate,
            'end_date' => $targetDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'purpose' => 'Permohonan dengan alasan pembatalan.',
            'status' => 'cancelled',
            'cancellation_reason' => 'Jadwal kegiatan berubah.',
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/user/reservations')
            ->assertOk()
            ->assertJsonFragment([
                'status_class' => 'rejected',
                'rejection_reason' => 'Fasilitas sedang dalam perbaikan.',
            ])
            ->assertJsonFragment([
                'status_class' => 'cancelled',
                'cancellation_reason' => 'Jadwal kegiatan berubah.',
            ]);
    }

    public function test_us6_and_us7_create_and_view_damage_reports(): void
    {
        Storage::fake('public');

        $tokenA = $this->userA->createToken('tokenA')->plainTextToken;
        $photo = UploadedFile::fake()->create('damage.jpg', 100, 'image/jpeg');

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/reports', [
                'facility' => $this->facility->id,
                'category' => 'kelistrikan',
                'location_detail' => 'Ruang 203 Lantai 2',
                'description' => 'Terjadi korsleting pada stop kontak meja praktikum nomor 4.',
                'photo' => $photo,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('damage_reports', [
            'user_id' => $this->userA->id,
            'facility_id' => $this->facility->id,
            'status' => 'baru',
        ]);

        // User A views report status
        $viewResponse = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson('/api/user/reports');

        $viewResponse->assertStatus(200)
            ->assertJsonFragment(['location' => 'Ruang 203 Lantai 2'])
            ->assertJsonFragment(['status' => 'Baru']);
    }
}
