<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Status Ujian Online</title>
<style>
body{margin:0;font-family:Georgia,serif;background:#eef3ed;color:#163126}.wrap{max-width:720px;margin:7vh auto;padding:24px}.card{background:#fff;border-top:8px solid #b68127;border-radius:18px;padding:32px;box-shadow:0 18px 50px #17352622}h1{font-size:clamp(2rem,6vw,3.5rem);margin:.2em 0}h2{margin-top:1.5em}.meta{font-family:sans-serif;color:#496052;line-height:1.7}.notice{background:#e6f7e9;padding:14px;border-radius:8px}.warning{background:#fff5df;padding:14px;border-radius:8px}a.btn{display:inline-block;margin-top:22px;background:#173f2c;color:#fff;border-radius:8px;padding:13px 20px;text-decoration:none;font-family:sans-serif;font-weight:bold}
</style>
</head>
<body><main class="wrap"><div class="card">
<small>SMA Al Furqon Boarding School</small>
@if($status === 'submitted')
<h1>Ujian sudah dikirim</h1>
<p class="notice">Jawaban ujian Anda sudah tercatat dengan aman.</p>
<div class="meta"><strong>Nama:</strong> {{ $attempt->studentToken->student_name }}<br><strong>Ujian:</strong> {{ $attempt->schedule->questionSet->title }}@if($attempt->submitted_at)<br><strong>Waktu kirim:</strong> {{ $attempt->submitted_at->format('d/m/Y H:i') }}@endif</div>
<p>Nilai essay menunggu koreksi guru. Hasil ujian akan diproses setelah pemeriksaan selesai.</p>
@else
<h1>Sesi ujian tidak aktif</h1>
<p class="warning">Sesi ujian ini tidak sedang terbuka di perangkat Anda atau sudah tidak aktif.</p>
<p>Silakan kembali ke halaman verifikasi dan isi ulang kelas, nama, NISN, tanggal lahir, serta token ujian sesuai data yang diberikan pengawas.</p>
@endif
<a class="btn" href="{{ route('exam.index') }}">Kembali ke halaman verifikasi</a>
</div></main></body></html>
