# Laporan Unit Test — Finance Shafa

| | |
|---|---|
| Tanggal eksekusi | 17 Oktober 2026 |
| Aplikasi | Finance Shafa (Laravel 13.37, PHP 8.4.11) |
| Database uji | MySQL 8.0.30 — `finance_db_test` (InnoDB, `RefreshDatabase`) |
| Framework uji | PHPUnit 12.5.40 |
| Perintah | `php artisan test --testsuite=Unit` |
| **Hasil** | **120 test, 268 asersi — 120 lulus, 0 gagal** (±14 detik) |

## 1. Ringkasan

Unit test menguji lapisan aturan bisnis (Service, Enum, Model, constraint database) secara langsung, tanpa lewat HTTP. Fokusnya mengikuti todo_list Fase 7: validasi debit = kredit, `PostingService` (saldo, idempotent, rollback), transisi status, nomor sequence, Maker ≠ Checker, dan periode CLOSED.

| Kelas test | Jumlah | Area yang diuji |
|---|:-:|---|
| `MoneyTest` | 10 | Aritmetika uang presisi (bcmath), normalisasi input, format Rupiah |
| `EnumRulesTest` | 36 | Diagram state transaksi (13 transisi), matriks hak akses peran (20 kasus), saldo normal per tipe akun |
| `SequenceServiceTest` | 5 | Penomoran: increment, reset per bulan, token `{TYPE}`, padding, 50 nomor tanpa duplikat |
| `JournalServiceTest` | 15 | Validasi jurnal (seimbang, min. 2 baris, debit ATAU kredit, akun aktif & postable, periode OPEN), CRUD jurnal manual |
| `PostingServiceTest` | 13 | Posting ke GL, saldo berjalan, idempotensi, rollback, carry-forward, propagasi saldo, immutability, batch, reversal |
| `TransactionServiceTest` | 14 | Input transaksi, nomor otomatis, periode CLOSED, nominal > 0, wajib lampiran, transisi, revisi, penyimpanan lampiran |
| `ApprovalServiceTest` | 10 | Approve → jurnal otomatis, Maker ≠ Checker, peran non-checker, reject wajib alasan, approval bertingkat, auto-post |
| `PeriodAndLedgerTest` | 10 | Tutup/buka periode, trial balance, buku besar per akun, audit log append-only |
| `DatabaseConstraintsTest` | 7 | CHECK `journal_lines`, unik `(account_id, period_id)`, semua kolom uang `DECIMAL(18,2)`, nomor referensi unik |
| **Total** | **120** | |

## 2. Detail per area fokus

### 2.1 Validasi Debit = Kredit
| Skenario | Hasil |
|---|:-:|
| Jurnal tidak seimbang (Rp15.000.000 vs Rp14.999.999,99) ditolak dengan pesan selisih | ✔ |
| Pengecekan tepat sampai sen: 0,10 + 0,20 = 0,30 dianggap seimbang (tidak ada galat float) | ✔ |
| Jurnal minimal 2 baris | ✔ |
| Satu baris berisi debit **dan** kredit ditolak | ✔ |
| Nilai negatif ditolak | ✔ |
| Akun nonaktif, atau akun induk (non-postable), ditolak | ✔ |
| Baris kosong diabaikan | ✔ |
| Constraint `CHECK` di MySQL menolak baris tidak valid walau aplikasi dilewati | ✔ |

### 2.2 PostingService (saldo, idempotent, rollback)
| Skenario | Hasil |
|---|:-:|
| Posting memperbarui `account_balances`, menulis `gl_entries`, lalu set `POSTED` + `posted_at`/`posted_by` | ✔ |
| Rumus saldo: normal debit `awal + D − K`; normal kredit `awal + K − D` | ✔ |
| Saldo berjalan per akun: 1.000.000 → 750.000 → 850.000,50 | ✔ |
| **Idempotent**: posting ulang ditolak, saldo dan jumlah baris GL tidak berubah | ✔ |
| **Rollback** saat validasi gagal di tengah (akun dinonaktifkan setelah jurnal dibuat): tidak ada GL/saldo tertulis | ✔ |
| **Rollback** saat error database di tengah (exception disimulasikan pada baris GL ke-2) | ✔ |
| Posting ke periode CLOSED ditolak | ✔ |
| Saldo awal terbawa (carry-forward) dari periode sebelumnya | ✔ |
| Posting ke periode lama yang masih OPEN menggeser saldo awal/akhir dan saldo berjalan periode setelahnya | ✔ |
| Jurnal POSTED tidak bisa diubah atau dihapus (`LogicException`) | ✔ |
| Posting batch melaporkan hasil per jurnal (sukses / sudah diposting / akun nonaktif / tidak ditemukan) | ✔ |
| Jurnal pembalik: baris ditukar, langsung diposting, jurnal asli menjadi `REVERSED`, saldo kembali 0 | ✔ |
| Pembalikan kedua kali dan pembalikan jurnal DRAFT ditolak | ✔ |

