<?php

namespace Tests\Feature\Auth;

use App\Actions\AttemptAuthentication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_nuptk_dan_password_benar_menghasilkan_session(): void
    {
        $user = User::factory()->guru()->create([
            'nuptk' => '100000000000000001',
            'password' => 'rahasia-kuat-123',
            'is_active' => true,
        ]);

        $this->attempt('100000000000000001', 'rahasia-kuat-123');

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_tidak_dapat_dipakai_sebagai_identitas(): void
    {
        User::factory()->guru()->create([
            'email' => 'guru@sekolah.sch.id',
            'password' => 'rahasia-kuat-123',
            'is_active' => true,
        ]);

        $this->assertAttemptRejected(
            'guru@sekolah.sch.id',
            'rahasia-kuat-123',
            'NUPTK atau password salah.'
        );

        $this->assertGuest();
    }

    public function test_nuptk_salah_ditolak(): void
    {
        User::factory()->guru()->create([
            'nuptk' => '100000000000000001',
            'password' => 'rahasia-kuat-123',
            'is_active' => true,
        ]);

        $this->assertAttemptRejected(
            '999999999999999999',
            'rahasia-kuat-123',
            'NUPTK atau password salah.'
        );

        $this->assertGuest();
    }

    public function test_password_salah_ditolak(): void
    {
        User::factory()->guru()->create([
            'nuptk' => '100000000000000001',
            'password' => 'rahasia-kuat-123',
            'is_active' => true,
        ]);

        $this->assertAttemptRejected(
            '100000000000000001',
            'password-salah',
            'NUPTK atau password salah.'
        );

        $this->assertGuest();
    }

    public function test_akun_nonaktif_ditolak_dengan_pesan_jelas(): void
    {
        User::factory()->guru()->create([
            'nuptk' => '100000000000000001',
            'password' => 'rahasia-kuat-123',
            'is_active' => false,
        ]);

        $this->assertAttemptRejected(
            '100000000000000001',
            'rahasia-kuat-123',
            'Akun ini sudah dinonaktifkan. Hubungi admin.'
        );

        $this->assertGuest();
    }

    public function test_admin_boleh_login_dengan_nuptk_sama_biasa(): void
    {
        $admin = User::factory()->admin()->create([
            'nuptk' => '100000000000000002',
            'password' => 'rahasia-kuat-123',
            'is_active' => true,
        ]);

        $this->attempt('100000000000000002', 'rahasia-kuat-123');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_pesan_akun_nonaktif_hanya_muncul_jika_password_benar(): void
    {
        User::factory()->guru()->create([
            'nuptk' => '100000000000000001',
            'password' => 'rahasia-kuat-123',
            'is_active' => false,
        ]);

        $this->assertAttemptRejected(
            '100000000000000001',
            'salah-sekali',
            'NUPTK atau password salah.'
        );
    }

    public function test_brute_force_ditahan_setelah_lima_percobaan(): void
    {
        User::factory()->guru()->create([
            'nuptk' => '100000000000000001',
            'password' => 'rahasia-kuat-123',
            'is_active' => true,
        ]);

        foreach (range(1, 5) as $ignored) {
            $this->assertAttemptRejected(
                '100000000000000001',
                'salah-tebak',
                'NUPTK atau password salah.'
            );
        }

        $this->assertThrottled('100000000000000001', 'rahasia-kuat-123');

        $this->assertGuest();
    }

    public function test_throttle_login_terdaftar(): void
    {
        $this->assertNotNull(
            RateLimiter::limiter('login'),
            'Rate limiter login harus terdaftar, kalau tidak brute force tidak mungkin dicegah'
        );
    }

    public function test_kunci_throttle_menggabungkan_nuptk_dan_ip(): void
    {
        $request = request()->merge(['nuptk' => '100000000000000001']);
        $request->server->set('REMOTE_ADDR', '192.0.2.1');

        $limit = RateLimiter::limiter('login')($request);

        $this->assertSame(
            AttemptAuthentication::throttleKeyFor('100000000000000001', '192.0.2.1'),
            $limit->key,
            'Named limiter dan attempt() harus menghitung kunci yang sama'
        );

        $this->assertSame('login|100000000000000001|192.0.2.1', $limit->key);
    }

    public function test_password_disimpan_sebagai_hash(): void
    {
        $user = User::factory()->guru()->create([
            'password' => 'rahasia-kuat-123',
        ]);

        $this->assertNotSame('rahasia-kuat-123', $user->password);
        $this->assertTrue(Hash::check('rahasia-kuat-123', $user->password));
    }

    private function attempt(string $nuptk, string $password): void
    {
        app(AttemptAuthentication::class)->attempt([
            'nuptk' => $nuptk,
            'password' => $password,
            'remember' => false,
        ]);
    }

    private function assertAttemptRejected(string $nuptk, string $password, string $expectedMessage): void
    {
        try {
            $this->attempt($nuptk, $password);
        } catch (ValidationException $exception) {
            $this->assertSame(
                [$expectedMessage],
                $exception->errors()['nuptk'] ?? []
            );

            return;
        }

        $this->fail("Percobaan login dengan nuptk {$nuptk} seharusnya ditolak, tapi diterima.");
    }

    /**
     * Throttle harus menolak login, tapi jumlah detik yang tersisa berubah
     * setiap detik berjalan, jadi angka spesifiknya tidak bisa dikunci.
     */
    private function assertThrottled(string $nuptk, string $password): void
    {
        try {
            $this->attempt($nuptk, $password);
        } catch (ValidationException $exception) {
            $this->assertMatchesRegularExpression(
                '/^Terlalu banyak percobaan login\. Coba lagi dalam \d+ detik\.$/u',
                $exception->errors()['nuptk'][0] ?? ''
            );

            return;
        }

        $this->fail("Percobaan login ke-6 dengan nuptk {$nuptk} seharusnya ditolak oleh throttle.");
    }
}
