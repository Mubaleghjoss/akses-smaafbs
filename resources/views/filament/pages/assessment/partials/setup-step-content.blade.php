<span class="boarding-material-card__number">
    {{ $step['number'] }}
</span>

<span class="boarding-material-card__body">
    <span class="boarding-material-card__title">
        {{ $step['title'] }}
    </span>
    <span class="boarding-material-card__subtitle">
        {{ $step['subtitle'] }}
    </span>
    <span class="mt-1 break-words text-[0.72rem] font-medium leading-5 text-gray-600 dark:text-gray-300">
        {{ $step['detail'] }}
    </span>
    <span @class([
        'mt-1 inline-flex w-fit max-w-full items-center gap-1 rounded-full px-2 py-1 text-[0.66rem] font-extrabold',
        'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-300' => $step['ready'],
        'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-300' => ! $step['ready'],
    ])>
        <x-filament::icon :icon="$step['ready'] ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle'" class="h-3.5 w-3.5 shrink-0" />
        <span class="break-words">{{ $step['ready'] ? 'Data tersedia' : 'Perlu dilengkapi' }}</span>
    </span>
    @if ($step['url'])
        <span class="mt-1 break-words text-[0.7rem] font-bold text-primary-700 dark:text-primary-300">
            {{ $step['action'] }}
        </span>
    @endif
</span>

@if ($step['url'])
    <span class="boarding-material-card__arrow" aria-hidden="true">&rsaquo;</span>
@endif
