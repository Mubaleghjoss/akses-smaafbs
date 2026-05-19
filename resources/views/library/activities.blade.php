@extends('layouts.app')

@section('content')
    @include('library._nav')

    <div class="card p-6 reveal">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-2xl">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Perpustakaan</div>
                <h1 class="mt-2 text-2xl font-semibold">Aktivitas literasi</h1>
                <p class="mt-1 text-sm text-slate-500">Rekap aktivitas membaca dan penggunaan buku perpustakaan.</p>
            </div>
            <div class="grid w-full gap-2 sm:w-auto sm:grid-cols-2">
                <a class="btn btn-primary w-full" href="{{ route('library.activities.create') }}">Form Aktivitas</a>
                <a class="btn btn-secondary w-full" href="{{ route('library.activities.result') }}">Input Hasil</a>
            </div>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total</div>
                <div class="mt-2 text-2xl font-semibold">{{ number_format($stats['total'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Literasi</div>
                <div class="mt-2 text-2xl font-semibold">{{ number_format($stats['literasi'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tugas</div>
                <div class="mt-2 text-2xl font-semibold">{{ number_format($stats['tugas'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pengguna</div>
                <div class="mt-2 text-2xl font-semibold">{{ number_format($stats['active_users'] ?? 0, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <form method="get" class="card mt-6 grid gap-3 p-4 md:grid-cols-5">
        <input class="input md:col-span-2" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama atau buku">
        <select class="input" name="purpose">
            <option value="">Semua tujuan</option>
            @foreach($purposeOptions as $value => $label)
                <option value="{{ $value }}" @selected(($filters['purpose'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select class="input" name="result_status">
            <option value="">Semua status</option>
            @foreach($resultStatusOptions as $value => $label)
                <option value="{{ $value }}" @selected(($filters['result_status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <input class="input" type="date" name="date" value="{{ $filters['date'] ?? '' }}">
        <select class="input md:col-span-2" name="class">
            <option value="">Semua kelas</option>
            @foreach($classOptions as $class)
                <option value="{{ $class }}" @selected(($filters['class'] ?? '') === $class)>{{ $class }}</option>
            @endforeach
        </select>
        <div class="grid gap-2 sm:grid-cols-3 md:col-span-3">
            <button class="btn btn-primary w-full" type="submit">Terapkan</button>
            <a class="btn btn-secondary w-full" href="{{ route('library.activities') }}">Reset</a>
            <a class="btn btn-secondary w-full" href="{{ route('library.activities.export', $filters) }}">Export CSV</a>
        </div>
    </form>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-3">
            @forelse($activities as $activity)
                @php
                    $statusLabel = \App\Models\PerpustakaanLiterasiActivity::resultStatusLabel($activity->result_status);
                    $purposeLabel = \App\Models\PerpustakaanLiterasiActivity::purposeLabel($activity->purpose);
                @endphp
                <article class="card p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap gap-2">
                                <span class="chip">{{ $purposeLabel }}</span>
                                <span class="chip">{{ $statusLabel }}</span>
                            </div>
                            <h2 class="mt-3 break-words text-lg font-semibold">{{ $activity->book_title_snapshot }}</h2>
                            <div class="mt-1 text-sm text-slate-500">
                                {{ $activity->participant_name }}
                                @if($activity->participant_class)
                                    - {{ $activity->participant_class }}
                                @endif
                            </div>
                        </div>
                        <div class="text-left text-xs text-slate-500 sm:text-right">
                            {{ $activity->activity_at?->format('d M Y H:i') ?? '-' }}
                            @if($activity->subject_name)
                                <div class="mt-1 font-semibold text-slate-700">{{ $activity->subject_name }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                        <span>{{ $activity->book_author_snapshot ?: 'Penulis belum diisi' }}</span>
                    </div>
                </article>
            @empty
                <div class="card p-6 text-sm text-slate-500">Belum ada aktivitas literasi untuk filter ini.</div>
            @endforelse

            <div>{{ $activities->links() }}</div>
        </div>

        <aside class="card h-fit p-5">
            <h2 class="text-lg font-semibold">Buku sering dipakai</h2>
            <div class="mt-4 space-y-3">
                @forelse($popularBooks as $book)
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <div class="break-words text-sm font-semibold">{{ $book->book_title_snapshot }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ number_format($book->total, 0, ',', '.') }} aktivitas</div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">Belum ada data buku.</div>
                @endforelse
            </div>
        </aside>
    </div>
@endsection
