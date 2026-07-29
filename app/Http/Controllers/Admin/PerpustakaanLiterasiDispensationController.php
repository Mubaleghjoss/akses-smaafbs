<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Http\Controllers\Controller;
use App\Models\DataSiswa;
use App\Models\PerpustakaanLiterasiDispensation;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PerpustakaanLiterasiDispensationController extends Controller
{
    public function store(
        Request $request,
        PerpustakaanLiterasiMaterial $material,
        DataSiswa $student,
    ): RedirectResponse {
        $this->authorizeMaterial($material);

        $validated = $request->validate([
            'reason' => [
                'required',
                Rule::in(array_keys(PerpustakaanLiterasiDispensation::reasonOptions())),
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $material, $student, $validated): void {
            $lockedStudent = DataSiswa::query()
                ->lockForUpdate()
                ->findOrFail($student->getKey());

            if ($lockedStudent->status !== 'aktif') {
                throw ValidationException::withMessages([
                    'student' => 'Dispensasi hanya dapat diberikan kepada siswa aktif.',
                ]);
            }

            $hasResponse = PerpustakaanLiterasiResponse::withTrashed()
                ->where('material_id', $material->getKey())
                ->where('data_siswa_id', $lockedStudent->getKey())
                ->exists();

            if ($hasResponse) {
                throw ValidationException::withMessages([
                    'student' => 'Siswa sudah memiliki jawaban aktif atau jawaban di Sampah.',
                ]);
            }

            $dispensation = PerpustakaanLiterasiDispensation::withTrashed()
                ->firstOrNew([
                    'material_id' => $material->getKey(),
                    'data_siswa_id' => $lockedStudent->getKey(),
                ]);

            $dispensation->forceFill([
                'reason' => $validated['reason'],
                'student_name_snapshot' => trim((string) $lockedStudent->nama),
                'student_class_snapshot' => trim((string) $lockedStudent->rombel_saat_ini) ?: null,
                'confirmed_by' => $request->user()?->getKey(),
                'confirmed_at' => now(),
                'note' => filled($validated['note'] ?? null)
                    ? trim((string) $validated['note'])
                    : null,
                'deleted_at' => null,
            ])->save();
        });

        return back()->with(
            'success',
            'Status '.$student->nama.' ditetapkan sebagai '
                .PerpustakaanLiterasiDispensation::reasonOptions()[$validated['reason']].'.',
        );
    }

    public function destroy(
        PerpustakaanLiterasiMaterial $material,
        DataSiswa $student,
    ): RedirectResponse {
        $this->authorizeMaterial($material);

        $dispensation = PerpustakaanLiterasiDispensation::query()
            ->where('material_id', $material->getKey())
            ->where('data_siswa_id', $student->getKey())
            ->firstOrFail();

        $dispensation->delete();

        return back()->with('success', 'Dispensasi '.$student->nama.' dibatalkan.');
    }

    protected function authorizeMaterial(PerpustakaanLiterasiMaterial $material): void
    {
        abort_unless(
            PerpustakaanLiterasiMaterialResource::canEdit($material),
            403,
        );
    }
}
