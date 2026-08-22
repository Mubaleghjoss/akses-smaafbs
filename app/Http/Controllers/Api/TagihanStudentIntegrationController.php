<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagihanStudentResource;
use App\Models\DataSiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagihanStudentIntegrationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:'.config('tagihan_student_integration.max_per_page', 100),
            ],
        ]);

        $students = DataSiswa::query()
            ->select([
                'id',
                'billing_code',
                'nipd',
                'nisn',
                'nama',
                'rombel_saat_ini',
                'wa_ortu',
                'status',
                'updated_at',
            ])
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 100))
            ->withQueryString();

        return TagihanStudentResource::collection($students)
            ->response()
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
