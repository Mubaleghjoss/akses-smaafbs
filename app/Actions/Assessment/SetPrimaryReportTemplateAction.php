<?php

namespace App\Actions\Assessment;

use App\Models\Assessment\ReportTemplate;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SetPrimaryReportTemplateAction
{
    public function __construct(private readonly AssessmentAuditLogger $audit) {}

    public function execute(User $actor, ReportTemplate $template): ReportTemplate
    {
        Gate::forUser($actor)->authorize('update', $template);

        return DB::transaction(function () use ($actor, $template): ReportTemplate {
            $template = ReportTemplate::query()
                ->whereKey($template->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('update', $template);

            $settings = is_array($template->settings) ? $template->settings : [];
            $missing = collect([
                'Nama sekolah' => data_get($settings, 'school_name'),
                'Nama kepala sekolah' => data_get($settings, 'principal_name'),
                'Tempat terbit' => data_get($settings, 'place'),
            ])->filter(fn (mixed $value): bool => blank($value))->keys();

            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'template' => 'Template belum dapat dijadikan utama. Lengkapi: '.$missing->implode(', ').'.',
                ]);
            }

            if ($template->effective_from?->isFuture()) {
                throw ValidationException::withMessages([
                    'template' => 'Tanggal berlaku template masih di masa depan. Aktifkan setelah tanggal tersebut atau ubah tanggal berlakunya.',
                ]);
            }

            $type = $template->type instanceof \BackedEnum
                ? $template->type->value
                : (string) $template->type;
            $previousPrimaryIds = ReportTemplate::query()
                ->where('type', $type)
                ->where('is_active', true)
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            ReportTemplate::query()
                ->where('type', $type)
                ->whereKeyNot($template->getKey())
                ->where('is_active', true)
                ->update(['is_active' => false, 'updated_at' => now()]);

            if (! $template->is_active) {
                $template->forceFill(['is_active' => true])->save();
            }

            $this->audit->record(
                actor: $actor,
                event: 'report_template.primary_changed',
                subject: $template,
                oldValues: ['primary_template_ids' => $previousPrimaryIds],
                newValues: [
                    'primary_template_id' => (int) $template->getKey(),
                    'type' => $type,
                ],
                reason: 'Template dipilih sebagai template utama melalui Pengaturan Penilaian.',
            );

            return $template->refresh();
        }, 3);
    }
}
