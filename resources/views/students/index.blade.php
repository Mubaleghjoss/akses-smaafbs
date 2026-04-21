@extends('layouts.app')

@section('content')
    <div class="card p-6 reveal">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Data siswa dan alumni</h1>
                <p class="mt-1 text-sm text-slate-500">Telusuri data siswa berdasarkan nama, NIPD, atau NISN untuk melihat informasi yang tersedia.</p>
            </div>
            <form method="get" class="grid w-full max-w-md gap-2 sm:flex sm:items-center">
                <input name="q" value="{{ $q }}" class="input min-w-0" placeholder="Masukkan nama, NIPD, atau NISN..." />
                <button class="btn btn-primary w-full sm:w-auto" type="submit">Cari</button>
            </form>
        </div>
    </div>

    <div class="card mt-6 overflow-hidden">
        <div class="w-full overflow-x-auto">
            <table class="min-w-[42rem] w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Rombel</th>
                        <th class="px-4 py-3">NIPD</th>
                        <th class="px-4 py-3">NISN</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($items as $s)
                        @php
                            $studentStatusLabel = match (strtolower((string) $s->status)) {
                                'aktif' => 'Aktif',
                                'alumni' => 'Alumni',
                                'pindah' => 'Pindah / Mutasi',
                                'keluar' => 'Keluar',
                                default => $s->status ?: '-',
                            };

                            $studentTestData = [
                                'KEPRIBADIAN' => $s->kepribadian,
                                'GAYA BELAJAR' => $s->gaya_belajar,
                                'PROFILING' => $s->profiling,
                                'MBTI' => $s->mbti,
                            ];

                            $studentTestDataStyles = [
                                'KEPRIBADIAN' => 'border-sky-200 bg-sky-50 text-sky-900',
                                'GAYA BELAJAR' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                                'PROFILING' => 'border-amber-200 bg-amber-50 text-amber-900',
                                'MBTI' => 'border-violet-200 bg-violet-50 text-violet-900',
                            ];

                            $studentTestData = collect($studentTestData)
                                ->map(fn ($value) => filled($value) ? \Illuminate\Support\Str::upper((string) $value) : '')
                                ->all();
                        @endphp
                        <tr class="transition hover:bg-slate-50/80">
                            <td class="px-4 py-3 align-top">
                                <a class="font-semibold hover:underline" href="{{ route('students.show', $s) }}">{{ $s->nama }}</a>
                                <div class="mt-3">
                                    <div class="text-[11px] uppercase tracking-[0.2em] text-slate-400">Data Tes Siswa</div>
                                    @if(collect($studentTestData)->filter()->isNotEmpty())
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach($studentTestData as $label => $value)
                                                @continue(blank($value))
                                                <span class="inline-flex max-w-full items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-medium leading-tight {{ $studentTestDataStyles[$label] ?? 'border-slate-200 bg-slate-50 text-slate-900' }}">
                                                    <span class="uppercase tracking-[0.14em] text-slate-500">{{ $label }}</span>
                                                    <span class="truncate font-semibold">{{ $value }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="mt-2 text-xs text-slate-500">Belum ada data tes siswa.</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $s->rombel_saat_ini ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $s->nipd ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $s->nisn ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="chip">{{ $studentStatusLabel }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-6 text-slate-500" colspan="5">Data siswa yang dicari belum ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $items->withQueryString()->links() }}</div>
@endsection
