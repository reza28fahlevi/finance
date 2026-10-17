# Laporan Functional Test — Finance Shafa

| | |
|---|---|
| Tanggal eksekusi | 17 Oktober 2026 |
| Aplikasi | Finance Shafa (Laravel 13.37, PHP 8.4.11, MySQL 8.0.30) |
| Lingkungan | Windows 11, `php artisan serve`, database `finflow_db` (data demo) dan `finance_db_test` |
| **Hasil akhir** | **Semua skenario lulus** setelah 9 temuan diperbaiki (lihat bagian 6) |

## 1. Ringkasan hasil

| Lapisan uji | Alat | Cakupan | Hasil |
|---|---|---|---|
| A. Functional test HTTP otomatis | PHPUnit Feature (`tests/Feature`) | 70 test, 432 asersi | **70/70 lulus** |
| B. E2E browser nyata | Chrome headless + puppeteer-core (`tests/e2e/browser-flow.mjs`) | 22 cek interaksi UI | **22/22 lulus** |
| C. Sapuan halaman per peran | curl (`tests/e2e/smoke-pages.sh`) | 6 peran × 28 halaman = 168 request | **0 error 500**, kode akses sesuai matriks |
| D. Uji konkurensi | proses PHP paralel (`tests/e2e/concurrency.php`) | penomoran & posting ganda | **Lulus** (setelah perbaikan F-1) |
| E. Pemeriksaan visual terhadap mockup | screenshot 1440px & 390px | 13 halaman | Sesuai tema `template_mockup` (setelah perbaikan UI) |

## 2. Alur end-to-end (todo_list Fase 7)

Alur utama diuji di lapisan A (HTTP) dan B (browser):

| Langkah | Peran | Ekspektasi | A | B |
|---|---|---|:-:|:-:|
| 1. Login | semua peran | Masuk ke dashboard, label peran tampil | ✔ | ✔ |
| 2. Input Pengeluaran Kas + unggah lampiran PDF | Staff | Nomor `BKK-YYYY-MM-NNNN` dibuat otomatis, lampiran tersimpan, preview Rupiah real-time | ✔ | ✔ |
| 3. Simpan & ajukan review | Staff | Status `PENDING_REVIEW`, transaksi terkunci dari edit | ✔ | ✔ |
| 4. Inbox Pending Review | Manager | Transaksi tampil dengan proyeksi jurnal dan lampiran | ✔ | ✔ |
| 5a. Reject | Supervisor | Alasan wajib (tombol nonaktif bila < 5 karakter), status `REJECTED` | ✔ | ✔ |
| 5b. Revisi & ajukan ulang | Staff | Kembali `DRAFT`, lalu `PENDING_REVIEW` | ✔ | – |
| 5c. Approve | Manager | Jurnal otomatis terbit (`JV-…`), transaksi `JOURNALED` | ✔ | ✔ |
| 6. Jurnal manual | Akuntan | Indikator selisih real-time (Rp1 → Rp0), tombol simpan nonaktif sampai seimbang, baris dinamis | ✔ | ✔ |
| 7. Posting Batch | Akuntan | Hasil per jurnal, status `POSTED` | ✔ | ✔ |
| 8. Buku Besar per Akun | Akuntan | Saldo awal, mutasi, saldo berjalan, saldo akhir | ✔ | ✔ |
| 9. Trial Balance | Manager | "Trial Balance Seimbang" | ✔ | ✔ |
| 10. Audit Log | Auditor | `transaction.created/submitted/approved/journaled`, `journal.posted` tercatat | ✔ | ✔ |

## 3. Uji hak akses per peran (RANCANGAN.md 10.4)

`AccessControlTest` menguji 19 halaman × 6 peran (114 kombinasi). Setiap halaman harus mengembalikan **200** bagi peran yang berhak dan **403** bagi yang tidak.

| Menu | Admin | Staff | Spv | Mgr | Akuntan | Auditor |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| Dashboard | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Master Data (lihat) / (kelola) | ✔ / ✔ | ✔ / 403 | ✔ / 403 | ✔ / 403 | ✔ / 403 | ✔ / 403 |
| Transaksi (lihat) / (input) | ✔ / 403 | ✔ / ✔ | ✔ / 403 | ✔ / 403 | ✔ / 403 | ✔ / 403 |
| Approval | 403 | 403 | ✔ | ✔ | 403 | ✔ (lihat) |
| Jurnal (lihat) / (kelola, posting) | ✔ / 403 | 403 | ✔ / 403 | ✔ / 403 | ✔ / ✔ | ✔ / 403 |
| General Ledger | ✔ | 403 | ✔ | ✔ | ✔ | ✔ |
| Pengaturan: Pengguna, Pengaturan Umum | ✔ | 403 | 403 | 403 | 403 | 403 |
| Pengaturan: Audit Log | ✔ | 403 | 403 | 403 | 403 | ✔ |

