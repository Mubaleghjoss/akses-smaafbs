# Student Local-to-Server Push Design

**Tanggal:** 2026-08-20
**Status:** Disetujui
**Proyek:** `akses2-laravel`
**Target server:** `https://app.smaafbs.sch.id`

## 1. Tujuan

Menambahkan sinkronisasi satu arah yang aman dari database Laravel lokal ke database Laravel server untuk memperbarui seluruh field bernilai pada siswa aktif, tanpa menghapus siswa server dan tanpa menimpa nilai server dengan nilai kosong. Fitur tersedia sebagai tombol manual dengan preview di halaman Data Siswa serta shortcut setelah pembuatan akun hotspot.

Kebutuhan ini terverifikasi dari data nyata pada 2026-08-20:

- Lokal dan server masing-masing memiliki 162 siswa aktif.
- Semua 162 primary key siswa aktif tersedia di kedua sisi.
- Sebanyak 44 siswa memiliki perubahan lokal yang dapat dikirim.
- Perubahan meliputi 39 NISN, 37 tempat lahir, 36 tanggal lahir, dan 10 nama.
- Lokal tidak memiliki siswa aktif dengan NISN/tanggal lahir kosong; server masih memiliki 38 NISN kosong dan 36 tanggal lahir kosong.

## 2. Keputusan Produk

1. Laravel lokal bertindak sebagai sumber perubahan untuk operasi push manual.
2. Hanya siswa lokal dengan `status=aktif` yang masuk kandidat.
3. Semua field data siswa lokal yang bernilai dapat memperbarui server, kecuali field sistem yang dilarang.
4. Nilai lokal `null`, string kosong, atau whitespace tidak menimpa nilai server.
5. Push tidak menghapus siswa server.
6. Versi pertama tidak membuat siswa server baru; kandidat yang tidak dapat dicocokkan menjadi konflik.
7. Push selalu melalui preview dan konfirmasi.
8. Halaman Data Siswa menyediakan aksi utama; halaman Buat Akun Hotspot hanya menyediakan shortcut ke preview terfilter.
9. Transport menggunakan API HTTPS dengan HMAC, bukan akses MySQL publik atau shell dari browser.

## 3. Ruang Lingkup

### 3.1 Preview

Preview mengirim kandidat siswa aktif ke endpoint server tanpa mengubah database. Server mengembalikan hasil per siswa:

- `unchanged`: ditemukan dan seluruh field efektif sama.
- `update`: ditemukan dan memiliki field yang akan diperbarui.
- `conflict`: kandidat cocok secara ambigu atau identitas bertentangan.
- `not_found`: tidak dapat dicocokkan; tidak dibuat pada versi pertama.
- `invalid`: payload/field tidak memenuhi kontrak.

UI merangkum:

- jumlah kandidat;
- jumlah tidak berubah;
- jumlah akan diperbarui;
- jumlah konflik/tidak ditemukan;
- jumlah perubahan per field;
- alasan konflik tanpa menampilkan data sensitif yang tidak diperlukan.

### 3.2 Apply

Apply hanya menerima snapshot preview yang belum kedaluwarsa. Server memverifikasi checksum payload dan token preview, lalu menerapkan record berstatus `update` dalam batch transaksi.

Apply menghasilkan:

- request/run ID;
- jumlah berhasil, dilewati, konflik, dan gagal;
- hasil per siswa;
- checksum sebelum/sesudah;
- path atau ID backup server;
- waktu mulai dan selesai.

### 3.3 Shortcut Hotspot

Setelah `Buat Akun Siswa` selesai, UI menampilkan shortcut **Preview Push Data Siswa ke Server**. Shortcut membawa ID siswa yang baru diproses ke halaman Data Siswa. Pengguna tetap melihat preview dan mengonfirmasi apply; pembuatan akun MikroTik tidak otomatis menulis ke server.

## 4. Kontrak Data

### 4.1 Field Dilarang

Field berikut tidak pernah diterima dari payload sebagai nilai update:

- `id` / primary key;
- `created_at`;
- `updated_at`;
- timestamp atau metadata internal sinkronisasi seperti `spmb_synced_at`;
- field audit/server-only yang ditetapkan dalam konfigurasi sinkronisasi.

`status` digunakan untuk menyaring kandidat aktif, tetapi tidak mengubah status server pada versi pertama. Field nonaktif (`kategori_non_aktif`, `alasan_non_aktif`, `tanggal_non_aktif`) tidak dikirim untuk kandidat aktif.

### 4.2 Field Diizinkan

Daftar field diizinkan dibentuk dari irisan kolom `data_siswa` lokal dan server, dikurangi denylist sistem. Nilai hanya masuk patch jika:

