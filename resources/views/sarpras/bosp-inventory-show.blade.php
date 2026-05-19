@extends('layouts.app')

@section('content')
    <section class="card p-6 md:p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <span class="chip">Data Sarpras BOSP</span>
                <h1 class="mt-4 text-3xl font-semibold text-slate-900 md:text-4xl">{{ $record->nama_barang }}</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
                    Halaman ini muncul dari QR code stiker sarpras untuk membantu verifikasi barang dan lokasi penempatannya.
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kode Barang</div>
                <div class="mt-1 font-semibold text-slate-900">{{ $record->kode_barang ?: 'ID-'.$record->getKey() }}</div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Jumlah</div>
                <div class="mt-2 text-xl font-semibold text-slate-900">{{ number_format((int) $record->quality) }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Lokasi Barang</div>
                <div class="mt-2 font-semibold text-slate-900">{{ $record->lokasi_barang ?: '-' }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tempat di Stiker</div>
                <div class="mt-2 font-semibold text-slate-900">{{ $record->tempat_stiker ?: ($record->lokasi_barang ?: '-') }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Periode Beli</div>
                <div class="mt-2 font-semibold text-slate-900">
                    {{ collect([$bulanOptions[$record->bulan_beli] ?? null, $record->tahun_beli])->filter()->implode(' ') ?: '-' }}
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal Datang</div>
                <div class="mt-2 font-semibold text-slate-900">{{ $record->tanggal_datang?->format('d/m/Y') ?: '-' }}</div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-[1fr_0.7fr]">
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold text-slate-900">Catatan Peletakan Barang</h2>
                <div class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ filled($record->catatan) ? $record->catatan : 'Catatan peletakan barang belum diisi.' }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <h2 class="text-lg font-semibold text-slate-900">Rincian Administrasi</h2>
                <dl class="mt-3 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">No Urut</dt>
                        <dd class="font-medium text-slate-900">{{ $record->nomor_urut ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Total Harga</dt>
                        <dd class="font-medium text-slate-900">Rp {{ number_format((float) ($record->total_harga ?? 0), 0, ',', '.') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Terakhir Diupdate</dt>
                        <dd class="font-medium text-slate-900">{{ $record->updated_at?->format('d/m/Y H:i') ?: '-' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>
@endsection
