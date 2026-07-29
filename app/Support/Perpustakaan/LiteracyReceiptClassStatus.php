<?php

namespace App\Support\Perpustakaan;

use App\Models\PerpustakaanLiterasiDispensation;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class LiteracyReceiptClassStatus
{
    /**
     * @param  array<string, mixed>  $receipt
     * @return array<string, mixed>|null
     */
    public function forReceipt(array $receipt): ?array
    {
        $materialId = (int) ($receipt['material_id'] ?? 0);
        $studentClass = trim((string) ($receipt['student_class'] ?? ''));
        $currentStudentId = (int) ($receipt['student_id'] ?? 0);

        if ($materialId < 1 || $studentClass === '') {
            return null;
        }

        $students = DB::table('data_siswa as students')
            ->leftJoin('perpustakaan_literasi_responses as responses', function (JoinClause $join) use ($materialId): void {
                $join->on('responses.data_siswa_id', '=', 'students.id')
                    ->where('responses.material_id', '=', $materialId);
            })
            ->leftJoin('perpustakaan_literasi_dispensations as dispensations', function (JoinClause $join) use ($materialId): void {
                $join->on('dispensations.data_siswa_id', '=', 'students.id')
                    ->where('dispensations.material_id', '=', $materialId)
                    ->whereNull('dispensations.deleted_at');
            })
            ->where('students.status', 'aktif')
            ->where('students.rombel_saat_ini', $studentClass)
            ->orderBy('students.nama')
            ->get([
                'students.id as student_id',
                'students.nama as name',
                'students.rombel_saat_ini as class',
                'responses.id as response_id',
                'responses.deleted_at as response_deleted_at',
                'dispensations.id as dispensation_id',
                'dispensations.reason as dispensation_reason',
            ]);

        $completed = [];
        $missing = [];
        $dispensated = [];
        $needsTeacherReview = 0;

        foreach ($students as $student) {
            $row = [
                'student_id' => (int) $student->student_id,
                'name' => (string) $student->name,
                'class' => trim((string) $student->class) ?: '-',
                'is_current' => (int) $student->student_id === $currentStudentId,
            ];

            if ($student->response_id !== null && $student->response_deleted_at === null) {
                $completed[] = $row;

                continue;
            }

            if ($student->response_id === null && $student->dispensation_id !== null) {
                $dispensated[] = $row + [
                    'reason' => (string) $student->dispensation_reason,
                    'reason_label' => PerpustakaanLiterasiDispensation::reasonOptions()[$student->dispensation_reason]
                        ?? 'Dispensasi',
                ];

                continue;
            }

            if ($student->response_id !== null) {
                $needsTeacherReview++;

                continue;
            }

            $missing[] = $row;
        }

        return [
            'class' => $studentClass,
            'active_total' => $students->count(),
            'completed_total' => count($completed),
            'missing_total' => count($missing),
            'dispensation_total' => count($dispensated),
            'needs_teacher_review_total' => $needsTeacherReview,
            'completed_students' => $completed,
            'missing_students' => $missing,
            'dispensated_students' => $dispensated,
        ];
    }
}
