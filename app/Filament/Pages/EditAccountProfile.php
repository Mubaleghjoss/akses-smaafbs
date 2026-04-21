<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\HtmlString;

class EditAccountProfile extends EditProfile
{
    protected static ?string $title = 'Profil Akun';

    protected static ?string $slug = 'account/profile';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);
        $data['email'] ??= null;
        $data['avatar_path'] = $this->getUser()->getAttributeValue('avatar_path');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = parent::mutateFormDataBeforeSave($data);
        $data['email'] = filled($data['email'] ?? null) ? trim((string) $data['email']) : null;
        $data['avatar_path'] = filled($data['avatar_path'] ?? null) ? trim((string) $data['avatar_path']) : null;

        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Profil akun berhasil diperbarui';
    }

    public function getFormActionsAlignment(): string | Alignment
    {
        return Alignment::Start;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profil Akun')
                    ->description('Foto di sini akan dipakai sebagai ikon akun di panel admin. Jika avatar akun kosong, sistem memakai foto profil guru/tendik yang terhubung bila tersedia.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Placeholder::make('avatar_preview')
                            ->label('Avatar Aktif')
                            ->content(fn (): HtmlString => $this->renderAvatarPreview())
                            ->columnSpanFull(),
                        FileUpload::make('avatar_path')
                            ->label('Foto Avatar Akun')
                            ->disk('public')
                            ->directory('users/avatar')
                            ->image()
                            ->imageEditor()
                            ->maxSize(4096)
                            ->helperText('Opsional. Jika diisi, avatar akun akan memakai foto ini. Jika kosong, akun guru/tendik memakai foto profil guru bila tersedia.'),
                        Placeholder::make('avatar_source')
                            ->label('Sumber Avatar')
                            ->content(fn (): string => $this->getAuthenticatedUser()?->avatarSourceLabel() ?? 'Inisial nama'),
                        $this->getNameFormComponent(),
                        $this->getUsernameFormComponent(),
                        $this->getEmailFormComponent(),
                    ]),
                Section::make('Keamanan Akun')
                    ->description('Kosongkan bagian ini jika tidak ingin mengganti password.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent()->columnSpanFull(),
                    ]),
            ]);
    }

    protected function getUsernameFormComponent(): TextInput
    {
        return TextInput::make('username')
            ->label('Username')
            ->required()
            ->maxLength(50)
            ->unique(ignoreRecord: true)
            ->autocomplete('username');
    }

    protected function getEmailFormComponent(): TextInput
    {
        return TextInput::make('email')
            ->label('Email')
            ->email()
            ->maxLength(255)
            ->unique(ignoreRecord: true)
            ->live(debounce: 500);
    }

    protected function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    protected function renderAvatarPreview(): HtmlString
    {
        $user = $this->getAuthenticatedUser();
        $url = $user?->getFilamentAvatarUrl();
        $source = $user?->avatarSourceLabel() ?? 'Inisial nama';

        if (blank($url)) {
            return new HtmlString('<div class="text-sm text-gray-500">Avatar saat ini memakai inisial nama.</div>');
        }

        return new HtmlString(
            '<div class="flex items-center gap-3">'
            .'<img src="'.e($url).'" alt="Avatar akun" class="h-16 w-16 rounded-full object-cover ring-2 ring-white shadow">'
            .'<div class="text-sm text-gray-600">'.e($source).'</div>'
            .'</div>'
        );
    }
}

