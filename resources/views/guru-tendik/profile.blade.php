@extends('layouts.app')

@section('content')
    @php
        $photoPath = $guruTendik->foto_profil ?: $strukturNode->foto;
        $photoUrl = filled($photoPath) ? asset('storage/'.$photoPath) : null;
        $activeDuties = $guruTendik->tugasTambahanAktif;
    @endphp

    <a class="chip" href="{{ route('home') }}#content">&larr; Kembali ke beranda</a>

    <div class="mt-5 grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
        <div class="card p-6">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-slate-100">
                @if($photoUrl)
                    <img class="aspect-[4/5] w-full object-cover" src="{{ $photoUrl }}" alt="Foto {{ $guruTendik->nama }}">
                @else
                    <div class="flex aspect-[4/5] items-center justify-center px-6 text-center text-sm text-slate-500">
                        Foto profil belum tersedia.
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-6">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Profil guru / tendik</div>
                <h1 class="mt-3 text-3xl font-semibold text-balance">{{ $guruTendik->nama }}</h1>
                <div class="mt-3 inline-flex rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700">
                    {{ $strukturNode->jabatan }}
                </div>

                <div class="mt-6 rounded-3xl border border-slate-200 bg-white/80 p-5">
                    <h2 class="text-lg font-semibold">Biografi singkat</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600">
                        {{ $guruTendik->bio_singkat ?: 'Biografi singkat belum tersedia untuk profil ini.' }}
                    </p>
                </div>
            </div>

            <div class="card p-6">
                <h2 class="text-lg font-semibold">Tugas tambahan aktif</h2>
                <p class="mt-1 text-sm text-slate-500">Daftar penugasan tambahan yang masih aktif berdasarkan data administrasi guru/tendik.</p>

                <div class="mt-4 space-y-3">
                    @forelse($activeDuties as $duty)
                        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm">
                            <div class="font-semibold text-slate-900">{{ $duty->tugas_tambahan }}</div>
                            <div class="mt-1 text-slate-500">No. SK: {{ $duty->no_sk }}</div>
                            <div class="mt-1 text-slate-500">Periode: {{ $duty->tmt?->format('d/m/Y') ?? '-' }} - {{ $duty->tst?->format('d/m/Y') ?? 'Sekarang' }}</div>
                            @if(filled($duty->keterangan))
                                <p class="mt-2 leading-relaxed text-slate-600">{{ $duty->keterangan }}</p>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
                            Belum ada tugas tambahan aktif yang dipublikasikan untuk profil ini.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
