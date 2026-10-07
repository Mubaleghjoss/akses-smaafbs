<?php

namespace Tests\Feature;

use App\Support\Assessment\AssessmentNumberFormatter;
use Tests\TestCase;

class AssessmentNumberFormatterTest extends TestCase
{
    public function test_score_biasa_memakai_tanda_hubung_untuk_kosong(): void
    {
        // Perilaku lama TIDAK berubah — dipakai di luar dokumen rapor.
        $this->assertSame('-', AssessmentNumberFormatter::score(null));
        $this->assertSame('-', AssessmentNumberFormatter::score(''));
        $this->assertSame('88', AssessmentNumberFormatter::score(88));
        $this->assertSame('88.5', AssessmentNumberFormatter::score(88.5));
    }

    public function test_score_rapor_menulis_belum_diisi_untuk_kosong(): void
    {
        $this->assertSame('(belum diisi)', AssessmentNumberFormatter::scoreRapor(null));
        $this->assertSame('(belum diisi)', AssessmentNumberFormatter::scoreRapor(''));
        $this->assertSame(
            AssessmentNumberFormatter::BELUM_DIISI,
            AssessmentNumberFormatter::scoreRapor(null),
        );
    }

    public function test_nilai_nol_tetap_tercetak_nol_bukan_belum_diisi(): void
    {
        // Inti pembedaan: siswa yang benar-benar mendapat 0 tidak boleh terbaca
        // sebagai belum dinilai.
        $this->assertSame('0', AssessmentNumberFormatter::scoreRapor(0));
        $this->assertSame('0', AssessmentNumberFormatter::scoreRapor('0'));
        $this->assertSame('0', AssessmentNumberFormatter::scoreRapor(0.0));
    }

    public function test_rapor_rounds_scores_to_whole_numbers_and_preserves_non_numeric_values(): void
    {
        $this->assertSame('75', AssessmentNumberFormatter::scoreRapor(75.00));
        $this->assertSame('94', AssessmentNumberFormatter::scoreRapor(93.50));
        $this->assertSame('93', AssessmentNumberFormatter::scoreRapor(93.49));
        $this->assertSame('90', AssessmentNumberFormatter::scoreRapor(90.33));

        // Teks bukan angka diteruskan apa adanya (mis. predikat manual).
        $this->assertSame('Tuntas', AssessmentNumberFormatter::scoreRapor('Tuntas'));
    }
}
