<x-filament-panels::page>
    <div class="space-y-5">
        <section class="grid gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900 md:grid-cols-2">
            <label class="min-w-0 text-sm font-semibold">Periode
                <select wire:model.live="periodId" class="mt-2 w-full min-w-0 rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950">
                    @foreach ($this->getPeriodOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                </select>
            </label>
            <label class="min-w-0 text-sm font-semibold">Kelas
                <select wire:model.live="homeroomId" class="mt-2 w-full min-w-0 rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950">
                    @foreach ($this->getHomeroomOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                </select>
            </label>
        </section>

        @if ($homeroomMeta)
            <div class="flex flex-wrap gap-2">
                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold dark:bg-white/10">{{ $homeroomMeta['rombel'] }}</span>
                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold dark:bg-white/10">{{ $homeroomMeta['teacher'] }}</span>
                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold dark:bg-white/10">{{ $homeroomMeta['status_label'] }}</span>
            </div>

            <section class="hidden max-w-full overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900 md:block">
                <div class="max-w-full overflow-x-auto">
                    <table class="w-full min-w-[1180px] border-collapse">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/5">
                            <tr>
                                <th class="sticky left-0 z-10 min-w-[210px] bg-gray-50 p-3 dark:bg-gray-800">Siswa</th>
                                <th class="p-3">Sakit</th><th class="p-3">Izin</th><th class="p-3">Alpa</th>
                                <th class="min-w-[230px] p-3">Ekstrakurikuler</th>
                                <th class="min-w-[230px] p-3">Prestasi</th>
                                <th class="min-w-[260px] p-3">Catatan Wali</th>
                                @if ($homeroomMeta['collect_promotion_status'])
                                    <th class="min-w-[160px] p-3">Status Semester</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($reportRows as $studentId => $row)
                                <tr>
                                    <td class="sticky left-0 z-[1] bg-white p-3 dark:bg-gray-900">
                                        <strong class="block">{{ $row['student_name'] }}</strong><small class="text-gray-500">{{ $row['nis'] }}</small>
                                    </td>
                                    @foreach (['sick_days', 'permission_days', 'absent_days'] as $field)
                                        <td class="p-2"><input type="number" min="0" max="366" class="w-20 rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950" wire:model.blur="reportRows.{{ $studentId }}.{{ $field }}" @disabled(! $homeroomMeta['editable'])></td>
                                    @endforeach
                                    <td class="p-2"><textarea rows="3" class="w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950" wire:model.blur="reportRows.{{ $studentId }}.extracurricular" @disabled(! $homeroomMeta['editable'])></textarea></td>
                                    <td class="p-2"><textarea rows="3" class="w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950" wire:model.blur="reportRows.{{ $studentId }}.achievement" @disabled(! $homeroomMeta['editable'])></textarea></td>
                                    <td class="p-2"><textarea rows="3" class="w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950" wire:model.blur="reportRows.{{ $studentId }}.homeroom_note" @disabled(! $homeroomMeta['editable'])></textarea></td>
                                    @if ($homeroomMeta['collect_promotion_status'])
                                        <td class="p-2"><input type="text" maxlength="50" class="w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950" wire:model.blur="reportRows.{{ $studentId }}.promotion_status" @disabled(! $homeroomMeta['editable'])></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="space-y-3 md:hidden">
                @foreach ($reportRows as $studentId => $row)
                    <article class="min-w-0 rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                        <h2 class="break-words font-bold">{{ $row['student_name'] }}</h2>
                        <p class="text-xs text-gray-500">{{ $row['nis'] }}</p>
                        <div class="mt-4 grid grid-cols-3 gap-2">
                            @foreach (['sick_days' => 'Sakit', 'permission_days' => 'Izin', 'absent_days' => 'Alpa'] as $field => $label)
                                <label class="text-xs font-semibold">{{ $label }}<input type="number" min="0" max="366" class="mt-1 w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950" wire:model.blur="reportRows.{{ $studentId }}.{{ $field }}" @disabled(! $homeroomMeta['editable'])></label>
                            @endforeach
                        </div>
                        <div class="mt-3 grid gap-3">
                            <label class="text-xs font-semibold">Ekstrakurikuler (satu per baris)<textarea rows="2" class="mt-1 w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950" wire:model.blur="reportRows.{{ $studentId }}.extracurricular" @disabled(! $homeroomMeta['editable'])></textarea></label>
                            <label class="text-xs font-semibold">Prestasi (satu per baris)<textarea rows="2" class="mt-1 w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950" wire:model.blur="reportRows.{{ $studentId }}.achievement" @disabled(! $homeroomMeta['editable'])></textarea></label>
                            <label class="text-xs font-semibold">Catatan Wali Kelas<textarea rows="3" class="mt-1 w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950" wire:model.blur="reportRows.{{ $studentId }}.homeroom_note" @disabled(! $homeroomMeta['editable'])></textarea></label>
                            @if ($homeroomMeta['collect_promotion_status'])
                                <label class="text-xs font-semibold">Status Semester<input type="text" maxlength="50" class="mt-1 w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950" wire:model.blur="reportRows.{{ $studentId }}.promotion_status" @disabled(! $homeroomMeta['editable'])></label>
                            @endif
                        </div>
                    </article>
                @endforeach
            </section>

            @if ($homeroomMeta['editable'])
                <div class="flex justify-end">
                    <x-filament::button wire:click="saveReports" wire:loading.attr="disabled" icon="heroicon-o-cloud-arrow-up">Simpan Rekap Wali Kelas</x-filament::button>
                </div>
            @endif
        @else
            <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/15">Belum ada penugasan wali kelas untuk periode ini.</div>
        @endif
    </div>
</x-filament-panels::page>
