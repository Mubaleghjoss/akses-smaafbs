<?php

namespace Tests\Feature;

use App\Models\GuruTendik;
use App\Models\User;
use App\Support\GuruTendik\GuruTendikAccountProvisioner;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\BootstrapsAdminFeatureTables;
use Tests\TestCase;

class GuruTendikAccountProvisioningTest extends TestCase
{
    use BootstrapsAdminFeatureTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapAdminFeatureTables();
    }

    public function test_bulk_provisioning_creates_linked_guru_accounts_with_unique_defaults(): void
    {
        $records = collect([
            GuruTendik::query()->create(['nama' => 'Ustadz Rahman', 'jenis_ptk' => 'Guru', 'status' => 'aktif']),
            GuruTendik::query()->create(['nama' => 'Ustadz Rahman', 'jenis_ptk' => 'Guru', 'status' => 'aktif']),
        ]);

        $summary = app(GuruTendikAccountProvisioner::class)->provisionForCollection($records);

        $this->assertSame(2, $summary['created']);
        $this->assertSame(0, $summary['reset']);
        $this->assertSame(0, $summary['skipped']);
        $this->assertCount(2, $summary['credentials']);
        $this->assertNotSame($summary['credentials'][0]['username'], $summary['credentials'][1]['username']);
        $this->assertNotSame($summary['credentials'][0]['password'], $summary['credentials'][1]['password']);

        $users = User::query()->whereIn('guru_tendik_id', $records->pluck('id'))->orderBy('guru_tendik_id')->get();

        $this->assertCount(2, $users);
        $this->assertTrue($users->every(fn (User $user): bool => $user->hasRole('guru')));
        $this->assertTrue($users->every(fn (User $user): bool => $user->uses_default_password));
    }

    public function test_username_collision_uses_deterministic_suffix_increment(): void
    {
        $firstGuru = GuruTendik::query()->create(['nama' => 'Bu Siti Aminah', 'jenis_ptk' => 'Guru', 'status' => 'aktif']);
        $secondGuru = GuruTendik::query()->create(['nama' => 'Bu Siti Aminah', 'jenis_ptk' => 'Guru', 'status' => 'aktif']);

        $provisioner = app(GuruTendikAccountProvisioner::class);
        $first = $provisioner->provisionOrResetForGuru($firstGuru);
        $second = $provisioner->provisionOrResetForGuru($secondGuru);

        $this->assertSame('bu.aminah', $first['username']);
        $this->assertSame('bu.aminah-2', $second['username']);
    }

    public function test_reset_flow_reissues_default_password_and_keeps_guru_block_flag(): void
    {
        $guru = GuruTendik::query()->create(['nama' => 'Pak Hadi', 'jenis_ptk' => 'Guru', 'status' => 'aktif']);
        $provisioner = app(GuruTendikAccountProvisioner::class);

        $initial = $provisioner->provisionOrResetForGuru($guru);
        $user = $initial['user']->fresh();
        $oldHash = $user->password;

        $reset = $provisioner->resetDefaultPassword($user, $guru);
        $refreshed = $user->fresh();

        $this->assertSame($user->username, $reset['username']);
        $this->assertTrue(Hash::check($reset['password'], $refreshed->password));
        $this->assertNotSame($oldHash, $refreshed->password);
        $this->assertTrue($refreshed->uses_default_password);
        $this->assertNotNull($refreshed->default_password_reset_at);
        $this->assertNull($refreshed->default_password_changed_at);
    }

    public function test_credentials_formal_share_text_is_whatsapp_ready(): void
    {
        $text = GuruTendikAccountProvisioner::credentialsAsFormalWhatsappText([
            [
                'guru_tendik' => 'Ustadz Rafi',
                'username' => 'ustadz.rafi',
                'password' => 'RAF5!120101ABC',
                'created' => true,
            ],
        ]);

        $this->assertStringContainsString('Assalamu\'alaikum Bapak/Ibu Guru/Tendik/Pamong,', $text);
        $this->assertStringContainsString('Berikut kami sampaikan kredensial login akun sekolah:', $text);
        $this->assertStringContainsString('Ustadz Rafi', $text);
        $this->assertStringContainsString('Status: Akun baru', $text);
        $this->assertStringContainsString('Username: ustadz.rafi', $text);
        $this->assertStringContainsString('Password: RAF5!120101ABC', $text);
        $this->assertStringContainsString('Mohon segera login dan ganti password default demi keamanan akun.', $text);
    }

    public function test_credentials_quick_share_text_is_whatsapp_ready(): void
    {
        $text = GuruTendikAccountProvisioner::credentialsAsQuickWhatsappText([
            [
                'guru_tendik' => 'Ustadz Rafi',
                'username' => 'ustadz.rafi',
                'password' => 'RAF5!120101ABC',
                'created' => false,
            ],
        ]);

        $this->assertStringContainsString('Kredensial login guru/tendik:', $text);
        $this->assertStringContainsString('Ustadz Rafi', $text);
        $this->assertStringNotContainsString('Status:', $text);
        $this->assertStringContainsString('Username: ustadz.rafi', $text);
        $this->assertStringContainsString('Password: RAF5!120101ABC', $text);
        $this->assertStringContainsString('Login lalu ganti password default.', $text);
    }

    public function test_credentials_friendly_share_text_is_whatsapp_ready(): void
    {
        $text = GuruTendikAccountProvisioner::credentialsAsFriendlyWhatsappText([
            [
                'guru_tendik' => 'Ustadz Rafi',
                'username' => 'ustadz.rafi',
                'password' => 'RAF5!120101ABC',
                'created' => true,
            ],
        ]);

        $this->assertStringContainsString('Assalamu\'alaikum Bapak/Ibu, izin share akun login sekolah:', $text);
        $this->assertStringContainsString('Ustadz Rafi', $text);
        $this->assertStringContainsString('Status: Akun baru', $text);
        $this->assertStringContainsString('Username: ustadz.rafi', $text);
        $this->assertStringContainsString('Password: RAF5!120101ABC', $text);
        $this->assertStringContainsString('Silakan digunakan untuk login, lalu mohon ganti password default saat sudah masuk.', $text);
        $this->assertStringContainsString('Jika ada kendala, balas chat ini ya.', $text);
    }

    public function test_credentials_copyable_html_includes_three_template_copy_buttons(): void
    {
        $html = GuruTendikAccountProvisioner::credentialsAsCopyableHtml([
            [
                'guru_tendik' => 'Ustadz Rafi',
                'username' => 'ustadz.rafi',
                'password' => 'RAF5!120101ABC',
                'created' => false,
            ],
        ]);

        $this->assertStringContainsString('Salin Template Formal', $html);
        $this->assertStringContainsString('Salin Template Ramah', $html);
        $this->assertStringContainsString('Salin Template Singkat', $html);
        $this->assertSame(3, substr_count($html, 'js-copy-credentials-btn'));
        $this->assertSame(3, substr_count($html, 'js-copy-credentials-text'));
        $this->assertStringContainsString('Template Formal (Komunikasi Sekolah)', $html);
        $this->assertStringContainsString('Template Ramah (Koordinasi Harian)', $html);
        $this->assertStringContainsString('Template Singkat (Quick Share)', $html);
        $this->assertStringContainsString('js-copy-credentials-btn', $html);
        $this->assertStringContainsString('js-copy-credentials-text', $html);
        $this->assertStringContainsString('<pre', $html);
    }

    public function test_guru_with_default_password_sees_blocking_modal_on_admin_pages(): void
    {
        $guru = GuruTendik::query()->create(['nama' => 'Ustadz Rafi', 'jenis_ptk' => 'Guru', 'status' => 'aktif']);
        $result = app(GuruTendikAccountProvisioner::class)->provisionOrResetForGuru($guru);
        $guruUser = $result['user']->fresh();

        $this->actingAs($guruUser)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Wajib Ganti Password')
            ->assertSee('Simpan Password Baru');
    }

    public function test_guru_is_unblocked_after_forced_password_change(): void
    {
        $guru = GuruTendik::query()->create(['nama' => 'Ustadz Ilham', 'jenis_ptk' => 'Guru', 'status' => 'aktif']);
        $result = app(GuruTendikAccountProvisioner::class)->provisionOrResetForGuru($guru);
        $guruUser = $result['user']->fresh();

        $this->actingAs($guruUser)
            ->post('/admin/force-guru-password-change', [
                'current_password' => $result['password'],
                'password' => 'PasswordBaru!123',
                'password_confirmation' => 'PasswordBaru!123',
                'redirect_to' => '/admin',
            ])
            ->assertRedirect('/admin');

        $guruUser->refresh();

        $this->assertFalse($guruUser->uses_default_password);
        $this->assertNotNull($guruUser->default_password_changed_at);
        $this->assertAuthenticatedAs($guruUser);

        $this->actingAs($guruUser)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Wajib Ganti Password');

        $this->get('/admin/login')->assertRedirect('/admin');
    }
}
