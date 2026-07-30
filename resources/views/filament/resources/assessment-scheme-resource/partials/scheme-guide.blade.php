<section class="assessment-scheme-guide" aria-labelledby="assessment-scheme-guide-title">
    <div class="assessment-scheme-guide__hero">
        <span class="assessment-scheme-guide__hero-icon">
            <x-filament::icon icon="heroicon-o-calculator" class="h-7 w-7" />
        </span>
        <div>
            <span class="assessment-scheme-guide__eyebrow">Sebelum mengisi form</span>
            <h2 id="assessment-scheme-guide-title">Apa itu Komponen dan Bobot?</h2>
            <p>
                Skema adalah rumus penilaian yang mengubah beberapa nilai dari guru menjadi satu nilai akhir siswa untuk satu periode.
            </p>
        </div>
    </div>

    <div class="assessment-scheme-guide__purpose-grid">
        <article>
            <span class="assessment-scheme-guide__number">1</span>
            <div>
                <h3>Maksud</h3>
                <p>Menentukan nilai apa saja yang harus diisi guru, misalnya Tugas, Tes ASTS, atau Tes ASAS.</p>
            </div>
        </article>
        <article>
            <span class="assessment-scheme-guide__number">2</span>
            <div>
                <h3>Tujuan</h3>
                <p>Membuat perhitungan seluruh guru seragam, transparan, dan otomatis sesuai bobot sekolah.</p>
            </div>
        </article>
        <article>
            <span class="assessment-scheme-guide__number">3</span>
            <div>
                <h3>Hasil</h3>
                <p>Guru mendapat kolom input sesuai komponen; sistem menghitung nilai akhir, KKM, predikat, dan deskripsi.</p>
            </div>
        </article>
    </div>

    <div class="assessment-scheme-guide__examples">
        <article>
            <header>
                <span>Contoh ASTS</span>
                <strong>Total 100%</strong>
            </header>
            <div><span>Tugas / Formatif</span><strong>40%</strong></div>
            <div><span>Tes Tengah Semester</span><strong>60%</strong></div>
            <footer>Jika siswa mendapat 80 dan 70, nilai akhirnya: (80 × 40%) + (70 × 60%) = <strong>74</strong>.</footer>
        </article>
        <article>
            <header>
                <span>Contoh ASAS</span>
                <strong>Total 100%</strong>
            </header>
            <div><span>Snapshot hasil ASTS</span><strong>30%</strong></div>
            <div><span>Tes Akhir Semester</span><strong>70%</strong></div>
            <footer>Snapshot ASTS bersifat opsional dan hanya dapat dipilih pada periode ASAS.</footer>
        </article>
    </div>

    <div class="assessment-scheme-guide__scope">
        <x-filament::icon icon="heroicon-o-funnel" class="h-5 w-5" />
        <div>
            <strong>Cara memilih cakupan</strong>
            <p>
                Kosongkan Mapel dan Kelas untuk skema default seluruh periode. Pilih Mapel untuk aturan khusus satu mapel. Pilih Mapel dan Kelas bila kelas tertentu membutuhkan aturan berbeda.
            </p>
        </div>
    </div>
</section>
