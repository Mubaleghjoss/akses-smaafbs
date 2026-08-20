<?php

namespace App\Support\StudentSync;

use App\Models\DataSiswa;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class StudentSyncMatcher
{
    /** @var array<int, string> */
    private const STRONG_IDENTIFIERS = ['nipd', 'nisn', 'billing_code'];

    /**
     * @param  array<string, mixed>  $source
     */
    public function match(array $source): StudentSyncMatchResult
    {
        $identifiers = $this->strongIdentifiers($source);
        $sourceId = $this->normalizeId($source['id'] ?? null);
        $idLackedEvidence = false;

        if ($sourceId !== null) {
            $idCandidate = DataSiswa::query()->find($sourceId);

            if ($idCandidate !== null) {
                [$matches, $conflicts] = $this->compareIdentifiers($identifiers, $idCandidate);

                if ($conflicts !== []) {
                    return new StudentSyncMatchResult(
                        StudentSyncMatchResult::CONFLICT,
                        null,
                        'contradictory_strong_identifiers',
                        ['id' => $sourceId, 'conflicts' => $conflicts],
                    );
                }

                if ($matches !== []) {
                    return new StudentSyncMatchResult(
                        StudentSyncMatchResult::MATCHED,
                        $idCandidate,
                        'matched_by_id',
                        ['id' => $sourceId, ...$matches],
                    );
                }

                $idLackedEvidence = true;
            }
        }

        if ($identifiers !== []) {
            $candidates = $this->strongCandidates($identifiers);

            if ($candidates->count() > 1) {
                return $this->candidateConflict('multiple_strong_candidates', $candidates);
            }

            if ($candidates->count() === 1) {
                /** @var DataSiswa $candidate */
                $candidate = $candidates->first();
                [$matches, $conflicts] = $this->compareIdentifiers($identifiers, $candidate);

                if ($conflicts !== []) {
                    return new StudentSyncMatchResult(
                        StudentSyncMatchResult::CONFLICT,
                        null,
                        'contradictory_strong_identifiers',
                        ['id' => $candidate->getKey(), 'conflicts' => $conflicts],
                    );
                }

                return new StudentSyncMatchResult(
                    StudentSyncMatchResult::MATCHED,
                    $candidate,
                    'matched_by_strong_identifier',
                    $matches,
                );
            }

            return new StudentSyncMatchResult(
                StudentSyncMatchResult::NOT_FOUND,
                null,
                'no_candidate',
                $identifiers,
            );
        }

        $fallback = $this->nameAndDateEvidence($source);

        if ($fallback !== null) {
            $candidates = DataSiswa::query()
                ->whereDate('tanggal_lahir', $fallback['tanggal_lahir'])
                ->get()
                ->filter(fn (DataSiswa $student): bool => $this->normalizeName($student->nama) === $fallback['nama'])
                ->values();

            if ($candidates->count() > 1) {
                return $this->candidateConflict('ambiguous_name_and_dob', $candidates);
            }

            if ($candidates->count() === 1) {
                return new StudentSyncMatchResult(
                    StudentSyncMatchResult::MATCHED,
                    $candidates->first(),
                    'matched_by_name_and_dob',
                    $fallback,
                );
            }
        }

        return new StudentSyncMatchResult(
            StudentSyncMatchResult::NOT_FOUND,
            null,
            $idLackedEvidence ? 'insufficient_id_evidence' : 'no_candidate',
            $sourceId !== null ? ['id' => $sourceId] : ($fallback ?? []),
        );
    }

    /**
     * @param  array<string, string>  $identifiers
     * @return Collection<int, DataSiswa>
     */
    private function strongCandidates(array $identifiers): Collection
    {
        return DataSiswa::query()
            ->where(function ($query) use ($identifiers): void {
                foreach ($identifiers as $column => $value) {
                    $query->orWhereRaw("TRIM({$column}) = ?", [$value]);
                }
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, string>  $identifiers
     * @return array{0: array<string, string>, 1: array<string, array{source: string, target: string}>}
     */
    private function compareIdentifiers(array $identifiers, DataSiswa $candidate): array
    {
        $matches = [];
        $conflicts = [];

        foreach ($identifiers as $column => $sourceValue) {
            $targetValue = $this->nonEmptyString($candidate->getAttribute($column));

            if ($targetValue === null) {
                continue;
            }

            if ($sourceValue === $targetValue) {
                $matches[$column] = $sourceValue;
            } else {
                $conflicts[$column] = ['source' => $sourceValue, 'target' => $targetValue];
            }
        }

        return [$matches, $conflicts];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, string>
     */
    private function strongIdentifiers(array $source): array
    {
        $identifiers = [];

        foreach (self::STRONG_IDENTIFIERS as $column) {
            $value = $this->nonEmptyString($source[$column] ?? null);

            if ($value !== null) {
                $identifiers[$column] = $value;
            }
        }

        return $identifiers;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array{nama: string, tanggal_lahir: string}|null
     */
    private function nameAndDateEvidence(array $source): ?array
    {
        $name = $this->normalizeName($source['nama'] ?? null);
        $date = $this->normalizeDate($source['tanggal_lahir'] ?? null);

        if ($name === '' || $date === null) {
            return null;
        }

        return ['nama' => $name, 'tanggal_lahir' => $date];
    }

    private function normalizeName(mixed $value): string
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';

        return mb_strtolower($name, 'UTF-8');
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeId(mixed $value): ?int
    {
        if (! is_numeric($value) || (int) $value < 1) {
            return null;
        }

        return (int) $value;
    }

    private function nonEmptyString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  Collection<int, DataSiswa>  $candidates
     */
    private function candidateConflict(string $reason, Collection $candidates): StudentSyncMatchResult
    {
        return new StudentSyncMatchResult(
            StudentSyncMatchResult::CONFLICT,
            null,
            $reason,
            ['candidate_ids' => $candidates->pluck('id')->sort()->values()->all()],
        );
    }
}
