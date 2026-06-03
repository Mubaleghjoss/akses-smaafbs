<?php

namespace App\Filament\Pages;

use App\Models\Pengaturan;
use App\Models\User;
use App\Support\Sarpras\SarprasStickerSettings as StickerSettings;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Livewire\Features\SupportFileUploads\WithFileUploads;

/**
 * @property-read Schema $form
 */
class SarprasStickerSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Pengaturan Stiker';

    protected static ?int $navigationSort = 14;

    protected static ?string $slug = 'sarpras/pengaturan-stiker';

    protected string $view = 'filament.pages.sarpras-sticker-settings';

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return SchemaFacade::hasTable('pengaturan')
            && $user instanceof User
            && (
                $user->hasFullAdminAccess()
                || $user->canManageModule('sarpras_bosp_inventory')
                || $user->canManageModule('sarpras_sticker_settings')
            );
    }

    public function mount(): void
    {
        $this->form->fill(StickerSettings::all());
    }

    public function getTitle(): string|Htmlable
    {
        return 'Pengaturan Stiker Sarpras';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Pengaturan Stiker Sarpras';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Atur logo, ukuran, dan teks stiker BOSP tanpa mengubah pengaturan situs utama.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Grid::make(['default' => 1, 'xl' => 2])
                    ->schema([
                        Section::make('Logo dan Teks')
                            ->description('Logo dipakai di kolom kiri stiker. Jika kosong, sistem memakai logo situs atau fallback AFBS.')
                            ->schema([
                                Forms\Components\FileUpload::make(StickerSettings::LOGO_PATH)
                                    ->label('Logo Stiker Sarpras')
                                    ->disk('public')
                                    ->directory('sarpras/sticker')
                                    ->image()
                                    ->imageEditor()
                                    ->downloadable()
                                    ->openable()
                                    ->live()
                                    ->maxSize(2048)
                                    ->helperText('Disarankan logo persegi atau transparan.'),
                                Forms\Components\TextInput::make(StickerSettings::SCHOOL_TEXT)
                                    ->label('Teks Sekolah')
                                    ->live(debounce: 500)
                                    ->required()
                                    ->maxLength(30),
                                Forms\Components\TextInput::make(StickerSettings::PROGRAM_TEXT)
                                    ->label('Teks Program')
                                    ->live(debounce: 500)
                                    ->required()
                                    ->maxLength(20)
                                    ->helperText('Tahun tetap otomatis dari Tahun Beli inventaris.'),
                            ]),
                        Section::make('Ukuran dan Responsif')
                            ->description('Ukuran dalam milimeter untuk layout cetak PDF. Perubahan ini memengaruhi download single dan bulk.')
                            ->columns(['default' => 1, 'md' => 2])
                            ->schema([
                                Forms\Components\TextInput::make(StickerSettings::WIDTH_MM)
                                    ->label('Lebar Stiker')
                                    ->live(debounce: 500)
                                    ->numeric()
                                    ->suffix('mm')
                                    ->minValue(70)
                                    ->maxValue(140)
                                    ->required(),
                                Forms\Components\TextInput::make(StickerSettings::HEIGHT_MM)
                                    ->label('Tinggi Stiker')
                                    ->live(debounce: 500)
                                    ->numeric()
                                    ->suffix('mm')
                                    ->minValue(18)
                                    ->maxValue(50)
                                    ->required(),
                                Forms\Components\TextInput::make(StickerSettings::LOGO_COLUMN_MM)
                                    ->label('Lebar Kolom Logo')
                                    ->live(debounce: 500)
                                    ->numeric()
                                    ->suffix('mm')
                                    ->minValue(16)
                                    ->maxValue(45)
                                    ->required(),
                                Forms\Components\TextInput::make(StickerSettings::QR_COLUMN_MM)
                                    ->label('Lebar Kolom QR')
                                    ->live(debounce: 500)
                                    ->numeric()
                                    ->suffix('mm')
                                    ->minValue(24)
                                    ->maxValue(55)
                                    ->required(),
                                Forms\Components\TextInput::make(StickerSettings::SCHOOL_FONT_PT)
                                    ->label('Ukuran Teks Sekolah')
                                    ->live(debounce: 500)
                                    ->numeric()
                                    ->suffix('pt')
                                    ->minValue(10)
                                    ->maxValue(28)
                                    ->required(),
                                Forms\Components\TextInput::make(StickerSettings::PROGRAM_FONT_PT)
                                    ->label('Ukuran Teks Program')
                                    ->live(debounce: 500)
                                    ->numeric()
                                    ->suffix('pt')
                                    ->minValue(8)
                                    ->maxValue(22)
                                    ->required(),
                                Forms\Components\TextInput::make(StickerSettings::DETAIL_FONT_PT)
                                    ->label('Ukuran Teks Kanan')
                                    ->live(debounce: 500)
                                    ->numeric()
                                    ->suffix('pt')
                                    ->minValue(4)
                                    ->maxValue(10)
                                    ->required(),
                            ]),
                        Section::make('Penyesuaian Layout')
                            ->description('Default 0 berarti posisi otomatis berada di tengah. Gunakan nilai negatif untuk naik, positif untuk turun.')
                            ->columns(['default' => 1, 'md' => 2])
                            ->schema([
                                Forms\Components\TextInput::make(StickerSettings::LOGO_OFFSET_Y_MM)
                                    ->label('Geser Logo Vertikal')
                                    ->live(debounce: 500)
                                    ->numeric()
                                    ->suffix('mm')
                                    ->minValue(-8)
                                    ->maxValue(8)
                                    ->required(),
                                Forms\Components\TextInput::make(StickerSettings::LOGO_SIZE_MM)
                                    ->label('Tinggi Logo Manual')
                                    ->live(debounce: 500)
                                    ->numeric()
                                    ->suffix('mm')
                                    ->minValue(0)
                                    ->maxValue(35)
                                    ->required()
                                    ->helperText('Isi 0 untuk ukuran otomatis. Turunkan nilainya jika logo masih terlalu mepet.'),
                                Forms\Components\TextInput::make(StickerSettings::TEXT_OFFSET_Y_MM)
                                    ->label('Geser Teks Tengah')
                                    ->live(debounce: 500)
                                    ->numeric()
                                    ->suffix('mm')
                                    ->minValue(-6)
                                    ->maxValue(6)
                                    ->required(),
                                Forms\Components\TextInput::make(StickerSettings::QR_GROUP_OFFSET_Y_MM)
                                    ->label('Geser QR dan Teks Kanan')
                                    ->live(debounce: 500)
                                    ->numeric()
                                    ->suffix('mm')
                                    ->minValue(-6)
                                    ->maxValue(6)
                                    ->required(),
                                Forms\Components\TextInput::make(StickerSettings::QR_SIZE_MM)
                                    ->label('Ukuran QR Manual')
                                    ->live(debounce: 500)
                                    ->numeric()
                                    ->suffix('mm')
                                    ->minValue(0)
                                    ->maxValue(20)
                                    ->required()
                                    ->helperText('Isi 0 untuk ukuran otomatis.'),
                                Forms\Components\TextInput::make(StickerSettings::RIGHT_LINE_HEIGHT_MM)
                                    ->label('Tinggi Baris Teks Kanan')
                                    ->live(debounce: 500)
                                    ->numeric()
                                    ->suffix('mm')
                                    ->minValue(2.4)
                                    ->maxValue(5)
                                    ->required()
                                    ->helperText('Naikkan jika huruf bawah seperti g/y masih terlihat terpotong di PDF.'),
                                Forms\Components\TextInput::make(StickerSettings::RIGHT_GAP_MM)
                                    ->label('Jarak Elemen Kanan')
                                    ->live(debounce: 500)
                                    ->numeric()
                                    ->suffix('mm')
                                    ->minValue(0)
                                    ->maxValue(2)
                                    ->required()
                                    ->helperText('Kecilkan jika QR dan teks kanan butuh ruang lebih banyak.'),
                            ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (array_keys(StickerSettings::defaults()) as $key) {
            StickerSettings::upsert($key, $data[$key] ?? null);
        }

        Notification::make()
            ->title('Pengaturan stiker tersimpan')
            ->body('Download stiker berikutnya akan memakai layout dan logo terbaru.')
            ->success()
            ->send();

        $this->form->fill(StickerSettings::all());
    }
}
