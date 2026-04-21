<?php

namespace Tests\Feature;

use App\Filament\Pages\EditAccountProfile;
use App\Models\GuruTendik;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsAdminFeatureTables;
use Tests\TestCase;

class AccountProfilePageTest extends TestCase
{
    use BootstrapsAdminFeatureTables;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->bootstrapAdminFeatureTables();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_linked_guru_photo_is_used_as_account_avatar_when_custom_avatar_is_empty(): void
    {
        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Putra',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
            'foto_profil' => 'guru-tendik/profil/putra.jpg',
        ]);

        $user = User::query()->create([
            'name' => 'Putra',
            'username' => 'putra.guru',
            'password' => bcrypt('password-lama'),
            'guru_tendik_id' => $guru->id,
        ]);
        $user->assignRole('guru');

        $this->assertSame('Foto profil guru/tendik', $user->avatarSourceLabel());
        $this->assertStringContainsString('/storage/guru-tendik/profil/putra.jpg', (string) $user->getFilamentAvatarUrl());
    }

    public function test_authenticated_user_can_update_avatar_and_password_from_account_profile_page(): void
    {
        $user = User::query()->create([
            'name' => 'Pamong Putri',
            'username' => 'pamong.putri',
            'password' => bcrypt('password-lama'),
        ]);
        $user->assignRole('pamong_putri');

        Livewire::actingAs($user)
            ->test(EditAccountProfile::class)
            ->set('data.name', 'Pamong Putri')
            ->set('data.username', 'pamong.putri')
            ->set('data.email', null)
            ->set('data.avatar_path', \Illuminate\Http\UploadedFile::fake()->image('avatar-pamong.jpg'))
            ->set('data.password', 'PasswordBaru123')
            ->set('data.passwordConfirmation', 'PasswordBaru123')
            ->set('data.currentPassword', 'password-lama')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        $this->assertSame('Avatar akun custom', $user->avatarSourceLabel());
        $this->assertStringContainsString('/storage/users/avatar/', (string) $user->getFilamentAvatarUrl());
        $this->assertTrue(Hash::check('PasswordBaru123', (string) $user->password));
    }
}
