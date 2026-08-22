<?php

namespace App\Support\Assessment\Reporting;

use App\Contracts\SiteSettingsAccessor;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AuditLog;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\ReportGenerationRun;
use App\Models\Assessment\ReportShareLink;
use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\ReportTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateReportSnapshotsAction
{
    /**
     * @return EloquentCollection<int, ReportSnapshot>
     */
    public function execute(
        AssessmentPeriod $period,
        ReportTemplate $template,
        int $generatedBy,
        bool $regenerate = false,
        ?string $reason = null,
    ): EloquentCollection {
        $actor = User::query()->findOrFail($generatedBy);
        Gate::forUser($actor)->authorize('create', ReportSnapshot::class);

        return DB::transaction(function () use (
            $actor,
            $period,
            $template,
            $generatedBy,
            $regenerate,
            $reason,
        ): EloquentCollection {
            $period = AssessmentPeriod::query()->lockForUpdate()->findOrFail($period->getKey());
            $template = ReportTemplate::query()
                ->lockForUpdate()
                ->findOrFail($template->getKey());

            $this->validateRequest($period, $template, $regenerate, $reason);

            $openRun = ReportGenerationRun::query()
                ->where('assessment_period_id', $period->getKey())
                ->where('assessment_report_template_id', $template->getKey())
                ->whereIn('status', ['prepared', 'running'])
                ->latest('revision')
                ->first();
            if ($regenerate && $openRun) {
                throw ValidationException::withMessages([
                    'reports' => "Revisi {$openRun->revision} masih terbuka. Hentikan atau mulai ulang revisi tersebut sebelum membuat revisi baru.",
                ]);
            }

            $existingRevision = (int) ReportSnapshot::query()
                ->where('assessment_period_id', $period->getKey())
                ->where('assessment_report_template_id', $template->getKey())
                ->max('revision');
            $periodStatus = $this->enumValue($period->status);
            $revisingPublished = $periodStatus === 'published' && $regenerate;
            $periodSettings = is_array($period->settings) ? $period->settings : [];
            $pendingTemplateId = (int) data_get($periodSettings, '_reporting.pending.template_id');
            $pendingRevision = (int) data_get($periodSettings, '_reporting.pending.revision');

            if ($periodStatus === 'locked' && $pendingRevision > 0) {
                if ($pendingTemplateId !== (int) $template->getKey()) {
                    throw ValidationException::withMessages([
                        'template' => 'Periode sedang menunggu PDF revisi dari template yang telah dipilih.',
                    ]);
                }

                if ($regenerate) {
                    throw ValidationException::withMessages([
                        'reports' => 'Revisi published masih diproses. Selesaikan atau buka kembali periode sebelum membuat revisi lain.',
                    ]);
                }
            }

            if ($periodStatus === 'published') {
                $publishedTemplateId = (int) data_get($periodSettings, '_reporting.published.template_id');

                if ($publishedTemplateId < 1) {
                    $publishedTemplateId = (int) ReportSnapshot::query()
                        ->where('assessment_period_id', $period->getKey())
                        ->orderByDesc('id')
                        ->value('assessment_report_template_id');
                }

                if ($publishedTemplateId < 1 || $publishedTemplateId !== (int) $template->getKey()) {
                    throw ValidationException::withMessages([
                        'template' => 'Revisi langsung pada periode terbit wajib memakai template yang sama. Gunakan alur buka kembali untuk mengganti template.',
                    ]);
                }

                if ($revisingPublished) {
                    Gate::forUser($actor)->authorize('publish', $period);
                }
            }

            if ($existingRevision > 0 && ! $regenerate) {
                return ReportSnapshot::query()
                    ->where('assessment_period_id', $period->getKey())
                    ->where('assessment_report_template_id', $template->getKey())
                    ->where('revision', $existingRevision)
                    ->orderBy('assessment_period_student_id')
                    ->get();
            }

            $revision = $existingRevision + 1;
            $students = DB::table('assessment_period_students')
                ->where('assessment_period_id', $period->getKey())
                ->where('is_active', true)
                ->orderBy('assessment_period_rombel_id')
                ->orderBy('student_name_snapshot')
                ->get();

            if ($students->isEmpty()) {
                throw ValidationException::withMessages([
                    'period' => 'Periode belum memiliki snapshot siswa aktif.',
                ]);
            }

            $periodRombelIds = $students
                ->pluck('assessment_period_rombel_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values();
            $run = ReportGenerationRun::query()->create([
                'assessment_period_id' => $period->getKey(),
                'assessment_report_template_id' => $template->getKey(),
                'revision' => $revision,
                'status' => 'prepared',
                'total_students' => $students->count(),
                'completed_students' => 0,
                'total_classes' => $periodRombelIds->count(),
                'completed_classes' => 0,
                'requested_by' => $generatedBy,
            ]);

            $results = DB::table('assessment_student_subject_results as results')
                ->join(
                    'assessment_period_assignments as assignments',
                    'assignments.id',
                    '=',
                    'results.assessment_period_assignment_id',
                )
                ->join(
                    'assessment_subjects as subjects',
                    'subjects.id',
                    '=',
                    'assignments.assessment_subject_id',
                )
                ->where('results.assessment_period_id', $period->getKey())
                ->select([
                    'results.assessment_period_student_id',
                    'results.final_score',
                    'results.predicate',
                    'results.description',
                    'results.calculation_detail',
                    'results.formula_version',
                    'assignments.assessment_subject_id',
                    'assignments.subject_name_snapshot',
                    'assignments.subject_group_code_snapshot',
                    'assignments.subject_group_name_snapshot',
                    'assignments.subject_group_sort_order_snapshot',
                    'assignments.subject_sort_order_snapshot',
                    'assignments.teacher_name_snapshot',
                ])
                ->orderBy('assignments.subject_group_sort_order_snapshot')
                ->orderBy('assignments.subject_sort_order_snapshot')
                ->orderBy('assignments.subject_name_snapshot')
                ->get()
                ->groupBy('assessment_period_student_id');

            $homeroomReports = DB::table('assessment_homeroom_reports')
                ->where('assessment_period_id', $period->getKey())
                ->get()
                ->keyBy('assessment_period_student_id');

            $homerooms = DB::table('assessment_period_homerooms')
                ->where('assessment_period_id', $period->getKey())
                ->get()
                ->keyBy('assessment_period_rombel_id');

            $academicYear = DB::table('assessment_academic_years')
                ->where('id', $period->assessment_academic_year_id)
                ->first();
            $semester = DB::table('assessment_semesters')
                ->where('id', $period->assessment_semester_id)
                ->first();
            $templateSettings = app(AssessmentReportWatermark::class)->freezeSettings(
                is_array($template->settings) ? $template->settings : [],
            );
            $collectPromotionStatus = data_get($period->settings, 'collect_promotion_status');
            if (! is_bool($collectPromotionStatus)) {
                $collectPromotionStatus = $this->enumValue($period->type) === 'asas';
            }
            $school = $this->schoolSnapshot();
            if (filled(data_get($templateSettings, 'school_name'))) {
                $school['name'] = trim((string) data_get($templateSettings, 'school_name'));
            }
            if (filled(data_get($templateSettings, 'school_address'))) {
                $school['address'] = trim((string) data_get($templateSettings, 'school_address'));
            }
            $snapshots = new EloquentCollection;

            foreach ($students as $student) {
                $homeroom = $homeroomReports->get($student->id);
                $homeroomAssignment = $homerooms->get($student->assessment_period_rombel_id);
                $snapshotData = [
                        'meta' => [
                            'revision' => $revision,
                            'snapshotted_at' => Carbon::now()->toIso8601String(),
                            'formula_versions' => $results
                                ->get($student->id, collect())
                                ->pluck('formula_version')
                                ->filter()
                                ->unique()
                                ->values()
                                ->all(),
                        ],
                        'school' => $school,
                        'period' => [
                            'id' => $period->getKey(),
                            'code' => $period->code,
                            'name' => $period->name,
                            'type' => $this->enumValue($period->type),
                            'academic_year' => $academicYear?->name ?? $academicYear?->code,
                            'semester' => $semester?->name ?? $semester?->code,
                            'report_date' => $period->report_date?->format('d-m-Y'),
                            'collect_promotion_status' => (bool) $collectPromotionStatus,
                        ],
                        'student' => [
                            'id' => $student->student_id,
                            'name' => $student->student_name_snapshot,
                            'nis' => $student->nis_snapshot,
                            'nisn' => $student->nisn_snapshot,
                            'gender' => $student->gender_snapshot,
                            'class_name' => $student->rombel_name_snapshot,
                        ],
                        'subjects' => $results
                            ->get($student->id, collect())
                            ->map(function (object $result): array {
                                $detail = $this->decodeJson($result->calculation_detail);
                                $precision = min(4, max(0, (int) data_get($detail, 'rounding_precision', 2)));

                                return [
                                    'subject_id' => $result->assessment_subject_id,
                                    'name' => $result->subject_name_snapshot,
                                    'teacher_name' => $result->teacher_name_snapshot,
                                    'group_code' => $result->subject_group_code_snapshot,
                                    'group_name' => $result->subject_group_name_snapshot,
                                    'group_sort_order' => (int) $result->subject_group_sort_order_snapshot,
                                    'sort_order' => (int) $result->subject_sort_order_snapshot,
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
                            ->all(),
                        'homeroom' => [
                            'sick_days' => (int) ($homeroom?->sick_days ?? 0),
                            'permission_days' => (int) ($homeroom?->permission_days ?? 0),
                            'absent_days' => (int) ($homeroom?->absent_days ?? 0),
                            'spiritual_predicate' => $homeroom?->spiritual_predicate,
                            'spiritual_description' => $homeroom?->spiritual_description,
                            'social_predicate' => $homeroom?->social_predicate,
                            'social_description' => $homeroom?->social_description,
                            'extracurricular_data' => $this->decodeJson($homeroom?->extracurricular_data),
                            'achievement_data' => $this->decodeJson($homeroom?->achievement_data),
                            'homeroom_note' => $homeroom?->homeroom_note,
                            'promotion_status' => $collectPromotionStatus
                                ? $homeroom?->promotion_status
                                : null,
                        ],
                        'signatures' => $this->signatureSnapshot(
                            $templateSettings,
                            $homeroomAssignment?->teacher_name_snapshot,
                            $period->report_date,
                        ),
                        'template' => [
                            'id' => $template->getKey(),
                            'code' => $template->code,
                            'version' => (int) $template->version,
                            'view_path' => $template->view_path,
                            'settings' => $templateSettings,
                        ],
                    ];
                $snapshot = ReportSnapshot::query()->create([
                    'assessment_period_id' => $period->getKey(),
                    'assessment_period_student_id' => $student->id,
                    'assessment_report_template_id' => $template->getKey(),
                    'assessment_report_generation_run_id' => $run->getKey(),
                    'revision' => $revision,
                    'template_version' => $template->version,
                    'snapshot_data' => $snapshotData,
                    'snapshot_checksum' => app(AssessmentSnapshotIntegrity::class)->checksum($snapshotData),
                    'generation_status' => 'ready',
                    'delivery_mode' => 'stream',
                    'pdf_path' => null,
                    'checksum' => null,
                    'error_message' => null,
                    'generated_at' => null,
                    'generated_by' => $generatedBy,
                ]);

                $snapshots->push($snapshot);

                AuditLog::query()->create([
                    'assessment_period_id' => $period->getKey(),
                    'actor_id' => $generatedBy,
                    'event' => $regenerate ? 'student_report_snapshot_regenerated' : 'student_report_snapshot_created',
                    'subject_type' => ReportSnapshot::class,
                    'subject_id' => $snapshot->getKey(),
                    'old_values' => null,
                    'new_values' => [
                        'revision' => $revision,
                        'template_id' => $template->getKey(),
                        'template_version' => $template->version,
                    ],
                    'reason' => $reason,
                    'ip_address' => request()?->ip(),
                    'user_agent' => mb_substr((string) request()?->userAgent(), 0, 500),
                    'created_at' => Carbon::now(),
                ]);

            }

            $run->forceFill(['completed_students' => $snapshots->count()])->save();

            if ($regenerate) {
                $oldSnapshotIds = ReportSnapshot::query()
                    ->where('assessment_period_id', $period->getKey())
                    ->whereNotIn('id', $snapshots->modelKeys())
                    ->pluck('id');

                $revokedLinks = ReportShareLink::query()
                    ->whereIn('assessment_report_snapshot_id', $oldSnapshotIds)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => Carbon::now()]);

                if ($revokedLinks > 0) {
                    AuditLog::query()->create([
                        'assessment_period_id' => $period->getKey(),
                        'actor_id' => $generatedBy,
                        'event' => 'report_share_links_revoked_for_revision',
                        'subject_type' => AssessmentPeriod::class,
                        'subject_id' => $period->getKey(),
                        'old_values' => ['active_share_links' => $revokedLinks],
                        'new_values' => ['active_share_links' => 0, 'revision' => $revision],
                        'reason' => $reason,
                        'ip_address' => request()?->ip(),
                        'user_agent' => mb_substr((string) request()?->userAgent(), 0, 500),
                        'created_at' => Carbon::now(),
                    ]);
                }
            }

            foreach ($periodRombelIds as $periodRombelId) {
                $artifact = ClassReportArtifact::query()->create([
                    'assessment_period_id' => $period->getKey(),
                    'assessment_period_rombel_id' => $periodRombelId,
                    'assessment_report_template_id' => $template->getKey(),
                    'assessment_report_generation_run_id' => $run->getKey(),
                    'revision' => $revision,
                    'generation_status' => 'not_scheduled',
                    'pdf_path' => null,
                    'checksum' => null,
                    'error_message' => null,
                    'queued_at' => Carbon::now(),
                    'started_at' => null,
                    'generated_at' => null,
                    'cache_expires_at' => null,
                    'generated_by' => $generatedBy,
                ]);

            }

            if ($revisingPublished) {
                $settings = is_array($period->settings) ? $period->settings : [];
                data_set($settings, '_reporting.pending', [
                    'template_id' => (int) $template->getKey(),
                    'revision' => $revision,
                    'started_at' => Carbon::now()->toIso8601String(),
                    'started_by' => $generatedBy,
                    'reason' => $reason,
                ]);
                $period->forceFill([
                    'status' => 'locked',
                    'settings' => $settings,
                ])->save();

                AuditLog::query()->create([
                    'assessment_period_id' => $period->getKey(),
                    'actor_id' => $generatedBy,
                    'event' => 'published_report_revision_started',
                    'subject_type' => AssessmentPeriod::class,
                    'subject_id' => $period->getKey(),
                    'old_values' => [
                        'status' => 'published',
                        'published_template_id' => data_get($settings, '_reporting.published.template_id'),
                        'published_revision' => data_get($settings, '_reporting.published.revision'),
                    ],
                    'new_values' => [
                        'status' => 'locked',
                        'pending_template_id' => (int) $template->getKey(),
                        'pending_revision' => $revision,
                    ],
                    'reason' => $reason,
                    'ip_address' => request()?->ip(),
                    'user_agent' => mb_substr((string) request()?->userAgent(), 0, 500),
                    'created_at' => Carbon::now(),
                ]);
            }

            return $snapshots;
        }, 3);
    }

    private function validateRequest(
        AssessmentPeriod $period,
        ReportTemplate $template,
        bool $regenerate,
        ?string $reason,
    ): void {
        $status = $this->enumValue($period->status);

        if (! in_array($status, ['locked', 'published'], true)) {
            throw ValidationException::withMessages([
                'period' => 'Snapshot rapor hanya dapat dibuat setelah periode dikunci.',
            ]);
        }

        if ($this->enumValue($period->type) !== $this->enumValue($template->type)) {
            throw ValidationException::withMessages([
                'template' => 'Jenis template harus sama dengan jenis periode penilaian.',
            ]);
        }

        if (! (bool) $template->is_active && ! $regenerate) {
            throw ValidationException::withMessages([
                'template' => 'Template rapor tidak aktif.',
            ]);
        }

        if ($regenerate && trim((string) $reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan wajib diisi ketika membuat revisi rapor.',
            ]);
        }
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
            'contact' => collect([
                $profile?->kontak_telepon,
                $profile?->kontak_email,
            ])->filter()->implode(' | ') ?: null,
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

        $path = Str::after($path, '/storage/');
        $path = ltrim($path, '/');

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $absolute = Storage::disk('public')->path($path);
        $mime = mime_content_type($absolute);

        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return null;
        }

        $contents = file_get_contents($absolute);

        return is_string($contents)
            ? 'data:'.$mime.';base64,'.base64_encode($contents)
            : null;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function signatureSnapshot(
        array $settings,
        ?string $homeroomTeacher,
        mixed $reportDate,
    ): array {
        $date = $reportDate instanceof \DateTimeInterface
            ? Carbon::instance($reportDate)->translatedFormat('d F Y')
            : null;
        $place = trim((string) data_get($settings, 'place', 'Bogor'));
        $placeDate = collect([$place, $date])->filter()->implode(', ');

        return [
            [
                'label' => data_get($settings, 'parent_signature_label', 'Orang Tua/Wali'),
                'name' => data_get($settings, 'parent_signature_name', '-'),
                'place_date' => '',
            ],
            [
                'label' => data_get(
                    $settings,
                    'homeroom_title',
                    data_get($settings, 'homeroom_signature_label', 'Wali Kelas'),
                ),
                'name' => $homeroomTeacher ?: '-',
                'place_date' => $placeDate,
            ],
            [
                'label' => data_get($settings, 'principal_signature_label', 'Kepala Sekolah'),
                'name' => data_get($settings, 'principal_name', '-'),
                'identifier' => data_get($settings, 'principal_identifier'),
                'place_date' => '',
            ],
        ];
    }

    private function enumValue(mixed $value): string
    {
        return strtolower($value instanceof \BackedEnum ? (string) $value->value : (string) $value);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
