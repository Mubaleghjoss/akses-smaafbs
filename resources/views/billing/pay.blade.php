@extends('layouts.app')

@section('content')
    <a class="chip" href="{{ route('billing.show.code', ['code' => request('code')]) }}"><- Kembali ke detail tagihan</a>

    <div class="card mt-5 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Unggah bukti pembayaran</div>
                <h1 class="mt-2 text-2xl font-semibold">{{ $student->nama }}</h1>
                <div class="mt-1 text-sm text-slate-500">{{ $student->rombel_saat_ini ?? '-' }}</div>
            </div>
            <div class="chip">Kode: {{ $student->billing_code ?? '-' }}</div>
        </div>

        @if(session('success'))
            <div class="mt-4 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mt-4 rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-6 rounded-2xl border border-white/70 bg-white/80 p-4 text-sm">
            <div><span class="text-slate-500">Periode:</span> {{ sprintf('%02d/%d', (int)$bill->period_month, (int)$bill->period_year) }}</div>
            <div class="mt-1"><span class="text-slate-500">Tagihan:</span> Rp {{ number_format((int)$bill->amount, 0, ',', '.') }}</div>
            <div class="mt-1"><span class="text-slate-500">Terbayar:</span> Rp {{ number_format((int)$bill->paid_amount, 0, ',', '.') }}</div>
            <div class="mt-1"><span class="text-slate-500">Sisa:</span> <span class="font-semibold">Rp {{ number_format((int)$remaining, 0, ',', '.') }}</span></div>
            <div class="mt-1"><span class="text-slate-500">Jatuh Tempo:</span> {{ $bill->due_date?->format('d/m/Y') ?? '-' }}</div>
        </div>

        @if($hasPending)
            <div class="mt-4 rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Sudah ada bukti pembayaran yang <strong>menunggu verifikasi</strong> untuk tagihan ini.
            </div>
        @endif

        <form class="mt-6 grid max-w-xl gap-4" method="post" action="{{ route('billing.pay.submit') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="code" value="{{ $student->billing_code }}" />
            <input type="hidden" name="bill_id" value="{{ $bill->id }}" />

            <div>
                <label class="mb-1 block text-sm font-medium">Nominal pembayaran</label>
                <input name="amount" value="{{ old('amount', $remaining) }}" class="input" type="number" min="1" max="{{ $remaining }}" {{ $hasPending ? 'disabled' : '' }} required />
                <div class="mt-1 text-xs text-slate-500">Maksimal Rp {{ number_format((int)$remaining, 0, ',', '.') }}</div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Unggah bukti pembayaran (maks. 4MB)</label>
                <div class="mt-2 grid gap-3">
                    <div class="rounded-2xl border border-white/70 bg-white/80 p-4">
                        <div class="text-xs text-slate-500">Ambil foto melalui kamera (disarankan di ponsel):</div>

                        <div class="mt-2 grid gap-2 sm:flex sm:flex-wrap sm:items-center">
                            <button id="btn-open-camera" type="button" class="btn btn-primary w-full text-xs disabled:opacity-50 sm:w-auto" {{ $hasPending ? 'disabled' : '' }}>
                                Buka kamera
                            </button>
                            <button id="btn-capture" type="button" class="btn btn-secondary w-full text-xs disabled:opacity-50 sm:w-auto" disabled>
                                Ambil foto
                            </button>
                            <button id="btn-stop" type="button" class="btn btn-secondary w-full text-xs disabled:opacity-50 sm:w-auto" disabled>
                                Matikan kamera
                            </button>
                            <span id="camera-status" class="text-xs text-slate-500"></span>
                        </div>

                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <div>
                                <video id="camera-video" class="hidden w-full rounded-xl border border-white/70" playsinline></video>
                                <canvas id="camera-canvas" class="hidden w-full rounded-xl border border-white/70"></canvas>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Atau gunakan input kamera bawaan perangkat:</div>
                                <input id="input-proof-camera" name="proof_camera" class="mt-1 w-full text-sm" type="file" accept="image/*" capture="environment" {{ $hasPending ? 'disabled' : '' }} />
                                <div class="mt-2 text-xs text-slate-500">
                                    Jika tombol kamera tidak muncul, pastikan browser sudah mendapat izin akses kamera (memerlukan HTTPS atau localhost).
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="text-xs text-slate-500">Atau unggah file dokumen (PDF/JPG/PNG):</div>
                        <input id="input-proof-file" name="proof_file" class="w-full text-sm" type="file" accept="application/pdf,image/jpeg,image/png" {{ $hasPending ? 'disabled' : '' }} />
                    </div>

                    <div class="text-xs text-slate-500">Silakan gunakan salah satu metode: kamera atau unggah file.</div>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Catatan (opsional)</label>
                <textarea name="notes" rows="3" class="input" {{ $hasPending ? 'disabled' : '' }}>{{ old('notes') }}</textarea>
            </div>

            <button class="btn btn-primary w-full disabled:opacity-50" {{ $hasPending ? 'disabled' : '' }}>
                Kirim untuk verifikasi
            </button>
        </form>

        <div class="mt-8">
            <h2 class="text-sm font-semibold">Riwayat unggahan bukti</h2>
            <div class="mt-3 overflow-hidden rounded-2xl border border-white/70 bg-white/80">
                <div class="w-full overflow-x-auto">
                    <table class="min-w-[28rem] w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Waktu</th>
                                <th class="px-4 py-3">Nominal</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($attachments as $a)
                                <tr>
                                    <td class="px-4 py-3">{{ $a->uploaded_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td class="px-4 py-3">Rp {{ number_format((int)($a->amount ?? 0), 0, ',', '.') }}</td>
                                    <td class="px-4 py-3">
                                        @if($a->status === 'approved')
                                            <span class="chip bg-emerald-50 text-emerald-700">Disetujui</span>
                                        @elseif($a->status === 'rejected')
                                            <span class="chip bg-red-50 text-red-700">Ditolak</span>
                                        @else
                                            <span class="chip bg-amber-50 text-amber-700">Menunggu</span>
                                        @endif
                                        @if($a->verification_notes)
                                            <div class="mt-2 text-xs text-slate-500">{{ $a->verification_notes }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="px-4 py-6 text-slate-500" colspan="3">Belum ada bukti pembayaran yang diunggah.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const openBtn = document.getElementById('btn-open-camera');
            const captureBtn = document.getElementById('btn-capture');
            const stopBtn = document.getElementById('btn-stop');
            const statusEl = document.getElementById('camera-status');

            const video = document.getElementById('camera-video');
            const canvas = document.getElementById('camera-canvas');

            const inputCamera = document.getElementById('input-proof-camera');
            const inputFile = document.getElementById('input-proof-file');

            if (!openBtn || !captureBtn || !stopBtn || !video || !canvas || !inputCamera || !inputFile) {
                return;
            }

            let stream = null;

            function setStatus(text) {
                statusEl.textContent = text || '';
            }

            function clearOtherInputs(active) {
                if (active === 'camera') {
                    inputFile.value = '';
                }
                if (active === 'file') {
                    inputCamera.value = '';
                }
            }

            inputFile.addEventListener('change', () => clearOtherInputs('file'));
            inputCamera.addEventListener('change', () => clearOtherInputs('camera'));

            openBtn.addEventListener('click', async () => {
                try {
                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        setStatus('Browser tidak mendukung akses kamera.');
                        return;
                    }

                    setStatus('Meminta izin akses kamera...');

                    stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: { ideal: 'environment' } },
                        audio: false,
                    });

                    video.srcObject = stream;
                    await video.play();

                    video.classList.remove('hidden');
                    canvas.classList.add('hidden');

                    captureBtn.disabled = false;
                    stopBtn.disabled = false;

                    setStatus('Kamera siap digunakan.');
                } catch (e) {
                    setStatus('Kamera tidak dapat dibuka. Pastikan izin akses kamera sudah diberikan.');
                }
            });

            captureBtn.addEventListener('click', () => {
                if (!stream) {
                    return;
                }

                const width = video.videoWidth || 1280;
                const height = video.videoHeight || 720;

                canvas.width = width;
                canvas.height = height;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, width, height);

                canvas.classList.remove('hidden');
                video.classList.add('hidden');

                canvas.toBlob((blob) => {
                    if (!blob) {
                        setStatus('Gagal mengambil foto.');
                        return;
                    }

                    const file = new File([blob], `camera_${Date.now()}.jpg`, { type: 'image/jpeg' });
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    inputCamera.files = dt.files;

                    clearOtherInputs('camera');
                    setStatus('Foto siap dikirim.');
                }, 'image/jpeg', 0.9);
            });

            stopBtn.addEventListener('click', () => {
                if (stream) {
                    stream.getTracks().forEach((t) => t.stop());
                }
                stream = null;

                captureBtn.disabled = true;
                stopBtn.disabled = true;

                video.classList.add('hidden');
                canvas.classList.add('hidden');

                setStatus('Kamera dimatikan.');
            });
        })();
    </script>
@endpush
