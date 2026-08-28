<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Http\Controllers\Controller;
use App\Models\DataSiswa;
use App\Models\PerpustakaanLiterasiDispensation;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Support\Perpustakaan\LiteracyDispensationWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'note' => [
                Rule::requiredIf($request->input('reason') === PerpustakaanLiterasiDispensation::REASON_PERMISSION),
                'nullable',
                'string',
                'min:5',
                'max:1000',
            ],
        ], [
            'note.required' => 'Keterangan izin wajib ditulis.',
            'note.min' => 'Keterangan izin minimal 5 karakter.',
        ]);

        LiteracyDispensationWriter::assign(
            $material,
            $student,
            $validated['reason'],
            $validated['note'] ?? null,
            $request->user(),
        );

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
