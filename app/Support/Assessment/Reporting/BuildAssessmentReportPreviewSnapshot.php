<?php

namespace App\Support\Assessment\Reporting;

use App\Contracts\SiteSettingsAccessor;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\ReportTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class BuildAssessmentReportPreviewSnapshot
{
    public function build(
        AssessmentPeriod $period,
        ReportTemplate $template,
        AssessmentPeriodStudent $student,
    ): ReportSnapshot {
        abort_unless((int) $student->assessment_period_id === (int) $period->getKey(), 404);

        $period->loadMissing('academicYear', 'semester');
        $student->loadMissing('results.assignment', 'homeroomReport');
        $homeroomAssignment = $period->homerooms()
            ->where('assessment_period_rombel_id', $student->assessment_period_rombel_id)
            ->first();
        $settings = app(AssessmentReportWatermark::class)->freezeSettings(
            is_array($template->settings) ? $template->settings : [],
        );
        $preflight = app(AssessmentReportPreflight::class)->inspect(
            $period,
            $template,
            [(int) $student->assessment_period_rombel_id],
        );
        data_set($settings, 'preview_data_incomplete', ! $preflight['ready']);

        $school = $this->schoolSnapshot();
        if (filled(data_get($settings, 'school_name'))) {
            $school['name'] = trim((string) data_get($settings, 'school_name'));
        }
        if (filled(data_get($settings, 'school_address'))) {
            $school['address'] = trim((string) data_get($settings, 'school_address'));
        }

        $homeroom = $student->homeroomReport;
        $subjectResults = $student->results
            ->filter(fn ($result): bool => (int) $result->assessment_period_id === (int) $period->getKey())
            ->sortBy(fn ($result): string => sprintf(
                '%04d|%04d|%010d',
                (int) ($result->assignment?->subject_group_sort_order_snapshot ?? 999),
                (int) ($result->assignment?->subject_sort_order_snapshot ?? 0),
                (int) $result->getKey(),
            ))
            ->map(function ($result): array {
                $assignment = $result->assignment;
                $detail = is_array($result->calculation_detail)
                    ? $result->calculation_detail
                    : (json_decode((string) $result->calculation_detail, true) ?: []);
                $precision = min(4, max(0, (int) data_get($detail, 'rounding_precision', 2)));

                return [
                    'subject_id' => $assignment?->assessment_subject_id,
                    'name' => $assignment?->subject_name_snapshot,
                    'teacher_name' => $assignment?->teacher_name_snapshot,
                    'group_code' => $assignment?->subject_group_code_snapshot ?: 'BELUM',
                    'group_name' => $assignment?->subject_group_name_snapshot ?: 'Belum Dikelompokkan',
                    'group_sort_order' => (int) ($assignment?->subject_group_sort_order_snapshot ?? 999),
                    'sort_order' => (int) ($assignment?->subject_sort_order_snapshot ?? 0),
                    'final_score' => $result->final_score !== null
                        ? number_format((float) $result->final_score, $precision, '.', '')
                        : null,
                    'predicate' => $result->predicate,
                    'description' => $result->description,
                    'calculation_detail' => $detail,
                    'formula_version' => $result->formula_version,
                ];
            })
            ->values()
            ->all();

        return new ReportSnapshot([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_student_id' => $student->getKey(),
            'assessment_report_template_id' => $template->getKey(),
            'revision' => 0,
            'template_version' => $template->version,
            'snapshot_data' => [
                'meta' => [
                    'revision' => 'PREVIEW',
                    'preview' => true,
                    'preview_data_incomplete' => ! $preflight['ready'],
                    'snapshotted_at' => now()->toIso8601String(),
                ],
                'school' => $school,
                'period' => [
                    'id' => $period->getKey(),
                    'code' => $period->code,
                    'name' => $period->name,
                    'type' => $period->type instanceof \BackedEnum ? $period->type->value : (string) $period->type,
                    'academic_year' => $period->academicYear?->name ?? $period->academicYear?->code,
                    'semester' => $period->semester?->name ?? $period->semester?->code,
                    'report_date' => $period->report_date?->format('d-m-Y'),
                ],
                'student' => [
                    'id' => $student->student_id,
                    'name' => $student->student_name_snapshot,
                    'nis' => $student->nis_snapshot,
                    'nisn' => $student->nisn_snapshot,
                    'gender' => $student->gender_snapshot,
                    'class_name' => $student->rombel_name_snapshot,
                ],
                'subjects' => $subjectResults,
                'homeroom' => [
                    'sick_days' => (int) ($homeroom?->sick_days ?? 0),
                    'permission_days' => (int) ($homeroom?->permission_days ?? 0),
                    'absent_days' => (int) ($homeroom?->absent_days ?? 0),
                    'spiritual_predicate' => $homeroom?->spiritual_predicate,
                    'spiritual_description' => $homeroom?->spiritual_description,
                    'social_predicate' => $homeroom?->social_predicate,
                    'social_description' => $homeroom?->social_description,
                    'extracurricular_data' => $homeroom?->extracurricular_data ?? [],
                    'achievement_data' => $homeroom?->achievement_data ?? [],
                    'homeroom_note' => $homeroom?->homeroom_note,
                    'promotion_status' => $homeroom?->promotion_status,
                ],
                'signatures' => $this->signatureSnapshot(
                    $settings,
                    $homeroomAssignment?->teacher_name_snapshot,
                    $period->report_date,
                ),
                'template' => [
                    'id' => $template->getKey(),
                    'code' => $template->code,
                    'version' => (int) $template->version,
                    'view_path' => $template->view_path,
                    'settings' => $settings,
                ],
            ],
        ]);
    }

    /**
     * @return array{name:string,address:?string,contact:?string,logo_data_uri:?string}
     */
    private function schoolSnapshot(): array
    {
        $settings = app(SiteSettingsAccessor::class);
        $profile = DB::getSchemaBuilder()->hasTable('profil_sekolahs')
            ? DB::table('profil_sekolahs')->first()
            : null;

        return [
            'name' => $profile?->nama_sekolah ?: $settings->siteName(),
            'address' => $profile?->alamat,
            'contact' => collect([$profile?->kontak_telepon, $profile?->kontak_email])->filter()->implode(' | ') ?: null,
            'logo_data_uri' => $this->logoDataUri($settings->logoPath()),
        ];
    }

    private function logoDataUri(?string $configuredPath): ?string
    {
        $path = trim((string) $configuredPath);
        if (preg_match('#^data:image/(?:png|jpeg|webp);base64,#i', $path) === 1) {
            return $path;
        }
        if (preg_match('#^https?://#i', $path) === 1) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }
        $path = ltrim(Str::after($path, '/storage/'), '/');
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }
        $absolute = Storage::disk('public')->path($path);
        $mime = mime_content_type($absolute);
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return null;
        }
        $contents = file_get_contents($absolute);

        return is_string($contents) ? 'data:'.$mime.';base64,'.base64_encode($contents) : null;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function signatureSnapshot(array $settings, ?string $homeroomTeacher, mixed $reportDate): array
    {
        $date = $reportDate instanceof \DateTimeInterface
            ? Carbon::instance($reportDate)->translatedFormat('d F Y')
            : null;
        $placeDate = collect([trim((string) data_get($settings, 'place', 'Bogor')), $date])->filter()->implode(', ');

        return [
            ['label' => data_get($settings, 'parent_signature_label', 'Orang Tua/Wali'), 'name' => '-', 'place_date' => ''],
            ['label' => data_get($settings, 'homeroom_title', 'Wali Kelas'), 'name' => $homeroomTeacher ?: '-', 'place_date' => $placeDate],
            ['label' => data_get($settings, 'principal_signature_label', 'Kepala Sekolah'), 'name' => data_get($settings, 'principal_name', '-'), 'identifier' => data_get($settings, 'principal_identifier'), 'place_date' => $placeDate],
        ];
    }
}
