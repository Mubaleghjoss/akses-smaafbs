<x-filament-panels::page>
    @include('filament.pages.assessment.partials.type-navigation', ['showAccess' => false])

    <div class="assessment-extracurricular-page">
        <x-filament::section heading="Periode ASTS" description="Nilai terverifikasi menggantikan data ekskul manual wali kelas pada rapor. Jika belum ada, rapor tetap memakai data manual.">
            <div class="assessment-extracurricular-field assessment-extracurricular-period">
                <label for="assessment-period">Periode penilaian</label>
                <select id="assessment-period" wire:model.live="periodId" class="fi-input">
                    @foreach ($this->getPeriodOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
            </div>
        </x-filament::section>

        <section class="assessment-extracurricular-summary" aria-label="Ringkasan nilai ekskul">
            @foreach (['active' => 'Ekskul aktif', 'participants' => 'Peserta', 'draft' => 'Draf/belum dinilai', 'submitted' => 'Dikirim', 'verified' => 'Terverifikasi', 'returned' => 'Dikembalikan'] as $key => $label)
                <article class="assessment-extracurricular-summary-card assessment-extracurricular-summary-card--{{ $key }}"><span>{{ $label }}</span><strong>{{ $this->summary[$key] }}</strong></article>
            @endforeach
        </section>

        @if ($this->isManager())
            <section class="assessment-extracurricular-workspace" aria-label="Pengelolaan peserta ekskul">
                <div class="assessment-extracurricular-steps" aria-label="Alur pengisian nilai">
                    <span><b>1</b> Buat ekskul</span><span><b>2</b> Tambah peserta</span><span><b>3</b> Isi nilai</span><span><b>4</b> Kirim verifikasi</span>
                </div>
                <div class="assessment-extracurricular-section">
                    <div class="assessment-extracurricular-section__heading"><div><h2>Buat ekskul baru</h2><p>Tambahkan kegiatan yang akan dinilai pada periode ini.</p></div></div>
                    <div class="assessment-extracurricular-form assessment-extracurricular-form--master">
                        <div class="assessment-extracurricular-field"><label for="new-extracurricular-name">Nama ekskul</label><input id="new-extracurricular-name" wire:model="newName" class="fi-input" placeholder="Contoh: Pramuka"></div>
                        <div class="assessment-extracurricular-field"><label for="new-extracurricular-code">Kode <span>(opsional)</span></label><input id="new-extracurricular-code" wire:model="newCode" class="fi-input" placeholder="Contoh: PRA"></div>
                        <div class="assessment-extracurricular-field"><label for="new-extracurricular-teacher">Guru pembina <span>(opsional)</span></label><select id="new-extracurricular-teacher" wire:model="newTeacherId" class="fi-input"><option value="">Pilih guru</option>@foreach($this->teacherOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
                        <div class="assessment-extracurricular-button"><x-filament::button wire:click="createExtracurricular">Buat ekskul baru</x-filament::button></div>
                    </div>
                </div>
                <div class="assessment-extracurricular-section-grid">
                    <div class="assessment-extracurricular-section"><div class="assessment-extracurricular-section__heading"><div><h2>Tambah peserta manual</h2><p>Pilih ekskul dan siswa untuk menambahkan peserta.</p></div></div><div class="assessment-extracurricular-form"><div class="assessment-extracurricular-field"><label for="participant-extracurricular">Ekskul</label><select id="participant-extracurricular" wire:model="selectedExtracurricularId" class="fi-input"><option value="">Pilih ekskul</option>@foreach($this->extracurriculars as $activity)<option value="{{ $activity->id }}">{{ $activity->name }}</option>@endforeach</select></div><div class="assessment-extracurricular-field"><label for="participant-student">Siswa</label><select id="participant-student" wire:model="selectedStudentId" class="fi-input"><option value="">Pilih siswa</option>@foreach($this->studentOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div><div class="assessment-extracurricular-button"><x-filament::button color="gray" wire:click="addParticipant">Tambah peserta</x-filament::button></div></div></div>
                    <div class="assessment-extracurricular-section"><div class="assessment-extracurricular-section__heading"><div><h2>Import peserta</h2><p>Masukkan banyak peserta sekaligus dari Excel atau CSV.</p></div></div><div class="assessment-extracurricular-form"><div class="assessment-extracurricular-field"><label for="participant-import">File peserta</label><input id="participant-import" type="file" wire:model="importFile" accept=".xlsx,.xls,.csv,.txt" class="fi-input assessment-extracurricular-file"><small>Template berisi seluruh siswa aktif periode ini; isi nama_ekskul, predikat, dan catatan bila nilai ingin langsung dibuat sebagai draf.</small></div><div class="assessment-extracurricular-button"><x-filament::button color="gray" wire:click="downloadImportTemplate">Download template siswa</x-filament::button><x-filament::button color="gray" wire:click="importParticipants">Import peserta</x-filament::button></div></div></div>
                </div>
            </section>
        @endif

        <div class="assessment-extracurricular-results">
            <x-filament::section heading="Daftar ekskul" description="Pilih satu ekskul untuk meninjau peserta dan progres nilainya.">
                <div class="assessment-extracurricular-activities">
                    @forelse($this->extracurriculars as $activity)
                        <button wire:click="$set('selectedExtracurricularId', {{ $activity->id }})" @class(['assessment-extracurricular-activity', 'is-selected' => $selectedExtracurricularId === $activity->id])>
                            <strong>{{ $activity->name }}</strong>
                            <span>{{ $activity->teachers->pluck('name')->join(', ') ?: 'Pembina belum ditentukan' }}</span>
                            <small>{{ $activity->participants_count }} peserta · {{ $activity->submitted_count }} dikirim · {{ $activity->verified_count }} terverifikasi</small>
                            <em>{{ $selectedExtracurricularId === $activity->id ? 'Sedang dipilih' : 'Pilih dan isi nilai' }}</em>
                        </button>
                    @empty
                        <p class="assessment-extracurricular-empty">Belum ada ekskul pada periode ini. {{ $this->isManager() ? 'Buat ekskul baru, lalu tambahkan peserta atau import file.' : 'Hubungi admin atau kurikulum untuk menugaskan ekskul Anda.' }}</p>
                    @endforelse
                </div>
            </x-filament::section>
            <x-filament::section heading="Nilai peserta" description="Simpan sebagai draf, lalu kirim agar admin/kurikulum dapat memverifikasi.">
                <div class="assessment-extracurricular-filters">
                    <div class="assessment-extracurricular-field"><label for="participant-search">Cari siswa atau kelas</label><input id="participant-search" wire:model.live.debounce.300ms="participantSearch" class="fi-input" placeholder="Contoh: Ahmad atau X-1"></div>
                    <div class="assessment-extracurricular-field"><label for="participant-status">Status nilai</label><select id="participant-status" wire:model.live="participantStatusFilter" class="fi-input"><option value="all">Semua status</option><option value="unscored">Belum dinilai</option><option value="draft">Draf</option><option value="submitted">Dikirim</option><option value="verified">Terverifikasi</option><option value="returned">Dikembalikan</option></select></div>
                </div>
                <div class="assessment-extracurricular-table-wrap"><table class="assessment-extracurricular-table"><thead><tr><th>Siswa</th><th>Kelas</th><th>Predikat</th><th>Catatan</th><th>Status dan aksi</th></tr></thead><tbody>@forelse($this->participants as $participant)@php($score = $participant->score)<tr><td data-label="Siswa" class="font-medium">{{ $participant->student->student_name_snapshot }}</td><td data-label="Kelas">{{ $participant->student->rombel_name_snapshot }}</td><td data-label="Predikat"><select wire:model="predicates.{{ $participant->id }}" class="fi-input" @disabled($score && in_array($score->status, ['submitted','verified']))><option value="">Pilih</option>@foreach(['A','B','C','D'] as $grade)<option>{{ $grade }}</option>@endforeach</select></td><td data-label="Catatan"><input wire:model="descriptions.{{ $participant->id }}" class="fi-input" @disabled($score && in_array($score->status, ['submitted','verified']))></td><td data-label="Status dan aksi"><span class="assessment-extracurricular-status">{{ $score?->status ?? 'belum diisi' }}</span><div class="assessment-extracurricular-actions">@if(!$score || in_array($score->status, ['draft','returned']))<x-filament::button size="xs" color="gray" wire:click="saveScore({{ $participant->id }})">Simpan</x-filament::button>@if($score)<x-filament::button size="xs" wire:click="submitScore({{ $participant->id }})">Kirim</x-filament::button>@endif @endif @if($this->isManager() && $score?->status === 'submitted')<x-filament::button size="xs" color="success" wire:click="verifyScore({{ $score->id }})">Verifikasi</x-filament::button><input wire:model="returnReasons.{{ $score->id }}" class="fi-input" placeholder="Alasan pengembalian"><x-filament::button size="xs" color="danger" wire:click="returnScore({{ $score->id }})">Kembalikan</x-filament::button>@endif</div></td></tr>@empty<tr><td colspan="5" class="assessment-extracurricular-empty">{{ $selectedExtracurricularId ? 'Tidak ada peserta yang cocok dengan filter ini.' : 'Pilih ekskul untuk melihat peserta dan mulai mengisi nilai.' }}</td></tr>@endforelse</tbody></table></div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
