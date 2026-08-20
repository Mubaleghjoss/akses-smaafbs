<?php

namespace App\Filament\Resources\DataSiswaResource\Pages;

use App\Filament\Resources\DataSiswaResource;
use App\Models\User;
use App\Support\StudentSync\StudentServerPushClient;
use App\Support\StudentSync\StudentServerPushPayloadBuilder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Throwable;

class PushDataSiswasToServer extends Page
{
    protected static string $resource = DataSiswaResource::class;

    protected static ?string $title = 'Push Data Siswa ke Server';

    protected static ?string $breadcrumb = 'Push ke Server';

    protected string $view = 'filament.resources.data-siswa-resource.pages.push-data-siswas-to-server';

    /** @var array<int, int> */
    public array $scopeIds = [];

    public ?string $scopeToken = null;

    public ?string $previewToken = null;

    public ?string $payloadChecksum = null;

    public ?string $previewExpiresAt = null;

    /** @var array<string, int> */
    public array $counts = [];

    /** @var array<string, int> */
    public array $fieldSummary = [];

    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    /** @var array<string, mixed>|null */
    public ?array $applyResult = null;

    public bool $isProcessing = false;

    public function mount(?string $scope = null): void
    {
        abort_unless(static::canAccessPage(), 403);
        $this->scopeToken = $scope;
    }

    public static function canAccessPage(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user instanceof User && ($user->hasFullAdminAccess()
            || ($user->canManageModule('data_siswa') && $user->can('data_siswa.push_server')));
    }

    public static function canAccess(array $parameters = []): bool
    {
        return static::canAccessPage();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Kembali ke Data Siswa')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(DataSiswaResource::getUrl('index')),
            Action::make('loadPreview')
                ->label('Muat Preview')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->disabled(fn (): bool => $this->isProcessing)
                ->action('loadPreview'),
            Action::make('applyPush')
                ->label('Terapkan Push')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('success')
                ->visible(fn (): bool => $this->previewToken !== null)
                ->disabled(fn (): bool => $this->isProcessing)
                ->requiresConfirmation()
                ->modalHeading('Terapkan push data siswa?')
                ->modalDescription('Hanya ringkasan perubahan yang ditampilkan. Data akan diproses memakai preview ini.')
                ->action('applyPush'),
            Action::make('resetPreview')
                ->label('Reset Preview')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('gray')
                ->visible(fn (): bool => $this->previewToken !== null)
                ->action('resetPreview'),
        ];
    }

    public function loadPreview(): void
    {
        $this->isProcessing = true;

        try {
            $payload = app(StudentServerPushPayloadBuilder::class)->build($this->scopeIds ?: null);
            $preview = app(StudentServerPushClient::class)->preview($payload);

            $this->previewToken = (string) ($preview['preview_token'] ?? '');
            $this->payloadChecksum = (string) ($preview['payload_checksum'] ?? '');
            $this->previewExpiresAt = isset($preview['expires_at']) ? (string) $preview['expires_at'] : null;
            $this->counts = is_array($preview['counts'] ?? null) ? $preview['counts'] : [];
            $this->fieldSummary = is_array($preview['field_summary'] ?? null) ? $preview['field_summary'] : [];
            $this->items = $this->safeItems($preview['items'] ?? []);
            $this->applyResult = null;

            if ($this->previewToken === '' || $this->payloadChecksum === '') {
                throw new \RuntimeException('Student sync server returned an incomplete preview.');
            }

            Notification::make()->success()->title('Preview push dimuat')->send();
        } catch (Throwable $exception) {
            report($exception);
            $this->resetPreview();
            Notification::make()->danger()->title('Gagal memuat preview push')->send();
        } finally {
            $this->isProcessing = false;
        }
    }

    public function applyPush(): void
    {
        if ($this->previewToken === null || $this->payloadChecksum === null) {
            Notification::make()->warning()->title('Muat preview terlebih dahulu')->send();

            return;
        }

        $this->isProcessing = true;

        try {
            $this->applyResult = app(StudentServerPushClient::class)->apply(
                $this->previewToken,
                $this->payloadChecksum,
                hash('sha256', $this->previewToken.'|'.$this->payloadChecksum),
            );
            Notification::make()->success()->title('Push data siswa selesai')->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->danger()->title('Push data siswa gagal')->send();
        } finally {
            $this->isProcessing = false;
        }
    }

    public function resetPreview(): void
    {
        $this->previewToken = null;
        $this->payloadChecksum = null;
        $this->previewExpiresAt = null;
        $this->counts = [];
        $this->fieldSummary = [];
        $this->items = [];
        $this->applyResult = null;
    }

    /** @return array<int, array<string, mixed>> */
    private function safeItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)->filter('is_array')->map(static fn (array $item): array => [
            'status' => (string) ($item['status'] ?? 'unknown'),
            'source_id' => $item['source_id'] ?? null,
            'target_id' => $item['target_id'] ?? null,
            'changed_fields' => array_values(array_filter($item['changed_fields'] ?? [], 'is_string')),
            'reason' => isset($item['reason']) ? (string) $item['reason'] : null,
        ])->values()->all();
    }
}
