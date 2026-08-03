@php
    $summary = $analytics['grading_summary'] ?? [];
    $classActivity = $analytics['class_activity'] ?? [];
    $classRanking = $analytics['class_response_ranking'] ?? [];
    $classCorrectRanking = $analytics['class_correct_ranking'] ?? [];
    $leastClassRanking = $analytics['least_class_response_ranking'] ?? [];
    $studentRankingByClass = $analytics['student_correct_ranking_by_class'] ?? [];
    $studentWrongRanking = $analytics['student_wrong_ranking'] ?? [];
    $missingStudents = $analytics['missing_students'] ?? [];
    $plagiarismClassRanking = $analytics['plagiarism_class_ranking'] ?? [];
    $plagiarismStudentRanking = $analytics['plagiarism_student_ranking'] ?? [];
    $materialCompletion = $analytics['material_completion'] ?? null;
    $compact = (bool) ($compact ?? false);

    $formatNumber = fn (int|float $value): string => number_format($value, 0, ',', '.');
    $formatPercent = fn (int|float $value): string => number_format((float) $value, 1, ',', '.').'%';
    $completionShareText = is_array($materialCompletion) && isset($material)
        ? \App\Support\Perpustakaan\LiteracyCompletionShareText::make($material, $materialCompletion)
        : null;
    $completionShareId = isset($material)
        ? 'literasi-completion-share-'.(int) $material->getKey()
        : null;
@endphp

