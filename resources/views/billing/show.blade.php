@extends('layouts.app')

@section('content')
    <a class="chip" href="{{ route('billing.index') }}"><- Kembali ke layanan tagihan</a>

    <div class="card mt-5 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Rincian tagihan siswa</div>
                <h1 class="mt-2 text-2xl font-semibold">{{ $student->nama }}</h1>
                <div class="mt-1 text-sm text-slate-500">{{ $student->rombel_saat_ini ?? '-' }}</div>
            </div>
            <div class="chip">Kode: {{ $student->billing_code ?? '-' }}</div>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl border border-white/70 bg-white/80">
            <div class="w-full overflow-x-auto">
                <table class="min-w-[56rem] w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Periode</th>
                            <th class="px-4 py-3">Jenis</th>
                            <th class="px-4 py-3">Tagihan</th>
                            <th class="px-4 py-3">Terbayar</th>
                            <th class="px-4 py-3">Sisa</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($bills as $b)
                            @php
                                $remaining = max(0, (int)$b->amount - (int)$b->paid_amount);
                                $attachments = $attachmentsByBill[$b->id] ?? collect();
                                $pending = $attachments->contains(fn ($a) => $a->status === 'pending');
                            @endphp
                            <tr class="align-top">
                                <td class="px-4 py-3">{{ sprintf('%02d/%d', (int)$b->period_month, (int)$b->period_year) }}</td>
                                <td class="px-4 py-3">{{ $b->feeType?->name ?? '-' }}</td>
                                <td class="px-4 py-3">Rp {{ number_format((int)$b->amount, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">Rp {{ number_format((int)$b->paid_amount, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 font-semibold">Rp {{ number_format((int)$remaining, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    @if($b->payment_status === 'paid')
                                        <span class="chip bg-emerald-50 text-emerald-700">Lunas</span>
                                    @elseif($pending)
                                        <span class="chip bg-amber-50 text-amber-700">Menunggu verifikasi</span>
                                    @else
                                        @php
                                            $paymentStatusLabel = match (strtolower((string) $b->payment_status)) {
                                                'unpaid' => 'Belum dibayar',
                                                'partial' => 'Pembayaran sebagian',
                                                default => $b->payment_status ?: '-',
                                            };
                                        @endphp
                                        <span class="chip">{{ $paymentStatusLabel }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($b->payment_status === 'paid')
                                        <span class="text-slate-400">-</span>
                                    @else
                                         <a class="btn btn-secondary w-full whitespace-nowrap sm:w-auto" href="{{ route('billing.pay.form', ['code' => $student->billing_code, 'bill_id' => $b->id]) }}">Bayar / Unggah Bukti</a>
                                     @endif
                                 </td>
                             </tr>
                         @empty
                            <tr><td class="px-4 py-6 text-slate-500" colspan="7">Belum ada data tagihan untuk siswa ini.</td></tr>
                         @endforelse
                     </tbody>
                 </table>
            </div>
        </div>
    </div>
@endsection
