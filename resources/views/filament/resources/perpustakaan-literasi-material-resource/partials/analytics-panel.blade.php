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
                    <p class="literasi-analytics__description">Seluruh waktu untuk semua siswa aktif. Jawaban di Sampah dipisahkan dari siswa yang belum pernah mengisi.</p>
                </div>
                <span class="literasi-analytics__period">Penyelesaian: {{ $formatPercent((float) ($materialCompletion['completion_percentage'] ?? 0)) }}</span>
            </div>

            <div class="literasi-completion__metrics">
                <div><span>Siswa Aktif</span><strong>{{ $formatNumber((int) ($materialCompletion['active_total'] ?? 0)) }}</strong></div>
                <div class="is-success"><span>Sudah Mengisi</span><strong>{{ $formatNumber((int) ($materialCompletion['completed_total'] ?? 0)) }}</strong></div>
                <div class="is-warning"><span>Belum Mengisi</span><strong>{{ $formatNumber((int) ($materialCompletion['missing_total'] ?? 0)) }}</strong></div>
                <div class="is-danger"><span>Di Sampah</span><strong>{{ $formatNumber((int) ($materialCompletion['trashed_total'] ?? 0)) }}</strong></div>
            </div>

            <div class="literasi-completion__classes">
                @forelse(($materialCompletion['classes'] ?? []) as $class)
                    <details class="literasi-completion__class" @if(($class['missing_total'] ?? 0) > 0 || ($class['trashed_total'] ?? 0) > 0) open @endif>
                        <summary>
                            <strong>{{ $class['class'] }}</strong>
                            <span>{{ $formatNumber((int) $class['completed_total']) }}/{{ $formatNumber((int) $class['active_total']) }} mengisi</span>
                            <span class="is-warning">{{ $formatNumber((int) $class['missing_total']) }} belum</span>
                            @if(($class['trashed_total'] ?? 0) > 0)
                                <span class="is-danger">{{ $formatNumber((int) $class['trashed_total']) }} di Sampah</span>
                            @endif
                        </summary>

                        <div class="literasi-completion__lists">
                            <section>
                                <h4>Belum Mengisi</h4>
                                @forelse(($class['missing_students'] ?? []) as $student)
                                    <div class="literasi-completion__student">{{ $student['name'] }}</div>
                                @empty
                                    <p class="literasi-empty-state">Tidak ada siswa yang belum mengisi.</p>
                                @endforelse
                            </section>

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
                                <td data-label="Hari Ini" class="is-number">{{ $formatNumber((int) $row['today']) }}</td>
                                <td data-label="Minggu Ini" class="is-number">{{ $formatNumber((int) $row['week']) }}</td>
                                <td data-label="Bulan Ini" class="is-number">{{ $formatNumber((int) $row['month']) }}</td>
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
                                    <td data-label="Responden" class="is-number">{{ $formatNumber((int) $row['total']) }}</td>
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
                                    <td data-label="Responden" class="is-number">{{ $formatNumber((int) $row['total']) }}</td>
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
