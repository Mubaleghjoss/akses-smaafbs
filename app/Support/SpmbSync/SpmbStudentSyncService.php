<?php

namespace App\Support\SpmbSync;

use App\Models\DataSiswa;
use App\Models\SpmbSyncRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SpmbStudentSyncService
{
    public function __construct(
        private readonly SpmbStudentMapper $mapper,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $sources
     * @return array{rows:array<int, array<string, mixed>>,stats:array<string, int>}
     */
    public function preview(array $sources): array
    {
        $rows = collect($sources)
            ->map(fn (array $source): array => $this->previewRow($source))
            ->values();

        return [
            'rows' => $rows->all(),
            'stats' => [
                'fetched' => $rows->count(),
                'new' => $rows->where('status', 'baru')->count(),
                'update' => $rows->where('status', 'update')->count(),
                'unchanged' => $rows->where('status', 'tidak_berubah')->count(),
                'conflict' => $rows->where('status', 'konflik')->count(),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sources
     * @param  array<int|string, mixed>  $selectedSourceIds
     * @param  array<int|string, mixed>  $resolutions
     * @param  array<string, string>  $rombelPilihan  source_id => nama rombel;
     *         penempatan kelas ditentukan di app (bukan SPMB), karena app yang
     *         memegang daftar rombel. Kosong = biarkan tanpa rombel.
     * @return array<string, int>
     */
    public function apply(
        array $sources,
        array $selectedSourceIds,
        array $resolutions,
        ?int $userId,
        array $rombelPilihan = [],
    ): array {
        $preview = $this->preview($sources);
        $selected = collect($selectedSourceIds)->map(fn ($value): string => (string) $value)->all();

        $run = SpmbSyncRun::query()->create([
            'user_id' => $userId,
            'status' => 'berjalan',
            'fetched_count' => count($sources),
            'started_at' => now(),
        ]);

        $result = [
            'fetched' => count($sources),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'conflict' => 0,
            'skipped' => 0,
        ];

        try {
            DB::transaction(function () use (
                $preview,
                $selected,
                $resolutions,
                $rombelPilihan,
                &$result,
            ): void {
                foreach ($preview['rows'] as $row) {
                    $sourceId = (string) $row['source_id'];

                    if ($row['status'] === 'tidak_berubah') {
                        $result['unchanged']++;

                        continue;
                    }

                    if (! in_array($sourceId, $selected, true)) {
                        if ($row['status'] === 'konflik') {
                            $result['conflict']++;
                        }
                        $result['skipped']++;

                        continue;
                    }

                    if ($row['errors'] !== []) {
                        $result['conflict']++;
                        $result['skipped']++;

                        continue;
                    }

                    $target = $this->resolveTargetForApply($row, $resolutions[$sourceId] ?? null);
                    if ($row['status'] === 'konflik' && ! $target && ($resolutions[$sourceId] ?? null) !== 'new') {
                        $result['conflict']++;
                        $result['skipped']++;

                        continue;
                    }

                    if ($target && filled($target->spmb_nomor_pendaftaran)
                        && $target->spmb_nomor_pendaftaran !== $row['nomor_pendaftaran']) {
                        $result['conflict']++;
                        $result['skipped']++;

                        continue;
                    }

                    $payload = $row['payload'];
                    $syncMetadata = [
                        'spmb_nomor_pendaftaran' => $row['nomor_pendaftaran'],
                        'spmb_source_updated_at' => filled($row['source_updated_at'])
                            ? Carbon::parse($row['source_updated_at'])
                            : null,
                        'spmb_synced_at' => now(),
                        'spmb_checksum' => $row['checksum'],
                    ];

                    // Rombel ditentukan admin di app. Untuk siswa yang sudah ada,
                    // rombel hanya ditimpa bila admin memilih nilai baru — supaya
                    // rombel berjalan tidak terhapus tanpa sengaja.
                    $rombel = trim((string) ($rombelPilihan[$sourceId] ?? ''));

                    if ($target) {
                        $atribut = [...$payload, ...$syncMetadata];

                        if ($rombel !== '') {
                            $atribut['rombel_saat_ini'] = $rombel;
                        }

                        $target->fill($atribut);
                        $target->save();
                        $result['updated']++;

                        continue;
                    }

                    DataSiswa::query()->create([
                        ...$payload,
                        ...$syncMetadata,
                        'status' => 'aktif',
                        'rombel_saat_ini' => $rombel !== '' ? $rombel : null,
                    ]);
                    $result['created']++;
                }
            });

            $run->update([
                'status' => 'berhasil',
                'created_count' => $result['created'],
                'updated_count' => $result['updated'],
                'unchanged_count' => $result['unchanged'],
                'conflict_count' => $result['conflict'],
                'skipped_count' => $result['skipped'],
                'message' => $this->summary($result),
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'gagal',
                'message' => str($exception->getMessage())->limit(1000),
                'finished_at' => now(),
            ]);

            throw $exception;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function previewRow(array $source): array
    {
        $sourceId = trim((string) ($source['source_id'] ?? ''));
        $nomor = trim((string) ($source['nomor_pendaftaran'] ?? ''));
        $payload = $this->mapper->map($source);
        $candidates = $this->findCandidates($nomor, $payload);

        $errors = [];
        if ($sourceId === '' || $nomor === '') {
            $errors[] = 'Identitas sumber SPMB tidak lengkap.';
        }
        if (blank($payload['nama'] ?? null)) {
            $errors[] = 'Nama siswa kosong.';
        }
        if (! in_array($payload['jk'] ?? null, ['L', 'P'], true)) {
            $errors[] = 'Jenis kelamin belum valid.';
        }

        $target = $candidates->count() === 1 ? $candidates->first() : null;
        $differences = $target
            ? $this->differences($target, $payload, $source)
            : [];

        $status = match (true) {
            $errors !== [] => 'konflik',
            $candidates->count() > 1 => 'konflik',
            $target === null => 'baru',
            $differences === [] => 'tidak_berubah',
            default => 'update',
        };

        return [
            'source_id' => $sourceId,
            'nomor_pendaftaran' => $nomor,
            'source_updated_at' => $source['source_updated_at'] ?? null,
            'checksum' => $source['checksum'] ?? null,
            'nama' => $payload['nama'] ?? '-',
            'nisn' => $payload['nisn'] ?? null,
            'jk' => $payload['jk'] ?? null,
            'tanggal_lahir' => $payload['tanggal_lahir'] ?? null,
            // Jalur & kelas tujuan dari SPMB (api 1.1). Dipakai untuk memilih
            // rombel yang tepat: pindahan masuk rombel yang sedang berjalan,
            // siswa baru masuk rombel angkatan baru.
            'jenis_pendaftaran' => data_get($source, 'pendaftaran.jenis_pendaftaran'),
            'jenis_pendaftaran_label' => data_get($source, 'pendaftaran.jenis_pendaftaran_label')
                ?: (data_get($source, 'pendaftaran.jenis_pendaftaran') === 'pindahan' ? 'Siswa Pindahan' : 'Siswa Baru'),
            'kelas_tujuan' => data_get($source, 'pendaftaran.kelas_tujuan'),
            'kelas_tujuan_label' => data_get($source, 'pendaftaran.kelas_tujuan_label'),
            'masuk_rombel_berjalan' => (bool) data_get($source, 'pendaftaran.masuk_rombel_berjalan', false),
            'tahun_ajaran' => data_get($source, 'pendaftaran.tahun_ajaran'),
            'status' => $status,
            'payload' => $payload,
            'target_id' => $target?->getKey(),
            'target' => $target ? $this->serializeCandidate($target) : null,
            'candidates' => $candidates->map(fn (DataSiswa $student): array => $this->serializeCandidate($student))->all(),
            'differences' => $differences,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, DataSiswa>
     */
    private function findCandidates(string $nomor, array $payload): Collection
    {
        if ($nomor !== '') {
            $matches = DataSiswa::query()
                ->where('spmb_nomor_pendaftaran', $nomor)
                ->get();
            if ($matches->isNotEmpty()) {
                return $matches;
            }
        }

        if (filled($payload['nisn'] ?? null)) {
            $matches = DataSiswa::query()
                ->where('nisn', $payload['nisn'])
                ->get();
            if ($matches->isNotEmpty()) {
                return $matches;
            }
        }

        if (filled($payload['nama'] ?? null) && filled($payload['tanggal_lahir'] ?? null)) {
            return DataSiswa::query()
                ->whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower(trim((string) $payload['nama']))])
                ->whereDate('tanggal_lahir', $payload['tanggal_lahir'])
                ->get();
        }

        return collect();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $source
     * @return array<int, array{field:string,local:mixed,source:mixed}>
     */
    private function differences(DataSiswa $target, array $payload, array $source): array
    {
        $differences = [];

        foreach ($payload as $field => $value) {
            $localValue = $target->getAttribute($field);
            if ($localValue instanceof Carbon) {
                $localValue = $localValue->format('Y-m-d');
            }

            if ($this->comparable($localValue, $field) !== $this->comparable($value, $field)) {
                $differences[] = [
                    'field' => $field,
                    'local' => $localValue,
                    'source' => $value,
                ];
            }
        }

        if ($target->spmb_nomor_pendaftaran !== ($source['nomor_pendaftaran'] ?? null)
            || $target->spmb_checksum !== ($source['checksum'] ?? null)) {
            $differences[] = [
                'field' => 'tautan_spmb',
                'local' => $target->spmb_nomor_pendaftaran,
                'source' => $source['nomor_pendaftaran'] ?? null,
            ];
        }

        return $differences;
    }

    private function comparable(mixed $value, string $field): string
    {
        if (in_array($field, ['tinggi_badan', 'berat_badan', 'lingkar_kepala'], true)
            && is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
        }

        return mb_strtolower(trim((string) ($value ?? '')));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveTargetForApply(array $row, mixed $resolution): ?DataSiswa
    {
        if ($row['status'] !== 'konflik') {
            return filled($row['target_id']) ? DataSiswa::query()->find($row['target_id']) : null;
        }

        if ($resolution === 'new') {
            return null;
        }

        if (is_numeric($resolution)) {
            return DataSiswa::query()->find((int) $resolution);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCandidate(DataSiswa $student): array
    {
        return [
            'id' => $student->getKey(),
            'nama' => $student->nama,
            'nisn' => $student->nisn,
            'rombel' => $student->rombel_saat_ini,
            'spmb_nomor_pendaftaran' => $student->spmb_nomor_pendaftaran,
        ];
    }

    /**
     * @param  array<string, int>  $result
     */
    private function summary(array $result): string
    {
        return sprintf(
            'Dibuat %d, diperbarui %d, tidak berubah %d, konflik %d, dilewati %d.',
            $result['created'],
            $result['updated'],
            $result['unchanged'],
            $result['conflict'],
            $result['skipped'],
        );
    }
}
