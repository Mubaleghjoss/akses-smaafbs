<x-filament-panels::page>
    <div @class(['assessment-score-page space-y-5', 'is-asts' => ! $this->usesDescriptions()])>
        @include('filament.pages.assessment.partials.type-navigation')

        @php($scopeNotice = $this->getEntryScopeNotice())
        <section @class([
            'rounded-2xl border p-4 sm:p-5',
            'border-warning-300 bg-warning-50 dark:border-warning-500/30 dark:bg-warning-950/20' => $scopeNotice['tone'] === 'warning',
            'border-primary-200 bg-primary-50 dark:border-primary-500/25 dark:bg-primary-950/20' => $scopeNotice['tone'] !== 'warning',
        ])>
            <div class="flex min-w-0 items-start gap-3">
                <span @class([
                    'flex size-10 shrink-0 items-center justify-center rounded-xl',
                    'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-300' => $scopeNotice['tone'] === 'warning',
                    'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300' => $scopeNotice['tone'] !== 'warning',
                ])>
                    <x-filament::icon :icon="$scopeNotice['tone'] === 'warning' ? 'heroicon-o-eye' : 'heroicon-o-academic-cap'" class="size-5" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-balance font-bold text-gray-950 dark:text-white">{{ $scopeNotice['title'] }}</h2>
                    <p class="mt-1 text-pretty text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $scopeNotice['description'] }}</p>
                </div>
            </div>
        </section>

        @php($assignmentProgress = $this->getAssignmentProgress())
        @if ($assignmentProgress['total'] > 0)
            <section class="assessment-progress-note">
                <span class="assessment-progress-note__icon">
                    <x-filament::icon icon="heroicon-o-information-circle" />
                </span>
                <div>
                    <strong>{{ $assignmentProgress['sent'] }} dari {{ $assignmentProgress['total'] }} penugasan sudah dikirim.</strong>
                    <p>{{ $assignmentProgress['remaining'] }} penugasan masih dapat dilengkapi. Pilihan Draf dan Dikembalikan ditampilkan lebih dahulu.</p>
                </div>
            </section>
        @endif

        <section class="assessment-score-toolbar">
            <div class="assessment-score-field">
                <label for="assessment-period">Periode</label>
                <select id="assessment-period" class="assessment-score-select" wire:model.live="periodId">
                    @forelse ($this->getPeriodOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @empty
                        <option value="">Belum ada periode untuk akun ini</option>
                    @endforelse
                </select>
            </div>
            <div class="assessment-score-field">
                <label for="assessment-assignment">Mapel dan Kelas</label>
                <select id="assessment-assignment" class="assessment-score-select" wire:model.live="assignmentId">
                    @forelse ($this->getAssignmentOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @empty
                        <option value="">Belum ada penugasan</option>
                    @endforelse
                </select>
            </div>
        </section>

        @if ($assignmentMeta)
            <section>
                <div class="assessment-score-meta">
                    <span class="assessment-score-pill">Status: {{ $assignmentMeta['status_label'] }}</span>
                    <span class="assessment-score-pill">{{ $assignmentMeta['teacher'] }}</span>
                    <span class="assessment-score-pill">{{ count($scoreRows) }} siswa</span>
                    <span class="assessment-score-pill">{{ $assignmentMeta['deadline_at'] ? 'Deadline: '.$assignmentMeta['deadline_at'] : 'Tanpa deadline' }}</span>
                    <span class="assessment-score-pill">Akses: {{ $assignmentMeta['access_mode'] }}</span>
                    <span class="assessment-score-pill">Versi {{ $lockVersion }}</span>
                </div>
                @if ($assignmentMeta['deadline_passed'])
                    <section @class([
                        'mt-3 rounded-xl border p-4 text-sm',
                        'border-warning-300 bg-warning-50 text-warning-900 dark:border-warning-500/30 dark:bg-warning-950/20 dark:text-warning-100' => $assignmentMeta['can_override_deadline'],
                        'border-danger-300 bg-danger-50 text-danger-900 dark:border-danger-500/30 dark:bg-danger-950/20 dark:text-danger-100' => ! $assignmentMeta['can_override_deadline'],
                    ])>
                        <strong>{{ $assignmentMeta['can_override_deadline'] ? 'Mode override deadline aktif' : 'Pengisian nilai terkunci' }}</strong>
                        <p class="mt-1">
                            @if ($assignmentMeta['can_override_deadline'])
                                Anda dapat menyimpan dan mengirim setelah deadline. Pengiriman dengan override dicatat pada audit penilaian.
                            @else
                                Deadline pengisian telah berakhir. Akun guru tidak dapat menyimpan atau mengirim nilai; hubungi Admin/Kurikulum bila perlu tindak lanjut.
                            @endif
                        </p>
                    </section>
                @endif
                @if ($assignmentMeta['returned_reason'])
                    <section class="assessment-revision-card">
                        <span class="assessment-revision-card__icon"><x-filament::icon icon="heroicon-o-exclamation-triangle" /></span>
                        <div>
                            <span class="assessment-revision-card__eyebrow">{{ $assignmentMeta['status'] === 'returned' ? 'Perlu Revisi' : 'Riwayat Revisi Terakhir' }}</span>
                            <h2>{{ $assignmentMeta['subject'] }} · {{ $assignmentMeta['rombel'] }}</h2>
                            <p>{{ $assignmentMeta['returned_reason'] }}</p>
                            <small>Diberikan oleh {{ $assignmentMeta['returned_by'] }}{{ $assignmentMeta['returned_at'] ? ' pada '.$assignmentMeta['returned_at'] : '' }}.</small>
                        </div>
                    </section>
                @endif
            </section>

            <div
                wire:key="assessment-entry-{{ $assignmentId }}-v{{ $lockVersion }}"
                x-data="{
                    draftKey: @js($this->draftKey()),
                    serverVersion: @js($lockVersion),
                    restored: false,
                    stale: false,
                    staleSavedAt: null,
                    normalizeDraftValue(path, value) {
                        if (!path.includes('.scores.')) return value;
                        if (value === null || String(value).trim() === '') return '';
                        const numeric = Number(value);
                        if (!Number.isFinite(numeric)) return value;
                        return String(Math.round((numeric + Number.EPSILON) * 100) / 100);
                    },
                    saveLocal(event) {
                        const path = event.target?.dataset?.assessmentPath;
                        if (!path) return;
                        let draft = { values: {} };
                        try { draft = JSON.parse(localStorage.getItem(this.draftKey) || 'null') || draft; } catch (e) {}
                        if (Number(draft.lockVersion ?? -1) !== Number(this.serverVersion)) {
                            draft = { values: {} };
                            this.stale = false;
                        }
                        draft.values = draft.values || {};
                        draft.values[path] = this.normalizeDraftValue(path, event.target.value);
                        draft.savedAt = new Date().toISOString();
                        draft.lockVersion = this.serverVersion;
                        localStorage.setItem(this.draftKey, JSON.stringify(draft));
                    },
                    persistRenderedFields() {
                        this.$root.querySelectorAll('[data-assessment-path]').forEach((field) => {
                            this.saveLocal({ target: field });
                        });
                    },
                    restoreLocal(force = false) {
                        let draft = null;
                        try { draft = JSON.parse(localStorage.getItem(this.draftKey) || 'null'); } catch (e) {}
                        if (!draft?.values) return;
                        if (!force && Number(draft.lockVersion ?? -1) !== Number(this.serverVersion)) {
                            this.stale = true;
                            this.staleSavedAt = draft.savedAt || null;
                            return;
                        }
                        Object.entries(draft.values).forEach(([path, value]) => {
                            const normalized = this.normalizeDraftValue(path, value);
                            this.$root.querySelectorAll(`[data-assessment-path='${path}']`).forEach((field) => field.value = normalized);
                            $wire.set(path, normalized, false);
                        });
                        this.stale = false;
                        this.restored = true;
                    },
                    discardLocal() {
                        localStorage.removeItem(this.draftKey);
                        this.stale = false;
                        this.staleSavedAt = null;
                    }
                }"
                x-init="restoreLocal(); window.addEventListener('assessment-draft-cleared', (event) => { if (event.detail?.key === draftKey) localStorage.removeItem(draftKey) }); window.addEventListener('assessment-bulk-applied', (event) => { if (event.detail?.key === draftKey) setTimeout(() => persistRenderedFields(), 50) })"
                x-on:input.debounce.250ms="saveLocal($event)"
                class="space-y-4"
            >
                @if ($assignmentMeta['editable'])
                    <section class="assessment-bulk-card">
                        <div>
                            <h2 class="font-bold text-gray-950 dark:text-white">{{ $this->usesDescriptions() ? 'Isi Nilai & Deskripsi Massal' : 'Isi Nilai Massal' }}</h2>
                            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">
                                Centang siswa, isi {{ $this->usesDescriptions() ? 'nilai atau deskripsi' : 'nilai' }}, lalu terapkan ke formulir. Data belum masuk server sampai tombol <strong>Simpan Draf</strong> ditekan.
                            </p>
                        </div>

                        <div class="assessment-bulk-grid">
                            <label class="assessment-score-field">
                                <span class="block text-xs font-bold text-gray-600 dark:text-gray-300">Komponen Nilai</span>
                                <select wire:model="bulkComponentId" class="assessment-score-select mt-2">
                                    @foreach ($components as $component)
                                        @if ($component['score_source'] === 'manual')
                                            <option value="{{ $component['id'] }}">{{ $component['name'] }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </label>
                            <label class="assessment-score-field">
                                <span class="block text-xs font-bold text-gray-600 dark:text-gray-300">Nilai Massal</span>
                                <input wire:model="bulkScore" type="number" step="0.01" class="assessment-score-input mt-2" placeholder="Contoh: 85">
                            </label>
                            @if ($this->usesDescriptions())
                                <label class="assessment-score-field is-wide">
                                    <span class="block text-xs font-bold text-gray-600 dark:text-gray-300">Deskripsi Massal (opsional)</span>
                                    <textarea wire:model="bulkDescription" rows="2" class="assessment-description mt-2 min-w-0" placeholder="Contoh: Menunjukkan pemahaman yang baik dan konsisten."></textarea>
                                </label>
                            @endif
                            <label class="is-wide flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" wire:model="bulkFillEmptyOnly" class="mt-1 rounded border-gray-300">
                                <span><strong>Hanya isi kolom yang masih kosong</strong><small class="block text-gray-500">Bawaan nonaktif: {{ $this->usesDescriptions() ? 'nilai/deskripsi' : 'nilai' }} lama pada siswa terpilih akan ditimpa setelah konfirmasi.</small></span>
                            </label>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <x-filament::button type="button" size="sm" color="gray" wire:click="selectAllStudents">Pilih Semua</x-filament::button>
                            <x-filament::button type="button" size="sm" color="gray" wire:click="clearStudentSelection">Kosongkan Pilihan</x-filament::button>
                            <span class="text-xs font-semibold text-gray-500">{{ count($selectedStudentIds) }} siswa dipilih</span>
                            <x-filament::button
                                type="button"
                                size="sm"
                                wire:click="applyBulkValues"
                                wire:confirm="{{ $this->bulkConfirmationMessage() }}"
                                wire:loading.attr="disabled"
                                icon="heroicon-o-bolt"
                                class="sm:ml-auto"
                            >
                                Terapkan ke Form
                            </x-filament::button>
                        </div>
                    </section>
                @endif

                <div x-show="stale" x-cloak class="rounded-xl border border-warning-200 bg-warning-50 p-3 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-950/30 dark:text-warning-200">
                    <strong>Draf browser berasal dari versi nilai yang lebih lama.</strong>
                    Draf tidak dipulihkan otomatis agar tidak menimpa perubahan terbaru dari tab lain.
                    <span x-show="staleSavedAt" class="mt-1 block text-xs" x-text="`Disimpan lokal: ${new Date(staleSavedAt).toLocaleString('id-ID')}`"></span>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="rounded-lg bg-warning-600 px-3 py-2 text-xs font-semibold text-white" x-on:click="restoreLocal(true)">
                            Pulihkan untuk Ditinjau
                        </button>
                        <button type="button" class="rounded-lg border border-warning-400 px-3 py-2 text-xs font-semibold" x-on:click="discardLocal()">
                            Hapus Draf Lama
                        </button>
                    </div>
                </div>

                <div x-show="restored" x-cloak class="rounded-xl border border-info-200 bg-info-50 p-3 text-sm text-info-800 dark:border-info-500/30 dark:bg-info-950/30 dark:text-info-200">
                    Draf browser dari sesi sebelumnya sudah dipulihkan. Periksa nilainya lalu tekan <strong>Simpan Draf</strong>.
                </div>

                <section class="assessment-desktop assessment-matrix-shell">
                    <div class="assessment-matrix-scroll">
                        <table @class(['assessment-matrix', 'is-asts' => ! $this->usesDescriptions()])>
                            <thead>
                                <tr>
                                    <th class="student-col">Siswa</th>
                                    @foreach ($components as $component)
                                        <th @class(['assessment-matrix-component', 'is-manual' => $component['score_source'] === 'manual', 'is-automatic' => $component['score_source'] !== 'manual'])>
                                            <span class="assessment-matrix-component__type">{{ $component['score_source'] === 'manual' ? 'Input manual' : 'Otomatis' }}</span>
                                            <span class="assessment-matrix-component__name">{{ $component['name'] }}</span>
                                            <span class="assessment-matrix-component__meta">{{ $this->formatComponentMeta($component) }}</span>
                                        </th>
                                    @endforeach
                                    @if ($this->usesDescriptions())
                                        <th>Deskripsi Capaian</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($scoreRows as $studentId => $row)
                                    <tr>
                                        <td class="student-col">
                                            <label class="assessment-student-check">
                                                @if ($assignmentMeta['editable'])
                                                    <input type="checkbox" value="{{ $studentId }}" wire:model.live="selectedStudentIds" class="rounded border-gray-300">
                                                @endif
                                                <span class="min-w-0">
                                                    <span class="block font-semibold text-gray-950 dark:text-white">{{ $row['student_name'] }}</span>
                                                    <span class="mt-1 block text-xs text-gray-500">{{ $row['nis'] }}</span>
                                                    @if ($row['updater_badge'])
                                                        <span class="mt-2 inline-flex rounded-full bg-warning-100 px-2 py-0.5 text-xs font-semibold text-warning-800 dark:bg-warning-500/15 dark:text-warning-200">{{ $row['updater_badge'] }}</span>
                                                    @endif
                                                </span>
                                            </label>
                                            @if ($row['final_score'] !== null)
                                                <div class="assessment-final-score mt-2">Nilai akhir: {{ \App\Support\Assessment\AssessmentNumberFormatter::score($row['final_score']) }}</div>
                                            @endif
                                        </td>
                                        @foreach ($components as $component)
                                            <td @class(['assessment-matrix-score-cell', 'is-manual' => $component['score_source'] === 'manual', 'is-automatic' => $component['score_source'] !== 'manual'])>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="{{ $component['minimum_score'] }}"
                                                    max="{{ $component['maximum_score'] }}"
                                                    class="assessment-score-input"
                                                    aria-label="{{ $component['name'] }} untuk {{ $row['student_name'] }}"
                                                    data-assessment-path="scoreRows.{{ $studentId }}.scores.{{ $component['id'] }}"
                                                    wire:model.blur="scoreRows.{{ $studentId }}.scores.{{ $component['id'] }}"
                                                    @disabled(! $assignmentMeta['editable'] || $component['score_source'] !== 'manual')
                                                >
                                            </td>
                                        @endforeach
                                        @if ($this->usesDescriptions())
                                            <td>
                                                <textarea
                                                    rows="3"
                                                    class="assessment-description"
                                                    data-assessment-path="scoreRows.{{ $studentId }}.description"
                                                    wire:model.blur="scoreRows.{{ $studentId }}.description"
                                                    @disabled(! $assignmentMeta['editable'])
                                                ></textarea>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="assessment-mobile" x-data="{ current: @entangle('currentStudentIndex').live }">
                    @foreach (array_values($scoreRows) as $index => $row)
                        @php($studentId = $row['student_id'])
                        <article class="assessment-mobile-card" x-show="current === {{ $index }}" x-cloak>
                            <div class="flex items-start justify-between gap-3">
                                <label class="assessment-student-check min-w-0">
                                    @if ($assignmentMeta['editable'])
                                        <input type="checkbox" value="{{ $studentId }}" wire:model.live="selectedStudentIds" class="rounded border-gray-300">
                                    @endif
                                    <span class="min-w-0">
                                        <span class="block break-words font-bold text-gray-950 dark:text-white">{{ $row['student_name'] }}</span>
                                        <span class="mt-1 block text-xs text-gray-500">{{ $row['nis'] }}</span>
                                        @if ($row['updater_badge'])
                                            <span class="mt-2 inline-flex rounded-full bg-warning-100 px-2 py-0.5 text-xs font-semibold text-warning-800 dark:bg-warning-500/15 dark:text-warning-200">{{ $row['updater_badge'] }}</span>
                                        @endif
                                    </span>
                                </label>
                                    <span class="assessment-score-pill">{{ $index + 1 }}/{{ count($scoreRows) }}</span>
                                </div>
                                @if ($row['final_score'] !== null)
                                    <div class="assessment-final-score">Nilai akhir: {{ \App\Support\Assessment\AssessmentNumberFormatter::score($row['final_score']) }}</div>
                                @endif
                            <div class="assessment-mobile-grid">
                                @foreach ($components as $component)
                                    <label @class(['assessment-mobile-score', 'is-manual' => $component['score_source'] === 'manual', 'is-automatic' => $component['score_source'] !== 'manual'])>
                                        <span class="min-w-0 text-sm font-semibold text-gray-700 dark:text-gray-200">
                                            <small class="assessment-mobile-score__type">{{ $component['score_source'] === 'manual' ? 'Input manual' : 'Otomatis' }}</small>
                                            {{ $component['name'] }}
                                            <small class="block font-normal text-gray-500">
                                                {{ $this->formatComponentMeta($component) }}
                                            </small>
                                        </span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="{{ $component['minimum_score'] }}"
                                            max="{{ $component['maximum_score'] }}"
                                            class="assessment-score-input"
                                            data-assessment-path="scoreRows.{{ $studentId }}.scores.{{ $component['id'] }}"
                                            wire:model.blur="scoreRows.{{ $studentId }}.scores.{{ $component['id'] }}"
                                            @disabled(! $assignmentMeta['editable'] || $component['score_source'] !== 'manual')
                                        >
                                    </label>
                                @endforeach
                                @if ($this->usesDescriptions())
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">
                                        Deskripsi Capaian
                                        <textarea
                                            rows="4"
                                            class="assessment-description mt-2 min-w-0"
                                            data-assessment-path="scoreRows.{{ $studentId }}.description"
                                            wire:model.blur="scoreRows.{{ $studentId }}.description"
                                            @disabled(! $assignmentMeta['editable'])
                                        ></textarea>
                                    </label>
                                @endif
                            </div>
                        </article>
                    @endforeach
                    @if ($scoreRows)
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <x-filament::button color="gray" x-on:click="current = Math.max(0, current - 1)" x-bind:disabled="current === 0">
                                Sebelumnya
                            </x-filament::button>
                            <x-filament::button color="gray" x-on:click="current = Math.min({{ count($scoreRows) - 1 }}, current + 1)" x-bind:disabled="current >= {{ count($scoreRows) - 1 }}">
                                Berikutnya
                            </x-filament::button>
                        </div>
                    @endif
                </section>

                @if ($assignmentMeta['editable'])
                    <div class="assessment-actionbar">
                        <x-filament::button wire:click="saveDraft" wire:loading.attr="disabled" icon="heroicon-o-cloud-arrow-up">
                            Simpan Draf
                        </x-filament::button>
                        <x-filament::button
                            wire:click="submitAssignment"
                            wire:confirm="{{ $assignmentMeta['deadline_passed'] && $assignmentMeta['can_override_deadline'] ? 'Kirim dengan override deadline? Aksi ini dicatat pada audit penilaian.' : 'Kirim seluruh nilai kelas ini untuk verifikasi? Setelah dikirim, nilai tidak dapat diedit sampai dikembalikan.' }}"
                            wire:loading.attr="disabled"
                            color="success"
                            icon="heroicon-o-paper-airplane"
                        >
                            {{ $assignmentMeta['deadline_passed'] && $assignmentMeta['can_override_deadline'] ? 'Kirim dengan Override Deadline' : 'Kirim untuk Verifikasi' }}
                        </x-filament::button>
                    </div>
                @else
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                        @if ($assignmentMeta['deadline_passed'] && ! $assignmentMeta['can_override_deadline'])
                            <strong>Deadline pengisian telah berakhir.</strong> Nilai terkunci untuk akun guru dan tidak dapat disimpan atau dikirim.
                        @else
                            Penugasan berstatus <strong>{{ $assignmentMeta['status_label'] }}</strong>. Nilai ditampilkan baca-saja.
                        @endif
                    </div>
                @endif
            </div>
        @else
            @php($emptyState = $this->getEmptyAssignmentState())
            <section class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center sm:p-8 dark:border-white/15 dark:bg-white/5">
                <span class="mx-auto flex size-12 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300">
                    <x-filament::icon icon="heroicon-o-clipboard-document-list" class="size-6" />
                </span>
                <h2 class="mt-4 text-balance font-bold text-gray-950 dark:text-white">{{ $emptyState['title'] }}</h2>
                <p class="mx-auto mt-2 max-w-xl text-pretty text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $emptyState['description'] }}</p>
                @if ($emptyState['action_url'])
                    <div class="mt-5">
                        <x-filament::button
                            :href="$emptyState['action_url']"
                            tag="a"
                            icon="heroicon-o-user-group"
                        >
                            {{ $emptyState['action_label'] }}
                        </x-filament::button>
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>
