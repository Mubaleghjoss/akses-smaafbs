<x-filament-panels::page>
    @include('filament.pages.assessment.partials.type-navigation', ['showAccess' => false])

    <div class="space-y-6">
        <x-filament::section heading="Periode ASTS" description="Nilai terverifikasi menggantikan data ekskul manual wali kelas pada rapor. Jika belum ada, rapor tetap memakai data manual.">
            <select wire:model.live="periodId" class="fi-input w-full max-w-xl rounded-lg border-gray-300 dark:border-white/10">
                @foreach ($this->getPeriodOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
        </x-filament::section>

        @if ($this->isManager())
            <x-filament::section heading="Master dan peserta ekskul">
                <div class="grid gap-3 md:grid-cols-4">
                    <input wire:model="newName" class="fi-input rounded-lg" placeholder="Nama ekskul">
                    <input wire:model="newCode" class="fi-input rounded-lg" placeholder="Kode (opsional)">
                    <select wire:model="newTeacherId" class="fi-input rounded-lg"><option value="">Pilih guru (opsional)</option>@foreach($this->teacherOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                    <x-filament::button wire:click="createExtracurricular">Buat ekskul</x-filament::button>
                </div>
                <div class="mt-5 grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium">Tambah peserta manual</label>
                        <div class="mt-1 flex gap-2">
                            <select wire:model="selectedExtracurricularId" class="fi-input min-w-0 flex-1 rounded-lg"><option value="">Pilih ekskul</option>@foreach($this->extracurriculars as $activity)<option value="{{ $activity->id }}">{{ $activity->name }}</option>@endforeach</select>
                            <select wire:model="selectedStudentId" class="fi-input min-w-0 flex-1 rounded-lg"><option value="">Pilih siswa</option>@foreach($this->studentOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                            <x-filament::button color="gray" wire:click="addParticipant">Tambah</x-filament::button>
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Import Excel/CSV</label>
                        <div class="mt-1 flex items-center gap-2"><input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv,.txt" class="block min-w-0 flex-1 text-sm"><x-filament::button color="gray" wire:click="importParticipants">Import</x-filament::button></div>
                        <p class="mt-1 text-xs text-gray-500">Header wajib: nama_siswa, kelas, nama_ekskul. nisn opsional.</p>
                    </div>
                </div>
            </x-filament::section>
        @endif

        <div class="grid gap-6 lg:grid-cols-[280px_1fr]">
            <x-filament::section heading="Beban ekskul">
                <div class="space-y-2">
                    @forelse($this->extracurriculars as $activity)
                        <button wire:click="$set('selectedExtracurricularId', {{ $activity->id }})" @class(['w-full rounded-xl border p-3 text-left', 'border-primary-500 bg-primary-50 dark:bg-primary-950' => $selectedExtracurricularId === $activity->id, 'border-gray-200 dark:border-white/10' => $selectedExtracurricularId !== $activity->id])>
                            <strong class="block">{{ $activity->name }}</strong><span class="text-xs text-gray-500">{{ $activity->verified_count }}/{{ $activity->participants_count }} terverifikasi</span>
                        </button>
                    @empty <p class="text-sm text-gray-500">Belum ada penugasan ekskul.</p> @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="Nilai peserta" description="Simpan sebagai draf, lalu kirim agar admin/kurikulum dapat memverifikasi.">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm"><thead><tr class="border-b text-left"><th class="p-2">Siswa</th><th class="p-2">Kelas</th><th class="p-2">Predikat</th><th class="p-2">Catatan</th><th class="p-2">Status / aksi</th></tr></thead>
                        <tbody>@forelse($this->participants as $participant)
                            @php($score = $participant->score)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="p-2 font-medium">{{ $participant->student->student_name_snapshot }}</td><td class="p-2">{{ $participant->student->rombel_name_snapshot }}</td>
                                <td class="p-2"><select wire:model="predicates.{{ $participant->id }}" class="fi-input rounded-lg" @disabled($score && in_array($score->status, ['submitted','verified']))><option value="">-</option>@foreach(['A','B','C','D'] as $grade)<option>{{ $grade }}</option>@endforeach</select></td>
                                <td class="p-2"><input wire:model="descriptions.{{ $participant->id }}" class="fi-input rounded-lg" @disabled($score && in_array($score->status, ['submitted','verified']))></td>
                                <td class="p-2"><span class="mb-2 block text-xs font-semibold uppercase">{{ $score?->status ?? 'belum diisi' }}</span>
                                    <div class="flex flex-wrap gap-1">
                                    @if(!$score || in_array($score->status, ['draft','returned']))<x-filament::button size="xs" color="gray" wire:click="saveScore({{ $participant->id }})">Simpan</x-filament::button>@if($score)<x-filament::button size="xs" wire:click="submitScore({{ $participant->id }})">Kirim</x-filament::button>@endif @endif
                                    @if($this->isManager() && $score?->status === 'submitted')<x-filament::button size="xs" color="success" wire:click="verifyScore({{ $score->id }})">Verifikasi</x-filament::button><input wire:model="returnReasons.{{ $score->id }}" class="fi-input w-36 rounded-lg" placeholder="Alasan"><x-filament::button size="xs" color="danger" wire:click="returnScore({{ $score->id }})">Kembalikan</x-filament::button>@endif
                                    </div>
                                </td>
                            </tr>
                        @empty<tr><td colspan="5" class="p-6 text-center text-gray-500">Pilih ekskul untuk melihat peserta.</td></tr>@endforelse</tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
