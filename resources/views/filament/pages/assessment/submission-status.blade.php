<x-filament-panels::page>
    <div class="assessment-status-page">
        @include('filament.pages.assessment.partials.type-navigation', ['showAccess' => false])

        <section class="assessment-status-filter-card">
            <label>
                <span>Periode</span>
                <select wire:model.live="periodId" class="mt-2 w-full min-w-0 rounded-lg border-gray-300 bg-white dark:border-white/10 dark:bg-gray-950">
                    @foreach ($this->getPeriodOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Status Penugasan</span>
                <select wire:model.live="statusFilter" class="mt-2 w-full min-w-0 rounded-lg border-gray-300 bg-white dark:border-white/10 dark:bg-gray-950">
                    @foreach ($this->getStatusOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </section>

        @php($rows = $this->getAssignmentRows())
        @can('penilaian.verify')
            <section class="assessment-status-bulk-card">
                <div>
                    <h2>Aksi Massal</h2>
                    <p>{{ count($selectedAssignmentIds) }} dari {{ count($rows) }} penugasan dipilih.</p>
                </div>
                <div class="assessment-status-bulk-actions">
                    <x-filament::button size="sm" color="gray" wire:click="selectAllFilteredAssignments">Pilih Semua Terfilter</x-filament::button>
                    <x-filament::button size="sm" color="gray" wire:click="clearAssignmentSelection">Kosongkan Pilihan</x-filament::button>
                    <x-filament::button
                        size="sm"
                        color="success"
                        wire:click="verifySelectedAssignments"
                        wire:confirm="Verifikasi seluruh penugasan terpilih? Semua pilihan harus berstatus Dikirim."
                        :disabled="count($selectedAssignmentIds) === 0"
                    >Verifikasi Terpilih</x-filament::button>
                    <x-filament::button
                        size="sm"
                        color="warning"
                        wire:click="prepareReturn"
                        :disabled="count($selectedAssignmentIds) === 0"
                    >Kembalikan untuk Revisi</x-filament::button>
                </div>
            </section>
        @endcan

        <section class="assessment-status-data-card">
            <div class="assessment-status-desktop">
                <table class="assessment-status-table">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5">
                        <tr>
                            @can('penilaian.verify')<th class="p-3"><span class="sr-only">Pilih</span></th>@endcan
                            <th class="p-3">Kelas / Mapel</th>
                            <th class="p-3">Guru</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Kelengkapan</th>
                            <th class="p-3">Dikirim</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($rows as $row)
                            <tr>
                                @can('penilaian.verify')
                                    <td class="p-3">
                                        <input type="checkbox" value="{{ $row['id'] }}" wire:model.live="selectedAssignmentIds" aria-label="Pilih {{ $row['rombel'] }} {{ $row['subject'] }}">
                                    </td>
                                @endcan
                                <td class="p-3">
                                    <div class="font-semibold text-gray-950 dark:text-white">{{ $row['rombel'] }}</div>
                                    <div class="text-sm text-gray-500">{{ $row['subject'] }}</div>
                                </td>
                                <td class="p-3 text-sm">{{ $row['teacher'] }}</td>
                                <td class="p-3"><span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold dark:bg-white/10">{{ $row['status_label'] }}</span></td>
                                <td class="p-3 text-sm">{{ $row['completed_count'] }}/{{ $row['student_count'] }} · {{ $row['completion_percent'] }}%</td>
                                <td class="p-3 text-sm">{{ $row['submitted_at'] ?: '-' }}</td>
                                <td class="p-3">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <x-filament::button
                                            tag="a"
                                            href="{{ $row['review_url'] }}"
                                            size="sm"
                                            color="gray"
                                            icon="heroicon-o-eye"
                                        >Tinjau Nilai</x-filament::button>
                                        @can('penilaian.verify')
                                            @if ($row['status'] === 'submitted')
                                                <x-filament::button size="sm" color="success" wire:click="verifyAssignment({{ $row['id'] }})" wire:confirm="Verifikasi penugasan ini?">Verifikasi</x-filament::button>
                                            @endif
                                            @if (in_array($row['status'], ['submitted', 'verified'], true))
                                                <x-filament::button
                                                    size="sm"
                                                    color="warning"
                                                    wire:click="prepareReturn({{ $row['id'] }})"
                                                >Kembalikan</x-filament::button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="assessment-status-mobile">
                @forelse ($rows as $row)
                    <article class="assessment-status-mobile-card">
                        <div class="flex items-start justify-between gap-3">
                            @can('penilaian.verify')
                                <input type="checkbox" value="{{ $row['id'] }}" wire:model.live="selectedAssignmentIds" aria-label="Pilih {{ $row['rombel'] }} {{ $row['subject'] }}">
                            @endcan
                            <div class="min-w-0">
                                <h2 class="break-words font-bold text-gray-950 dark:text-white">{{ $row['rombel'] }} · {{ $row['subject'] }}</h2>
                                <p class="mt-1 break-words text-sm text-gray-500">{{ $row['teacher'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold dark:bg-white/10">{{ $row['status_label'] }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5"><strong>{{ $row['completion_percent'] }}%</strong><br><span class="text-xs text-gray-500">{{ $row['completed_count'] }}/{{ $row['student_count'] }} lengkap</span></div>
                            <div class="rounded-lg bg-gray-50 p-2 dark:bg-white/5"><strong>Dikirim</strong><br><span class="text-xs text-gray-500">{{ $row['submitted_at'] ?: '-' }}</span></div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <x-filament::button
                                tag="a"
                                href="{{ $row['review_url'] }}"
                                size="sm"
                                color="gray"
                                icon="heroicon-o-eye"
                            >Tinjau Nilai</x-filament::button>
                            @can('penilaian.verify')
                                @if ($row['status'] === 'submitted')
                                    <x-filament::button size="sm" color="success" wire:click="verifyAssignment({{ $row['id'] }})" wire:confirm="Verifikasi penugasan ini?">Verifikasi</x-filament::button>
                                @endif
                                @if (in_array($row['status'], ['submitted', 'verified'], true))
                                    <x-filament::button size="sm" color="warning" wire:click="prepareReturn({{ $row['id'] }})">Kembalikan</x-filament::button>
                                @endif
                            @endcan
                        </div>
                    </article>
                @empty
                    <div class="assessment-status-empty">Belum ada penugasan pada filter ini.</div>
                @endforelse
            </div>
        </section>

        <x-filament::modal id="assessment-return-modal" width="lg">
            <x-slot name="heading">Kembalikan untuk Revisi</x-slot>
            <x-slot name="description">{{ count($returnTargetIds) }} penugasan akan dikembalikan secara atomik.</x-slot>

            <label class="assessment-status-return-field">
                <span>Alasan revisi</span>
                <textarea wire:model="returnReason" rows="4" maxlength="1000" placeholder="Jelaskan data yang perlu diperbaiki, minimal 10 karakter."></textarea>
                @error('returnReason')<small>{{ $message }}</small>@enderror
            </label>

            <x-slot name="footerActions">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'assessment-return-modal' })">Batal</x-filament::button>
                <x-filament::button color="warning" wire:click="confirmReturnAssignments" wire:loading.attr="disabled">Kembalikan Penugasan</x-filament::button>
            </x-slot>
        </x-filament::modal>
    </div>
</x-filament-panels::page>
