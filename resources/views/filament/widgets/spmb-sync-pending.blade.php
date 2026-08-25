{{--
    Kartu pemberitahuan siswa lulus SPMB yang siap disinkron.
    Hanya dirender bila SpmbSyncPendingWidget::canView() mengizinkan
    (ada perubahan DAN pengguna boleh menambah data siswa).
--}}
@php
    $r = $this->getRingkasan();
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-start;justify-content:space-between">
            <div style="flex:1 1 320px;min-width:0">
                <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem">
                    <x-filament::icon
                        icon="heroicon-o-academic-cap"
                        style="width:1.25rem;height:1.25rem;color:rgb(22 163 74)"
                    />
                    <span style="font-weight:600">
                        Ada {{ $r['total'] }} data siswa lulus SPMB menunggu
                    </span>
                </div>

                <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.6rem">
                    @if($r['baru'] > 0)
                        <x-filament::badge color="success">
                            {{ $r['baru'] }} siswa belum ada di app
                        </x-filament::badge>
                    @endif
                    @if($r['siswa_baru'] > 0)
                        <x-filament::badge color="success">
                            Siswa Baru: {{ $r['siswa_baru'] }}
                        </x-filament::badge>
                    @endif
                    @if($r['pindahan'] > 0)
                        <x-filament::badge color="info">
                            Pindahan: {{ $r['pindahan'] }}
                        </x-filament::badge>
                    @endif
                    @if($r['berubah'] > 0)
                        <x-filament::badge color="warning">
                            {{ $r['berubah'] }} data berubah di SPMB
                        </x-filament::badge>
                    @endif
                </div>

                @if(!empty($r['daftar']))
                    <div style="font-size:.8rem;line-height:1.5">
                        @foreach($r['daftar'] as $siswa)
                            <div style="display:flex;flex-wrap:wrap;gap:.35rem;align-items:center">
                                <span style="font-weight:500">{{ $siswa['nama'] }}</span>
                                <span style="opacity:.6">{{ $siswa['nomor_pendaftaran'] }}</span>
                                <span style="opacity:.75">· {{ $siswa['jalur'] }}</span>
                                @if(!empty($siswa['kelas_tujuan']))
                                    <span style="opacity:.75">· Kelas {{ $siswa['kelas_tujuan'] }}</span>
                                @endif
                                @if($siswa['status'] === 'berubah')
                                    <span style="opacity:.6">· data berubah</span>
                                @endif
                            </div>
                        @endforeach
                        @if($r['total'] > count($r['daftar']))
                            <div style="opacity:.6;margin-top:.25rem">
                                dan {{ $r['total'] - count($r['daftar']) }} lainnya…
                            </div>
                        @endif
                    </div>
                @endif

                @if(!empty($r['error']))
                    <div style="font-size:.78rem;color:rgb(180 83 9);margin-top:.5rem">
                        Gagal memeriksa SPMB: {{ $r['error'] }}
                    </div>
                @endif
            </div>

            <div style="display:flex;flex-direction:column;gap:.4rem">
                <x-filament::button
                    tag="a"
                    :href="$this->getSyncUrl()"
                    icon="heroicon-o-cloud-arrow-down"
                    color="success"
                >
                    Sinkron Sekarang
                </x-filament::button>
                <x-filament::button
                    wire:click="periksaUlang"
                    icon="heroicon-o-arrow-path"
                    color="gray"
                    outlined
                    size="sm"
                >
                    Periksa lagi
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
