<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pengguna;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Ira Kusumadewi',
            'identity_number' => '198501012010122001',
            'email' => 'admin@admin.undip.ac.id',
            'password' => bcrypt('Password123!'),
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        $this->pengguna = User::create([
            'name' => 'Husni Ulyaa',
            'identity_number' => '24060124120021',
            'email' => 'husni@students.undip.ac.id',
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

    public function test_us13_and_us14_admin_can_create_petugas_and_pengguna_accounts(): void
    {
        $token = $this->admin->createToken('admin')->plainTextToken;

        // 1. Admin creates Petugas account (US 13)
        $resPetugas = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/users', [
                'name' => 'Petugas Baru',
                'identity_number' => '199901012022011001',
                'email' => 'petugasbaru@facility.undip.ac.id',
                'password' => 'PetugasPassword123!',
                'password_confirmation' => 'PetugasPassword123!',
            ]);
        $resPetugas->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'petugasbaru@facility.undip.ac.id',
            'role' => 'petugas',
            'status' => 'aktif',
        ]);

        // 2. Admin creates Dosen account (US 14)
        $resDosen = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/users', [
                'name' => 'Dr. Budi Dosen',
                'identity_number' => '197505122001121002',
                'email' => 'budi@lectures.undip.ac.id',
                'password' => 'DosenPassword123!',
                'password_confirmation' => 'DosenPassword123!',
            ]);
        $resDosen->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'budi@lectures.undip.ac.id',
            'role' => 'pengguna',
            'status' => 'aktif',
        ]);
    }

    public function test_us15_admin_approves_rejects_and_toggles_user_access(): void
    {
        $pendingUser = User::create([
            'name' => 'Pendaftar Baru',
            'identity_number' => '24060124199999',
            'email' => 'pendaftar@students.undip.ac.id',
            'password' => bcrypt('Password123!'),
            'role' => 'pengguna',
            'status' => 'pending',
        ]);

        $token = $this->admin->createToken('admin')->plainTextToken;

        // Admin verifies account
        $resVerify = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/users/{$pendingUser->id}/verify");
        $resVerify->assertStatus(200);
        $this->assertEquals('aktif', $pendingUser->fresh()->status);

        // Now user can log in!
        $loginRes = $this->postJson('/api/auth/login', [
            'email' => 'pendaftar@students.undip.ac.id',
            'password' => 'Password123!',
        ]);
        $loginRes->assertStatus(200);

        // Admin revokes access
        $resRevoke = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/users/{$pendingUser->id}/toggle-status");
        $resRevoke->assertStatus(200);
        $this->assertEquals('nonaktif', $pendingUser->fresh()->status);

        // Now user cannot log in
        $loginBlocked = $this->postJson('/api/auth/login', [
            'email' => 'pendaftar@students.undip.ac.id',
            'password' => 'Password123!',
        ]);
        $loginBlocked->assertStatus(403);
    }

    public function test_us16_admin_manages_facilities_crud_and_soft_deactivates_if_history_exists(): void
    {
        $token = $this->admin->createToken('admin')->plainTextToken;

        // Add facility
        $resAdd = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/facilities', [
                'name' => 'Ruang Teater FSM',
                'category' => 'Ruang Seminar',
                'location' => 'FSM',
                'capacity' => 150,
                'address' => 'Gedung E Lantai 3',
            ]);
        $resAdd->assertStatus(201);
        $newFacId = $resAdd->json('facility.id');

        // Edit facility
        $resEdit = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/facilities/{$newFacId}", [
                'name' => 'Ruang Teater FSM Updated',
                'category' => 'Ruang Seminar',
                'location' => 'FSM',
                'capacity' => 200,
                'address' => 'Gedung E Lantai 3',
            ]);
        $resEdit->assertStatus(200);
        $this->assertEquals(200, Facility::find($newFacId)->capacity);

        // Facility without history can be hard deleted
        $resDel = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/admin/facilities/{$newFacId}");
        $resDel->assertStatus(200);
        $this->assertNull(Facility::find($newFacId));

        // Facility WITH history is NOT hard deleted, but marked as inactive
        Reservation::create([
            'user_id' => $this->pengguna->id,
            'facility_id' => $this->facility->id,
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(1)->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'start_at' => now()->addDays(1),
            'end_at' => now()->addDays(1)->addHours(2),
            'purpose' => 'History check',
            'status' => 'approved',
        ]);

        $resSoftDel = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/admin/facilities/{$this->facility->id}");
        $resSoftDel->assertStatus(200);

        // Ensure facility still exists in DB but with status 'inactive'
        $this->assertNotNull($this->facility->fresh());
        $this->assertEquals('inactive', $this->facility->fresh()->status);
    }

    public function test_us17_admin_can_view_rekap_and_export_reports(): void
    {
        $token = $this->admin->createToken('admin')->plainTextToken;

        // View rekap summary
        $rekapRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/rekap');

        $rekapRes->assertStatus(200)
            ->assertJsonStructure([
                'summary' => ['average_occupancy', 'total_hours', 'total_damages'],
                'facilities' => [
                    '*' => ['name', 'location', 'total_bookings_label', 'occupancy_label', 'damage_label'],
                ],
            ]);

        // Export CSV
        $exportCsv = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/admin/export/csv');
        $exportCsv->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // Export Excel
        $exportXls = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/admin/export/excel');
        $exportXls->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // Export PDF
        $exportPdf = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/admin/export/pdf');
        $exportPdf->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="rekapitulasi_fasilitas_diserve.pdf"');
        $this->assertStringStartsWith('%PDF-1.4', $exportPdf->getContent());
        $this->assertStringEndsWith('%%EOF', $exportPdf->getContent());
        $this->assertStringContainsString('Total Jam Peminjaman', $exportPdf->getContent());
        $this->assertStringContainsString('Muladi Dome', $exportPdf->getContent());
    }

    public function test_security_pengguna_cannot_access_admin_endpoints(): void
    {
        $tokenPengguna = $this->pengguna->createToken('pengguna')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$tokenPengguna}")
            ->getJson('/api/admin/users');

        $response->assertStatus(403);
    }
}