Semua kombinasi sesuai. Uji tambahan yang juga lulus:
- **Endpoint tulis terlindungi**: Staff tidak bisa approve, posting, atau kelola master. Auditor tidak bisa reject, posting batch, atau ubah pengaturan. Manager tidak bisa posting atau tutup periode. Akuntan tidak bisa membuka kembali periode.
- **Staff tidak bisa mengedit atau mengajukan transaksi milik staff lain.**
- **Sidebar** hanya menampilkan menu yang boleh diakses peran tersebut.

## 4. Skenario fungsional lain (lapisan A)

| Area | Skenario | Hasil |
|---|---|:-:|
| Autentikasi | Login gagal dengan password salah; user nonaktif tidak bisa login; sesi user yang dinonaktifkan langsung diputus; rate limit login (5 percobaan); halaman registrasi/lupa password publik tidak tersedia; logout | ✔ |
| Profil | Ubah nama/email; tidak bisa mengubah peran sendiri; tidak bisa menghapus akun sendiri | ✔ |
| Transaksi | Validasi form (tanggal, deskripsi, nominal > 0, akun berbeda, akun postable); periode CLOSED ditolak; submit tanpa lampiran wajib tetap DRAFT dengan pesan; batalkan draft; filter status/pencarian; ekspor CSV | ✔ |
| Lampiran | Tolak `.exe` dan berkas > 5 MB; terima JPG/PDF; disimpan di luar web root; unduh hanya via signed URL (tanpa signature / signature dimanipulasi → 403; kedaluwarsa setelah 30 menit → 403; tamu → login); header `nosniff`; hapus hanya selama Draft/Rejected | ✔ |
| Jurnal | Jurnal tidak seimbang ditolak; simpan & posting; edit/hapus hanya DRAFT; jurnal POSTED tidak bisa diedit/dihapus; jurnal pembalik (alasan wajib); posting batch & posting ulang gagal (idempotent); filter daftar; perintah terjadwal `finance:post-batch` | ✔ |
| Master data | Tambah akun (kode unik, parent harus akun induk); akun terpakai tidak bisa dihapus, hanya dinonaktifkan; kode akun yang sudah dijurnal dikunci; buat/tutup/buka periode (format & duplikat divalidasi); tutup periode ditolak bila ada item pending; ubah template jurnal otomatis lalu dipakai sebagai default form | ✔ |
| Pengaturan | Ubah nama perusahaan, format nomor (langsung dipakai untuk transaksi/jurnal baru), ambang lampiran/approval; format tanpa `{SEQ}`/`{TYPE}` ditolak; kelola pengguna (buat, ubah peran, nonaktifkan, reset password tercatat di audit); admin tidak bisa menonaktifkan dirinya sendiri; password tidak tercatat di audit log | ✔ |
| Auto-post | Setting "Posting Otomatis saat Approve" langsung memposting jurnal ke GL | ✔ |

## 5. Uji konkurensi (lapisan D)

Uji ini dijalankan terhadap MySQL sungguhan dengan beberapa proses PHP paralel, karena kondisi balapan (race condition) tidak bisa diuji di dalam satu transaksi test.

| Uji | Skenario | Hasil awal | Hasil akhir |
|---|---|---|---|
| Penomoran atomik | 8 proses × 40 nomor dengan prefix yang sama dan baris sequence yang belum ada | ✘ 4–6 proses gagal **deadlock** (1213) saat membuat baris sequence bersamaan. Tidak ada nomor duplikat | ✔ **320 nomor, 320 unik, 0 gagal**, `last_number` = 320 |
| Posting ganda | 5 proses memposting jurnal DRAFT yang sama bersamaan | ✔ 1 sukses, 4 ditolak "sudah POSTED", baris GL = baris jurnal (tidak dobel), saldo bertambah sekali | ✔ sama |

## 6. Temuan dan perbaikan

