@extends('layouts.app')

@section('content')
    @include('library._nav')

    <div class="card p-6 reveal">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Perpustakaan</div>
                <h1 class="mt-2 text-2xl font-semibold">Form aktivitas perpustakaan</h1>
                <p class="mt-1 text-sm text-slate-500">Catat kegiatan literasi atau tugas berbasis buku.</p>
            </div>
            <a class="btn btn-secondary w-full sm:w-auto" href="{{ route('library.activities.result') }}">Input Hasil Literasi</a>
        </div>

        @if(session('success'))
            <div class="mt-4 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
    </div>

    <form method="post" action="{{ route('library.activities.store') }}" class="card mt-6 space-y-6 p-6" data-library-activity-form>
        @csrf
        @php
            $oldRole = old('participant_role', 'siswa');
            $oldParticipantId = (string) old('participant_id', '');
            $activityAtValue = old('activity_at', $defaultActivityAt ?? now()->format('Y-m-d\TH:i'));
        @endphp

        @if($errors->any())
            <div class="rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <div class="font-semibold">Periksa kembali isian berikut:</div>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="text-sm font-semibold text-slate-700" for="participant_role">Pilih Peran *</label>
                <select class="input mt-2" id="participant_role" name="participant_role" data-role-select required>
                    <option value="siswa" @selected($oldRole === 'siswa')>Siswa</option>
                    <option value="guru" @selected($oldRole === 'guru')>Guru</option>
                </select>
                @error('participant_role')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-700" for="purpose">Tujuan Kegiatan *</label>
                <select class="input mt-2" id="purpose" name="purpose" data-purpose-select required>
                    @foreach($purposeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('purpose', \App\Models\PerpustakaanLiterasiActivity::PURPOSE_LITERASI) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('purpose')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label class="text-sm font-semibold text-slate-700" for="participant_id" data-participant-select-label>Pilih dari Data Siswa</label>
                <select class="input mt-2" id="participant_id" name="participant_id" data-participant-select data-selected-id="{{ $oldParticipantId }}">
                    <option value="">Ketik manual</option>
                </select>
                <div class="mt-1 text-xs text-slate-500" data-participant-select-help>Pilih data siswa untuk mengisi nama dan kelas otomatis, atau biarkan manual.</div>
                @error('participant_id')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-700" for="participant_name" data-student-name-label>Nama Siswa *</label>
                <label class="hidden text-sm font-semibold text-slate-700" for="participant_name" data-teacher-name-label>Nama Guru *</label>
                <input class="input mt-2" id="participant_name" name="participant_name" value="{{ old('participant_name') }}" required>
                @error('participant_name')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>

            <div data-class-field>
                <label class="text-sm font-semibold text-slate-700" for="participant_class">Kelas *</label>
                <input class="input mt-2" id="participant_class" name="participant_class" value="{{ old('participant_class') }}">
                @error('participant_class')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>

            <div data-subject-field>
                <label class="text-sm font-semibold text-slate-700" for="subject_name">Mata Pelajaran *</label>
                <input class="input mt-2" id="subject_name" name="subject_name" value="{{ old('subject_name') }}">
                @error('subject_name')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-700" for="activity_at">Waktu Aktivitas</label>
                <input class="input mt-2" id="activity_at" type="datetime-local" name="activity_at" value="{{ $activityAtValue }}">
                @error('activity_at')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="text-sm font-semibold text-slate-700" for="book_id">Pilih dari Katalog</label>
                <select class="input mt-2" id="book_id" name="book_id">
                    <option value="">Isi judul manual</option>
                    @foreach($books as $book)
                        <option value="{{ $book->id }}" @selected((string) old('book_id') === (string) $book->id)>
                            {{ $book->judul_buku }}{{ $book->penulis ? ' - '.$book->penulis : '' }}
                        </option>
                    @endforeach
                </select>
                @error('book_id')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-700" for="book_title">Judul Buku *</label>
                <input class="input mt-2" id="book_title" name="book_title" value="{{ old('book_title') }}">
                @error('book_title')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label class="text-sm font-semibold text-slate-700" for="book_author">Penulis</label>
                <input class="input mt-2" id="book_author" name="book_author" value="{{ old('book_author') }}">
                @error('book_author')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
        </section>

        <div class="grid gap-2 sm:flex sm:flex-wrap">
            <button class="btn btn-primary w-full sm:w-auto" type="submit">Simpan Aktivitas</button>
            <a class="btn btn-secondary w-full sm:w-auto" href="{{ route('library.index') }}">Cari Buku</a>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-library-activity-form]');
            if (!form) {
                return;
            }

            const students = @json($students ?? []);
            const teachers = @json($teachers ?? []);
            const roleSelect = form.querySelector('[data-role-select]');
            const purposeSelect = form.querySelector('[data-purpose-select]');
            const participantSelect = form.querySelector('[data-participant-select]');
            const participantSelectLabel = form.querySelector('[data-participant-select-label]');
            const participantSelectHelp = form.querySelector('[data-participant-select-help]');
            const classField = form.querySelector('[data-class-field]');
            const classInput = form.querySelector('#participant_class');
            const subjectField = form.querySelector('[data-subject-field]');
            const subjectInput = form.querySelector('#subject_name');
            const nameInput = form.querySelector('#participant_name');
            const studentNameLabel = form.querySelector('[data-student-name-label]');
            const teacherNameLabel = form.querySelector('[data-teacher-name-label]');
            let selectedId = participantSelect.dataset.selectedId || '';

            const optionFor = (entry) => {
                const option = document.createElement('option');
                option.value = String(entry.id);
                option.textContent = entry.label;
                option.dataset.name = entry.name || '';
                option.dataset.class = entry.class || '';

                return option;
            };

            const refreshParticipantOptions = () => {
                const isStudent = roleSelect.value === 'siswa';
                const source = isStudent ? students : teachers;
                const placeholder = document.createElement('option');

                placeholder.value = '';
                placeholder.textContent = 'Ketik manual';
                participantSelect.replaceChildren(placeholder, ...source.map(optionFor));
                participantSelect.value = source.some((entry) => String(entry.id) === selectedId) ? selectedId : '';

                participantSelectLabel.textContent = isStudent ? 'Pilih dari Data Siswa' : 'Pilih dari Data Guru/Tendik';
                participantSelectHelp.textContent = isStudent
                    ? 'Pilih data siswa untuk mengisi nama dan kelas otomatis, atau biarkan manual.'
                    : 'Pilih data guru/tendik untuk mengisi nama otomatis, atau biarkan manual.';
            };

            const applySelectedParticipant = () => {
                selectedId = participantSelect.value;
                const option = participantSelect.selectedOptions[0];

                if (!option || option.value === '') {
                    return;
                }

                nameInput.value = option.dataset.name || '';

                if (roleSelect.value === 'siswa') {
                    classInput.value = option.dataset.class || '';
                } else {
                    classInput.value = '';
                }
            };

            const refresh = () => {
                const isStudent = roleSelect.value === 'siswa';
                const isTask = purposeSelect.value === 'tugas';

                classField.classList.toggle('hidden', !isStudent);
                classInput.toggleAttribute('required', isStudent);
                studentNameLabel.classList.toggle('hidden', !isStudent);
                teacherNameLabel.classList.toggle('hidden', isStudent);

                subjectField.classList.toggle('hidden', !isTask);
                subjectInput.toggleAttribute('required', isTask);

                refreshParticipantOptions();
                applySelectedParticipant();
            };

            roleSelect.addEventListener('change', () => {
                selectedId = '';
                refresh();
            });
            purposeSelect.addEventListener('change', refresh);
            participantSelect.addEventListener('change', applySelectedParticipant);
            refresh();
        })();
    </script>
@endpush
