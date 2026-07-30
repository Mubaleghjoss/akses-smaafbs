<x-filament-panels::page>
    <div class="assessment-master-import space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button wire:click="previewImport" icon="heroicon-o-magnifying-glass">
                Periksa &amp; Buat Pratinjau
            </x-filament::button>
            @if ($preview && empty($preview['errors']))
                <x-filament::button
                    wire:click="applyImport"
                    wire:confirm="Terapkan seluruh perubahan pada pratinjau ini?"
                    color="success"
                    icon="heroicon-o-check-circle"
                >
                    Terapkan Impor
                </x-filament::button>
            @endif
        </div>

        @if ($preview)
            @php
                $summary = $preview['summary'] ?? [];
            @endphp
            <section class="grid grid-cols-2 gap-3 md:grid-cols-5">
                @foreach ([
                    ['Baru', $summary['create'] ?? 0, 'emerald'],
                    ['Perubahan', $summary['update'] ?? 0, 'amber'],
                    ['Tetap', $summary['unchanged'] ?? 0, 'slate'],
                    ['Peringatan', $summary['warnings'] ?? 0, 'sky'],
                    ['Kesalahan', $summary['errors'] ?? 0, 'rose'],
                ] as [$label, $value, $tone])
                    <article class="min-w-0 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $value }}</p>
                    </article>
                @endforeach
            </section>

            @if (! empty($preview['errors']))
                <section class="rounded-xl border border-danger-200 bg-danger-50 p-4 dark:border-danger-500/30 dark:bg-danger-950/30">
                    <h2 class="font-semibold text-danger-800 dark:text-danger-200">Perbaiki sebelum diterapkan</h2>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-danger-700 dark:text-danger-300">
                        @foreach ($preview['errors'] as $error)
                            <li class="break-words">{{ $error }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if (! empty($preview['warnings']))
                <section class="rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-950/30">
                    <h2 class="font-semibold text-warning-800 dark:text-warning-200">Peringatan untuk diperiksa</h2>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-warning-700 dark:text-warning-300">
                        @foreach ($preview['warnings'] as $warning)
                            <li class="break-words">{{ $warning }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h2 class="font-semibold text-gray-950 dark:text-white">Rincian pratinjau</h2>
                <p class="mt-1 text-sm text-gray-500">Buka setiap kelompok untuk memastikan data baru dan perubahan sudah benar sebelum diterapkan.</p>

                @php
                    $previewGroups = [
                        'academic_years' => 'Tahun Pelajaran',
                        'semesters' => 'Semester',
                        'subjects' => 'Mata Pelajaran',
                        'teaching_assignments' => 'Penugasan Guru',
                        'homeroom_assignments' => 'Wali Kelas',
                    ];
                    $actionLabels = [
                        'create' => ['Baru', 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-300'],
                        'update' => ['Berubah', 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-300'],
                        'unchanged' => ['Tetap', 'bg-gray-100 text-gray-600 ring-gray-500/20 dark:bg-white/10 dark:text-gray-300'],
                    ];
                @endphp

                <div class="mt-4 space-y-3">
                    @foreach ($previewGroups as $key => $label)
                        @php
                            $rows = $preview['payload'][$key] ?? [];
                            $hasChanges = collect($rows)
                                ->contains(fn ($row) => ($row['action'] ?? '') !== 'unchanged');
                        @endphp
                        <details class="group overflow-hidden rounded-xl border border-gray-200 dark:border-white/10" @if ($hasChanges) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 bg-gray-50 px-4 py-3 dark:bg-white/5">
                                <span class="min-w-0 break-words text-sm font-semibold text-gray-900 dark:text-white">{{ $label }}</span>
                                <span class="shrink-0 rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-gray-600 shadow-sm dark:bg-gray-900 dark:text-gray-300">{{ count($rows) }} baris</span>
                            </summary>
                            <div class="grid gap-2 p-3 sm:grid-cols-2 xl:grid-cols-3">
                                @forelse ($rows as $row)
                                    @php
                                        $action = $row['action'] ?? 'unchanged';
                                        [$actionLabel, $actionClasses] = $actionLabels[$action] ?? [str($action)->headline(), $actionLabels['unchanged'][1]];
                                        $title = match ($key) {
                                            'academic_years' => ($row['code'] ?? '-').' — '.($row['name'] ?? '-'),
                                            'semesters' => ($row['code'] ?? '-').' — '.($row['name'] ?? '-'),
                                            'subjects' => ($row['code'] ?? '-').' — '.($row['name'] ?? '-'),
                                            'teaching_assignments' => ($row['rombel_name'] ?? '-').' — '.($row['subject_code'] ?? '-'),
                                            'homeroom_assignments' => ($row['rombel_name'] ?? '-').' — Wali Kelas',
                                            default => '-',
                                        };
                                        $detail = match ($key) {
                                            'academic_years' => trim(($row['starts_on'] ?? '').' s.d. '.($row['ends_on'] ?? '')),
                                            'semesters' => 'Tahun '.($row['academic_year_code'] ?? '-'),
                                            'subjects' => filled($row['description'] ?? null) ? $row['description'] : 'Urutan '.($row['sort_order'] ?? 0),
                                            'teaching_assignments' => ($row['teacher_name'] ?? '-').' · Semester '.($row['semester_code'] ?? '-'),
                                            'homeroom_assignments' => ($row['teacher_name'] ?? '-').' · Semester '.($row['semester_code'] ?? '-'),
                                            default => '',
                                        };
                                    @endphp
                                    <article class="min-w-0 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                                        <div class="flex min-w-0 items-start justify-between gap-2">
                                            <strong class="min-w-0 break-words text-sm text-gray-900 dark:text-white">{{ $title }}</strong>
                                            <span class="shrink-0 rounded-full px-2 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $actionClasses }}">{{ $actionLabel }}</span>
                                        </div>
                                        @if ($detail !== '')
                                            <p class="mt-2 break-words text-xs text-gray-500">{{ $detail }}</p>
                                        @endif
                                        <p class="mt-1 text-xs text-gray-500">{{ ($row['is_active'] ?? true) ? 'Aktif' : 'Tidak aktif' }}</p>
                                    </article>
                                @empty
                                    <p class="p-3 text-sm text-gray-500 sm:col-span-2 xl:col-span-3">Tidak ada baris valid pada kelompok ini.</p>
                                @endforelse
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
