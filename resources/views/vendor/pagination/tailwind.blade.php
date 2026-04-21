@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="mt-6">
        <div class="flex items-center justify-between gap-2 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary cursor-not-allowed opacity-50">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a class="btn btn-secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="btn btn-secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="btn btn-secondary cursor-not-allowed opacity-50">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        <div class="hidden items-center justify-between sm:flex">
            <p class="text-xs text-slate-500">
                {!! __('Showing') !!}
                @if ($paginator->firstItem())
                    <span class="font-semibold text-slate-700">{{ $paginator->firstItem() }}</span>
                    {!! __('to') !!}
                    <span class="font-semibold text-slate-700">{{ $paginator->lastItem() }}</span>
                @else
                    {{ $paginator->count() }}
                @endif
                {!! __('of') !!}
                <span class="font-semibold text-slate-700">{{ $paginator->total() }}</span>
                {!! __('results') !!}
            </p>

            <div class="inline-flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-300">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </span>
                @else
                    <a class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-disabled="true">
                            <span class="inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-full text-sm text-slate-400">
                                {{ $element }}
                            </span>
                        </span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page">
                                    <span class="inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-full bg-slate-900 text-sm font-semibold text-white">
                                        {{ $page }}
                                    </span>
                                </span>
                            @else
                                <a class="inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-full border border-slate-200 bg-white text-sm font-medium text-slate-600 transition hover:bg-slate-50" href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-300">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
