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
            <section class="assessment-homeroom-summary-card">
                <span>
                    <x-filament::icon icon="heroicon-o-user-group" />
                </span>
                <div>
                    <h2>{{ $homeroomMeta['rombel'] }} · {{ $homeroomMeta['teacher'] }}</h2>
                    <p>{{ count($reportRows) }} siswa · Periode {{ $homeroomMeta['status_label'] }}</p>
                </div>
            </section>

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
                    </div>

                    <label class="assessment-homeroom-bulk-check">
                        <input type="checkbox" wire:model="bulkFillEmptyOnly">
                        <span><strong>Hanya isi data yang masih kosong</strong><small>Untuk Sakit, Izin, dan Alpa, angka 0 dianggap masih kosong.</small></span>
                    </label>

                    <div class="assessment-homeroom-bulk-actions">
                        <x-filament::button type="button" size="sm" color="gray" wire:click="selectAllStudents">Pilih Semua</x-filament::button>
                        <x-filament::button type="button" size="sm" color="gray" wire:click="clearStudentSelection">Kosongkan</x-filament::button>
                        <x-filament::button type="button" size="sm" wire:click="applyBulkValue" wire:loading.attr="disabled" icon="heroicon-o-bolt">
                            Terapkan ke Form
                        </x-filament::button>
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
                                            @if ($definition['input'] === 'number')
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

                        <div class="assessment-homeroom-absence-grid">
                            @foreach (['sick_days' => 'Sakit', 'permission_days' => 'Izin', 'absent_days' => 'Alpa'] as $field => $label)
                                <label><span>{{ $label }}</span><input type="number" min="0" max="366" wire:model.blur="reportRows.{{ $studentId }}.{{ $field }}" @disabled(! $homeroomMeta['editable'])></label>
                            @endforeach
                        </div>

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

                        <div class="assessment-homeroom-text-grid">
                            <label><span>Ekstrakurikuler</span><textarea rows="2" wire:model.blur="reportRows.{{ $studentId }}.extracurricular" @disabled(! $homeroomMeta['editable'])></textarea></label>
                            <label><span>Prestasi</span><textarea rows="2" wire:model.blur="reportRows.{{ $studentId }}.achievement" @disabled(! $homeroomMeta['editable'])></textarea></label>
                            <label><span>Catatan Wali Kelas</span><textarea rows="3" wire:model.blur="reportRows.{{ $studentId }}.homeroom_note" @disabled(! $homeroomMeta['editable'])></textarea></label>
                            @if ($homeroomMeta['collect_promotion_status'])
                                <label><span>Status Semester</span><input type="text" maxlength="50" wire:model.blur="reportRows.{{ $studentId }}.promotion_status" @disabled(! $homeroomMeta['editable'])></label>
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
