@extends('layouts.app')

@section('content')
    <a class="chip" href="{{ route('students.index') }}"><- Kembali ke data siswa</a>

    @php
        $studentStatusLabel = match (strtolower((string) $student->status)) {
            'aktif' => 'Aktif',
            'alumni' => 'Alumni',
            'pindah' => 'Pindah / Mutasi',
            'keluar' => 'Keluar',
            default => $student->status ?: '-',
        };

        $studentTestData = [
            'Kepribadian' => $student->kepribadian,
            'Gaya Belajar' => $student->gaya_belajar,
            'Profiling' => $student->profiling,
            'MBTI' => $student->mbti,
        ];

        $studentTestDataStyles = [
            'Kepribadian' => 'border-sky-200/80 bg-sky-50/80',
            'Gaya Belajar' => 'border-emerald-200/80 bg-emerald-50/80',
            'Profiling' => 'border-amber-200/80 bg-amber-50/80',
            'MBTI' => 'border-violet-200/80 bg-violet-50/80',
        ];

        $hasStudentTestData = collect($studentTestData)->filter(fn ($value) => filled($value))->isNotEmpty();
    @endphp

    <div class="mt-5 grid gap-6 lg:grid-cols-3">
        <div class="card p-6 lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Profil siswa</div>
                    <h1 class="mt-3 text-2xl font-semibold">{{ $student->nama }}</h1>
                </div>
                <span class="chip">{{ $studentStatusLabel }}</span>
            </div>
            <div class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                <div><span class="text-slate-500">Rombel:</span> {{ $student->rombel_saat_ini ?? '-' }}</div>
                <div><span class="text-slate-500">JK:</span> {{ $student->jk }}</div>
                <div><span class="text-slate-500">NIPD:</span> {{ $student->nipd ?? '-' }}</div>
                <div><span class="text-slate-500">NISN:</span> {{ $student->nisn ?? '-' }}</div>
                <div><span class="text-slate-500">Tgl Lahir:</span> {{ $student->tanggal_lahir?->format('d/m/Y') ?? '-' }}</div>
            </div>
        </div>
        <div class="card p-6">
            <h2 class="text-lg font-semibold">Data Tes Siswa</h2>
            <p class="mt-1 text-sm text-slate-500">Ringkasan hasil tes siswa yang sudah tersimpan pada sistem sekolah.</p>

            @if($hasStudentTestData)
                <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    @foreach ($studentTestData as $label => $value)
                        <div class="rounded-2xl border px-3 py-3 {{ $studentTestDataStyles[$label] ?? 'border-slate-200/80 bg-white/80' }}">
                            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">{{ $label }}</div>
                            <div class="mt-1 text-sm font-semibold leading-snug text-slate-900">
                                {{ filled($value) ? \Illuminate\Support\Str::upper($value) : '-' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white/70 px-4 py-5 text-sm text-slate-500">
                    Data tes siswa belum tersedia.
                </div>
            @endif
        </div>
    </div>
@endsection