### 2.3 Transisi status (RANCANGAN.md 4.1)
Ada 13 transisi yang diuji. Enam transisi sah lolos:
- DRAFT → PENDING_REVIEW
- DRAFT → CANCELLED
- PENDING_REVIEW → APPROVED
- PENDING_REVIEW → REJECTED
- REJECTED → DRAFT
- APPROVED → JOURNALED

Tujuh transisi tidak sah ditolak, contohnya DRAFT → APPROVED, PENDING_REVIEW → CANCELLED, dan JOURNALED → apa pun. Di tingkat service juga diuji bahwa:
- submit ganda ditolak,
- pembatalan hanya bisa dari DRAFT,
- transaksi PENDING terkunci dari edit,
- revisi transaksi REJECTED kembali menjadi DRAFT.

### 2.4 Nomor sequence
Diuji: format `JV-{YYYY}-{MM}-{SEQ:4}`, reset per bulan, penghitung terpisah per `{TYPE}` (BKK/BKM/INV-OUT), padding khusus (`{SEQ:6}`), dan 50 panggilan berturut-turut yang semuanya unik. Uji paralel (multi-proses) dijalankan di functional test, karena butuh beberapa koneksi database sungguhan.

### 2.5 Maker ≠ Checker & hak approval
- Pembuat transaksi yang kemudian berperan checker tetap tidak bisa approve transaksinya sendiri. ✔
- Staff, Akuntan, Auditor, dan Admin tidak bisa memutuskan approval. ✔
- Checker yang dinonaktifkan tidak bisa memutuskan. ✔
- Approval bertingkat: nominal ≥ ambang hanya bisa disetujui Manager; tepat di bawah ambang cukup Supervisor. ✔

### 2.6 Periode CLOSED
Transaksi, jurnal manual, posting, dan approval (penerbitan jurnal) di periode CLOSED ditolak. Penutupan periode ditolak bila masih ada jurnal DRAFT atau transaksi Pending/Approved. Pembukaan kembali tercatat beserta alasannya di audit log. ✔

## 3. Temuan selama unit testing

| # | Temuan | Jenis | Tindak lanjut |
|---|---|---|---|
| 1 | Fixture test mengajukan transaksi > Rp1 juta tanpa lampiran, sehingga ditolak aturan wajib lampiran | Kesalahan test (aturan aplikasi benar) | Fixture otomatis melampirkan bukti bila nominal melewati ambang |
| 2 | `UploadedFile::fake()` menebak MIME dari ekstensi, sehingga uji "isi .exe berekstensi .pdf" tidak mewakili upload nyata | Kesalahan test | Test memakai upload asli. MIME terbukti dideteksi dari isi berkas (`finfo`) |
| 3 | Setelah penomoran dipindah ke koneksi terpisah (lihat laporan functional, temuan F-1), asersi tabel `sequences` di koneksi default tidak melihat datanya | Penyesuaian test | Asersi diarahkan ke koneksi `sequence`, dan `connectionsToTransact` di `TestCase` mencakup `sequence` |

Tidak ada bug aplikasi yang ditemukan oleh unit test. Bug yang terungkap selama pengujian ditemukan oleh uji konkurensi dan uji browser, dan dicatat di [LAPORAN_FUNCTIONAL_TEST.md](LAPORAN_FUNCTIONAL_TEST.md).

## 4. Keterbatasan
- **Code coverage belum diukur**, karena di mesin uji tidak ada ekstensi Xdebug/PCOV. Untuk mengukurnya, pasang PCOV lalu jalankan `php artisan test --coverage`.
- Pemindaian antivirus (ClamAV) hanya diuji dalam kondisi nonaktif (default). Jalur aktifnya butuh `clamscan` terpasang.
- Unit test berjalan di MySQL (bukan SQLite) agar constraint `CHECK`, `lockForUpdate`, dan tipe `DECIMAL` teruji seperti di produksi.

## 5. Cara menjalankan ulang
```bash
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS finance_db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan test --testsuite=Unit            # ringkas
php vendor/bin/phpunit --testsuite=Unit --testdox   # daftar skenario
```
