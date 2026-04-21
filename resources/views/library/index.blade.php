@extends('layouts.app')

@section('content')
    <div class="card p-6 reveal">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Layanan perpustakaan</h1>
                <p class="mt-1 text-sm text-slate-500">Temukan buku fisik dan e-book untuk mendukung kegiatan belajar siswa.</p>
            </div>
            <form method="get" class="grid w-full max-w-md gap-2 sm:flex sm:items-center">
                <input name="q" value="{{ $q }}" class="input min-w-0" placeholder="Cari judul buku atau nama penulis..." />
                <button class="btn btn-primary w-full sm:w-auto" type="submit">Cari</button>
            </form>
        </div>
    </div>

    <div class="card mt-6 overflow-hidden">
        <div class="w-full overflow-x-auto">
            <table class="min-w-[38rem] w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Judul</th>
                        <th class="px-4 py-3">Penulis</th>
                        <th class="px-4 py-3">Tipe</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($items as $b)
                        @php
                            $bookTypeLabel = strtolower((string) ($b->file_type ?? 'physical')) === 'ebook' ? 'E-Book' : 'Buku Fisik';
                            $bookStatusLabel = match (strtolower((string) $b->status)) {
                                'available', 'tersedia' => 'Tersedia',
                                'borrowed', 'dipinjam' => 'Dipinjam',
                                'inactive', 'nonaktif' => 'Tidak Aktif',
                                default => $b->status ?: '-',
                            };
                        @endphp
                        <tr class="transition hover:bg-slate-50/80">
                            <td class="px-4 py-3 font-semibold">
                                <a class="hover:underline" href="{{ route('library.show', $b) }}">{{ $b->judul_buku }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $b->penulis ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $bookTypeLabel }}</td>
                            <td class="px-4 py-3">
                                <span class="chip">{{ $bookStatusLabel }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-6 text-slate-500" colspan="4">Data buku belum tersedia untuk pencarian ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $items->withQueryString()->links() }}</div>
@endsection
