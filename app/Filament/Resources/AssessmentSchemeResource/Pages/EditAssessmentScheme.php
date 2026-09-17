<?php

namespace App\Filament\Resources\AssessmentSchemeResource\Pages;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Filament\Resources\AssessmentSchemeResource;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentScheme;
use App\Support\Assessment\AssessmentAuditLogger;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditAssessmentScheme extends EditRecord
{
    protected static string $resource = AssessmentSchemeResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    /** @var array<string, mixed> */
    private array $revisionBefore = [];

    protected function beforeValidate(): void
    {
        $newPeriodId = (int) (data_get($this->form->getRawState(), 'assessment_period_id') ?: $this->record->assessment_period_id);
        $periodIds = collect([
            (int) $this->record->assessment_period_id,
            $newPeriodId,
        ])->filter()->unique()->sort()->values();
        $periods = AssessmentPeriod::query()
            ->whereIn('id', $periodIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $currentPeriod = $periods->get((int) $this->record->assessment_period_id);
        $targetPeriod = $periods->get($newPeriodId);

        if (! $currentPeriod || ! $targetPeriod || (
            $currentPeriod->status !== AssessmentPeriodStatus::DRAFT
            && $newPeriodId !== (int) $this->record->assessment_period_id
        ) || (
            $currentPeriod->status === AssessmentPeriodStatus::DRAFT
            && $targetPeriod->status !== AssessmentPeriodStatus::DRAFT
        )) {
            throw ValidationException::withMessages([
                'data.assessment_period_id' => 'Skema pada periode final tidak dapat dipindahkan; periode tujuan harus masih berstatus Draf.',
            ]);
        }

        $lockedScheme = AssessmentScheme::query()
            ->with('components')
            ->whereKey($this->record->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        abort_unless(AssessmentSchemeResource::canEdit($lockedScheme), 403);

        $submittedComponentIds = collect(data_get($this->form->getRawState(), 'components', []))
            ->pluck('id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $protectedComponentIds = $lockedScheme->components()
            ->whereHas('scores')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id);
        if ($protectedComponentIds->diff($submittedComponentIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'data.components' => 'Komponen yang sudah memiliki nilai tidak dapat dihapus agar bukti nilai historis tetap utuh.',
            ]);
        }

        $this->revisionBefore = [
            'scheme' => $lockedScheme->getAttributes(),
            'components' => $lockedScheme->components
                ->mapWithKeys(fn ($component): array => [$component->getKey() => $component->getAttributes()])
                ->all(),
            'period_status' => $currentPeriod->status->value,
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['assessment_period_id'] ??= $this->record->assessment_period_id;
        $components = data_get($this->form->getRawState(), 'components', []);

        return AssessmentSchemeResource::validateSchemeData(
            $data,
            (int) $this->record->getKey(),
            is_array($components) ? $components : [],
        );
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $freshRecord = $record->fresh(['period']);
        abort_unless($freshRecord && AssessmentSchemeResource::canEdit($freshRecord), 403);

        return parent::handleRecordUpdate($record, $data);
    }

    protected function afterSave(): void
    {
        if (($this->revisionBefore['period_status'] ?? AssessmentPeriodStatus::DRAFT->value) === AssessmentPeriodStatus::DRAFT->value) {
            return;
        }

        $scheme = $this->record->fresh(['components']);
        if (! $scheme) {
            return;
        }

        app(AssessmentAuditLogger::class)->record(
            auth()->user(),
            'assessment_scheme.revised_after_finalization',
            $scheme,
            $this->revisionBefore,
            [
                'scheme' => $scheme->getAttributes(),
                'components' => $scheme->components
                    ->mapWithKeys(fn ($component): array => [$component->getKey() => $component->getAttributes()])
                    ->all(),
                'report_snapshots_unchanged' => true,
                'requires_explicit_regeneration_and_republish' => true,
            ],
            'Perubahan konfigurasi setelah finalisasi; rapor terbit tidak diubah otomatis.',
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->databaseTransaction()
                ->before(function (): void {
                    $period = AssessmentPeriod::query()
                        ->whereKey($this->record->assessment_period_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $scheme = AssessmentScheme::query()
                        ->whereKey($this->record->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    abort_unless(
                        $period->status === AssessmentPeriodStatus::DRAFT
                            && AssessmentSchemeResource::canDelete($scheme),
                        403,
                    );
                }),
        ];
    }
}
