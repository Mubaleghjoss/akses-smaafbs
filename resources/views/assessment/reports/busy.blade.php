<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>PDF sedang disiapkan</title>
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f1f5f9;color:#0f172a;font:16px/1.55 system-ui,sans-serif;padding:20px;box-sizing:border-box}.card{width:min(100%,520px);background:#fff;border:1px solid #cbd5e1;border-radius:20px;padding:28px;box-shadow:0 18px 45px rgba(15,23,42,.12)}h1{font-size:1.35rem;margin:0 0 10px}p{margin:0 0 16px;color:#475569}.timer{font-weight:800;color:#0f766e}.actions{display:flex;gap:10px;flex-wrap:wrap}button{border:0;border-radius:12px;padding:11px 16px;background:#0f766e;color:#fff;font-weight:700;cursor:pointer}
    </style>
</head>
<body>
<main class="card">
    <h1>PDF rapor sedang disiapkan untuk pengguna lain</h1>
    <p>Agar hosting tetap ringan, sistem hanya merender satu PDF pada satu waktu. Data rapor Anda aman dan tidak perlu membuat ulang revisi.</p>
    <p>Silakan coba lagi dalam <span class="timer" id="retry-count">{{ $retryAfterSeconds }}</span> detik.</p>
    <div class="actions"><button type="button" onclick="location.reload()">Periksa Lagi</button></div>
</main>
<script>
    let remaining = @json($retryAfterSeconds);
    const output = document.getElementById('retry-count');
    const timer = window.setInterval(() => {
        remaining -= 1;
        output.textContent = Math.max(0, remaining);
        if (remaining <= 0) { window.clearInterval(timer); location.reload(); }
    }, 1000);
</script>
</body>
</html>
