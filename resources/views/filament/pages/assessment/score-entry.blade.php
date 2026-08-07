<x-filament-panels::page>
    <style>
        .assessment-score-page { min-width: 0; }
        .assessment-score-toolbar {
            display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem;
            padding: 1rem; border: 1px solid rgba(148,163,184,.25); border-radius: 1rem;
            background: var(--gray-50, #f8fafc);
        }
        .dark .assessment-score-toolbar { background: rgba(255,255,255,.035); border-color: rgba(255,255,255,.1); }
        .assessment-score-field label { display:block; margin-bottom:.35rem; font-size:.75rem; font-weight:700; color:#64748b; }
        .assessment-score-select {
            width:100%; min-width:0; min-height:2.65rem; padding:.55rem .75rem; border-radius:.75rem;
            border:1px solid #cbd5e1; background:#fff; color:#0f172a;
        }
        .dark .assessment-score-select { background:#111827; color:#f8fafc; border-color:rgba(255,255,255,.15); }
        .assessment-score-meta { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; margin-top:1rem; }
        .assessment-score-pill { display:inline-flex; padding:.35rem .65rem; border-radius:999px; background:#e2e8f0; color:#334155; font-size:.75rem; font-weight:700; }
        .dark .assessment-score-pill { background:rgba(255,255,255,.1); color:#e2e8f0; }
        .assessment-matrix-shell { max-width:100%; overflow:hidden; border:1px solid rgba(148,163,184,.25); border-radius:1rem; }
        .assessment-matrix-scroll { max-width:100%; overflow:auto; -webkit-overflow-scrolling:touch; }
        .assessment-matrix { width:100%; min-width:780px; border-collapse:separate; border-spacing:0; }
        .assessment-matrix th, .assessment-matrix td { padding:.7rem; border-right:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0; vertical-align:top; }
        .dark .assessment-matrix th, .dark .assessment-matrix td { border-color:rgba(255,255,255,.08); }
        .assessment-matrix th { position:sticky; top:0; z-index:3; background:#f1f5f9; color:#475569; font-size:.72rem; text-align:left; }
        .dark .assessment-matrix th { background:#1f2937; color:#cbd5e1; }
        .assessment-matrix .student-col { position:sticky; left:0; z-index:2; min-width:210px; max-width:240px; background:#fff; }
        .assessment-matrix th.student-col { z-index:4; background:#f1f5f9; }
        .dark .assessment-matrix .student-col { background:#111827; }
        .dark .assessment-matrix th.student-col { background:#1f2937; }
        .assessment-score-input, .assessment-description {
            width:100%; min-width:5.5rem; border:1px solid #cbd5e1; border-radius:.65rem; background:#fff; color:#0f172a; padding:.55rem .65rem;
        }
        .assessment-score-input:focus, .assessment-description:focus { outline:2px solid rgba(13,148,136,.28); border-color:#0d9488; }
        .assessment-score-input:disabled, .assessment-description:disabled { opacity:.65; background:#f1f5f9; }
        .dark .assessment-score-input, .dark .assessment-description { background:#0f172a; color:#fff; border-color:rgba(255,255,255,.15); }
        .assessment-description { min-width:240px; resize:vertical; }
        .assessment-mobile-card { border:1px solid rgba(148,163,184,.25); border-radius:1rem; background:#fff; padding:1rem; }
        .dark .assessment-mobile-card { background:#111827; border-color:rgba(255,255,255,.1); }
        .assessment-mobile-grid { display:grid; gap:.85rem; margin-top:1rem; }
        .assessment-mobile-score { display:grid; grid-template-columns:minmax(0,1fr) minmax(90px,120px); gap:.7rem; align-items:center; }
        .assessment-actionbar { display:flex; flex-wrap:wrap; gap:.65rem; align-items:center; justify-content:flex-end; }
        .assessment-bulk-card {
            display:grid; gap:1rem; padding:1rem; border:1px solid rgba(13,148,136,.28);
            border-radius:1rem; background:rgba(240,253,250,.72);
        }
        .dark .assessment-bulk-card { border-color:rgba(45,212,191,.22); background:rgba(13,148,136,.08); }
        .assessment-bulk-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:.8rem; }
        .assessment-bulk-grid .is-wide { grid-column:1 / -1; }
        .assessment-student-check { display:flex; align-items:flex-start; gap:.55rem; cursor:pointer; }
        .assessment-student-check input { margin-top:.18rem; flex:0 0 auto; }
        @media (max-width: 767px) {
            .assessment-score-toolbar { grid-template-columns:minmax(0,1fr); }
            .assessment-bulk-grid { grid-template-columns:minmax(0,1fr); }
            .assessment-bulk-grid .is-wide { grid-column:auto; }
            .assessment-desktop { display:none !important; }
            .assessment-actionbar > * { flex:1 1 auto; }
        }
        @media (min-width: 768px) { .assessment-mobile { display:none !important; } }
    </style>

    <div class="assessment-score-page space-y-5">
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
        <section class="assessment-progress-note">
            <span class="assessment-progress-note__icon">
                <x-filament::icon icon="heroicon-o-information-circle" />
            </span>
            <div>
                <strong>{{ $assignmentProgress['sent'] }} dari {{ $assignmentProgress['total'] }} penugasan sudah dikirim.</strong>
                <p>{{ $assignmentProgress['remaining'] }} penugasan masih dapat dilengkapi. Pilihan Draf dan Dikembalikan ditampilkan lebih dahulu.</p>
            </div>
        </section>

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
                    <span class="assessment-score-pill">{{ $assignmentMeta['status_label'] }}</span>
                    <span class="assessment-score-pill">{{ $assignmentMeta['teacher'] }}</span>
                    <span class="assessment-score-pill">{{ count($scoreRows) }} siswa</span>
                    <span class="assessment-score-pill">Versi {{ $lockVersion }}</span>
                </div>
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
                            <h2 class="font-bold text-gray-950 dark:text-white">Isi Nilai & Deskripsi Massal</h2>
                            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">
                                Centang siswa, isi nilai atau deskripsi, lalu terapkan ke formulir. Data belum masuk server sampai tombol <strong>Simpan Draf</strong> ditekan.
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
                            <label class="assessment-score-field is-wide">
                                <span class="block text-xs font-bold text-gray-600 dark:text-gray-300">Deskripsi Massal (opsional)</span>
                                <textarea wire:model="bulkDescription" rows="2" class="assessment-description mt-2 min-w-0" placeholder="Contoh: Menunjukkan pemahaman yang baik dan konsisten."></textarea>
                            </label>
                            <label class="is-wide flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" wire:model="bulkFillEmptyOnly" class="mt-1 rounded border-gray-300">
                                <span><strong>Hanya isi kolom yang masih kosong</strong><small class="block text-gray-500">Bawaan nonaktif: nilai/deskripsi lama pada siswa terpilih akan ditimpa setelah konfirmasi.</small></span>
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
                        <table class="assessment-matrix">
                            <thead>
                                <tr>
                                    <th class="student-col">Siswa</th>
                                    @foreach ($components as $component)
                                        <th>
                                            {{ $component['name'] }}
                                            <div class="mt-1 font-normal">
                                                {{ $component['weight'] }}% · {{ $component['minimum_score'] }}–{{ $component['maximum_score'] }}
                                            </div>
                                        </th>
                                    @endforeach
                                    <th>Deskripsi Capaian</th>
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
                                                </span>
                                            </label>
                                            @if ($row['final_score'] !== null)
                                                <div class="assessment-final-score mt-2">Nilai akhir: {{ \App\Support\Assessment\AssessmentNumberFormatter::score($row['final_score']) }}</div>
                                            @endif
                                        </td>
                                        @foreach ($components as $component)
                                            <td>
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
                                        <td>
                                            <textarea
                                                rows="3"
                                                class="assessment-description"
                                                data-assessment-path="scoreRows.{{ $studentId }}.description"
                                                wire:model.blur="scoreRows.{{ $studentId }}.description"
                                                @disabled(! $assignmentMeta['editable'])
                                            ></textarea>
                                        </td>
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
                                    </span>
                                </label>
                                    <span class="assessment-score-pill">{{ $index + 1 }}/{{ count($scoreRows) }}</span>
                                </div>
                                @if ($row['final_score'] !== null)
                                    <div class="assessment-final-score">Nilai akhir: {{ \App\Support\Assessment\AssessmentNumberFormatter::score($row['final_score']) }}</div>
                                @endif
                            <div class="assessment-mobile-grid">
                                @foreach ($components as $component)
                                    <label class="assessment-mobile-score">
                                        <span class="min-w-0 text-sm font-semibold text-gray-700 dark:text-gray-200">
                                            {{ $component['name'] }}
                                            <small class="block font-normal text-gray-500">
                                                {{ $component['weight'] }}% · {{ $component['minimum_score'] }}–{{ $component['maximum_score'] }}
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
                            wire:confirm="Kirim seluruh nilai kelas ini untuk verifikasi? Setelah dikirim, nilai tidak dapat diedit sampai dikembalikan."
                            wire:loading.attr="disabled"
                            color="success"
                            icon="heroicon-o-paper-airplane"
                        >
                            Kirim untuk Verifikasi
                        </x-filament::button>
                    </div>
                @else
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                        Penugasan berstatus <strong>{{ $assignmentMeta['status_label'] }}</strong>. Nilai ditampilkan baca-saja.
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
