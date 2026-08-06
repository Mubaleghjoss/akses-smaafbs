# Jenis Pertanyaan dan Penilaian

Modul mempunyai tepat tiga tipe:

| `question_type` | Tampilan murid | Penilaian |
| --- | --- | --- |
| `essay` | Textarea, opsional dikte | Kunci jawaban atau guru |
| `true_false` | Tabel/kartu Benar dan Salah | Otomatis per pernyataan |
| `matching` | Garis berpanah pada layar lebar, dropdown berpanah pada HP/no-JS | Otomatis per pasangan |

Semua record lama memakai default `essay`.

## Konfigurasi

Benar/Salah:

```json
{
  "version": 1,
  "items": [
    {"id": "uuid-stabil", "statement": "Isi pernyataan", "correct": true}
  ]
}
```

Menjodohkan:

```json
{
  "version": 1,
  "left": [
    {"id": "left-uuid", "label": "Item kiri", "correct_target_id": "right-uuid"}
  ],
  "right": [
    {"id": "right-uuid", "label": "Pilihan kanan"}
  ]
}
```

ID tidak boleh berubah setelah disimpan. Menjodohkan bersifat satu-ke-satu: jumlah sisi kiri dan kanan sama dan satu target hanya menjadi kunci satu item.

## Editor admin

- Benar/Salah diisi sebagai tabel `Kolom A · Pernyataan | Kolom B · Kunci Jawaban`.
- Menjodohkan diisi sebagai tabel `Kolom A · Soal | Kolom B · Jawaban`.
- Setiap baris Menjodohkan merupakan satu pasangan kunci. Lapisan form mengubah baris tersebut kembali menjadi struktur `left` dan `right` dengan ID stabil saat disimpan.
- Record lama dengan struktur `left`/`right` otomatis dipetakan ke tabel pasangan saat diedit.
- Filament menampilkan tabel pada area lebar dan kartu berlabel pada area sempit agar form admin tidak menyebabkan overflow halaman.

## Garis pasangan di frontend

- Pada layar minimal 768 px, murid memilih item kiri lalu jawaban kanan. SVG lokal menggambar garis berpanah dan tidak membuat request server.
- Pilihan kanan ditampilkan dengan pergeseran urutan yang tetap satu posisi agar kunci tidak tampak sejajar, bukan pengacakan acak.
- Pada HP, tanpa JavaScript, atau ketika validasi browser perlu difokuskan, sistem memakai dropdown berpanah yang tetap mengirim payload yang sama.
- Garis hanya representasi visual. Nilai sumber tetap berada pada field `answers[question-id][pairs][left-id]` dan selalu divalidasi server.

## Payload jawaban

- Esai memakai `answer_text`; `answer_payload` bernilai `null`.
- Benar/Salah memakai `{"version":1,"items":{"item-id":true}}`.
- Menjodohkan memakai `{"version":1,"pairs":{"left-id":"right-id"}}`.
- Server selalu membuat `answer_text` ringkas untuk tampilan admin; server tidak mempercayai ringkasan dari browser.

Jawaban wajib harus lengkap. Pertanyaan opsional boleh kosong seluruhnya, tetapi jawaban objektif sebagian ditolak.

## Skor

- Setiap pernyataan atau pasangan bernilai satu poin.
- `score_earned` berisi poin benar dan `score_possible` berisi poin maksimum.
- `is_correct=true` hanya jika semua butir benar.
- `grading_source` dapat berupa `automatic`, `answer_key`, `manual`, atau `legacy`.
- Edit murid menghitung ulang objektif dan membatalkan koreksi manual lama.
- Guru dapat mengoreksi poin objektif dari 0 sampai poin maksimum dengan catatan.

Deteksi plagiasi, kunci teks, batas karakter, dan dikte hanya berlaku untuk Esai.

Jika Kunci Jawaban Esai diisi, batas minimal efektif tidak boleh melebihi panjang kunci. Contoh batas minimal 20 dengan kunci `40 hari` menjadi 7 karakter. Batas yang sudah lebih kecil tetap dipertahankan, sedangkan batas maksimal dinaikkan bila lebih pendek daripada kunci. Jawaban yang sama dengan kunci dinilai benar otomatis dan tidak dimasukkan ke indikasi kemiripan.

## Dikte

Dikte memakai `SpeechRecognition`/`webkitSpeechRecognition`, bahasa `id-ID`, maksimal 45 detik per sesi. Hanya transkrip final yang dimasukkan ke textarea. Aplikasi tidak memiliki endpoint upload audio dan tidak menyimpan rekaman. Jika browser tidak mendukung, izin ditolak, atau layanan suara gagal, textarea tetap berfungsi.

Teks wajib di UI:

> Suara diproses oleh layanan pengenal suara browser dan tidak disimpan oleh aplikasi. Periksa kembali teks sebelum mengirim.
