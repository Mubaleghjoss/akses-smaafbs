@extends('layouts.app')

@section('content')
    <section class="card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="chip">Agenda sekolah</div>
                <h1 class="mt-3 text-2xl font-semibold">Kalender agenda kegiatan</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Seluruh agenda kegiatan publik ditampilkan dalam format kalender agar mudah dipantau oleh wali santri dan masyarakat.
                </p>
            </div>
            <a class="btn btn-secondary w-full sm:w-auto" href="{{ route('home') }}">Kembali ke beranda</a>
        </div>

        <div class="mt-6">
            @include('partials.public-agenda-calendar', [
                'calendarId' => 'agenda-calendar',
                'modalId' => 'agenda-modal',
                'titleId' => 'agenda-modal-title',
                'dateId' => 'agenda-modal-date',
                'descId' => 'agenda-modal-desc',
                'closeId' => 'agenda-modal-close',
            ])
        </div>
    </section>
@endsection
