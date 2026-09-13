<x-filament-panels::page>
    <div class="space-y-6">
        @if(session('exam_notice'))
            <div class="rounded-xl bg-success-50 p-4">{{ session('exam_notice') }} @if(session('exam_student_token'))<strong class="ml-2 rounded bg-white px-2 py-1 font-mono text-base">{{ session('exam_student_token') }}</strong>@endif</div>
        @endif
        @unless($this->schemaReady())
            <div class="rounded-xl bg-warning-50 p-5">Migration ujian online belum tersedia.</div>
        @else
            <section class="rounded-2xl border bg-white p-5 dark:bg-gray-900">
                <h2 class="text-xl font-bold">Paket Soal</h2>
                <p class="text-sm text-gray-600">Kunci, pembahasan, dan rubrik hanya terlihat untuk admin/guru berwenang.</p>
                <div class="mt-3 space-y-3">
                    @forelse($this->sets() as $set)
                        <details class="rounded-xl border p-3">
                            <summary class="cursor-pointer font-semibold">{{ $set->title }} · {{ $set->subject }} · {{ $set->questions->count() }} soal</summary>
                            <ol class="mt-3 list-decimal space-y-3 pl-5 text-sm">
                                @foreach($set->questions as $question)
                                    <li><b>{{ $question->type }}</b> · bobot {{ $question->weight }}<div>{{ $question->prompt }}</div><div class="text-gray-600">Kunci: {{ implode(', ', $question->answer_key ?? []) }}</div>@if($question->explanation)<div class="text-gray-600">Pembahasan: {{ $question->explanation }}</div>@endif @if($question->rubric)<div class="text-gray-600">Rubrik: {{ $question->rubric }}</div>@endif</li>
                                @endforeach
                            </ol>
                        </details>
                    @empty
                        <p class="text-sm">Belum ada paket soal.</p>
                    @endforelse
                </div>
            </section>
            <section class="rounded-2xl border bg-white p-5 dark:bg-gray-900">
                <h2 class="text-xl font-bold">Buat Jadwal Ujian</h2>
                <form class="mt-4 grid gap-3 md:grid-cols-3" method="post" action="{{ route('admin.exam.schedules.store') }}">@csrf
                    <select class="fi-input" name="question_set_id">@foreach($this->sets() as $set)<option value="{{ $set->id }}">{{ $set->title }}</option>@endforeach</select><input class="fi-input" name="class_name" placeholder="Kelas / rombel" required><input class="fi-input" name="exam_code" placeholder="Kode ujian" required><input class="fi-input" type="password" name="supervisor_code" placeholder="Kode pengawas (min. 4)" required><input class="fi-input" type="datetime-local" name="starts_at" required><input class="fi-input" type="datetime-local" name="ends_at" required><input class="fi-input" type="number" name="duration_minutes" value="90" required><button class="fi-btn bg-primary-600 px-4 py-2 text-white">Buat Jadwal</button>
                </form>
            </section>
            @foreach($this->schedules() as $schedule)
                <section class="rounded-2xl border bg-white p-5 dark:bg-gray-900"><h3 class="font-bold">{{ $schedule->questionSet->title }} · {{ $schedule->class_name }}</h3><p class="text-sm">Kode: {{ $schedule->exam_code }} | Terverifikasi {{ $schedule->tokens->whereNotNull('verified_at')->count() }}/{{ $schedule->tokens->count() }} | Mulai {{ $schedule->attempts->whereNotNull('started_at')->count() }} | Submit {{ $schedule->attempts->where('status','submitted')->count() }}</p><details class="mt-3"><summary>Tambah peserta</summary><form class="mt-3 grid gap-2 md:grid-cols-4" method="post" action="{{ route('admin.exam.schedules.students.store',$schedule) }}">@csrf<input class="fi-input" name="student_name" placeholder="Nama" required><input class="fi-input" name="nisn" placeholder="NISN" required><input class="fi-input" type="date" name="birth_date" required><input class="fi-input" name="class_name" value="{{ $schedule->class_name }}" required><button class="fi-btn bg-primary-600 px-3 py-2 text-white">Tambah</button></form></details></section>
            @endforeach
            <section class="rounded-2xl border bg-white p-5 dark:bg-gray-900">
                <h2 class="text-xl font-bold">Hasil & Koreksi Ujian</h2>
                <p class="text-sm">Badge <b>Murni Ujian</b> adalah nilai final attempt. Untuk demo ASTS yang siswa, kelas, dan mapelnya cocok, badge yang sama tampil di pratinjau/PDF rapor tanpa menimpa nilai rapor yang sudah ada.</p>
                <div class="mt-4 space-y-4">
                    @forelse($this->attempts() as $attempt)
                        <article class="rounded-xl border p-4"><b>{{ $attempt->studentToken->student_name }} — {{ $attempt->schedule->questionSet->title }}</b><p class="text-sm">Status {{ $attempt->status }} | Terjawab {{ $attempt->answers->count() }} | Keluar {{ $attempt->exit_count }} | Offline {{ $attempt->offline_count }} | Auto {{ $attempt->auto_score }} | Essay {{ $attempt->essay_score }} | <span class="rounded bg-amber-100 px-2 py-1 font-semibold text-amber-900">Murni Ujian: {{ $attempt->final_score ?? 'menunggu' }}</span></p>
                            <details class="mt-2"><summary class="cursor-pointer text-sm">Jawaban dan nilai per soal</summary><div class="mt-2 space-y-2 text-sm">@foreach($attempt->answers as $answer)<div class="rounded border p-2"><b>{{ $answer->question->type }}</b> · {{ \Illuminate\Support\Str::limit($answer->question->prompt, 100) }}<br>Jawaban: {{ implode(', ', $answer->answer ?? []) }} | Benar: {{ $answer->is_correct === null ? 'dinilai manual' : ($answer->is_correct ? 'ya' : 'tidak') }} | Auto: {{ $answer->auto_score }} | Essay: {{ $answer->manual_score ?? '-' }}</div>@endforeach</div></details>
                            @foreach($attempt->answers->where('question.type','essay') as $answer)<form class="mt-2 flex flex-wrap gap-2" method="post" action="{{ route('admin.exam.answers.grade',$answer) }}">@csrf <span class="text-sm">{{ \Illuminate\Support\Str::limit($answer->question->prompt,60) }}</span><input class="fi-input w-24" type="number" step=".01" max="{{ $answer->question->weight }}" name="manual_score" value="{{ $answer->manual_score }}" required><input class="fi-input" name="teacher_feedback" value="{{ $answer->teacher_feedback }}" placeholder="Catatan"><button class="fi-btn bg-primary-600 px-3 py-2 text-white">Simpan</button></form>@endforeach
                        </article>
                    @empty <p>Belum ada attempt.</p>
                    @endforelse
                </div>
            </section>
        @endunless
    </div>
</x-filament-panels::page>
