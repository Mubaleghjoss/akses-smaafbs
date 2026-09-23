<x-filament-panels::page>
    <div class="assessment-homeroom-page">
        @include('filament.pages.assessment.partials.type-navigation', ['showAccess' => false])

        <section class="assessment-homeroom-filter-card">
            <label>
                <span>Periode</span>
                <select wire:model.live="periodId">
                    @foreach ($this->getPeriodOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Kelas Wali</span>
                <select wire:model.live="homeroomId">
                    @foreach ($this->getHomeroomOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </section>

        @if ($homeroomMeta)
            @if ($this->isAstsHomeroomRecap())
                <div class="flex justify-end">
                    <a href="{{ route('admin.assessment.asts.homeroom.export', ['assessmentPeriod' => $periodId, 'homeroom' => $homeroomId]) }}" data-navigate="false" class="fi-btn fi-btn-size-sm fi-btn-color-gray">Download Excel</a>
                </div>
            @endif
            <section class="assessment-homeroom-summary-card">
                <span>
                    <x-filament::icon icon="heroicon-o-user-group" />
                </span>
                <div>
                    <h2>{{ $homeroomMeta['rombel'] }} · {{ $homeroomMeta['teacher'] }}</h2>
                    <p>{{ count($reportRows) }} siswa · Periode {{ $homeroomMeta['status_label'] }}</p>
                </div>
            </section>

            @if ($astsRanking)
                <section class="assessment-asts-ranking-card">
                    <div class="assessment-asts-ranking-card__head">
                        <div>
                            <h2>Ringkasan Nilai Akhir dan Peringkat ASTS</h2>
                            <p>Peringkat kompetisi dihitung dari rata-rata Nilai Akhir Mapel ASTS yang tersedia. Nilai sama memiliki peringkat sama.</p>
                        </div>
                        <span>{{ count($astsRanking['subjects']) }} mapel</span>
                    </div>
                    <div class="assessment-asts-ranking-scroll">
                        <table class="assessment-asts-ranking-table">
                            <thead>
                                <tr>
                                    <th class="assessment-asts-ranking-rank">Peringkat</th>
                                    <th class="assessment-asts-ranking-student">Siswa</th>
                                    @foreach ($astsRanking['subjects'] as $subjectName)
                                        <th>{{ $subjectName }}</th>
                                    @endforeach
                                    <th>Total</th>
                                    <th>Rata-rata</th>
                                    <th>Kelengkapan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($astsRanking['rows'] as $row)
                                    <tr>
                                        <td class="assessment-asts-ranking-rank" data-label="Peringkat">{{ $row['rank'] ?? '-' }}</td>
                                        <td class="assessment-asts-ranking-student" data-label="Siswa"><strong>{{ $row['student_name'] }}</strong><small>{{ $row['nis'] }}</small></td>
                                        @foreach ($astsRanking['subjects'] as $assignmentId => $subjectName)
                                            <td data-label="{{ $subjectName }}">{{ $row['scores'][$assignmentId] === null ? '-' : number_format($row['scores'][$assignmentId], 2, ',', '.') }}</td>
                                        @endforeach
                                        <td data-label="Total">{{ $row['completed'] ? number_format($row['total'], 2, ',', '.') : '-' }}</td>
                                        <td data-label="Rata-rata">{{ $row['average'] === null ? '-' : number_format($row['average'], 2, ',', '.') }}</td>
                                        <td data-label="Kelengkapan" @class(['assessment-asts-ranking-incomplete' => $row['completed'] < $row['expected']])>
                                            {{ $row['completed'] }}/{{ $row['expected'] }}
                                            @if ($row['completed'] < $row['expected'])
                                                <small>belum lengkap</small>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ 6 + count($astsRanking['subjects']) }}">Belum ada siswa aktif pada kelas ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($homeroomMeta['editable'])
                <section class="assessment-homeroom-bulk-card">
                    <div class="assessment-homeroom-bulk-card__head">
                        <div>
                            <h2>Isi Massal Rekap Wali Kelas</h2>
                            <p>Pilih siswa dan satu kolom. Perubahan baru tersimpan setelah tombol Simpan Rekap ditekan.</p>
                        </div>
                        <span>{{ count($selectedStudentIds) }} dipilih</span>
                    </div>

                    <div class="assessment-homeroom-bulk-grid">
                        <label>
                            <span>Kolom yang Diisi</span>
                            <select wire:model.live="bulkField">
                                @foreach ($this->getBulkFieldOptions() as $field => $label)
                                    <option value="{{ $field }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        @if ($this->isStructuredBulkField())
                            <div class="assessment-homeroom-bulk-structured">
                                <label>
                                    <span>{{ $bulkField === 'achievement_items' ? 'Jenis Prestasi' : 'Nama Ekstrakurikuler' }}</span>
                                    <input wire:model="bulkStructuredItem.name" type="text" maxlength="255" placeholder="Contoh: Pramuka">
                                </label>
                                <label>
                                    <span>{{ $this->usesExtracurricularPredicates() ? 'Predikat' : 'Keterangan' }}</span>
                                    @if ($this->usesExtracurricularPredicates())
                                        <select wire:model="bulkStructuredItem.description">
                                            <option value="">Pilih predikat</option>
                                            @foreach ($this->getExtracurricularPredicateOptions() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <textarea wire:model="bulkStructuredItem.description" rows="2" maxlength="2000" placeholder="Contoh: Sangat Baik"></textarea>
                                    @endif
                                </label>
                                <label>
                                    <span>Cara Menerapkan</span>
                                    <select wire:model="bulkStructuredMode">
                                        <option value="append">Tambah ke daftar yang ada</option>
                                        <option value="replace">Ganti seluruh daftar</option>
                                    </select>
                                </label>
                            </div>
                        @else
                            <label>
                                <span>Nilai / Teks</span>
                            @if (data_get($this->getBulkFieldDefinition(), 'input') === 'number')
                                <input wire:model="bulkValue" type="number" min="0" max="{{ data_get($this->getBulkFieldDefinition(), 'max') }}" placeholder="Contoh: 2">
                            @elseif (data_get($this->getBulkFieldDefinition(), 'input') === 'predicate')
                                <select wire:model="bulkValue">
                                    <option value="">Pilih predikat</option>
                                    @foreach ($this->getAttitudePredicateOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            @else
                                <textarea wire:model="bulkValue" rows="2" placeholder="Tulis isian yang akan diterapkan"></textarea>
                            @endif
                            </label>
                        @endif
                    </div>

                    @if ($this->isStructuredBulkField())
                        <p class="assessment-homeroom-bulk-note">
                            Mode tambah tidak menghapus poin lama dan melewati duplikat persis. Mode ganti memerlukan konfirmasi sebelum diterapkan ke formulir.
                        </p>
                    @else
                        <label class="assessment-homeroom-bulk-check">
                            <input type="checkbox" wire:model="bulkFillEmptyOnly">
                            <span><strong>Hanya isi data yang masih kosong</strong><small>Untuk Sakit, Izin, dan Alpa, angka 0 dianggap masih kosong.</small></span>
                        </label>
                    @endif

                    <div class="assessment-homeroom-bulk-actions">
                        <x-filament::button type="button" size="sm" color="gray" wire:click="selectAllStudents">Pilih Semua</x-filament::button>
                        <x-filament::button type="button" size="sm" color="gray" wire:click="clearStudentSelection">Kosongkan</x-filament::button>
                        @if ($this->isStructuredBulkField() && $bulkStructuredMode === 'replace')
                            <x-filament::button type="button" size="sm" color="warning" wire:click="applyBulkValue" wire:confirm="Ganti seluruh daftar pada siswa terpilih? Perubahan masih berada di formulir sampai tombol Simpan ditekan." wire:loading.attr="disabled" icon="heroicon-o-arrow-path">
                                Ganti Daftar di Form
                            </x-filament::button>
                        @else
                            <x-filament::button type="button" size="sm" wire:click="applyBulkValue" wire:loading.attr="disabled" icon="heroicon-o-bolt">
                                Terapkan ke Form
                            </x-filament::button>
                        @endif
                    </div>
                </section>
            @endif

            <section class="assessment-homeroom-desktop">
                <div class="assessment-homeroom-table-scroll">
                    <table class="assessment-homeroom-table">
                        <thead>
                            <tr>
                                <th class="student-column">Siswa</th>
                                @foreach ($this->getRecapFieldDefinitions() as $definition)
                                    <th>{{ $definition['header'] }}</th>
                                @endforeach
                                @if ($homeroomMeta['collect_promotion_status'])
                                    <th>Status Semester</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reportRows as $studentId => $row)
                                <tr>
                                    <td class="student-column">
                                        <label class="assessment-homeroom-student">
                                            @if ($homeroomMeta['editable'])
                                                <input type="checkbox" value="{{ $studentId }}" wire:model.live="selectedStudentIds">
                                            @endif
                                            <span><strong>{{ $row['student_name'] }}</strong><small>{{ $row['nis'] }}</small></span>
                                        </label>
                                    </td>
                                    @foreach ($this->getRecapFieldDefinitions() as $field => $definition)
                                        <td>
                                            @if ($definition['input'] === 'items')
                                                @include('filament.pages.assessment.partials.structured-homeroom-items', [
                                                    'surface' => 'desktop',
                                                    'studentId' => $studentId,
                                                    'studentName' => $row['student_name'],
                                                    'field' => $field,
                                                    'items' => $row[$field] ?? [],
                                                    'editable' => $homeroomMeta['editable'],
                                                    'predicateOptions' => $this->isAstsHomeroomRecap() && $field === 'extracurricular_items' ? $this->getExtracurricularPredicateOptions() : [],
                                                ])
                                            @elseif ($definition['input'] === 'number')
                                                <input type="number" min="0" max="{{ $definition['max'] }}" class="is-number" wire:model.blur="reportRows.{{ $studentId }}.{{ $field }}" @disabled(! $homeroomMeta['editable'])>
                                            @elseif ($definition['input'] === 'predicate')
                                                <select wire:model.blur="reportRows.{{ $studentId }}.{{ $field }}" @disabled(! $homeroomMeta['editable'])>
                                                    <option value="">Belum diisi</option>
                                                    @foreach ($this->getAttitudePredicateOptions() as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <textarea rows="3" maxlength="{{ $definition['max'] }}" wire:model.blur="reportRows.{{ $studentId }}.{{ $field }}" @disabled(! $homeroomMeta['editable'])></textarea>
                                            @endif
                                        </td>
                                    @endforeach
                                    @if ($homeroomMeta['collect_promotion_status'])
                                        <td><input type="text" maxlength="50" wire:model.blur="reportRows.{{ $studentId }}.promotion_status" @disabled(! $homeroomMeta['editable'])></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="assessment-homeroom-mobile">
                @foreach ($reportRows as $studentId => $row)
                    <article class="assessment-homeroom-student-card">
                        <label class="assessment-homeroom-student">
                            @if ($homeroomMeta['editable'])
                                <input type="checkbox" value="{{ $studentId }}" wire:model.live="selectedStudentIds">
                            @endif
                            <span><strong>{{ $row['student_name'] }}</strong><small>{{ $row['nis'] }}</small></span>
                        </label>

                        @if ($this->isAstsHomeroomRecap())
                            <strong>Ketidakhadiran</strong>
                        @endif
                        <div class="assessment-homeroom-absence-grid">
                            @foreach (['sick_days' => 'Sakit', 'permission_days' => 'Izin', 'absent_days' => 'Alpa'] as $field => $label)
                                <label><span>{{ $label }}</span><input type="number" min="0" max="366" wire:model.blur="reportRows.{{ $studentId }}.{{ $field }}" @disabled(! $homeroomMeta['editable'])></label>
                            @endforeach
                        </div>

                        @if (! $this->isAstsHomeroomRecap())
                        <div class="assessment-homeroom-attitude-grid">
                            <section>
                                <strong>Sikap Spiritual</strong>
                                <label>
                                    <span>Predikat</span>
                                    <select wire:model.blur="reportRows.{{ $studentId }}.spiritual_predicate" @disabled(! $homeroomMeta['editable'])>
                                        <option value="">Belum diisi</option>
                                        @foreach ($this->getAttitudePredicateOptions() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label><span>Deskripsi</span><textarea rows="3" wire:model.blur="reportRows.{{ $studentId }}.spiritual_description" @disabled(! $homeroomMeta['editable'])></textarea></label>
                            </section>
                            <section>
                                <strong>Sikap Sosial</strong>
                                <label>
                                    <span>Predikat</span>
                                    <select wire:model.blur="reportRows.{{ $studentId }}.social_predicate" @disabled(! $homeroomMeta['editable'])>
                                        <option value="">Belum diisi</option>
                                        @foreach ($this->getAttitudePredicateOptions() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label><span>Deskripsi</span><textarea rows="3" wire:model.blur="reportRows.{{ $studentId }}.social_description" @disabled(! $homeroomMeta['editable'])></textarea></label>
                            </section>
                        </div>
                        @endif

                        <div class="assessment-homeroom-text-grid">
                            <section class="assessment-homeroom-structured-section">
                                @if ($this->isAstsHomeroomRecap())
                                    <strong>Ekstrakurikuler (Predikat A/B/C/D)</strong>
                                @else
                                <strong>Ekstrakurikuler</strong>
                                @endif
                                @include('filament.pages.assessment.partials.structured-homeroom-items', [
                                    'surface' => 'mobile',
                                    'studentId' => $studentId,
                                    'studentName' => $row['student_name'],
                                    'field' => 'extracurricular_items',
                                    'items' => $row['extracurricular_items'] ?? [],
                                    'editable' => $homeroomMeta['editable'],
                                    'predicateOptions' => $this->isAstsHomeroomRecap() ? $this->getExtracurricularPredicateOptions() : [],
                                ])
                            </section>
                            @if (! $this->isAstsHomeroomRecap())
                            <section class="assessment-homeroom-structured-section">
                                <strong>Prestasi</strong>
                                @include('filament.pages.assessment.partials.structured-homeroom-items', [
                                    'surface' => 'mobile',
                                    'studentId' => $studentId,
                                    'studentName' => $row['student_name'],
                                    'field' => 'achievement_items',
                                    'items' => $row['achievement_items'] ?? [],
                                    'editable' => $homeroomMeta['editable'],
                                ])
                            </section>
                            <label><span>Catatan Wali Kelas</span><textarea rows="3" wire:model.blur="reportRows.{{ $studentId }}.homeroom_note" @disabled(! $homeroomMeta['editable'])></textarea></label>
                            @if ($homeroomMeta['collect_promotion_status'])
                                <label><span>Status Semester</span><input type="text" maxlength="50" wire:model.blur="reportRows.{{ $studentId }}.promotion_status" @disabled(! $homeroomMeta['editable'])></label>
                            @endif
                            @endif
                        </div>
                    </article>
                @endforeach
            </section>

            @if ($homeroomMeta['editable'])
                <div class="assessment-homeroom-savebar">
                    <span>Pastikan data sudah diperiksa sebelum disimpan.</span>
                    <x-filament::button wire:click="saveReports" wire:loading.attr="disabled" icon="heroicon-o-cloud-arrow-up">
                        Simpan Rekap Wali Kelas
                    </x-filament::button>
                </div>
            @endif
        @else
            <section class="assessment-homeroom-empty">
                <x-filament::icon icon="heroicon-o-user-group" />
                <h2>Belum ada kelas wali</h2>
                <p>Penugasan wali kelas untuk periode ini belum tersedia pada akun Anda.</p>
            </section>
        @endif
    </div>
</x-filament-panels::page>
