<?php

namespace App\Http\Controllers;

use App\Models\DataSiswa;
use App\Models\SppBill;
use App\Models\SppPaymentAttachment;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index()
    {
        return view('billing.index', [
            'title' => 'Cek Tagihan',
        ]);
    }

    public function show(Request $request, ?string $code = null)
    {
        $code = $code ?: (string) $request->query('code', '');
        $code = trim($code);

        if ($code === '') {
            return redirect()->route('billing.index');
        }

        $student = DataSiswa::query()->where('billing_code', $code)->firstOrFail();

        $bills = SppBill::query()
            ->with('feeType')
            ->where('siswa_id', $student->id)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->get();

        $attachmentsByBill = SppPaymentAttachment::query()
            ->whereIn('bill_id', $bills->pluck('id')->all())
            ->orderByDesc('uploaded_at')
            ->get()
            ->groupBy('bill_id');

        return view('billing.show', [
            'title' => 'Tagihan',
            'student' => $student,
            'bills' => $bills,
            'attachmentsByBill' => $attachmentsByBill,
        ]);
    }

    public function payForm(Request $request)
    {
        $code = trim((string) $request->query('code', ''));
        $billId = (int) $request->query('bill_id', 0);

        if ($code === '' || $billId <= 0) {
            return redirect()->route('billing.index');
        }

        $student = DataSiswa::query()->where('billing_code', $code)->firstOrFail();

        $bill = SppBill::query()
            ->where('id', $billId)
            ->where('siswa_id', $student->id)
            ->firstOrFail();

        $remaining = max(0, (int) $bill->amount - (int) $bill->paid_amount);

        $attachments = SppPaymentAttachment::query()
            ->where('bill_id', $bill->id)
            ->orderByDesc('uploaded_at')
            ->limit(20)
            ->get();

        $hasPending = $attachments->contains(fn ($a) => $a->status === 'pending');

        return view('billing.pay', [
            'title' => 'Bayar Tagihan',
            'student' => $student,
            'bill' => $bill,
            'remaining' => $remaining,
            'attachments' => $attachments,
            'hasPending' => $hasPending,
        ]);
    }

    public function paySubmit(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'bill_id' => ['required', 'integer'],
            'amount' => ['required', 'integer', 'min:1'],

            // salah satu harus diisi
            'proof_camera' => ['required_without:proof_file', 'nullable', 'file', 'max:4096', 'mimes:jpg,jpeg,png'],
            'proof_file' => ['required_without:proof_camera', 'nullable', 'file', 'max:4096', 'mimes:pdf,jpg,jpeg,png'],

            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $code = trim((string) $validated['code']);
        $billId = (int) $validated['bill_id'];
        $amount = (int) $validated['amount'];

        $student = DataSiswa::query()->where('billing_code', $code)->firstOrFail();

        $bill = SppBill::query()
            ->where('id', $billId)
            ->where('siswa_id', $student->id)
            ->firstOrFail();

        $remaining = max(0, (int) $bill->amount - (int) $bill->paid_amount);
        if ($remaining <= 0) {
            return redirect()->route('billing.show', ['code' => $code])->with('success', 'Tagihan sudah lunas.');
        }

        if ($amount > $remaining) {
            return back()->withErrors(['amount' => 'Nominal melebihi sisa tagihan.'])->withInput();
        }

        $hasPending = SppPaymentAttachment::query()
            ->where('bill_id', $bill->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return back()->withErrors(['proof_file' => 'Masih ada bukti bayar yang menunggu verifikasi.'])->withInput();
        }

        $file = $request->file('proof_file') ?: $request->file('proof_camera');
        if (! $file) {
            return back()->withErrors(['proof_file' => 'Mohon upload bukti bayar (kamera atau file).'])->withInput();
        }

        $ext = $file->getClientOriginalExtension();
        $filename = 'proof_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $path = $file->storeAs('spp/payments/'.$bill->id, $filename, 'public');

        SppPaymentAttachment::query()->create([
            'bill_id' => $bill->id,
            'amount' => $amount,
            'status' => 'pending',
            'file_name' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_at' => now(),
            'verification_notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('billing.pay.form', ['code' => $code, 'bill_id' => $bill->id])
            ->with('success', 'Bukti pembayaran terkirim. Menunggu verifikasi admin.');
    }
}
