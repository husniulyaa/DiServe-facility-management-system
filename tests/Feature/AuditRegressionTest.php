<?php

namespace Tests\Feature;

use App\Mail\AccountApprovedMail;
use App\Mail\ResetPasswordMail;
use App\Models\DamageReport;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Support\SubmissionWindow;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_window_uses_jakarta_time_and_excludes_twenty_hundred(): void
    {
        $this->assertFalse(SubmissionWindow::isOpen(Carbon::parse('2026-10-09 06:59:00', 'Asia/Jakarta')));
        $this->assertTrue(SubmissionWindow::isOpen(Carbon::parse('2026-10-09 07:00:00', 'Asia/Jakarta')));
        $this->assertTrue(SubmissionWindow::isOpen(Carbon::parse('2026-10-09 19:59:00', 'Asia/Jakarta')));
        $this->assertFalse(SubmissionWindow::isOpen(Carbon::parse('2026-10-09 20:00:00', 'Asia/Jakarta')));
    }

    public function test_reservation_submission_window_is_separate_from_room_use_hours(): void
    {
        $user = $this->activeUser();
        $facility = $this->facility();
        $this->actingAs($user, 'sanctum');
        $this->travelTo(Carbon::parse('2026-10-09 19:59:00', 'Asia/Jakarta'));

        $response = $this->postJson('/api/reservations', $this->reservationPayload($facility, '22:30', '23:00'));
        $response->assertCreated();
        $this->assertDatabaseHas('reservations', [
            'facility_id' => $facility->id,
            'start_time' => '22:30',
            'end_time' => '23:00',
        ]);

        $this->travelTo(Carbon::parse('2026-10-09 20:00:00', 'Asia/Jakarta'));
        $this->postJson('/api/reservations', $this->reservationPayload($facility, '06:00', '06:30'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.submission_time.0', 'Waktu pengajuan berada di luar jam layanan.');
    }

    public function test_reservation_allows_six_am_and_rejects_use_after_eleven_pm(): void
    {
        $user = $this->activeUser();
        $facility = $this->facility();
        $this->actingAs($user, 'sanctum');
        $this->travelTo(Carbon::parse('2026-10-09 08:00:00', 'Asia/Jakarta'));

        $this->postJson('/api/reservations', $this->reservationPayload($facility, '06:00', '06:30'))
            ->assertCreated();

        $this->postJson('/api/reservations', $this->reservationPayload($facility, '22:30', '23:30'))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Waktu pemakaian harus berada pada slot 30 menit antara pukul 06.00 dan 23.00 WIB.');

        $this->postJson('/api/reservations', $this->reservationPayload($facility, '05:30', '06:00'))
            ->assertUnprocessable();
    }

    public function test_damage_report_submission_is_rejected_outside_jakarta_service_hours(): void
    {
        $user = $this->activeUser();
        $facility = $this->facility();
        $this->actingAs($user, 'sanctum');
        $this->travelTo(Carbon::parse('2026-10-09 06:59:00', 'Asia/Jakarta'));

        $this->postJson('/api/reports', [
            'facility' => $facility->id,
            'category' => 'Kelistrikan',
            'location_detail' => 'Lantai 1',
            'description' => 'Lampu ruangan tidak menyala.',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.submission_time.0', 'Waktu pengajuan berada di luar jam layanan.');
    }

    public function test_availability_includes_first_and_last_thirty_minute_slots(): void
    {
        $facility = $this->facility();
        $this->travelTo(Carbon::parse('2026-10-09 08:00:00', 'Asia/Jakarta'));

        $response = $this->getJson("/api/facilities/{$facility->id}/availability?date=2026-10-10");
        $response->assertOk();
        $times = collect($response->json('slots'))->pluck('time');
        $this->assertSame('06.00 - 06.30', $times->first());
        $this->assertSame('22.30 - 23.00', $times->last());
        $this->assertFalse($times->contains('23.00 - 23.30'));
    }

    public function test_register_rejects_password_without_required_character_classes(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'New User',
            'identity_number' => '240601000001',
            'email' => 'new.user@students.undip.ac.id',
            'password' => 'lowercase1!',
            'password_confirmation' => 'lowercase1!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_login_does_not_accept_legacy_fallback_password(): void
    {
        $user = $this->activeUser('Password123!');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertUnauthorized();
    }

    public function test_login_token_expires_after_two_hours_without_authenticated_activity(): void
    {
        $user = $this->activeUser();
        $this->travelTo(Carbon::parse('2026-10-09 08:00:00', 'Asia/Jakarta'));
        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertOk();
        $token = $login->json('token');

        $this->travelTo(Carbon::parse('2026-10-09 10:01:00', 'Asia/Jakarta'));
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_authenticated_activity_refreshes_the_idle_expiration(): void
    {
        $user = $this->activeUser();
        $this->travelTo(Carbon::parse('2026-10-09 08:00:00', 'Asia/Jakarta'));
        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertOk();
        $plainTextToken = $login->json('token');
        $storedToken = PersonalAccessToken::findToken($plainTextToken);

        $this->travelTo(Carbon::parse('2026-10-09 09:45:00', 'Asia/Jakarta'));
        $this->withHeader('Authorization', "Bearer {$plainTextToken}")
            ->getJson('/api/auth/me')
            ->assertOk();

        $this->assertTrue($storedToken->fresh()->expires_at->greaterThan(now()->addMinutes(119)));
    }

    public function test_password_reset_is_single_use_and_new_password_is_hashed(): void
    {
        Mail::fake();
        $user = $this->activeUser();
        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertAccepted();

        $resetMail = null;
        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use (&$resetMail) {
            $resetMail = $mail;

            return true;
        });
        $this->assertStringContainsString(
            url('/reset-password').'?token='.urlencode($resetMail->token).'&amp;email='.urlencode($user->email),
            $resetMail->render(),
        );

        $payload = [
            'email' => $user->email,
            'token' => $resetMail->token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];
        $this->postJson('/api/auth/reset-password', [
            ...$payload,
            'password' => 'weakpassword',
            'password_confirmation' => 'weakpassword',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'NewPassword123!',
        ])->assertOk();
        $this->postJson('/api/auth/reset-password', $payload)->assertUnprocessable();
    }

    public function test_expired_password_reset_token_is_rejected(): void
    {
        Mail::fake();
        $user = $this->activeUser();
        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertAccepted();
        $mail = null;
        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $sent) use (&$mail) {
            $mail = $sent;

            return true;
        });

        $this->travel(61)->minutes();
        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $mail->token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnprocessable();
        $this->assertTrue(Hash::check('Password123!', $user->fresh()->password));
    }

    public function test_reset_password_page_is_reachable_from_the_email_link(): void
    {
        $this->get('/reset-password')
            ->assertOk();
        $this->assertFileExists(public_path('reset-password.html'));
        $this->assertStringContainsString('id="reset-form"', file_get_contents(public_path('reset-password.html')));
        $this->assertStringContainsString('assets/js/resetpw.js', file_get_contents(public_path('reset-password.html')));
    }

    public function test_smtp_failure_is_logged_without_exposing_details_or_claiming_delivery(): void
    {
        config(['mail.default' => 'smtp']);
        $user = $this->activeUser();
        $failure = new RuntimeException('private smtp credential detail');
        Mail::shouldReceive('to')->once()->with($user->email)->andThrow($failure);
        Log::shouldReceive('error')
            ->once()
            ->with('Password reset email delivery failed.', Mockery::on(
                fn (array $context) => $context === [
                    'user_id' => $user->id,
                    'exception' => RuntimeException::class,
                ],
            ));

        $response = $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertAccepted();

        $response->assertJsonMissing(['message' => 'private smtp credential detail']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'unknown.user@students.undip.ac.id']);
        $this->assertSame($response->json('message'), $unknown->json('message'));
    }

    public function test_forgot_password_explicitly_reports_log_mailer_without_enumerating_accounts(): void
    {
        config(['mail.default' => 'log']);
        $this->activeUser();

        $registered = $this->postJson('/api/auth/forgot-password', [
            'email' => 'audit.user@students.undip.ac.id',
        ]);
        $unknown = $this->postJson('/api/auth/forgot-password', [
            'email' => 'unknown.user@students.undip.ac.id',
        ]);

        $registered->assertStatus(503)->assertJsonPath('message', $unknown->json('message'));
        $unknown->assertStatus(503);
    }

    public function test_private_reservation_attachment_is_owner_or_staff_only(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('reservations/private.pdf', 'private reservation document');
        $owner = $this->activeUser();
        $otherUser = User::create([
            'name' => 'Other User',
            'identity_number' => '240601000002',
            'email' => 'other.user@students.undip.ac.id',
            'password' => 'Password123!',
            'role' => 'pengguna',
            'status' => 'aktif',
        ]);
        $facility = $this->facility();
        $reservation = Reservation::create([
            'user_id' => $owner->id,
            'facility_id' => $facility->id,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-10',
            'start_time' => '10:00',
            'end_time' => '10:30',
            'purpose' => 'Dokumen privat untuk pengujian.',
            'supporting_file' => 'reservations/private.pdf',
            'status' => 'pending',
        ]);

        $this->actingAs($otherUser, 'sanctum')
            ->get("/api/reservations/{$reservation->id}/supporting-file")
            ->assertForbidden();
        $this->actingAs($owner, 'sanctum')
            ->get("/api/reservations/{$reservation->id}/supporting-file")
            ->assertDownload('private.pdf');
    }

    public function test_private_report_photo_is_owner_or_staff_only(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('reports/private.jpg', 'private report photo');
        $owner = $this->activeUser();
        $otherUser = User::create([
            'name' => 'Other User',
            'identity_number' => '240601000002',
            'email' => 'other.user@students.undip.ac.id',
            'password' => 'Password123!',
            'role' => 'pengguna',
            'status' => 'aktif',
        ]);
        $report = DamageReport::create([
            'user_id' => $owner->id,
            'facility_id' => $this->facility()->id,
            'category' => 'Kelistrikan',
            'location_detail' => 'Lantai 1',
            'description' => 'Foto lampu rusak untuk uji akses.',
            'photo' => 'reports/private.jpg',
            'status' => 'baru',
        ]);

        $this->actingAs($otherUser, 'sanctum')
            ->get("/api/reports/{$report->id}/photo")
            ->assertForbidden();
        $this->actingAs($owner, 'sanctum')
            ->get("/api/reports/{$report->id}/photo")
            ->assertDownload('private.jpg');
    }

    public function test_account_rejection_reason_is_saved_and_repeat_transition_is_rejected(): void
    {
        $admin = $this->activeUser('Password123!', 'admin');
        $applicant = User::create([
            'name' => 'Pending Applicant',
            'identity_number' => '240601000002',
            'email' => 'pending.user@students.undip.ac.id',
            'password' => 'Password123!',
            'role' => 'pengguna',
            'status' => 'pending',
        ]);
        $this->actingAs($admin, 'sanctum');

        $this->postJson("/api/admin/users/{$applicant->id}/reject", [
            'reason' => 'Berkas identitas belum lengkap.',
        ])->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $applicant->id,
            'status' => 'ditolak',
            'rejection_reason' => 'Berkas identitas belum lengkap.',
        ]);
        $this->postJson("/api/admin/users/{$applicant->id}/reject", [
            'reason' => 'Status tidak lagi menunggu.',
        ])->assertUnprocessable();
    }

    public function test_approval_updates_only_status_and_succeeds_without_rejection_reason_column(): void
    {
        $admin = $this->activeUser('Password123!', 'admin');
        $applicant = User::create([
            'name' => 'Pending Applicant',
            'identity_number' => '240601000002',
            'email' => 'pending.user@students.undip.ac.id',
            'password' => 'Password123!',
            'role' => 'pengguna',
            'status' => 'pending',
        ]);
        Schema::table('users', fn ($table) => $table->dropColumn('rejection_reason'));
        config(['mail.default' => 'array']);
        Mail::fake();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/users/{$applicant->id}/verify")
            ->assertOk()
            ->assertJsonPath('email_sent', true);
        $this->assertModelExists($applicant->fresh());
        $this->assertSame('aktif', $applicant->fresh()->status);
        Mail::assertSent(AccountApprovedMail::class);
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $applicant->id,
                'status' => 'aktif',
            ]);
        $this->assertStringNotContainsString('rejection_reason', $response->getContent());
    }

    public function test_account_remains_approved_when_approval_notification_fails(): void
    {
        config(['mail.default' => 'smtp']);
        $admin = $this->activeUser('Password123!', 'admin');
        $applicant = User::create([
            'name' => 'Pending Applicant',
            'identity_number' => '240601000002',
            'email' => 'pending.user@students.undip.ac.id',
            'password' => 'Password123!',
            'role' => 'pengguna',
            'status' => 'pending',
        ]);
        Mail::shouldReceive('to')->once()->with($applicant->email)
            ->andThrow(new RuntimeException('private smtp credential detail'));
        Log::shouldReceive('error')
            ->once()
            ->with('Account approval notification could not be sent.', Mockery::on(
                fn (array $context) => $context === [
                    'user_id' => $applicant->id,
                    'exception' => RuntimeException::class,
                ],
            ));

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/users/{$applicant->id}/verify")
            ->assertOk()
            ->assertJsonPath('email_sent', false);

        $this->assertSame('aktif', $applicant->fresh()->status);
        $this->assertStringNotContainsString('private smtp credential detail', $response->getContent());
    }

    public function test_rejection_fails_safely_and_leaves_status_pending_without_reason_column(): void
    {
        $admin = $this->activeUser('Password123!', 'admin');
        $applicant = User::create([
            'name' => 'Pending Applicant',
            'identity_number' => '240601000002',
            'email' => 'pending.user@students.undip.ac.id',
            'password' => 'Password123!',
            'role' => 'pengguna',
            'status' => 'pending',
        ]);
        Schema::table('users', fn ($table) => $table->dropColumn('rejection_reason'));

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/users/{$applicant->id}/reject", [
                'reason' => 'Berkas belum lengkap.',
            ])
            ->assertStatus(503);

        $this->assertSame('pending', $applicant->fresh()->status);
        $response->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace')
            ->assertJsonMissingPath('sql');
    }

    public function test_account_state_routes_handle_missing_users_and_non_admin_users(): void
    {
        $admin = $this->activeUser('Password123!', 'admin');
        $user = User::create([
            'name' => 'Pending Applicant',
            'identity_number' => '240601000002',
            'email' => 'pending.user@students.undip.ac.id',
            'password' => 'Password123!',
            'role' => 'pengguna',
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/users/999999/verify')
            ->assertNotFound();
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/users/{$user->id}/verify")
            ->assertForbidden();
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/users/{$user->id}/reject", ['reason' => 'A valid reason'])
            ->assertForbidden();
        $this->assertSame('pending', $user->fresh()->status);
    }

    public function test_rekap_reports_real_counts_and_does_not_invent_occupancy_or_hours(): void
    {
        $admin = $this->activeUser('Password123!', 'admin');
        $this->facility();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/rekap')
            ->assertOk();
        $response->assertJsonPath('summary.average_occupancy', null);
        $response->assertJsonPath('summary.total_hours', '0,0 Jam');
        $response->assertJsonPath('facilities.0.total_bookings', 0);
        $response->assertJsonPath('facilities.0.occupancy_rate', null);
    }

    public function test_rekap_counts_approved_booking_hours_and_damage_reports_from_records(): void
    {
        $admin = $this->activeUser('Password123!', 'admin');
        $user = User::create([
            'name' => 'Reservation Owner',
            'identity_number' => '240601000002',
            'email' => 'reservation.owner@students.undip.ac.id',
            'password' => 'Password123!',
            'role' => 'pengguna',
            'status' => 'aktif',
        ]);
        $facility = $this->facility();
        Reservation::create([
            'user_id' => $user->id,
            'facility_id' => $facility->id,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-10',
            'start_time' => '08:00',
            'end_time' => '10:30',
            'start_at' => '2026-10-10 08:00:00',
            'end_at' => '2026-10-10 10:30:00',
            'purpose' => 'Approved use for rekap test.',
            'status' => 'approved',
        ]);
        Reservation::create([
            'user_id' => $user->id,
            'facility_id' => $facility->id,
            'start_date' => '2026-10-11',
            'end_date' => '2026-10-11',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'start_at' => '2026-10-11 08:00:00',
            'end_at' => '2026-10-11 09:00:00',
            'purpose' => 'Pending use for rekap test.',
            'status' => 'pending',
        ]);
        DamageReport::create([
            'user_id' => $user->id,
            'facility_id' => $facility->id,
            'category' => 'Kelistrikan',
            'location_detail' => 'Lantai 1',
            'description' => 'Damage report for rekap test.',
            'status' => 'baru',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/rekap')
            ->assertOk();
        $response->assertJsonPath('summary.total_hours', '2,5 Jam');
        $response->assertJsonPath('summary.total_damages', '1 Laporan');
        $response->assertJsonPath('facilities.0.total_bookings', 2);
        $response->assertJsonPath('facilities.0.damage_count', 1);
        $response->assertJsonPath('facilities.0.occupancy_rate', null);
    }

    public function test_admin_export_requires_admin_auth_and_rejects_fake_pdf_export(): void
    {
        $this->getJson('/api/admin/export/csv')->assertUnauthorized();

        $admin = $this->activeUser('Password123!', 'admin');
        $this->actingAs($admin, 'sanctum')
            ->get('/api/admin/export/pdf')
            ->assertUnprocessable();
    }

    private function activeUser(string $password = 'Password123!', string $role = 'pengguna'): User
    {
        return User::create([
            'name' => 'Audit User',
            'identity_number' => '240601000001',
            'email' => 'audit.user@students.undip.ac.id',
            'password' => $password,
            'role' => $role,
            'status' => 'aktif',
        ]);
    }

    private function facility(): Facility
    {
        return Facility::create([
            'name' => 'Audit Facility',
            'type' => 'Laboratorium',
            'category' => 'Laboratorium',
            'slug' => 'audit-facility',
            'location' => 'FSM',
            'capacity' => 20,
            'status' => 'active',
        ]);
    }

    private function reservationPayload(Facility $facility, string $startTime, string $endTime): array
    {
        $date = now()->addDay()->toDateString();

        return [
            'facility' => $facility->id,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'purpose' => 'Pengujian audit reservasi.',
        ];
    }
}
