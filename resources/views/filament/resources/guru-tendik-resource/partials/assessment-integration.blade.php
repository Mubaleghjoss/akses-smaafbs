<section class="guru-assessment-integration" aria-labelledby="guru-assessment-integration-title">
    <div class="guru-assessment-integration__head">
        <span class="guru-assessment-integration__icon">
            <x-filament::icon icon="heroicon-o-academic-cap" class="h-6 w-6" />
        </span>
        <div>
            <span class="guru-assessment-integration__eyebrow">Terintegrasi dengan Penilaian</span>
            <h2 id="guru-assessment-integration-title">Mapel, kelas mengajar, dan wali kelas</h2>
            <p>
                Data pada bagian ini menjadi sumber resmi ASTS dan ASAS ketika periode dibuka. Perubahan tidak mengubah snapshot periode yang sudah dibuka.
            </p>
        </div>
    </div>

    <div class="guru-assessment-integration__stats">
        <article>
            <span class="guru-assessment-integration__stat-icon is-teaching">
                <x-filament::icon icon="heroicon-o-book-open" class="h-5 w-5" />
            </span>
            <div>
                <strong>{{ number_format($teachingCount, 0, ',', '.') }}</strong>
                <span>Mapel dan kelas aktif</span>
            </div>
        </article>
        <article>
            <span class="guru-assessment-integration__stat-icon is-homeroom">
                <x-filament::icon icon="heroicon-o-user-group" class="h-5 w-5" />
            </span>
            <div>
                <strong>{{ number_format($homeroomCount, 0, ',', '.') }}</strong>
                <span>Penugasan wali kelas aktif</span>
            </div>
        </article>
        <article class="guru-assessment-integration__instruction">
            <x-filament::icon icon="heroicon-o-cursor-arrow-rays" class="h-5 w-5" />
            <p>
                @if ($guru)
                    Gunakan tab <strong>Penilaian ASTS–ASAS</strong> di bawah form untuk menambah atau mengubah penugasan.
                @else
                    Simpan identitas guru terlebih dahulu. Pengaturan Penilaian tersedia setelah data guru dibuat.
                @endif
            </p>
        </article>
    </div>
</section>
