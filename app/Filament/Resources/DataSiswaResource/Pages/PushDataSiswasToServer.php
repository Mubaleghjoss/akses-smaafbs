<?php

namespace App\Filament\Resources\DataSiswaResource\Pages;

use App\Filament\Resources\DataSiswaResource;
use App\Models\User;
use App\Support\StudentSync\StudentPushScopeToken;
use App\Support\StudentSync\StudentServerPushClient;
use App\Support\StudentSync\StudentServerPushPayloadBuilder;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Schema;
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
        $this->scopeIds = app(StudentPushScopeToken::class)->idsFor(auth()->user(), $scope);
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

            $projected = $this->projectPreview($preview);
            $this->previewToken = $projected['preview_token'];
            $this->payloadChecksum = $projected['payload_checksum'];
            $this->previewExpiresAt = $projected['expires_at'];
            $this->counts = $projected['counts'];
            $this->fieldSummary = $projected['field_summary'];
            $this->items = $projected['items'];
            $this->applyResult = null;

            if ($this->previewToken === null || $this->payloadChecksum === null) {
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
            $result = app(StudentServerPushClient::class)->apply(
                $this->previewToken,
                $this->payloadChecksum,
                hash('sha256', $this->previewToken.'|'.$this->payloadChecksum),
            );
            $this->applyResult = ['counts' => $this->safeCounts($result['counts'] ?? null)];
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

    /**
     * Project a remote response to the only values the Blade view may consume.
     *
     * @param  array<string, mixed>  $preview
     * @return array{preview_token: ?string, payload_checksum: ?string, expires_at: ?string, counts: array<string, int>, field_summary: array<string, int>, items: array<int, array<string, mixed>>}
     */
    private function projectPreview(array $preview): array
    {
        return [
            'preview_token' => $this->safeOpaqueToken($preview['preview_token'] ?? null),
            'payload_checksum' => $this->safeChecksum($preview['payload_checksum'] ?? null),
            'expires_at' => $this->safeExpiry($preview['expires_at'] ?? null),
            'counts' => $this->safeCounts($preview['counts'] ?? null),
            'field_summary' => $this->safeFieldSummary($preview['field_summary'] ?? null),
            'items' => $this->safeItems($preview['items'] ?? null),
        ];
    }

    /** @return array<string, int> */
    private function safeCounts(mixed $counts): array
    {
        $allowed = ['total', 'update', 'unchanged', 'conflict', 'not_found'];

        return collect(is_array($counts) ? $counts : [])
            ->only($allowed)
            ->filter(fn (mixed $count): bool => is_int($count) || ctype_digit((string) $count))
            ->map(static fn (mixed $count): int => max(0, (int) $count))
            ->all();
    }

    /** @return array<string, int> */
    private function safeFieldSummary(mixed $summary): array
    {
        $allowed = array_flip($this->permittedFieldNames());

        return collect(is_array($summary) ? $summary : [])
            ->filter(fn (mixed $count, mixed $field): bool => is_string($field)
                && isset($allowed[$field])
                && (is_int($count) || ctype_digit((string) $count)))
            ->map(static fn (mixed $count): int => max(0, (int) $count))
            ->sortKeys()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function safeItems(mixed $items): array
    {
        $allowedFields = array_flip($this->permittedFieldNames());
        $statuses = ['update', 'unchanged', 'conflict', 'not_found'];
        $reasons = [
            'contradictory_strong_identifiers' => 'Identifier kuat tidak cocok.',
            'multiple_strong_candidates' => 'Ditemukan lebih dari satu kandidat.',
            'ambiguous_name_and_dob' => 'Nama dan tanggal lahir ambigu.',
            'insufficient_id_evidence' => 'Bukti identitas belum cukup.',
            'no_candidate' => 'Tidak ada kandidat yang cocok.',
            'matched_by_id' => 'Cocok berdasarkan ID.',
            'matched_by_strong_identifier' => 'Cocok berdasarkan identifier.',
            'matched_by_name_and_dob' => 'Cocok berdasarkan nama dan tanggal lahir.',
        ];

        return collect(is_array($items) ? $items : [])
            ->filter(static fn (mixed $item): bool => is_array($item))
            ->map(function (array $item) use ($allowedFields, $reasons, $statuses): ?array {
                $status = $item['status'] ?? null;

                if (! is_string($status) || ! in_array($status, $statuses, true)) {
                    return null;
                }

                return [
                    'status' => $status,
                    'source_id' => $this->safeId($item['source_id'] ?? null),
                    'target_id' => $this->safeId($item['target_id'] ?? null),
                    'changed_fields' => array_values(array_filter(
                        is_array($item['changed_fields'] ?? null) ? $item['changed_fields'] : [],
                        static fn (mixed $field): bool => is_string($field) && isset($allowedFields[$field]),
                    )),
                    'reason' => is_string($item['reason'] ?? null) ? ($reasons[$item['reason']] ?? null) : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function permittedFieldNames(): array
    {
        $denied = [...config('student_sync.denied_fields', []), 'id'];

        return array_values(array_diff(Schema::getColumnListing('data_siswa'), $denied));
    }

    private function safeId(mixed $id): ?int
    {
        return (is_int($id) || ctype_digit((string) $id)) && (int) $id > 0 ? (int) $id : null;
    }

    private function safeOpaqueToken(mixed $token): ?string
    {
        return is_string($token) && preg_match('/^[A-Za-z0-9-]{1,128}$/', $token) ? $token : null;
    }

    private function safeChecksum(mixed $checksum): ?string
    {
        return is_string($checksum) && preg_match('/^[a-f0-9]{64}$/', $checksum) ? $checksum : null;
    }

    private function safeExpiry(mixed $expiresAt): ?string
    {
        if (! is_string($expiresAt)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($expiresAt)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }
}
