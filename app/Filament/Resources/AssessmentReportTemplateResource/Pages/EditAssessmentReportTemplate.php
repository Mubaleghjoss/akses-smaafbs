<?php

namespace App\Filament\Resources\AssessmentReportTemplateResource\Pages;

use App\Filament\Resources\AssessmentReportTemplateResource;
use App\Models\Assessment\ReportTemplate;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAssessmentReportTemplate extends EditRecord
{
    protected static string $resource = AssessmentReportTemplateResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function beforeValidate(): void
    {
        $template = ReportTemplate::query()
            ->whereKey($this->record->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        abort_unless(AssessmentReportTemplateResource::canEdit($template), 403);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $settings = $data['settings'] ?? [];
        if (! is_array($settings)) {
            $settings = [];
        }

        // The repeater and select components cannot hydrate values saved before validation.
        $sections = data_get($settings, 'layout.sections');
        $sections = is_array($sections) ? $sections : [];
        foreach ($sections as $key => $section) {
            if (! is_array($section)) {
                unset($sections[$key]);

                continue;
            }

            foreach (['type', 'title', 'page', 'sort_order', 'enabled'] as $field) {
                if (is_array($section[$field] ?? null)) {
                    $section[$field] = null;
                }
            }
            $sections[$key] = $section;
        }
        data_set($settings, 'layout.sections', $sections);

        foreach ([
            'foundation_name', 'school_name', 'report_title', 'school_address', 'school_contact',
            'score_label', 'predicate_label', 'description_label', 'footer_text',
            'show_predicate', 'show_description', 'parent_signature_label',
            'parent_signature_name', 'principal_signature_label', 'principal_name',
            'principal_identifier', 'homeroom_title', 'place', 'semester_status_label',
            'watermark_enabled', 'watermark_path', 'watermark_opacity',
            'watermark_position', 'watermark_width', 'report_layout.kop_alignment',
            'report_layout.show_logo', 'report_layout.logo_size',
            'report_layout.kop_title_spacing', 'report_layout.title_identity_spacing',
            'report_layout.identity_style', 'report_layout.identity_font_size',
            'report_layout.identity_table_spacing', 'report_layout.table_signature_spacing',
            'report_layout.subject_group_spacing', 'report_layout.subject_group_table_spacing',
            'report_layout.score_table_row_padding', 'report_layout.score_table_kktp_spacing',
            'report_layout.kktp_next_section_spacing', 'report_layout.signature_spacing',
            'report_layout.labels.student_name',
            'report_layout.labels.student_number', 'report_layout.labels.class',
            'report_layout.labels.semester', 'report_layout.labels.report_type',
            'report_layout.semester_value_override',
        ] as $path) {
            if (is_array(data_get($settings, $path))) {
                data_set($settings, $path, null);
            }
        }

        $data['settings'] = $settings;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return AssessmentReportTemplateResource::validateTemplateData($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview_template')
                ->label('Pratinjau Template')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn (): ?string => $this->templatePreviewUrl())
                ->visible(fn (): bool => $this->templatePreviewUrl() !== null)
                ->openUrlInNewTab()
                ->tooltip('Menampilkan pengaturan yang sudah disimpan dengan data contoh; simpan perubahan terlebih dahulu.'),
            Actions\DeleteAction::make()
                ->label('Hapus')
                ->visible(fn (): bool => AssessmentReportTemplateResource::canDelete($this->record))
                ->databaseTransaction()
                ->before(function (Actions\DeleteAction $action): void {
                    $template = ReportTemplate::query()
                        ->whereKey($this->record->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    abort_unless(AssessmentReportTemplateResource::canDelete($template), 403);

                    if ($message = AssessmentReportTemplateResource::deletionBlockedMessage($template)) {
                        \Filament\Notifications\Notification::make()
                            ->title('Template tidak dapat dihapus')
                            ->body($message)
                            ->warning()
                            ->persistent()
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }

    protected function templatePreviewUrl(): ?string
    {
        try {
            if (! isset($this->record)) {
                return null;
            }

            $routeKey = $this->record->getRouteKey();

            if ($routeKey === null || $routeKey === '') {
                return null;
            }

            return url('/admin/penilaian/pengaturan/template-rapor/'.rawurlencode((string) $routeKey).'/preview');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $freshRecord = $record->fresh();
        abort_unless($freshRecord && AssessmentReportTemplateResource::canEdit($freshRecord), 403);

        return parent::handleRecordUpdate($record, $data);
    }
}