<section class="literasi-analytics{{ $compact ? ' literasi-analytics--compact' : '' }}" aria-label="{{ $title ?? 'Analisa Literasi' }}">
    <header class="literasi-analytics__header">
        <div class="literasi-analytics__heading">
            <h2 class="literasi-analytics__title">{{ $title ?? 'Analisa Literasi' }}</h2>
            <p class="literasi-analytics__description">{{ $description ?? 'Rekap bulan berjalan.' }}</p>
        </div>
        <div class="literasi-analytics__period">Periode: {{ $analytics['period_label'] ?? '-' }}</div>
    </header>

    <div class="literasi-analytics__summary-grid">
        <article class="literasi-metric-card">
            <span class="literasi-metric-card__label">Responden Bulan Ini</span>
            <strong class="literasi-metric-card__value">{{ $formatNumber((int) ($summary['responses'] ?? 0)) }}</strong>
            <small class="literasi-metric-card__detail">
                {{ $formatNumber((int) ($summary['response_records'] ?? $summary['responses'] ?? 0)) }} jawaban
                + {{ $formatNumber((int) ($summary['dispensations'] ?? 0)) }} dispensasi
            </small>
        </article>

        <article class="literasi-metric-card">
            <span class="literasi-metric-card__label">Jawaban Dinilai</span>
            <strong class="literasi-metric-card__value">
                {{ $formatNumber((int) ($summary['graded_answers'] ?? 0)) }}/{{ $formatNumber((int) ($summary['total_answers'] ?? 0)) }}
            </strong>
        </article>

        <article class="literasi-metric-card literasi-metric-card--success">
            <span class="literasi-metric-card__label">Jawaban Benar</span>
            <strong class="literasi-metric-card__value">{{ $formatNumber((int) ($summary['correct_answers'] ?? 0)) }}</strong>
        </article>

        @unless($compact)
            <article class="literasi-metric-card">
                <span class="literasi-metric-card__label">Akurasi Dinilai</span>
                <strong class="literasi-metric-card__value">{{ $formatPercent((float) ($summary['accuracy'] ?? 0)) }}</strong>
            </article>

            <details class="literasi-metric-card literasi-metric-card--danger" id="literasi-plagiarism-students-card">
                <summary class="cursor-pointer">
                    <span class="literasi-metric-card__label">Siswa Plagiasi</span>
                    <strong class="literasi-metric-card__value">{{ $formatNumber(count($plagiarismStudentRanking)) }}</strong>
                </summary>
                <div class="mt-3 space-y-2 text-xs">
                    @forelse($plagiarismStudentRanking as $row)
                        <div class="flex items-start justify-between gap-2 rounded-lg bg-white/70 px-2 py-1 dark:bg-white/10">
                            <span>{{ $row['name'] }} <span class="opacity-70">({{ $row['class'] }})</span></span>
                            <strong>{{ $formatNumber((int) $row['total']) }}</strong>
                        </div>
                    @empty
                        <div class="text-xs opacity-80">Belum ada siswa plagiasi pada periode ini.</div>
                    @endforelse
                </div>
            </details>
        @endunless

        <article class="literasi-metric-card literasi-metric-card--danger">
            <span class="literasi-metric-card__label">Plagiasi Terkonfirmasi</span>
            <strong class="literasi-metric-card__value">{{ $formatNumber((int) ($summary['confirmed_plagiarism'] ?? 0)) }}</strong>
        </article>
    </div>

    @if(is_array($materialCompletion))
        <article class="literasi-panel literasi-completion">
            <div class="literasi-completion__header">
                <div>
                    <h3 class="literasi-panel__title">Status Pengisian Materi</h3>
                    <p class="literasi-analytics__description">Seluruh waktu untuk semua siswa aktif. Responden adalah jawaban nyata + dispensasi; jawaban di Sampah tetap dipisahkan.</p>
                </div>
                <div class="literasi-completion__header-tools">
                    <span class="literasi-analytics__period">Penyelesaian: {{ $formatPercent((float) ($materialCompletion['completion_percentage'] ?? 0)) }}</span>

                    @if($completionShareText !== null && $completionShareId !== null)
                        <div class="literasi-completion__share">
                            <button
                                type="button"
                                class="literasi-completion__copy-button js-literacy-completion-copy"
                                data-copy-target="{{ $completionShareId }}"
                                data-default-label="Salin daftar untuk WhatsApp"
                                aria-describedby="{{ $completionShareId }}-status"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M8 7V5.75A2.75 2.75 0 0 1 10.75 3h7.5A2.75 2.75 0 0 1 21 5.75v7.5A2.75 2.75 0 0 1 18.25 16H17v1.25A2.75 2.75 0 0 1 14.25 20h-7.5A2.75 2.75 0 0 1 4 17.25v-7.5A2.75 2.75 0 0 1 6.75 7H8Zm2 0h4.25A2.75 2.75 0 0 1 17 9.75V14h1.25c.97 0 1.75-.78 1.75-1.75v-6.5C20 4.78 19.22 4 18.25 4h-7.5C9.78 4 9 4.78 9 5.75V7h1Zm-3.25 1C5.78 8 5 8.78 5 9.75v7.5C5 18.22 5.78 19 6.75 19h7.5c.97 0 1.75-.78 1.75-1.75v-7.5C16 8.78 15.22 8 14.25 8h-7.5Z" fill="currentColor"/>
                                </svg>
                                <span>Salin daftar untuk WhatsApp</span>
                            </button>
                            <span class="literasi-completion__copy-status" id="{{ $completionShareId }}-status" aria-live="polite"></span>
                            <textarea id="{{ $completionShareId }}" class="js-literacy-completion-share-text" hidden readonly>{{ $completionShareText }}</textarea>
                        </div>
                    @endif
                </div>
            </div>

            <div class="literasi-completion__metrics">
                <div><span>Siswa Aktif</span><strong>{{ $formatNumber((int) ($materialCompletion['active_total'] ?? 0)) }}</strong></div>
                <div class="is-success"><span>Sudah Mengisi</span><strong>{{ $formatNumber((int) ($materialCompletion['completed_total'] ?? 0)) }}</strong></div>
                <div class="is-info"><span>Dispensasi</span><strong>{{ $formatNumber((int) ($materialCompletion['dispensation_total'] ?? 0)) }}</strong></div>
                <div class="is-warning"><span>Belum Mengisi</span><strong>{{ $formatNumber((int) ($materialCompletion['missing_total'] ?? 0)) }}</strong></div>
                <div class="is-danger"><span>Di Sampah</span><strong>{{ $formatNumber((int) ($materialCompletion['trashed_total'] ?? 0)) }}</strong></div>
                <div class="is-primary"><span>Total Responden</span><strong>{{ $formatNumber((int) ($materialCompletion['respondent_total'] ?? 0)) }}</strong></div>
            </div>

            <div class="literasi-completion__classes">
                @forelse(($materialCompletion['classes'] ?? []) as $class)
                    <details class="literasi-completion__class" @if(($class['missing_total'] ?? 0) > 0 || ($class['trashed_total'] ?? 0) > 0) open @endif>
                        <summary>
                            <strong>{{ $class['class'] }}</strong>
                            <span>{{ $formatNumber((int) $class['respondent_total']) }}/{{ $formatNumber((int) $class['active_total']) }} responden</span>
                            <span>{{ $formatNumber((int) $class['completed_total']) }} jawaban + {{ $formatNumber((int) $class['dispensation_total']) }} dispensasi</span>
                            <span class="is-warning">{{ $formatNumber((int) $class['missing_total']) }} belum</span>
                            @if(($class['trashed_total'] ?? 0) > 0)
                                <span class="is-danger">{{ $formatNumber((int) $class['trashed_total']) }} di Sampah</span>
                            @endif
                        </summary>

                        <div class="literasi-completion__lists">
                            <section>
                                <h4>Belum Mengisi</h4>
                                @forelse(($class['missing_students'] ?? []) as $student)
                                    <div class="literasi-completion__student-row">
                                        <span class="literasi-completion__student">{{ $student['name'] }}</span>
                                        @if(($canManageDispensations ?? false) && isset($material))
                                            <div class="literasi-completion__actions">
                                                @foreach(\App\Models\PerpustakaanLiterasiDispensation::reasonOptions() as $reason => $label)
                                                    @if($reason === \App\Models\PerpustakaanLiterasiDispensation::REASON_PERMISSION)
                                                        @php($permissionModalId = 'literacy-permission-'.$material->getKey().'-'.$student['student_id'])
                                                        <button type="button" class="literasi-completion__action literasi-completion__action--permission" x-on:click="$dispatch('open-modal', { id: '{{ $permissionModalId }}' })">Izin</button>
                                                        <x-filament::modal :id="$permissionModalId" width="md">
                                                            <x-slot name="heading">Tetapkan Status Izin</x-slot>
                                                            <x-slot name="description">{{ $student['name'] }} · {{ $student['class'] }}</x-slot>
                                                            <form method="post" action="{{ route('admin.perpustakaan-literasi.dispensations.store', [$material, $student['student_id']]) }}" class="literasi-permission-form">
                                                                @csrf
                                                                <input type="hidden" name="reason" value="permission">
                                                                <label>
                                                                    <span>Keterangan izin <strong>*</strong></span>
                                                                    <textarea name="note" rows="4" minlength="5" maxlength="1000" required placeholder="Contoh: Mengikuti kegiatan keluarga yang telah dikonfirmasi wali kelas."></textarea>
                                                                    <small>Wajib 5–1.000 karakter. Keterangan hanya tampil untuk admin dan salinan daftar petugas.</small>
                                                                </label>
                                                                <div class="literasi-permission-form__actions">
                                                                    <x-filament::button type="button" color="gray" x-on:click="$dispatch('close-modal', { id: '{{ $permissionModalId }}' })">Batal</x-filament::button>
                                                                    <x-filament::button type="submit" color="primary">Simpan Izin</x-filament::button>
                                                                </div>
                                                            </form>
                                                        </x-filament::modal>
                                                    @else
                                                        <form method="post" action="{{ route('admin.perpustakaan-literasi.dispensations.store', [$material, $student['student_id']]) }}">
                                                            @csrf
                                                            <input type="hidden" name="reason" value="{{ $reason }}">
                                                            <button type="submit" class="literasi-completion__action literasi-completion__action--{{ $reason === 'sick' ? 'sick' : 'mt' }}" onclick="return confirm('Tetapkan status {{ $label }}?')">{{ $label }}</button>
                                                        </form>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="literasi-empty-state">Tidak ada siswa yang belum mengisi.</p>
                                @endforelse
                            </section>

                            @if(($class['dispensation_total'] ?? 0) > 0)
                                <section class="is-dispensation">
                                    <h4>Dispensasi</h4>
                                    @foreach(($class['dispensated_students'] ?? []) as $student)
                                        <div class="literasi-completion__student-row">
                                            <span class="literasi-completion__student">
                                                {{ $student['name'] }}
                                                <small>{{ $student['reason_label'] }} · {{ $student['confirmed_at'] ?? '-' }}@if(filled($student['confirmed_by'] ?? null)) · {{ $student['confirmed_by'] }}@endif</small>
                                                @if(filled($student['note'] ?? null))
                                                    <small class="literasi-completion__dispensation-note">{{ $student['note'] }}</small>
                                                @endif
                                            </span>
                                            @if(($canManageDispensations ?? false) && isset($material))
                                                <form method="post" action="{{ route('admin.perpustakaan-literasi.dispensations.destroy', [$material, $student['student_id']]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="literasi-completion__action literasi-completion__action--cancel" onclick="return confirm('Batalkan dispensasi ini?')">Batalkan</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endforeach
                                </section>
                            @endif

                            @if(($class['trashed_total'] ?? 0) > 0)
                                <section class="is-trash">
                                    <h4>Jawaban di Sampah</h4>
                                    @foreach(($class['trashed_students'] ?? []) as $student)
                                        <div class="literasi-completion__student">{{ $student['name'] }}</div>
                                    @endforeach
                                    <p class="literasi-completion__hint">Restore atau hapus permanen melalui pengelolaan responden materi.</p>
                                </section>
                            @endif
                        </div>
                    </details>
                @empty
                    <p class="literasi-empty-state">Data siswa aktif belum tersedia.</p>
                @endforelse
            </div>
        </article>
    @endif

    <div class="literasi-analytics__grid">
        <article class="literasi-panel">
            <h3 class="literasi-panel__title">Responden Per Kelas</h3>
            <div class="literasi-table-wrap">
                <table class="literasi-table">
                    <thead>
                        <tr>
                            <th>Kelas</th>
                            <th class="is-number">Hari Ini</th>
                            <th class="is-number">Minggu Ini</th>
                            <th class="is-number">Bulan Ini</th>
                            <th class="is-number">Rasio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($classActivity as $row)
                            <tr>
                                <td data-label="Kelas">{{ $row['class'] }}</td>
                                <td data-label="Hari Ini" class="is-number">{{ $formatNumber((int) $row['today']) }}<small>{{ $formatNumber((int) ($row['today_responses'] ?? $row['today'])) }} + {{ $formatNumber((int) ($row['today_dispensations'] ?? 0)) }}</small></td>
                                <td data-label="Minggu Ini" class="is-number">{{ $formatNumber((int) $row['week']) }}<small>{{ $formatNumber((int) ($row['week_responses'] ?? $row['week'])) }} + {{ $formatNumber((int) ($row['week_dispensations'] ?? 0)) }}</small></td>
                                <td data-label="Bulan Ini" class="is-number">{{ $formatNumber((int) $row['month']) }}<small>{{ $formatNumber((int) ($row['month_responses'] ?? $row['month'])) }} jawaban + {{ $formatNumber((int) ($row['month_dispensations'] ?? 0)) }} dispensasi</small></td>
                                <td data-label="Rasio" class="is-number">{{ $row['month_ratio'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="literasi-table__empty" colspan="5">Belum ada responden bulan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        @unless($compact)
            <article class="literasi-panel">
                <h3 class="literasi-panel__title">Ranking Kelas Terbanyak Mengisi</h3>
                <div class="literasi-table-wrap">
                    <table class="literasi-table">
                        <thead>
                            <tr>
                                <th class="is-rank">#</th>
                                <th>Kelas</th>
                                <th class="is-number">Responden</th>
                                <th class="is-number">Rasio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($classRanking as $index => $row)
                                <tr>
                                    <td data-label="#" class="is-rank">{{ $index + 1 }}</td>
                                    <td data-label="Kelas">{{ $row['class'] }}</td>
                                    <td data-label="Responden" class="is-number">
                                        {{ $formatNumber((int) $row['total']) }}
                                        <small>{{ $formatNumber((int) ($row['response_total'] ?? $row['total'])) }} jawaban + {{ $formatNumber((int) ($row['dispensation_total'] ?? 0)) }} dispensasi</small>
                                    </td>
                                    <td data-label="Rasio" class="is-number">{{ $row['ratio'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="literasi-table__empty" colspan="4">Belum ada ranking kelas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        @endunless

        <article class="literasi-panel">
            <h3 class="literasi-panel__title">Ranking 3 Kelas Jawaban Benar Terbanyak</h3>
            <div class="literasi-table-wrap">
                <table class="literasi-table">
                    <thead>
                        <tr>
                            <th class="is-rank">#</th>
                            <th>Kelas</th>
                            <th class="is-number">Benar</th>
                            <th class="is-number">Murid</th>
                            <th class="is-number">Akurasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($classCorrectRanking as $index => $row)
                            <tr>
                                <td data-label="#" class="is-rank">{{ $index + 1 }}</td>
                                <td data-label="Kelas">{{ $row['class'] }}</td>
                                <td data-label="Benar" class="is-number">{{ $formatNumber((int) $row['correct_answers']) }}</td>
                                <td data-label="Murid" class="is-number">{{ $formatNumber((int) $row['response_count']) }}</td>
                                <td data-label="Akurasi" class="is-number">{{ $formatPercent((float) $row['accuracy']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="literasi-table__empty" colspan="5">Belum ada kelas dengan jawaban benar bulan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        @unless($compact)
            <article class="literasi-panel">
                <h3 class="literasi-panel__title">Ranking 3 Kelas Tersedikit Mengisi</h3>
                <div class="literasi-table-wrap">
                    <table class="literasi-table">
                        <thead>
                            <tr>
                                <th class="is-rank">#</th>
                                <th>Kelas</th>
                                <th class="is-number">Responden</th>
                                <th class="is-number">Rasio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($leastClassRanking as $index => $row)
                                <tr>
                                    <td data-label="#" class="is-rank">{{ $index + 1 }}</td>
                                    <td data-label="Kelas">{{ $row['class'] }}</td>
                                    <td data-label="Responden" class="is-number">
                                        {{ $formatNumber((int) $row['total']) }}
                                        <small>{{ $formatNumber((int) ($row['response_total'] ?? $row['total'])) }} jawaban + {{ $formatNumber((int) ($row['dispensation_total'] ?? 0)) }} dispensasi</small>
                                    </td>
                                    <td data-label="Rasio" class="is-number">{{ $row['ratio'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="literasi-table__empty" colspan="4">Belum ada data kelas aktif.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        @endunless
    </div>

    <article class="literasi-panel">
        <h3 class="literasi-panel__title">{{ $compact ? 'Siswa Benar Per Kelas' : 'Ranking Siswa Per Kelas Berdasarkan Jawaban Benar' }}</h3>
        <div class="literasi-student-rankings">
            @forelse($studentRankingByClass as $class => $rows)
                <details class="literasi-class-ranking" @if(! $compact) open @endif>
                    <summary>{{ $class }}</summary>
                    <div class="literasi-table-wrap">
                        <table class="literasi-table">
                            <thead>
                                <tr>
                                    <th class="is-rank">#</th>
                                    <th>Siswa</th>
                                    <th class="is-number">Benar</th>
                                    <th class="is-number">Dinilai</th>
                                    <th class="is-number">Akurasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $index => $row)
                                    <tr>
                                        <td data-label="#" class="is-rank">{{ $index + 1 }}</td>
                                        <td data-label="Siswa">{{ $row['name'] }}</td>
                                        <td data-label="Benar" class="is-number">{{ $formatNumber((int) $row['correct_answers']) }}</td>
                                        <td data-label="Dinilai" class="is-number">{{ $formatNumber((int) $row['graded_answers']) }}</td>
                                        <td data-label="Akurasi" class="is-number">{{ $formatPercent((float) $row['accuracy']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @empty
                <p class="literasi-empty-state">Belum ada jawaban yang dinilai pada periode ini.</p>
            @endforelse
        </div>
    </article>

    @unless($compact)
        <div class="literasi-analytics__grid">
            <details class="literasi-panel">
                <summary class="literasi-panel__title cursor-pointer">Siswa Banyak Salah</summary>
                <div class="literasi-table-wrap mt-3">
                    <table class="literasi-table">
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Kelas</th>
                                <th class="is-number">Salah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($studentWrongRanking as $row)
                                <tr>
                                    <td data-label="Siswa">{{ $row['name'] }}</td>
                                    <td data-label="Kelas">{{ $row['class'] }}</td>
                                    <td data-label="Salah" class="is-number">{{ $formatNumber((int) $row['wrong_answers']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="literasi-table__empty" colspan="3">Belum ada jawaban salah pada periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </details>

            <details class="literasi-panel">
                <summary class="literasi-panel__title cursor-pointer">Siswa Tidak Mengisi</summary>
                <div class="literasi-table-wrap mt-3">
                    <table class="literasi-table">
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Kelas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($missingStudents as $row)
                                <tr>
                                    <td data-label="Siswa">{{ $row['name'] }}</td>
                                    <td data-label="Kelas">{{ $row['class'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="literasi-table__empty" colspan="2">Semua siswa aktif sudah memiliki respon pada scope ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    @endunless

    <div class="literasi-analytics__grid">
        <article class="literasi-panel literasi-panel--danger">
            <h3 class="literasi-panel__title">{{ $compact ? 'Daftar Plagiat Per Kelas' : 'Kelas Tersering Plagiasi' }}</h3>
            <div class="literasi-table-wrap">
                <table class="literasi-table">
                    <thead>
                        <tr>
                            <th class="is-rank">#</th>
                            <th>Kelas</th>
                            <th class="is-number">Indikasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plagiarismClassRanking as $index => $row)
                            <tr>
                                <td data-label="#" class="is-rank">{{ $index + 1 }}</td>
                                <td data-label="Kelas">{{ $row['class'] }}</td>
                                <td data-label="Indikasi" class="is-number">{{ $formatNumber((int) $row['total']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="literasi-table__empty" colspan="3">Belum ada indikasi plagiasi bulan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="literasi-panel literasi-panel--danger">
            <h3 class="literasi-panel__title">{{ $compact ? 'Daftar Plagiat Per Siswa' : 'Siswa Tersering Plagiasi' }}</h3>
            <div class="literasi-table-wrap">
                <table class="literasi-table">
                    <thead>
                        <tr>
                            <th class="is-rank">#</th>
                            <th>Siswa</th>
                            <th>Kelas</th>
                            <th class="is-number">Indikasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plagiarismStudentRanking as $index => $row)
                            <tr>
                                <td data-label="#" class="is-rank">{{ $index + 1 }}</td>
                                <td data-label="Siswa">{{ $row['name'] }}</td>
                                <td data-label="Kelas">{{ $row['class'] }}</td>
                                <td data-label="Indikasi" class="is-number">{{ $formatNumber((int) $row['total']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="literasi-table__empty" colspan="4">Belum ada ranking siswa plagiasi bulan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</section>
