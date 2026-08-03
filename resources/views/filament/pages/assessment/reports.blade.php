<x-filament-panels::page>
    <div class="assessment-reports-page">
        @include('filament.pages.assessment.partials.type-navigation', ['showAccess' => false])

        <section class="assessment-report-card is-step">
            <span class="assessment-report-step">1</span>
            <div class="assessment-report-card__body">
                <div class="assessment-report-card__head">
                    <div>
                        <span class="assessment-report-eyebrow">Persiapan</span>
                        <h2>Pilih periode dan template</h2>
                        <p>Snapshot, PDF, dan tautan selalu terikat pada periode serta revisi template ini.</p>
                    </div>
                </div>
                <div class="assessment-report-form-grid">
                    <label><span>Periode</span>
                        <select wire:model.live="periodId">
                            @foreach ($this->getPeriodOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label><span>Template</span>
                        <select wire:model.live="templateId">
                            @foreach ($this->getTemplateOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                </div>
            </div>
        </section>

        @php($preflight = $this->getReportPreflight())
        <section class="assessment-report-card assessment-report-preflight {{ $preflight['ready'] ? 'is-ready' : 'is-warning' }}">
            <div class="assessment-report-card__body">
                <div class="assessment-report-card__head">
                    <div>
                        <span class="assessment-report-eyebrow">Kelengkapan Data Rapor</span>
                        <h2>{{ $preflight['ready'] ? 'Siap membuat rapor resmi' : 'Data wajib masih perlu dilengkapi' }}</h2>
                        <p>
                            {{ $preflight['ready']
                                ? 'Nilai, kelompok mapel, data wali kelas, dan identitas template sudah lolos pemeriksaan.'
                                : 'Pratinjau tetap dapat dipakai. Pembuatan PDF resmi ditahan sampai seluruh pemeriksaan berwarna hijau.' }}
                        </p>
                    </div>
                    <span class="assessment-report-status {{ $preflight['ready'] ? 'is-completed' : 'is-failed' }}">
                        {{ $preflight['ready'] ? 'Siap' : 'Perlu dilengkapi' }}
                    </span>
                </div>
                <div class="assessment-report-preflight-grid">
                    @foreach($preflight['groups'] as $group)
                        <article class="{{ $group['issues'] === [] ? 'is-ready' : 'is-warning' }}">
                            <strong>{{ $group['label'] }}</strong>
                            @if($group['issues'] === [])
                                <p>Semua pemeriksaan pada bagian ini sudah lengkap.</p>
                            @else
                                <ul class="assessment-report-preflight-issues">
                                    @foreach($group['issues'] as $issue)
                                        <li>
                                            @php($repair = $issue['repair'] ?? null)
                                            @if($repair)
                                                <a
                                                    href="{{ $repair['url'] }}"
                                                    wire:navigate
                                                    class="assessment-report-preflight-issue is-actionable"
                                                    aria-label="{{ $repair['label'] }}: {{ $issue['message'] }}"
                                                >
                                                    <span class="assessment-report-preflight-issue__copy">
                                                        <span>{{ $issue['message'] }} <b>{{ $issue['count'] }}</b></span>
                                                        @if($issue['samples'] !== [])
                                                            <small>{{ implode(' · ', $issue['samples']) }}</small>
                                                        @endif
                                                    </span>
                                                    <span class="assessment-report-preflight-issue__action">
                                                        <x-filament::icon :icon="$repair['icon']" />
                                                        {{ $repair['label'] }}
                                                    </span>
                                                </a>
                                            @else
                                                <div class="assessment-report-preflight-issue">
                                                    <span class="assessment-report-preflight-issue__copy">
                                                        <span>{{ $issue['message'] }} <b>{{ $issue['count'] }}</b></span>
                                                        @if($issue['samples'] !== [])
                                                            <small>{{ implode(' · ', $issue['samples']) }}</small>
                                                        @endif
                                                    </span>
                                                    <span class="assessment-report-preflight-issue__restricted">Tidak ada akses perbaikan</span>
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </article>
                    @endforeach
                </div>
                @if (! $preflight['ready'])
                    <div class="assessment-report-primary-actions">
                        <x-filament::button
                            tag="a"
                            href="{{ \App\Filament\Pages\Assessment\AssessmentDashboard::getUrl(['period' => $periodId]) }}"
                            color="gray"
                            icon="heroicon-o-wrench-screwdriver"
                        >
                            Buka Wizard Kelengkapan
                        </x-filament::button>
                    </div>
                @endif
            </div>
        </section>

        <section class="assessment-report-card">
            <div class="assessment-report-card__body">
                <div class="assessment-report-card__head">
                    <div>
                        <span class="assessment-report-eyebrow">Pratinjau satu siswa</span>
                        <h2>Periksa tampilan PDF dan watermark</h2>
                        <p>Pratinjau memakai data siswa nyata dari periode, tidak disimpan, tidak membuat job, dan bukan rapor resmi.</p>
                    </div>
                </div>
                <div class="assessment-report-preview-row">
                    <label><span>Siswa</span>
                        <select wire:model.live="previewStudentId">
                            <option value="">Pilih siswa</option>
                            @foreach ($this->getPreviewOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    @if ($this->previewUrl())
                        <x-filament::button tag="a" href="{{ $this->previewUrl() }}" target="_blank" color="gray" icon="heroicon-o-eye">Buka Pratinjau PDF</x-filament::button>
                    @else
                        <span class="assessment-report-inline-note">Pilih periode yang sudah memiliki siswa agar pratinjau dapat dibuka.</span>
                    @endif
                </div>
            </div>
        </section>

        @php($run = $this->getGenerationRun())
        @if ($this->canGenerateReports())
            <section class="assessment-report-card is-step">
                <span class="assessment-report-step">2</span>
                <div class="assessment-report-card__body">
                    <div class="assessment-report-card__head">
                        <div>
                            <span class="assessment-report-eyebrow">Pipeline PDF ringan</span>
                            <h2>Pilih kelas yang akan diproses</h2>
                            <p>Satu job memproses maksimal {{ config('assessment.reports.pipeline.students_per_job', 3) }} siswa atau sekitar {{ config('assessment.reports.pipeline.max_seconds', 40) }} detik, lalu dilanjutkan pada putaran berikutnya.</p>
                        </div>
                    </div>

                    <div class="assessment-report-class-actions">
                        <x-filament::button size="sm" color="gray" wire:click="selectAllClasses">Pilih Semua Kelas</x-filament::button>
                        <x-filament::button size="sm" color="gray" wire:click="clearClassSelection">Kosongkan</x-filament::button>
                        <span>{{ count($selectedClassIds) }} kelas dipilih</span>
                    </div>
                    <div class="assessment-report-class-grid">
                        @foreach ($this->getClassOptions() as $id => $label)
                            <label class="assessment-report-class-choice">
                                <input type="checkbox" value="{{ $id }}" wire:model.live="selectedClassIds">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="assessment-report-pipeline-state">
                        @if (! $run)
                            <strong>Belum ada revisi yang disiapkan</strong>
                            <span>Siapkan snapshot terlebih dahulu. Langkah ini belum membuat job PDF.</span>
                        @elseif ($run->status->value === 'prepared')
                            <strong>Revisi {{ $run->revision }} siap · kelas belum dijadwalkan</strong>
                            <span>Snapshot siswa sudah siap diunduh tanpa file permanen. Pilih kelas hanya jika membutuhkan PDF gabungan 24 jam.</span>
                        @elseif ($run->status->value === 'running')
                            <strong>Revisi {{ $run->revision }} sedang diproses</strong>
                            <span>Kelas yang belum masuk antrean masih dapat dipilih dan dijadwalkan pada revisi yang sama.</span>
                        @elseif ($run->status->value === 'completed')
                            <strong>Revisi {{ $run->revision }} sudah selesai</strong>
                            <span>Gunakan Mulai Ulang dengan Revisi Baru hanya jika nilai atau template memang berubah.</span>
                        @elseif ($run->status->value === 'cancelled')
                            <strong>Revisi {{ $run->revision }} telah dihentikan</strong>
                            <span>Siapkan revisi baru dengan alasan agar riwayat lama tetap dapat diaudit.</span>
                        @else
                            <strong>Revisi {{ $run->revision }} perlu diperiksa</strong>
                            <span>Coba jadwalkan ulang kelas yang gagal atau mulai revisi baru jika datanya berubah.</span>
                        @endif
                    </div>

                    <div class="assessment-report-primary-actions">
                        @if (! $run)
                            <x-filament::button wire:click="prepareRevision" wire:loading.attr="disabled" icon="heroicon-o-document-check">Siapkan Revisi</x-filament::button>
                        @elseif (in_array($run->status->value, ['prepared', 'running', 'failed'], true))
                            <x-filament::button wire:click="scheduleSelectedClasses" wire:loading.attr="disabled" icon="heroicon-o-play">Jadwalkan Kelas Terpilih</x-filament::button>
                        @endif
                        @if ($run)
                            <x-filament::button color="warning" x-on:click="$dispatch('open-modal', { id: 'assessment-restart-revision-modal' })" icon="heroicon-o-arrow-path">Mulai Ulang dengan Revisi Baru</x-filament::button>
                        @endif
                        <x-filament::button color="danger" x-on:click="$dispatch('open-modal', { id: 'assessment-stop-reports-modal' })" icon="heroicon-o-stop">Hentikan Semua Antrean PDF</x-filament::button>
                    </div>
                </div>
            </section>
        @endif

        @php($classRows = $this->getClassRows())
        <section class="assessment-report-card is-step">
            <span class="assessment-report-step">3</span>
            <div class="assessment-report-card__body">
                <div class="assessment-report-card__head">
                    <div>
                        <span class="assessment-report-eyebrow">Progres</span>
                        <h2>Cache PDF per kelas</h2>
                        <p>PDF siswa dirender langsung saat diunduh. Bagian ini hanya membuat satu PDF gabungan yang otomatis dihapus setelah 24 jam.</p>
                    </div>
                    @if ($run)
                        <span class="assessment-report-status is-{{ $run->status->value }}">{{ $run->status->label() }} · Revisi {{ $run->revision }}</span>
                    @endif
                </div>

                @if ($run)
                    <div class="assessment-report-stat-grid">
                        <article><strong>{{ $run->completed_students }}/{{ $run->total_students }}</strong><span>Snapshot siswa siap</span></article>
                        <article><strong>{{ $run->completed_classes }}/{{ $run->total_classes }}</strong><span>Cache kelas tersedia</span></article>
                        <article><strong>{{ $run->status->label() }}</strong><span>Status pipeline</span></article>
                    </div>
                @endif

                <div class="assessment-report-class-result-grid">
                    @forelse ($classRows as $row)
                        <article>
                            <div class="assessment-report-result-head">
                                <div><strong>{{ $row['rombel'] }}</strong><small>Revisi {{ $row['revision'] }}</small></div>
                                <span class="assessment-report-status is-{{ $row['status'] }}">{{ $row['status_label'] }}</span>
                            </div>
                            <div class="assessment-report-progress"><span style="width: {{ $row['student_count'] > 0 ? round($row['completed_students'] / $row['student_count'] * 100) : 0 }}%"></span></div>
                            <p>{{ $row['completed_students'] }}/{{ $row['student_count'] }} snapshot siswa siap.</p>
                            @if ($row['cache_expires_at'])<p>Cache berlaku sampai {{ $row['cache_expires_at'] }}.</p>@endif
                            @if ($row['error'])<p class="assessment-report-error">{{ $row['error'] }}</p>@endif
                            <div class="assessment-report-result-actions">
                                @if ($row['download_url'])<x-filament::button tag="a" href="{{ $row['download_url'] }}" target="_blank" size="sm" color="gray">Download PDF Kelas</x-filament::button>@endif
                                @if ($this->canGenerateReports())
                                    @if (in_array($row['status'], ['failed', 'expired'], true))<x-filament::button wire:click="retryClass({{ $row['id'] }})" size="sm" color="warning">Buat Ulang Cache</x-filament::button>@endif
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="assessment-report-empty">Belum ada pipeline kelas pada periode dan template ini.</div>
                    @endforelse
                </div>
            </div>
        </section>

        @if ($latestShareUrl)
            <section x-data="{ copied:false }" class="assessment-report-share-result">
                <h2>Tautan sementara baru</h2>
                <div><input readonly value="{{ $latestShareUrl }}"><x-filament::button color="success" x-on:click="navigator.clipboard.writeText(@js($latestShareUrl)); copied=true" x-text="copied ? 'Tersalin' : 'Salin Tautan'"></x-filament::button></div>
            </section>
        @endif

        @if ($latestShareLinks !== [])
            @php($latestShareLinksText = implode("\n\n", $latestShareLinks))
            <section x-data="{ copied:false }" class="assessment-report-share-result">
                <h2>{{ count($latestShareLinks) }} tautan berhasil dibuat</h2>
                <textarea readonly rows="{{ min(14, count($latestShareLinks) * 3) }}">{{ $latestShareLinksText }}</textarea>
                <x-filament::button color="success" x-on:click="navigator.clipboard.writeText(@js($latestShareLinksText)); copied=true" x-text="copied ? 'Semua Tersalin' : 'Salin Semua Tautan'"></x-filament::button>
                <small>Token asli hanya ditampilkan pada pembuatan ini.</small>
            </section>
        @endif

        @php($snapshotRows = $this->getSnapshotRows())
        <section class="assessment-report-card is-share is-step">
            <span class="assessment-report-step">4</span>
            <div class="assessment-report-card__body">
                <div class="assessment-report-card__head">
                    <div>
                        <span class="assessment-report-eyebrow">Distribusi opsional</span>
                        <h2>Pilih siswa yang akan dibuatkan tautan</h2>
                        <p>Tautan tidak memakai queue. Maksimal 50 siswa per proses dan hanya tersedia setelah periode diterbitkan.</p>
                    </div>
                </div>
                @if ($this->canPublishReports())
                    <div class="assessment-report-share-toolbar">
                        <label><span>Masa aktif</span>
                            <select wire:model="shareExpiryDays"><option value="1">1 hari</option><option value="3">3 hari</option><option value="7">7 hari</option></select>
                        </label>
                        <x-filament::button size="sm" color="gray" wire:click="selectAllShareableSnapshots">Pilih Maks. 50</x-filament::button>
                        <x-filament::button size="sm" color="gray" wire:click="clearShareSelection">Kosongkan</x-filament::button>
                        <x-filament::button size="sm" color="success" wire:click="issueSelectedShareLinks" wire:confirm="Buat tautan sementara untuk seluruh siswa terpilih?" :disabled="count($selectedShareSnapshotIds) === 0 || ! $this->selectedPeriodIsPublished()">Buat {{ count($selectedShareSnapshotIds) }} Tautan</x-filament::button>
                    </div>
                    @if (! $this->selectedPeriodIsPublished())<div class="assessment-report-inline-note">Periode belum published. Pastikan seluruh snapshot siap lalu terbitkan periode sebelum membuat tautan.</div>@endif
                @endif

                <div class="assessment-report-student-list">
                    @forelse ($snapshotRows as $row)
                        <article>
                            @if ($this->canPublishReports())
                                <input type="checkbox" value="{{ $row['id'] }}" wire:model.live="selectedShareSnapshotIds" @disabled(! in_array($row['status'], ['ready', 'completed'], true)) aria-label="Pilih {{ $row['student'] }}">
                            @endif
                            <div class="assessment-report-student-copy">
                                <strong>{{ $row['student'] }}</strong>
                                <span>{{ $row['rombel'] }} · Revisi {{ $row['revision'] }} · tautan aktif {{ $row['active_links'] }}</span>
                                @if ($row['error'])<small class="assessment-report-error">{{ $row['error'] }}</small>@endif
                            </div>
                            <span class="assessment-report-status is-{{ $row['status'] }}">{{ $row['status_label'] }}</span>
                            <div class="assessment-report-student-actions">
                                <x-filament::button tag="a" href="{{ $row['preview_url'] }}" target="_blank" size="sm" color="gray">Preview</x-filament::button>
                                @if ($row['download_url'])<x-filament::button tag="a" href="{{ $row['download_url'] }}" target="_blank" size="sm" color="gray">Download</x-filament::button>@endif
                                @if ($this->canGenerateReports())
                                    @if ($row['status'] === 'failed')<x-filament::button wire:click="retrySnapshot({{ $row['id'] }})" size="sm" color="warning">Coba Lagi</x-filament::button>@endif
                                @endif
                                @if ($this->canPublishReports())
                                    @if ($row['active_links'] > 0)<x-filament::button wire:click="revokeShareLinks({{ $row['id'] }})" wire:confirm="Cabut seluruh tautan aktif rapor ini?" size="sm" color="danger">Cabut</x-filament::button>@endif
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="assessment-report-empty">Belum ada snapshot rapor.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <x-filament::modal id="assessment-stop-reports-modal" width="lg">
            <x-slot name="heading">Hentikan Semua Antrean PDF</x-slot>
            <x-slot name="description">Hanya queue assessment-reports yang dibersihkan. Queue Literasi dan default tidak disentuh; snapshot siswa tetap aman.</x-slot>
            <label class="assessment-report-stop-field"><span>Alasan penghentian</span><textarea wire:model="stopReason" rows="4" maxlength="1000" placeholder="Minimal 10 karakter"></textarea></label>
            <x-slot name="footerActions">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'assessment-stop-reports-modal' })">Batal</x-filament::button>
                <x-filament::button color="danger" wire:click="stopAllReportJobs" wire:confirm="Konfirmasi terakhir: hapus SELURUH job assessment-reports dan tandai proses berjalan sebagai dihentikan?">Ya, Hentikan Semua PDF</x-filament::button>
            </x-slot>
        </x-filament::modal>

        <x-filament::modal id="assessment-restart-revision-modal" width="lg">
            <x-slot name="heading">Mulai Ulang dengan Revisi Baru</x-slot>
            <x-slot name="description">Revisi terbuka akan ditandai dihentikan, bukan dihapus. Snapshot revisi baru disiapkan tanpa menjadwalkan kelas atau job PDF.</x-slot>
            <label class="assessment-report-stop-field">
                <span>Alasan revisi baru</span>
                <textarea wire:model="restartReason" rows="4" maxlength="1000" placeholder="Minimal 10 karakter"></textarea>
            </label>
            <x-slot name="footerActions">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'assessment-restart-revision-modal' })">Batal</x-filament::button>
                <x-filament::button color="warning" wire:click="restartWithNewRevision" wire:confirm="Siapkan revisi baru dan hentikan seluruh revisi terbuka untuk periode serta template ini?">Ya, Siapkan Revisi Baru</x-filament::button>
            </x-slot>
        </x-filament::modal>
    </div>
</x-filament-panels::page>
