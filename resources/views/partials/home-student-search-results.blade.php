@if($q === '' || mb_strlen($q) < 2)
    <div class="card p-5 text-sm text-slate-500 md:col-span-2 xl:col-span-3">Silakan ketik minimal 2 huruf atau angka untuk mencari nama atau NISN siswa.</div>
@elseif($studentResults->isEmpty())
    <div class="card p-5 text-sm text-slate-500 md:col-span-2 xl:col-span-3">Data siswa yang Anda cari belum ditemukan. Silakan periksa kembali nama atau NISN yang dimasukkan.</div>
@else
    @foreach($studentResults as $student)
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
            'Kepribadian' => 'border-sky-200 bg-sky-50 text-sky-900',
            'Gaya Belajar' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
            'Profiling' => 'border-amber-200 bg-amber-50 text-amber-900',
            'MBTI' => 'border-violet-200 bg-violet-50 text-violet-900',
        ];

        $studentTestData = collect($studentTestData)
            ->map(fn ($value) => filled($value) ? \Illuminate\Support\Str::upper((string) $value) : '')
            ->all();
    @endphp
    <a class="rounded-2xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-lg" href="{{ route('students.show', $student) }}">
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="text-lg font-semibold text-slate-900">{{ $student->nama }}</div>
                <div class="mt-1 text-xs uppercase tracking-[0.2em] text-slate-400">{{ $student->status === 'alumni' ? 'Alumni' : 'Aktif' }}</div>
            </div>
            <span class="chip">{{ $studentStatusLabel }}</span>
        </div>
        <dl class="mt-4 space-y-2 text-sm text-slate-600">
            <div class="flex items-center justify-between gap-3">
                <dt>NISN</dt>
                <dd class="font-medium text-slate-900">{{ $student->nisn ?: '-' }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt>Tanggal Lahir</dt>
                <dd class="font-medium text-slate-900">{{ $student->tanggal_lahir?->format('d/m/Y') ?: '-' }}</dd>
            </div>
        </dl>
        <div class="mt-4 border-t border-slate-100 pt-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Data Tes Siswa</div>
            @if(collect($studentTestData)->filter()->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($studentTestData as $label => $value)
                        @continue(blank($value))
                        <span class="inline-flex max-w-full items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-medium leading-tight {{ $studentTestDataStyles[$label] ?? 'border-slate-200 bg-slate-50 text-slate-900' }}">
                            <span class="uppercase tracking-[0.14em] text-slate-500">{{ $label }}</span>
                            <span class="truncate font-semibold">{{ $value }}</span>
                        </span>
                    @endforeach
                </div>
            @else
                <div class="mt-3 rounded-2xl bg-slate-50 px-3 py-2 text-sm text-slate-500">Data tes siswa belum tersedia.</div>
            @endif
        </div>
    </a>
    @endforeach
@endif