- key termasuk allowlist efektif;
- nilai lokal bukan `null`;
- setelah trim, string tidak kosong;
- nilai berbeda dari nilai server setelah normalisasi tipe/tanggal/boolean.

Server tetap menjalankan validasi tipe, panjang, enum, dan format tanggal. Klien tidak menentukan SQL column secara bebas.

### 4.3 Payload Siswa

Setiap item memuat:

- `source_id` (ID lokal sebagai petunjuk, bukan instruksi mengganti PK);
- kunci identitas yang tersedia: `nipd`, `nisn`, `billing_code`, nama, tanggal lahir;
- `fields`: patch kandidat yang sudah difilter;
- `source_checksum`;
- konteks opsional: rombel dan asal aksi (`data_siswa` atau `hotspot`).

## 5. Pencocokan Identitas

Server menjalankan pencocokan berurutan dengan guard konflik:

1. Cari record dengan ID yang sama.
2. Validasi record ID melalui bukti identitas lain yang tersedia (NIPD, NISN, billing code, atau kombinasi nama/tanggal lahir).
3. Bila ID tidak aman, cari NIPD eksak.
4. Cari NISN eksak.
5. Cari billing code eksak jika nilainya stabil dan tersedia di kedua sisi.
6. Cari kombinasi nama ternormalisasi dan tanggal lahir.
7. Kandidat ganda atau bukti identitas yang bertentangan menjadi `conflict`.
8. Tanpa kecocokan menjadi `not_found`.

Nama boleh diperbarui ketika ID dan minimal satu identitas kuat lain cocok. Perbedaan nama saja tidak otomatis dianggap siswa berbeda.

## 6. Keamanan API

### 6.1 Endpoint

Endpoint internal versioned:

- `POST /api/internal/v1/student-sync/preview`
- `POST /api/internal/v1/student-sync/apply`

Keduanya hanya tersedia ketika feature flag sinkronisasi server aktif.

### 6.2 Autentikasi dan Integritas

Setiap request memiliki header:

- client ID;
- Unix timestamp;
- nonce unik;
- idempotency key;
- body SHA-256;
- signature HMAC-SHA256 atas method, path, timestamp, nonce, idempotency key, dan body hash.

Server:

- membandingkan signature secara constant-time;
- menolak timestamp di luar toleransi;
- menyimpan nonce agar tidak dapat dipakai ulang;
- rate limit per client;
- membatasi ukuran payload dan jumlah siswa per batch;
- tidak mencatat secret/signature penuh;
- hanya menerima HTTPS di production.

Secret disimpan di `.env` lokal dan server, tidak di database browser, Git, log, atau response.

### 6.3 Preview Token

Preview server menghasilkan token opaque yang mengikat:

- client ID;
- checksum payload;
- daftar target dan patch;
- waktu kedaluwarsa;
- hasil matching saat preview.

Apply wajib mengirim token tersebut dan payload checksum yang sama. Perubahan data server setelah preview menyebabkan record terkait diperiksa ulang dan dapat berubah menjadi konflik.

## 7. Backup, Audit, dan Idempotency

Sebelum apply, server menyimpan snapshot record yang akan diubah ke storage private. Backup berisi field sebelum perubahan, run ID, checksum, dan waktu. Backup tidak tersedia melalui URL publik.

Tabel audit/run menyimpan:

- UUID run;
- client ID dan user pelaksana;
- jenis `preview`/`apply`;
- status;
- idempotency key unik;
- jumlah kandidat/update/unchanged/conflict/not_found/failed;
- ringkasan field;
- payload checksum;
- backup path/identifier;
- started/finished timestamps;
- error aman tanpa secret atau data pribadi lengkap.

Idempotency key yang sama mengembalikan hasil apply pertama dan tidak menjalankan update ulang.

## 8. Model dan Komponen

Komponen target:

- `StudentServerPushPayloadBuilder`: memilih siswa aktif, membentuk allowlist, dan normalisasi payload lokal.
- `StudentServerPushClient`: request HTTPS, HMAC, timeout, retry aman untuk preview, dan idempotency apply.
- `StudentSyncSignatureVerifier`: middleware/verifier server.
- `StudentSyncMatcher`: pencocokan identitas dan deteksi konflik.
- `StudentSyncMergePolicy`: non-empty patch, denylist, normalisasi, dan validasi.
- `StudentSyncPreviewService`: menghasilkan preview/token.
- `StudentSyncApplyService`: backup, transaksi, audit, dan apply idempoten.
- `StudentSyncRun`: model audit.
- Filament Page/Action untuk preview, apply, hasil, dan riwayat.

Business logic tidak ditempatkan di controller atau class tampilan Filament.

## 9. UI Filament

### 9.1 Halaman Data Siswa

Header action **Push ke Server** membuka flow:

