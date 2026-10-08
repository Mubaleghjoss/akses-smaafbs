<?php

namespace App\Support\Assessment\Reporting;

use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\ReportTemplate;

final class BuildAssessmentReportTemplatePreviewSnapshot
{
    /**
     * Build fictional data solely for inspecting a saved template. Nothing is persisted.
     */
    public function build(ReportTemplate $template): ReportSnapshot
    {
        $type = $template->type instanceof \BackedEnum ? $template->type->value : (string) $template->type;
        // Legacy templates may contain stale watermark paths or malformed values. A
        // preview must remain usable even when the saved template predates validation.
        $rawSettings = is_array($template->settings) ? $template->settings : [];
        try {
            $settings = app(AssessmentReportWatermark::class)->freezeSettings($rawSettings);
        } catch (\Throwable) {
            $settings = $rawSettings;
            unset($settings['watermark_path'], $settings['watermark_data_uri']);
            data_set($settings, 'watermark_enabled', false);
        }
        $schoolName = trim((string) data_get($settings, 'school_name')) ?: 'SMA Contoh Nusantara';

        return new ReportSnapshot([
            'assessment_report_template_id' => $template->getKey(),
            'revision' => 0,
            'template_version' => $template->version,
            'snapshot_data' => [
                'meta' => [
                    'revision' => 'TEMPLATE PREVIEW',
                    'preview' => true,
                    'sample_data' => true,
                ],
                'school' => [
                    'foundation_name' => trim((string) data_get($settings, 'foundation_name')) ?: 'YAYASAN CONTOH PENDIDIKAN',
                    'name' => $schoolName,
                    'address' => trim((string) data_get($settings, 'school_address')) ?: 'Jl. Pendidikan No. 8, Indonesia',
                    'contact' => trim((string) data_get($settings, 'school_contact')) ?: 'info@contoh.sch.id | 0812-0000-0000',
                    'logo_data_uri' => null,
                ],
                'period' => [
                    'code' => 'CONTOH-2026',
                    'name' => 'Periode Contoh',
                    'type' => $type,
                    'academic_year' => '2025/2026',
                    'semester' => $type === 'asas' ? 'GENAP' : 'GANJIL',
                    'report_date' => '17-08-2026',
                    'collect_promotion_status' => $type === 'asas',
                ],
                'student' => [
                    'name' => 'Siswa Contoh',
                    'nis' => '260001',
                    'nisn' => '0123456789',
                    'gender' => 'L',
                    'class_name' => 'XI IPA 1',
                ],
                'subjects' => [
                    ['name' => 'Pendidikan Agama', 'group_code' => 'A', 'group_name' => 'Kelompok Umum', 'group_sort_order' => 1, 'sort_order' => 1, 'final_score' => '90', 'predicate' => 'A', 'description' => 'Menunjukkan penguasaan materi yang sangat baik.'],
                    ['name' => 'Matematika', 'group_code' => 'A', 'group_name' => 'Kelompok Umum', 'group_sort_order' => 1, 'sort_order' => 2, 'final_score' => '88', 'predicate' => 'A', 'description' => 'Mampu menyelesaikan soal dengan baik.'],
                    ['name' => 'Bahasa Indonesia', 'group_code' => 'B', 'group_name' => 'Kelompok Pilihan', 'group_sort_order' => 2, 'sort_order' => 1, 'final_score' => '86', 'predicate' => 'A', 'description' => 'Aktif dan teliti dalam pembelajaran.'],
                ],
                'homeroom' => [
                    'sick_days' => 1,
                    'permission_days' => 0,
                    'absent_days' => 0,
                    'spiritual_predicate' => 'A',
                    'spiritual_description' => 'Sangat baik.',
                    'social_predicate' => 'A',
                    'social_description' => 'Sangat baik.',
                    'extracurricular_data' => [['name' => 'Pramuka', 'predicate' => 'A', 'description' => 'Sangat baik.']],
                    'achievement_data' => [],
                    'homeroom_note' => 'Pertahankan semangat belajar dan akhlak yang baik.',
                    'promotion_status' => 'Naik kelas',
                ],
                'signatures' => [
                    ['label' => data_get($settings, 'parent_signature_label', 'Orang Tua/Wali'), 'name' => '-', 'place_date' => ''],
                    ['label' => data_get($settings, 'homeroom_title', 'Wali Kelas'), 'name' => 'Guru Contoh, S.Pd.', 'place_date' => 'Bogor, 17 Agustus 2026'],
                    ['label' => data_get($settings, 'principal_signature_label', 'Kepala Sekolah'), 'name' => data_get($settings, 'principal_name', 'Kepala Sekolah Contoh'), 'identifier' => data_get($settings, 'principal_identifier'), 'place_date' => 'Bogor, 17 Agustus 2026'],
                ],
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
}
