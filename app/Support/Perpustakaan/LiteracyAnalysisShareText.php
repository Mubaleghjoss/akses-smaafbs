<?php

namespace App\Support\Perpustakaan;

use Illuminate\Support\Carbon;

/**
 * Menyusun teks siap-tempel WhatsApp dari data yang sudah tampil di halaman
 * Analisis Literasi.
 *
 * Berbeda dengan LiteracyMonthlyShareText yang menghitung ulang rekap bulanan
 * dari database, kelas ini memakai array $base dan $analytics yang sedang
 * dirender. Dengan begitu teks yang disalin persis sama dengan angka yang
 * dilihat pengguna, termasuk saat filter materi atau kelas dipakai.
 */
final class LiteracyAnalysisShareText
{
    /**
     * Bagian yang tersedia beserta labelnya, dipakai untuk tab pada modal.
     *
     * @return array<string, string>
     */
    public static function sectionLabels(): array
    {
        return [
            'ringkasan' => 'Ringkasan',
            'partisipasi' => 'Partisipasi Kelas',
            'timeline' => 'Timeline Pengisian',
            'belum' => 'Belum Mengisi',
            'dispensasi' => 'Dispensasi',
            'siswa' => 'Analisis Siswa',
            'peringkat' => 'Peringkat Benar',
            'plagiasi' => 'Kemiripan',
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $analytics
     * @return array<string, string>
     */
    public static function sections(
        array $base,
        array $analytics,
        string $periodeLabel,
        string $lingkupLabel,
        ?string $kelasLabel = null,
    ): array {
        $header = self::header($periodeLabel, $lingkupLabel, $kelasLabel);

        return [
            'ringkasan' => self::join($header, self::ringkasan($base, $analytics)),
            'partisipasi' => self::join($header, self::partisipasi($base)),
            'timeline' => self::join($header, self::timeline($analytics)),
            'belum' => self::join($header, self::belumMengisi($base)),
            'dispensasi' => self::join($header, self::dispensasi($base)),
            'siswa' => self::join($header, self::analisisSiswa($analytics)),
            'peringkat' => self::join($header, self::peringkatBenar($analytics)),
            'plagiasi' => self::join($header, self::plagiasi($analytics)),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function header(string $periodeLabel, string $lingkupLabel, ?string $kelasLabel): array
    {
        $lines = [
            '*ANALISIS LITERASI NUMERASI*',
            '*Periode:* '.$periodeLabel,
            '*Lingkup:* '.self::plain($lingkupLabel),
        ];

        if (filled($kelasLabel)) {
            $lines[] = '*Kelas:* '.self::plain($kelasLabel);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $analytics
     * @return array<int, string>
     */
    private static function ringkasan(array $base, array $analytics): array
    {
        $summary = $analytics['grading_summary'] ?? [];

        return [
            '*RINGKASAN RESPONDEN*',
            '- Total kesempatan mengisi: '.self::number($base['active_total'] ?? 0),
            '- Tidak dihitung karena dispensasi: '.self::number($base['excluded_total'] ?? 0),
            '- Target pengisian setelah dispensasi: '.self::number($base['respondent_base'] ?? 0),
            '- Kesempatan pengisian yang sudah terisi: '.self::number($base['completed_total'] ?? 0)
                .' dari '.self::number($base['respondent_base'] ?? 0),
            '- Tingkat partisipasi: '.self::percent($base['participation_percentage'] ?? null),
            '- Kesempatan pengisian yang belum terisi: '.self::number($base['missing_total'] ?? 0),
            '- Jawaban di sampah: '.self::number($base['trashed_total'] ?? 0),
            '- Jumlah materi: '.self::number($base['material_count'] ?? 0),
            '',
            '*PENILAIAN*',
            '- Jawaban yang sudah diperiksa: '.self::number($summary['graded_answers'] ?? 0)
                .' dari '.self::number($summary['total_answers'] ?? 0),
            '- Jawaban benar: '.self::number($summary['correct_answers'] ?? 0),
            '- Tingkat jawaban benar: '.self::percent($summary['accuracy'] ?? null)
                .' ('.self::number($summary['correct_answers'] ?? 0).' benar dari '
                .self::number($summary['graded_answers'] ?? 0).' jawaban yang diperiksa)',
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<int, string>
     */
    private static function partisipasi(array $base): array
    {
        $rows = $base['classes'] ?? [];
        $lines = ['*PARTISIPASI PER KELAS*'];

        if ($rows === []) {
            $lines[] = 'Tidak ada data pada rentang dan filter ini.';

            return $lines;
        }

        foreach ($rows as $index => $row) {
            $lines[] = ($index + 1).'. '.self::plain($row['class'] ?? '-')
                .' — '.self::number($row['completed_total'] ?? 0).' dari '.self::number($row['respondent_base'] ?? 0)
                .' kesempatan pengisian sudah terisi ('.self::percent($row['participation_percentage'] ?? null).')'
                .', belum terisi '.self::number($row['missing_total'] ?? 0)
                .(($row['excluded_total'] ?? 0) > 0 ? ', tidak dihitung karena dispensasi '.self::number($row['excluded_total']) : '');
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $analytics
     * @return array<int, string>
     */
    private static function timeline(array $analytics): array
    {
        $rows = $analytics['class_submission_timeline'] ?? [];
        $lines = ['*TIMELINE PENGISIAN PER KELAS*'];

        if ($rows === []) {
            $lines[] = 'Belum ada pengisian pada rentang ini.';

            return $lines;
        }

        foreach ($rows as $index => $row) {
            $mulai = $row['first_at'] ?? null;
            $akhir = $row['last_at'] ?? null;

            $lines[] = ($index + 1).'. *'.self::plain($row['class'] ?? '-').'*';
            $lines[] = '   Kesempatan pengisian yang sudah terisi: '.self::number($row['total'] ?? 0)
                .' dari '.self::number($row['respondent_base'] ?? 0)
                .' ('.self::percent($row['percentage'] ?? null).')';
            $lines[] = '   Perhitungannya: '.self::number($row['active_total'] ?? 0).' kesempatan mengisi'
                .' - '.self::number($row['excluded_total'] ?? 0).' dispensasi'
                .' = '.self::number($row['respondent_base'] ?? 0).' target pengisian'
                .' dari '.self::number($row['unique_students'] ?? 0).' siswa';
            $lines[] = '   Mulai: '.($mulai ? $mulai->translatedFormat('d M Y H:i') : '-');
            $lines[] = '   Terakhir: '.($akhir ? $akhir->translatedFormat('d M Y H:i') : '-');
            $lines[] = '   Hari yang memiliki pengisian: '.self::number($row['active_days'] ?? 0)
                .' dari '.self::number($row['span_days'] ?? 0).' hari';

            if (filled($row['busiest_day'] ?? null)) {
                $lines[] = '   Hari tersibuk: '
                    .Carbon::parse($row['busiest_day'])->translatedFormat('d M Y')
                    .' ('.self::number($row['busiest_day_total'] ?? 0).' pengisian)';
            }

            $lines = array_merge($lines, self::rincianHarian($row));
        }

        return $lines;
    }

    /**
     * Rincian per hari satu kelas: jumlah pengisi, nama-namanya, dan siapa yang
     * belum. Dipakai oleh bagian timeline dan ranking supaya isi salinan sama
     * dengan drill-down di layar.
     *
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private static function rincianHarian(array $row, string $indent = '   '): array
    {
        $lines = [];
        $days = $row['days'] ?? [];

        if ($days !== []) {
            $lines[] = $indent.'Rincian per hari:';

            foreach ($days as $day) {
                $lines[] = $indent.'- '.Carbon::parse($day['date'])->translatedFormat('d M Y')
                    .': '.self::number($day['total'] ?? 0).' mengisi';

                foreach ($day['students'] ?? [] as $student) {
                    $lines[] = $indent.'  . '.self::plain($student['name'] ?? '-')
                        .' ('.self::plain($student['time'] ?? '-').' - '.self::plain($student['material_title'] ?? '-').')';
                }
            }
        }

        $belum = $row['missing_students'] ?? [];

        if ($belum !== []) {
            $lines[] = $indent.'Belum mengisi ('.self::number($row['missing_total'] ?? count($belum)).'):';

            foreach (array_values($belum) as $index => $student) {
                $lines[] = $indent.'  '.($index + 1).'. '.self::plain($student['name'] ?? '-')
                    .' ('.self::plain($student['material_title'] ?? '-').')';
            }
        }

        $dispensasi = $row['excluded_students'] ?? [];

        if ($dispensasi !== []) {
            $lines[] = $indent.'Tidak dihitung karena dispensasi ('.self::number($row['excluded_total'] ?? count($dispensasi)).'):';

            foreach (array_values($dispensasi) as $index => $student) {
                $lines[] = $indent.'  '.($index + 1).'. '.self::plain($student['name'] ?? '-')
                    .' - '.self::plain($student['reason_label'] ?? '-')
                    .' ('.self::plain($student['material_title'] ?? '-').')';
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<int, string>
     */
    private static function belumMengisi(array $base): array
    {
        $rows = collect($base['classes'] ?? [])
            ->filter(fn (array $row): bool => ($row['missing_total'] ?? 0) > 0)
            ->values();
        $lines = ['*SISWA BELUM MENGISI*'];

        if ($rows->isEmpty()) {
            $lines[] = 'Semua kesempatan pengisian sudah terisi.';

            return $lines;
        }

        foreach ($rows as $row) {
            $lines[] = '';
            $lines[] = '*'.self::plain($row['class'] ?? '-').'* — '.self::number($row['missing_total'] ?? 0).' belum mengisi';

            foreach (array_values($row['missing_students'] ?? []) as $index => $student) {
                $lines[] = ($index + 1).'. '.self::plain($student['name'] ?? '-')
                    .' ('.self::plain($student['material_title'] ?? '-').')';
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<int, string>
     */
    private static function dispensasi(array $base): array
    {
        $lines = ['*SISWA YANG TIDAK DIHITUNG KARENA DISPENSASI*'];

        if (($base['excluded_total'] ?? 0) < 1) {
            $lines[] = 'Belum ada dispensasi pada rentang dan filter ini.';

            return $lines;
        }

        $lines[] = '- Total tidak dihitung: '.self::number($base['excluded_total']);

        foreach ($base['classes'] ?? [] as $row) {
            if (($row['excluded_total'] ?? 0) < 1) {
                continue;
            }

            $lines[] = '';
            $lines[] = '*'.self::plain($row['class'] ?? '-').'* — '.self::number($row['excluded_total']).' tidak dihitung';

            foreach (array_values($row['excluded_students'] ?? []) as $index => $student) {
                $lines[] = ($index + 1).'. '.self::plain($student['name'] ?? '-')
                    .' — '.self::plain($student['reason_label'] ?? '-')
                    .' ('.self::plain($student['material_title'] ?? '-').')'
                    .(filled($student['note'] ?? null) ? ' — '.self::plain($student['note']) : '');
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $analytics
     * @return array<int, string>
     */
    private static function analisisSiswa(array $analytics): array
    {
        $lines = ['*ANALISIS SISWA*', '', '*SISWA TERBAIK PER KELAS*'];
        $perKelas = $analytics['student_correct_ranking_by_class'] ?? [];

        if ($perKelas === []) {
            $lines[] = 'Belum ada jawaban yang diperiksa.';
        } else {
            foreach (collect($perKelas)->sortKeys(SORT_NATURAL) as $kelas => $rows) {
                $lines[] = '';
                $lines[] = '*'.self::plain($kelas).'*';
                foreach (array_values($rows) as $index => $row) {
                    $lines[] = ($index + 1).'. '.self::plain($row['name'] ?? '-')
                        .' — '.self::number($row['correct_answers'] ?? 0).' jawaban benar dari '
                        .self::number($row['graded_answers'] ?? 0).' yang diperiksa'
                        .' ('.self::percent($row['accuracy'] ?? null).')';
                }
            }
        }

        $lines[] = '';
        $lines[] = '*SISWA DENGAN JAWABAN SALAH TERBANYAK*';
        $salah = $analytics['student_wrong_ranking'] ?? [];

        if ($salah === []) {
            $lines[] = 'Belum ada jawaban salah.';
        } else {
            foreach ($salah as $index => $row) {
                $lines[] = ($index + 1).'. '.self::plain($row['name'] ?? '-')
                    .' — '.self::plain($row['class'] ?? '-')
                    .' — '.self::number($row['wrong_answers'] ?? 0).' salah';
            }
        }

        $seringKosong = $analytics['frequent_missing_students'] ?? [];
        if ($seringKosong !== []) {
            $lines[] = '';
            $lines[] = '*SISWA YANG SERING TIDAK MENGISI*';
            foreach ($seringKosong as $index => $row) {
                $lines[] = ($index + 1).'. '.self::plain($row['name'] ?? '-')
                    .' — '.self::plain($row['class'] ?? '-')
                    .' — '.self::number($row['missing_total'] ?? 0).' kesempatan pengisian belum terisi';
                if (($row['materials'] ?? []) !== []) {
                    $lines[] = '   Materi: '.self::plain(implode(', ', $row['materials']));
                }
            }
        }

        return $lines;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  bool  $withDaily  sertakan rincian harian dan daftar belum mengisi
     * @return array<int, string>
     */
    private static function rasioRows(array $rows, bool $withDaily = false): array
    {
        if ($rows === []) {
            return ['Belum ada data kelas.'];
        }

        $lines = [];

        foreach ($rows as $index => $row) {
            $lines[] = ($index + 1).'. '.self::plain($row['class'] ?? '-')
                .' — '.($row['ratio'] ?? '-')
                .' ('.self::percent($row['percentage'] ?? null).')'
                .(array_key_exists('missing_total', $row) ? ', belum '.self::number($row['missing_total']) : '');

            if (! $withDaily) {
                continue;
            }

            $lines[] = '   Perhitungannya: '.self::number($row['active_total'] ?? 0).' kesempatan mengisi'
                .' - '.self::number($row['excluded_total'] ?? 0).' dispensasi'
                .' = '.self::number($row['respondent_base'] ?? 0).' target pengisian'
                .' ('.self::number($row['unique_students'] ?? 0).' siswa x '
                .self::number($row['material_count'] ?? 0).' materi)';

            $lines = array_merge($lines, self::rincianHarian($row));
        }

        return $lines;
    }

    /**
     * Peringkat kelas menurut jawaban benar, lengkap dengan catatan bahwa
     * urutannya masih dapat berubah selama ada jawaban yang belum dinilai.
     *
     * @param  array<string, mixed>  $analytics
     * @return array<int, string>
     */
    private static function peringkatBenar(array $analytics): array
    {
        $rows = $analytics['class_correct_ranking_full'] ?? [];
        $lines = ['*PERINGKAT KELAS: JAWABAN BENAR TERBANYAK*'];

        if ($rows === []) {
            $lines[] = 'Belum ada jawaban pada lingkup ini.';

            return $lines;
        }

        $tertunda = (int) collect($rows)->sum('pending_answers');

        if ($tertunda > 0) {
            $lines[] = '(Belum final: '.self::number($tertunda).' jawaban masih menunggu penilaian,'
                .' urutan dapat berubah setelah penilaiannya selesai)';
        }

        foreach ($rows as $row) {
            $lines[] = ($row['rank'] ?? '-').'. '.self::plain($row['class'] ?? '-')
                .' — '.self::number($row['correct_answers'] ?? 0).' jawaban benar'
                .' dari '.self::number($row['graded_answers'] ?? 0).' yang diperiksa'
                .' ('.self::percent($row['accuracy'] ?? null).')';

            if (($row['pending_answers'] ?? 0) > 0) {
                $lines[] = '   Belum diperiksa: '.self::number($row['pending_answers'])
                    .' jawaban dari '.self::number($row['pending_students'] ?? 0).' siswa'
                    .' — potensi benar sampai '.self::number($row['potential_correct'] ?? 0);

                foreach ($row['pending_materials'] ?? [] as $item) {
                    $lines[] = '   - '.self::plain($item['material_title'] ?? '-')
                        .': '.self::number($item['pending_answers'] ?? 0).' jawaban';
                }
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $analytics
     * @return array<int, string>
     */
    private static function plagiasi(array $analytics): array
    {
        $summary = $analytics['grading_summary'] ?? [];
        $lines = [
            '*INDIKASI JAWABAN MIRIP*',
            '- Kemiripan yang sudah dikonfirmasi: '.self::number($summary['confirmed_plagiarism'] ?? 0),
            '',
            '*PER KELAS*',
        ];

        $kelas = $analytics['plagiarism_class_ranking'] ?? [];

        if ($kelas === []) {
            $lines[] = 'Tidak ada indikasi kemiripan.';
        } else {
            foreach ($kelas as $index => $row) {
                $lines[] = ($index + 1).'. '.self::plain($row['class'] ?? '-')
                    .' — '.self::number($row['total'] ?? 0).' indikasi';
            }
        }

        $lines[] = '';
        $lines[] = '*PER SISWA*';
        $siswa = $analytics['plagiarism_student_ranking'] ?? [];

        if ($siswa === []) {
            $lines[] = 'Tidak ada indikasi kemiripan.';

            return $lines;
        }

        foreach ($siswa as $index => $row) {
            $lines[] = ($index + 1).'. '.self::plain($row['name'] ?? '-')
                .' — '.self::plain($row['class'] ?? '-')
                .' — '.self::number($row['total'] ?? 0).' indikasi';
        }

        return $lines;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string>  $body
     */
    private static function join(array $header, array $body): string
    {
        return trim(implode("\n", array_merge($header, [''], $body)));
    }

    private static function number(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    private static function percent(mixed $value): string
    {
        return $value === null ? '-' : number_format((float) $value, 1, ',', '.').'%';
    }

    private static function plain(mixed $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim(strip_tags((string) $value)));
        $normalized = str_replace(['*', '_', '~', '`'], '', (string) $normalized);

        return $normalized !== '' ? $normalized : '-';
    }
}
