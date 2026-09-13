<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ujian Online SMA AFBS</title>
<style>
body{margin:0;font-family:Georgia,serif;background:#eef3ed;color:#163126}.wrap{max-width:720px;margin:7vh auto;padding:24px}.card{background:#fff;border-top:8px solid #b68127;border-radius:18px;padding:32px;box-shadow:0 18px 50px #17352622}h1{font-size:clamp(2rem,6vw,3.7rem);margin:.2em 0}label{display:block;font:700 14px sans-serif;margin-top:16px}input,select{box-sizing:border-box;width:100%;padding:12px;border:1px solid #b8c6bc;border-radius:8px;margin-top:5px;background:#fff}button{margin-top:22px;background:#173f2c;color:white;border:0;border-radius:8px;padding:13px 20px;font-weight:bold}.error{background:#fff1ee;color:#8b251b;padding:12px;border-radius:8px}.success{background:#e6f7e9;padding:12px;border-radius:8px}.hint{font:13px sans-serif;color:#496052;margin:6px 0 0}.suggestions{list-style:none;margin:4px 0 0;padding:0;border:1px solid #b8c6bc;border-radius:8px;overflow:hidden}.suggestions[hidden]{display:none}.suggestions button{display:block;width:100%;margin:0;padding:10px 12px;text-align:left;background:#fff;color:#163126;border:0;border-radius:0}.suggestions button:hover,.suggestions button:focus{background:#eef3ed}
</style>
</head>
<body>
<main class="wrap"><div class="card">
<small>SMA Al Furqon Boarding School</small><h1>Ujian Online</h1><p>Masukkan identitas persis seperti data sekolah. Sistem hanya menampilkan ujian yang sedang aktif.</p>
@if(session('success'))<p class="success">{{ session('success') }}</p>@endif
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
@if($classes->isEmpty())
<p class="hint">Belum ada jadwal ujian yang aktif. Silakan hubungi pengawas untuk informasi jadwal.</p>
@else
<form method="post" action="{{ route('exam.verify') }}">@csrf
<label>Kelas / Rombel
<select name="class_name" id="class_name" required><option value="">Pilih rombel</option>@foreach($classes as $class)<option value="{{ $class }}" @selected(old('class_name') === $class)>{{ $class }}</option>@endforeach</select>
</label>
<label>Nama lengkap
<input name="student_name" id="student_name" value="{{ old('student_name') }}" required autocomplete="off" disabled aria-describedby="student_hint" aria-controls="student_suggestions" aria-expanded="false">
</label>
<p class="hint" id="student_hint">Pilih rombel, lalu ketik minimal 2 huruf nama siswa.</p>
<ul id="student_suggestions" class="suggestions" role="listbox" hidden></ul>
<label>NISN<input name="nisn" value="{{ old('nisn') }}" required inputmode="numeric"></label>
<label>Tanggal lahir<input type="date" name="birth_date" value="{{ old('birth_date') }}" required></label>
<label>Kode Siswa / Token Ujian<input name="exam_code" value="{{ old('exam_code') }}" required autocomplete="off" placeholder="ABCD-1234" aria-describedby="exam_code_hint"></label>
<p class="hint" id="exam_code_hint">Gunakan kode siswa dari pengawas/admin. Contoh: ABCD-1234. Jangan isi kode jadwal ujian.</p>
<button>Verifikasi & Lihat Ujian</button>
</form>
<script>
const studentsByClass = @json($studentsByClass);
const classInput = document.getElementById('class_name');
const studentInput = document.getElementById('student_name');
const suggestions = document.getElementById('student_suggestions');
function hideSuggestions() { suggestions.hidden = true; suggestions.replaceChildren(); studentInput.setAttribute('aria-expanded', 'false'); }
function showSuggestions() {
    const query = studentInput.value.trim().toLocaleLowerCase();
    if (query.length < 2) return hideSuggestions();
    const names = (studentsByClass[classInput.value] || []).filter((name) => name.toLocaleLowerCase().includes(query)).slice(0, 8);
    if (!names.length) return hideSuggestions();
    suggestions.replaceChildren(...names.map((name) => {
        const item = document.createElement('li'); const button = document.createElement('button');
        button.type = 'button'; button.role = 'option'; button.textContent = name;
        button.addEventListener('click', () => { studentInput.value = name; hideSuggestions(); studentInput.focus(); });
        item.append(button); return item;
    }));
    suggestions.hidden = false; studentInput.setAttribute('aria-expanded', 'true');
}
classInput.addEventListener('change', () => { studentInput.disabled = !classInput.value; studentInput.value = ''; hideSuggestions(); if (!studentInput.disabled) studentInput.focus(); });
studentInput.addEventListener('input', showSuggestions);
studentInput.addEventListener('blur', () => setTimeout(hideSuggestions, 150));
if (classInput.value) { studentInput.disabled = false; }
</script>
@endif
</div></main>
</body>
</html>