1. Pilih cakupan: seluruh siswa aktif atau siswa terpilih/ID dari shortcut hotspot.
2. Jalankan preview.
3. Tampilkan kartu ringkasan dan tabel perubahan.
4. Pengguna membuka konflik bila ada.
5. Tombol apply hanya aktif bila ada update valid.
6. Modal konfirmasi menyebut jumlah siswa/field dan bahwa nilai kosong tidak menghapus server.
7. Tampilkan hasil dan link riwayat run.

Aksi hanya terlihat bagi pengguna dengan hak kelola Data Siswa dan izin khusus `push_student_data_to_server`.

### 9.2 Halaman Buat Akun Hotspot

Hasil pembuatan akun menyimpan daftar ID siswa yang diproses dalam state/temporary run. Shortcut membuka URL preview Data Siswa dengan token cakupan sementara, bukan daftar ID mentah yang dapat dimanipulasi tanpa validasi.

## 10. Error Handling

- Server tidak terjangkau: preview gagal tanpa mengubah data lokal/server.
- Signature/nonce gagal: HTTP 401/409 dan audit keamanan minimal.
- Schema berbeda: field di luar irisan diabaikan dan dilaporkan; field wajib yang hilang menyebabkan invalid.
- Konflik identitas: tidak di-update otomatis.
- Apply batch gagal: transaksi batch di-rollback; item gagal tercatat.
- Request timeout setelah apply: retry dengan idempotency key yang sama mengambil hasil sebelumnya.
- Backup gagal: apply dibatalkan.
- Audit gagal dibuat: apply dibatalkan.

## 11. Pengujian TDD

### Unit

- Nilai kosong tidak menimpa server.
- Field sistem ditolak.
- Tipe tanggal/boolean dinormalisasi.
- Matching ID dengan guard identitas.
- Matching NIPD/NISN/billing/name+DOB.
- Ambiguitas menjadi konflik.
- HMAC benar/salah, timestamp kedaluwarsa, replay nonce.
- Token preview mengikat checksum.
- Idempotency apply.

### Feature/API

- Preview tidak mengubah database.
- Apply tanpa preview ditolak.
- Apply membuat backup dan audit.
- Authorization/rate limit/size limit.
- Tidak ada nilai pribadi/secret di log response error.

### Filament/Livewire

- Aksi terlihat sesuai permission.
- Preview menampilkan ringkasan.
- Konfirmasi apply.
- Shortcut hotspot membawa cakupan siswa yang tepat.

### Integrasi Nyata

- Deploy receiver server lebih dahulu.
- Preview batch nyata 162 aktif.
- Pastikan sekitar 44 kandidat update sesuai baseline.
- Apply setelah backup.
- Verifikasi server: 162 aktif, tidak ada penghapusan, NISN/tanggal lahir kosong turun sesuai hasil konflik.
- Ulangi request dengan idempotency key sama dan pastikan tidak ada update ganda.

## 12. Deployment dan Cutover

1. Bersihkan dan uji perubahan lokal terkait yang akan dideploy.
2. Push branch ke GitHub; server saat ini masih berada pada `e405919` sedangkan lokal sudah lebih maju.
3. Deploy migration, receiver API, config, dan permission ke server.
4. Isi client ID/secret di `.env` server dan lokal melalui kanal aman.
5. Jalankan migration dan clear/cache config server.
6. Uji endpoint dengan payload fixture tanpa siswa nyata.
7. Deploy sender/UI lokal.
8. Jalankan preview data nyata.
9. Apply setelah persetujuan operasional.
10. Verifikasi count/field server dan simpan laporan run.

Deployment tidak boleh menggunakan `git reset --hard` pada working tree server karena server memiliki file storage dan asset termodifikasi. Proses deploy harus melindungi storage, `.env`, file pengguna, dan perubahan runtime.

## 13. Kriteria Penerimaan

Fitur diterima jika:

1. Preview 162 siswa aktif tidak mengubah server.
2. Semua perubahan valid tampil beserta ringkasan field.
3. Konflik tidak di-update.
4. Apply tidak menghapus siswa dan tidak menimpa dengan nilai kosong.
5. Backup dan audit dibuat sebelum update.
6. Retry apply idempoten.
7. Shortcut hotspot membuka preview terfilter.
8. Seluruh tes unit/feature/Livewire lulus.
9. Batch nyata memperbarui server dan verifikasi pasca-apply lulus.
10. Tidak ada secret atau data pribadi lengkap di Git/log/error.

## 14. Di Luar Ruang Lingkup Versi Pertama

- Sinkronisasi otomatis tanpa konfirmasi.
- Penghapusan siswa server.
- Pembuatan siswa baru di server dari kandidat tidak ditemukan.
- Sinkron dua arah atau resolusi konflik otomatis.
- Push akun hotspot MikroTik ke database server; shortcut hanya menyinkronkan data siswa sumber.
