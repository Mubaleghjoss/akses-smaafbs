@php
    $items = is_array($items ?? null) ? $items : [];
    $nameLabel = $field === 'achievement_items' ? 'Jenis Prestasi' : 'Nama Ekstrakurikuler';
@endphp

<div class="assessment-homeroom-items" wire:key="{{ $surface }}-items-{{ $studentId }}-{{ $field }}">
    @forelse ($items as $index => $item)
        @php
            $nameErrorKey = "rows.{$studentId}.{$field}.{$index}.name";
            $descriptionErrorKey = "rows.{$studentId}.{$field}.{$index}.description";
        @endphp
        <article class="assessment-homeroom-item" wire:key="{{ $surface }}-item-{{ $studentId }}-{{ $field }}-{{ $index }}">
            <label>
                <span>{{ $nameLabel }} <b aria-hidden="true">*</b></span>
                <input
                    type="text"
                    maxlength="255"
                    wire:model.blur="reportRows.{{ $studentId }}.{{ $field }}.{{ $index }}.name"
                    @disabled(! $editable)
                    @error($nameErrorKey) aria-invalid="true" @enderror
                >
                @error($nameErrorKey)<small class="assessment-homeroom-field-error" role="alert">{{ $message }}</small>@enderror
            </label>
            <label>
                <span>Keterangan</span>
                <textarea
                    rows="2"
                    maxlength="2000"
                    wire:model.blur="reportRows.{{ $studentId }}.{{ $field }}.{{ $index }}.description"
                    @disabled(! $editable)
                    @error($descriptionErrorKey) aria-invalid="true" @enderror
                ></textarea>
                @error($descriptionErrorKey)<small class="assessment-homeroom-field-error" role="alert">{{ $message }}</small>@enderror
            </label>
            @if ($editable)
                <button
                    type="button"
                    class="assessment-homeroom-item__remove"
                    wire:click="removeStructuredItem({{ $studentId }}, '{{ $field }}', {{ $index }})"
                    aria-label="Hapus {{ $nameLabel }} ke-{{ $index + 1 }} untuk {{ $studentName }}"
                >
                    <x-filament::icon icon="heroicon-o-trash" />
                    <span>Hapus poin</span>
                </button>
            @endif
        </article>
    @empty
        <p class="assessment-homeroom-items__empty">Belum ada poin.</p>
    @endforelse

    @if ($editable)
        <button
            type="button"
            class="assessment-homeroom-item__add"
            wire:click="addStructuredItem({{ $studentId }}, '{{ $field }}')"
        >
            <x-filament::icon icon="heroicon-o-plus" />
            Tambah {{ $field === 'achievement_items' ? 'prestasi' : 'ekstrakurikuler' }}
        </button>
    @endif
</div>
