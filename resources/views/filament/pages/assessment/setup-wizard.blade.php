<x-filament-panels::page>
    @php
        $status = $this->getSetupStatus();
        $links = $this->getStepLinks();
        $persen = $status['total_langkah'] > 0
            ? (int) round($status['langkah_selesai'] / $status['total_langkah'] * 100)
            : 0;
    @endphp

    <div class="asmt-setup">
        {{-- Ringkasan: satu baris yang menjawab "sudah siap menilai atau belum" --}}
        <section class="asmt-setup__hero {{ $status['siap'] ? 'is-ready' : '' }}">
            <div class="asmt-setup__hero-main">
                <span class="asmt-setup__eyebrow">Setelan Awal</span>
                <h2>
                    @if ($status['siap'])
                        Semua langkah selesai — penilaian siap dijalankan
                    @else
                        {{ $status['langkah_selesai'] }} dari {{ $status['total_langkah'] }} langkah selesai
                    @endif
                </h2>
                <p>
                    Kerjakan dari atas ke bawah. Setiap langkah menyebut apa yang masih kurang,
                    dan tombolnya menuju tempat pengaturannya.
                </p>

                <div class="asmt-setup__bar" role="progressbar" aria-valuenow="{{ $persen }}" aria-valuemin="0" aria-valuemax="100">
                    <span style="width: {{ $persen }}%"></span>
                </div>
            </div>

            <div class="asmt-setup__hero-side">
                <label for="asmt-semester" class="asmt-setup__label">Semester yang disiapkan</label>
                <select id="asmt-semester" wire:model.live="semesterId" class="asmt-setup__select">
                    <option value="">— pilih semester —</option>
                    @foreach ($this->getSemesterOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>

                <div class="asmt-setup__extra">
                    @foreach ($this->getExtraLinks() as $extra)
                        @if ($extra['url'])
                            <a href="{{ $extra['url'] }}" wire:navigate>{{ $extra['label'] }}</a>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Enam langkah. Langkah terkunci tetap TERLIHAT (bukan disembunyikan)
             supaya admin tahu apa yang menunggu di depan. --}}
        <ol class="asmt-steps">
            @foreach ($status['langkah'] as $langkah)
                @php
                    $tautan = $links[$langkah['nomor']] ?? null;
                    $kelas = match ($langkah['status']) {
                        'selesai' => 'is-done',
                        'kurang' => 'is-partial',
                        default => 'is-todo',
                    };
                @endphp

                <li class="asmt-step {{ $kelas }} {{ $langkah['terkunci'] ? 'is-locked' : '' }}">
                    <span class="asmt-step__num">
                        @if ($langkah['status'] === 'selesai')
                            <x-filament::icon icon="heroicon-m-check" />
                        @else
                            {{ $langkah['nomor'] }}
                        @endif
                    </span>

                    <div class="asmt-step__body">
                        <div class="asmt-step__head">
                            <h3>{{ $langkah['judul'] }}</h3>
                            <span class="asmt-step__state">
                                @switch($langkah['status'])
                                    @case('selesai') Selesai @break
                                    @case('kurang') Belum lengkap @break
                                    @default Belum diatur
                                @endswitch
                            </span>
                        </div>

                        <p class="asmt-step__summary">{{ $langkah['ringkasan'] }}</p>

                        @if ($langkah['catatan'])
                            <p class="asmt-step__note">{{ $langkah['catatan'] }}</p>
                        @endif

                        @if ($langkah['terkunci'])
                            <p class="asmt-step__locked">
                                Selesaikan langkah sebelumnya lebih dulu agar setelan ini tidak salah menempel.
                            </p>
                        @elseif ($tautan && $tautan['url'])
                            <a href="{{ $tautan['url'] }}" wire:navigate class="asmt-step__cta">
                                {{ $tautan['label'] }}
                                <x-filament::icon icon="heroicon-m-arrow-right" />
                            </a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</x-filament-panels::page>
