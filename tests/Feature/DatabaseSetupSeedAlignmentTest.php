<?php

namespace Tests\Feature;

use App\Models\DamageReport;
use App\Models\Facility;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSetupSeedAlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_github_seed_users_are_correctly_loaded_and_can_authenticate(): void
    {
        // Ira Kusumadewi (Admin)
        $admin = User::where('email', 'ira.admin@admin.undip.ac.id')->first();
        $this->assertNotNull($admin);
        $this->assertEquals('198001012005012001', $admin->identity_number);
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->isActive());

        // Seeded demo accounts use the single password configured by DatabaseSeeder.
        $loginRes = $this->postJson('/api/auth/login', [
            'email' => 'ira.admin@admin.undip.ac.id',
            'password' => 'Password123!',
        ]);
        $loginRes->assertStatus(200)->assertJsonPath('user.role', 'admin');

        // Arif Pratama (Petugas)
        $petugas = User::where('email', 'arifpratama35@facility.undip.ac.id')->first();
        $this->assertNotNull($petugas);
        $this->assertEquals('198803112014021004', $petugas->identity_number);
        $this->assertTrue($petugas->isPetugas());

        $loginPetugas = $this->postJson('/api/auth/login', [
            'email' => 'arifpratama35@facility.undip.ac.id',
            'password' => 'Password123!',
        ]);
        $loginPetugas->assertStatus(200)->assertJsonPath('user.role', 'petugas');

        // Nabila Kayla Rafa (Pending Pengguna)
        $nabila = User::where('email', 'nabilakay@students.undip.ac.id')->first();
        $this->assertNotNull($nabila);
        $this->assertEquals('24060124120022', $nabila->identity_number);
        $this->assertFalse($nabila->isActive());

        // Pending user cannot login
        $loginNabila = $this->postJson('/api/auth/login', [
            'email' => 'nabilakay@students.undip.ac.id',
            'password' => 'Password123!',
        ]);
        $loginNabila->assertStatus(403);
    }

    public function test_github_seed_facilities_match_blueprint_and_statuses(): void
    {
        $facilities = Facility::all();
        $this->assertCount(6, $facilities);

        $muladi = Facility::where('name', 'Muladi Dome')->first();
        $this->assertNotNull($muladi);
        $this->assertEquals(5000, $muladi->capacity);
        $this->assertEquals('Gedung/Aula', $muladi->type);
        $this->assertEquals('Gedung/Aula', $muladi->category);
        $this->assertTrue($muladi->isActive());

        $soedarto = Facility::where('name', 'Gedung Auditorium Prof. Soedarto, S.H.')->first();
        $this->assertNotNull($soedarto);
        $this->assertTrue($soedarto->isMaintenance());

        $terpadu = Facility::where('name', 'Gedung Laboratorium Terpadu')->first();
        $this->assertNotNull($terpadu);
        $this->assertTrue($terpadu->isInactive());
    }

    public function test_github_seed_reservations_are_accessible_and_bilingual_status_works(): void
    {
        $reservations = Reservation::all();
        $this->assertCount(4, $reservations);

        // Husni's reservation in waiting status
        $husniRes = Reservation::where('purpose', 'Pelatihan UI/UX Design')->first();
        $this->assertNotNull($husniRes);
        $this->assertTrue($husniRes->isPending());
        $this->assertEquals('2026-10-25', $husniRes->reservation_date->format('Y-m-d'));

        // Lintang's approved reservation
        $lintangRes = Reservation::where('purpose', 'Seminar Generative AI untuk Mahasiswa Informatika')->first();
        $this->assertNotNull($lintangRes);
        $this->assertTrue($lintangRes->isApproved());
    }

    public function test_github_seed_reports_table_and_damage_reports_compatibility(): void
    {
        // Verify reports exist in reports table
        $this->assertDatabaseCount('reports', 4);

        // Check DamageReport model works with reports table
        $kelistrikan = DamageReport::where('category', 'Kelistrikan')->first();
        $this->assertNotNull($kelistrikan);
        $this->assertTrue($kelistrikan->isBaru());

        // Check Report model alias
        $reportModel = Report::where('category', 'Kelistrikan')->first();
        $this->assertNotNull($reportModel);
        $this->assertEquals($kelistrikan->id, $reportModel->id);

        // Check resolved report
        $selesaiReport = DamageReport::where('status', 'Selesai')->first();
        $this->assertNotNull($selesaiReport);
        $this->assertTrue($selesaiReport->isSelesai());
        $this->assertNotEmpty($selesaiReport->resolution_note);
    }
}
