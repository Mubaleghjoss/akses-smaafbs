<?php

namespace App\Http\Resources;

use App\Models\DataSiswa;
use App\Support\DataSiswa\DataSiswaSupport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DataSiswa */
class TagihanStudentResource extends JsonResource
{
    /**
     * @return array<string, int|string|null>
     */
    public function toArray(Request $request): array
    {
        $status = strtolower(trim((string) $this->status));
        $status = array_key_exists($status, DataSiswa::STATUS_OPTIONS) ? $status : 'aktif';

        $payload = [
            'source_id' => (int) $this->getKey(),
            'billing_code' => $this->nullableString($this->billing_code),
            'nipd' => $this->nullableString($this->nipd),
            'nisn' => $this->nullableString($this->nisn),
            'nama' => trim((string) $this->nama),
            'kelas' => $this->nullableString($this->rombel_saat_ini),
            'angkatan' => DataSiswaSupport::angkatanLabelForRombel($this->rombel_saat_ini),
            'wa_ortu' => $this->nullableString($this->wa_ortu),
            'status' => $status,
            'source_updated_at' => $this->updated_at?->copy()->utc()->toIso8601String(),
        ];

        return [
            ...$payload,
            'checksum' => hash('sha256', json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            )),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