| # | Temuan | Ditemukan lewat | Dampak | Perbaikan | Status |
|---|---|---|---|---|:-:|
| F-1 | **Deadlock penomoran** saat beberapa user membuat nomor pertama untuk prefix baru (mis. awal bulan) secara bersamaan. Lock S dari `insertOrIgnore` naik menjadi X pada `SELECT … FOR UPDATE` | Uji konkurensi | User mendapat error 500 dan harus mengulang. Tidak ada duplikat | `SequenceService` dijalankan di koneksi DB terpisah (`sequence`) sehingga komit segera, mencoba `SELECT … FOR UPDATE` lebih dulu, dan mengulang otomatis hingga 5× saat deadlock. Konsekuensinya, nomor bisa melompat bila transaksi bisnis gagal, tetapi tetap unik | ✔ |
| F-2 | **Baris jurnal kosong memblokir submit**: `<select required>` di baris tambahan yang kosong membuat validasi HTML5 menolak form, padahal server mengabaikan baris kosong | E2E browser | Akuntan tidak bisa menyimpan jurnal bila menambah baris lebih | Atribut `required` hanya aktif bila baris berisi nilai debit/kredit | ✔ |
| F-3 | Input form berwarna putih (tidak sesuai tema), karena class `.form-input` bentrok dengan class bawaan plugin `@tailwindcss/forms` | Pemeriksaan visual | Tampilan login & form | Plugin diset `strategy: 'base'` | ✔ |
| F-4 | Konten utama tertimpa sidebar dan panel kiri login hilang, karena aset CSS di-build sebelum view selesai | Pemeriksaan visual | Tata letak rusak | Build ulang aset. Docker selalu build aset di stage `assets` | ✔ |
| F-5 | Logo hanya tampil titik di halaman login (gradient SVG direferensikan dari elemen yang tersembunyi) | Pemeriksaan visual | Kosmetik | Logo memakai warna solid | ✔ |
| F-6 | Teks submenu aktif nyaris tidak terbaca (urutan CSS `subnav-link` menimpa warna aktif) | Pemeriksaan visual | Keterbacaan navigasi | Kelas aktif didefinisikan setelah kelas dasar | ✔ |
| F-7 | Tabel Semua Transaksi melebar sehingga kolom Nominal/Status terpotong, dan input tanggal di filter terlalu sempit | Pemeriksaan visual | Data penting tersembunyi di 1440px | Lebar kolom dibatasi, filter memakai layout fleksibel | ✔ |
| F-8 | Judul halaman di mobile memakai ukuran teks dasar (token `headline-lg-mobile` belum didefinisikan) | Pemeriksaan visual 390px | Hirarki visual mobile | Token ukuran mobile ditambahkan sesuai DESIGN.md | ✔ |
| F-9 | Ukuran lampiran kecil tampil "0 KB" | Pemeriksaan visual | Informasi menyesatkan | Ukuran < 1 KB ditampilkan dalam byte | ✔ |

Setelah semua perbaikan, seluruh lapisan uji (A–E) dijalankan ulang dan lulus.

## 7. Kriteria Penerimaan (RANCANGAN.md bagian 13)

| Kriteria | Bukti | Status |
|---|---|:-:|
| COA dan periode dapat dikonfigurasi | `MasterDataAndSettingsTest`, halaman Chart of Accounts / Periode | ✔ |
| Transaksi dengan lampiran dapat dibuat dan disubmit | `TransactionFlowTest::full_flow…`, E2E langkah 2–3 | ✔ |
| Approve/Reject berjalan dengan alasan dan audit log | `reject_and_revise_flow`, `ApprovalServiceTest`, E2E langkah 5 | ✔ |
| Jurnal tidak seimbang ditolak sistem | `unbalanced_manual_journal_is_rejected`, CHECK constraint, E2E langkah 6 | ✔ |
| Posting memperbarui saldo akun per periode dan Buku Besar secara akurat | `PostingServiceTest`, E2E langkah 7–8 | ✔ |
| Jurnal terposting tidak dapat diubah; reversal tersedia | `posted_journal_is_immutable`, `reversal_via_http` | ✔ |
| Total debit = total kredit pada ringkasan saldo seluruh akun | `trial_balance_is_balanced_after_postings`, E2E langkah 9 | ✔ |

## 8. Keterbatasan & yang belum diuji
- **Image Docker belum di-build/dijalankan**, karena Docker tidak terpasang di mesin uji. Konfigurasi sudah diperiksa secara statis (sintaks YAML compose dan shell entrypoint). Langkah yang dijalankan entrypoint (`config:cache`, `route:cache`, `view:cache`) sudah diverifikasi di lokal. Build pertama perlu dicoba di server target.
- Pemindaian antivirus (ClamAV) belum diuji dalam kondisi aktif.
- Pengujian browser hanya memakai Chrome (desktop 1440px dan mobile 390px). Safari/Firefox belum diuji.
- Uji beban/performa tidak termasuk ruang lingkup (volume transaksi kecil sesuai RANCANGAN.md 11.1).

## 9. Cara menjalankan ulang
```bash
# A. Functional test HTTP (database finance_db_test)
php artisan test --testsuite=Feature

# Siapkan server dev dengan data demo untuk B–D
php artisan migrate:fresh --seed
php artisan serve --port=8123

# B. E2E browser (butuh Chrome; set CHROME_PATH bila lokasinya berbeda)
npm run test:e2e

# C. Sapuan halaman per peran
bash tests/e2e/smoke-pages.sh http://127.0.0.1:8123

# D. Konkurensi
php tests/e2e/concurrency.php sequence
php tests/e2e/concurrency.php posting
```
Uji B dan D mengubah data demo. Jalankan `php artisan migrate:fresh --seed` lagi bila ingin kembali ke kondisi awal.
