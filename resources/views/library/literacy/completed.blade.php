@extends('layouts.app')

@section('content')
    @php
        $hasReceipt = is_array($receipt);
        $nextUrl = $hasReceipt && filled($receipt['material_slug'] ?? null)
            ? route('library.literacy.show', $receipt['material_slug'])
            : route('library.literacy.index');
        $submittedAt = $hasReceipt && filled($receipt['submitted_at'] ?? null)
            ? \Illuminate\Support\Carbon::parse($receipt['submitted_at'])->format('d/m/Y H:i')
            : null;
        $classStatus = is_array($classStatus ?? null) ? $classStatus : null;
    @endphp

    <div class="mx-auto max-w-4xl">
        <section class="card overflow-hidden" aria-labelledby="receipt-title">
            <div class="border-b border-emerald-100 bg-emerald-50 px-6 py-7 text-center md:px-8">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-600 text-2xl font-bold text-white" aria-hidden="true">✓</div>
                <div class="mt-4 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Struk Pengiriman</div>
                <h1 id="receipt-title" class="mt-2 text-2xl font-semibold text-slate-950">Jawaban berhasil disimpan</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">Soal dan jawaban tidak ditampilkan agar perangkat aman dipakai murid berikutnya.</p>
            </div>

            <div class="space-y-5 p-6 md:p-8">
                @if($hasReceipt)
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nama Siswa</dt>
                            <dd class="mt-1 font-semibold text-slate-950">{{ $receipt['student_name'] ?? '-' }}</dd>
                            <dd class="text-slate-500">{{ $receipt['student_class'] ?? '-' }}</dd>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Materi</dt>
                            <dd class="mt-1 font-semibold text-slate-950">{{ $receipt['material_title'] ?? '-' }}</dd>
                            <dd class="text-slate-500">{{ $submittedAt ?? '-' }}</dd>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status Pengiriman</dt>
                            <dd class="mt-1 font-semibold text-emerald-700">{{ $receipt['submission_status'] ?? 'Tersimpan' }}</dd>
                            @if(filled($receipt['submission_status_detail'] ?? null))
                                <dd class="mt-1 text-slate-500">{{ $receipt['submission_status_detail'] }}</dd>
                            @endif
                        </div>
                    </dl>

                    <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-center">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700">Kode Edit</div>
                        <div class="mt-2 break-all text-2xl font-bold tracking-wider text-slate-950" data-literacy-receipt-code>{{ $receipt['edit_code'] ?? '-' }}</div>
                        <p class="mt-2 text-xs leading-5 text-slate-600">Simpan kode ini jika jawaban perlu diperbaiki. Halaman struk tidak menyediakan tombol langsung ke jawaban.</p>
                        <button class="btn btn-secondary mt-4 w-full sm:w-auto" type="button" data-literacy-copy-code>Salin Kode Edit</button>
                        <div class="mt-2 min-h-5 text-xs font-semibold text-emerald-700" role="status" aria-live="polite" data-literacy-copy-status></div>
                    </div>

                    @if($classStatus !== null)
                        <section class="space-y-4 rounded-2xl border border-violet-200 bg-violet-50/70 p-4 sm:p-5" aria-labelledby="class-status-title">
                            <div class="rounded-2xl border border-violet-200 bg-white p-4">
                                <div class="text-xs font-bold uppercase tracking-[0.16em] text-violet-700">Amal Salih Hari Ini</div>
                                <h2 id="class-status-title" class="mt-1.5 text-lg font-semibold text-slate-950">Ingatkan teman yang belum mengisi</h2>
                                <p class="mt-1 text-sm leading-6 text-slate-600">
                                    Sampaikan dengan sopan dan kirimkan tautan materinya. Jangan membagikan soal atau jawaban—cukup bantu teman agar tidak terlupa.
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                                <span class="rounded-full bg-emerald-100 px-3 py-1.5 text-emerald-800">{{ $classStatus['completed_total'] ?? 0 }} sudah mengisi</span>
                                <span class="rounded-full bg-amber-100 px-3 py-1.5 text-amber-800">{{ $classStatus['missing_total'] ?? 0 }} belum mengisi</span>
                                @if(($classStatus['dispensation_total'] ?? 0) > 0)
                                    <span class="rounded-full bg-sky-100 px-3 py-1.5 text-sky-800">{{ $classStatus['dispensation_total'] }} dispensasi</span>
                                @endif
                                <span class="rounded-full bg-slate-200 px-3 py-1.5 text-slate-700">Kelas {{ $classStatus['class'] ?? '-' }}</span>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <section class="min-w-0 rounded-2xl border border-emerald-200 bg-white p-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <h3 class="font-semibold text-emerald-800">Sudah Mengisi</h3>
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">{{ $classStatus['completed_total'] ?? 0 }}</span>
                                    </div>
                                    <ul class="mt-3 max-h-72 space-y-2 overflow-y-auto pr-1">
                                        @forelse(($classStatus['completed_students'] ?? []) as $student)
                                            <li class="flex min-w-0 items-center justify-between gap-2 rounded-xl bg-emerald-50 px-3 py-2">
                                                <span class="min-w-0">
                                                    <strong class="block break-words text-sm text-slate-900">{{ $student['name'] }}</strong>
                                                    <small class="block text-xs text-slate-500">{{ $student['class'] }}</small>
                                                </span>
                                                @if($student['is_current'] ?? false)
                                                    <span class="shrink-0 rounded-full bg-emerald-600 px-2 py-1 text-[0.65rem] font-bold text-white">Kamu</span>
                                                @endif
                                            </li>
                                        @empty
                                            <li class="rounded-xl bg-slate-50 px-3 py-3 text-sm text-slate-500">Belum ada siswa yang tercatat mengisi.</li>
                                        @endforelse
                                    </ul>
                                </section>

                                <section class="min-w-0 rounded-2xl border border-amber-200 bg-white p-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <h3 class="font-semibold text-amber-800">Belum Mengisi</h3>
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">{{ $classStatus['missing_total'] ?? 0 }}</span>
                                    </div>
                                    <ul class="mt-3 max-h-72 space-y-2 overflow-y-auto pr-1">
                                        @forelse(($classStatus['missing_students'] ?? []) as $student)
                                            <li class="min-w-0 rounded-xl bg-amber-50 px-3 py-2">
                                                <strong class="block break-words text-sm text-slate-900">{{ $student['name'] }}</strong>
                                                <small class="block text-xs text-slate-500">{{ $student['class'] }}</small>
                                            </li>
                                        @empty
                                            <li class="rounded-xl bg-emerald-50 px-3 py-3 text-sm font-semibold text-emerald-700">Alhamdulillah, semua teman satu kelas sudah mengisi atau memiliki dispensasi.</li>
                                        @endforelse
                                    </ul>
                                </section>
                            </div>

                            @if(($classStatus['dispensation_total'] ?? 0) > 0)
                                <section class="rounded-2xl border border-sky-200 bg-white p-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <h3 class="font-semibold text-sky-800">Dispensasi—Tidak Perlu Diingatkan</h3>
                                        <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-bold text-sky-800">{{ $classStatus['dispensation_total'] }}</span>
                                    </div>
                                    <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                                        @foreach(($classStatus['dispensated_students'] ?? []) as $student)
                                            <li class="min-w-0 rounded-xl bg-sky-50 px-3 py-2">
                                                <strong class="block break-words text-sm text-slate-900">{{ $student['name'] }}</strong>
                                                <small class="block text-xs text-slate-500">{{ $student['class'] }} · {{ $student['reason_label'] }}</small>
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endif
                        </section>
                    @endif
                @else
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
                        Data struk sudah ditutup atau halaman dimuat ulang. Demi keamanan, identitas dan kode edit tidak disimpan pada halaman ini.
                    </div>
                @endif

                <div class="grid gap-3 sm:grid-cols-2">
                    <button class="btn btn-primary w-full" type="button" data-literacy-next-student data-next-url="{{ $nextUrl }}">
                        Isi Murid Berikutnya
                    </button>
                    <a class="btn btn-secondary w-full" href="{{ route('library.literacy.index') }}">Kembali ke Daftar</a>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const draftKey = @js($receipt['draft_key'] ?? null);
            const submissionRequestId = @js($receipt['submission_request_id'] ?? null);
            const nextUrl = @js($nextUrl);

            if (draftKey) {
                try {
                    window.sessionStorage.removeItem(`literacy.submission.draft.v2:${draftKey}`);
                } catch (error) {
                    // Storage can be unavailable in strict private modes.
                }
            }

            if (submissionRequestId) {
                try {
                    window.sessionStorage.setItem(
                        `literacy.submission.completed.v1:${submissionRequestId}`,
                        JSON.stringify({ redirect_url: nextUrl, completed_at: Date.now() })
                    );
                } catch (error) {
                    // Storage can be unavailable in strict private modes.
                }
            }

            const code = document.querySelector('[data-literacy-receipt-code]')?.textContent?.trim() || '';
            const copyStatus = document.querySelector('[data-literacy-copy-status]');

            document.querySelector('[data-literacy-copy-code]')?.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(code);
                    copyStatus.textContent = 'Kode edit berhasil disalin.';
                } catch (error) {
                    copyStatus.textContent = 'Salin manual kode edit yang tampil di atas.';
                }
            });

            document.querySelector('[data-literacy-next-student]')?.addEventListener('click', (event) => {
                window.location.replace(event.currentTarget.dataset.nextUrl);
            });
        })();
    </script>
@endpush
