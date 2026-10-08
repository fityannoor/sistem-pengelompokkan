# Sistem Clustering Nilai Siswa dengan K-Means

Aplikasi web untuk mengelola data akademik dan mengelompokkan siswa berdasarkan nilai mata pelajaran menggunakan algoritma K-Means. Proyek ini dibuat dengan PHP dan MySQL untuk aplikasi web, serta Python dan scikit-learn untuk proses analisis clustering.

## Fitur

- Mengelola data siswa, mata pelajaran, nilai, dan akun pengguna.
- Mengimpor nilai dari leger.
- Menjalankan clustering per mata pelajaran atau menggunakan seluruh mata pelajaran.
- Membantu memilih jumlah cluster dengan Elbow Method dan Silhouette Score.
- Menampilkan hasil, profil cluster, dan jejak perhitungan.
- Mengekspor laporan PDF, mencatat aktivitas, dan membuat backup database.
- Menyediakan skrip analisis perbandingan normalisasi dan pemeriksaan centroid yang bersifat baca-saja.

## Teknologi

- PHP 8.1 atau lebih baru
- MySQL atau MariaDB
- Python 3.10 atau lebih baru
- Composer
- Paket Python tercantum di [`requirements.txt`](requirements.txt)

## Menjalankan secara lokal

1. Pasang XAMPP, Composer, Python, dan MySQL/MariaDB.
2. Clone repositori ke folder `htdocs` XAMPP, lalu masuk ke folder proyek.
3. Pasang dependensi PHP:

   ```powershell
   composer install
   ```

4. Buat virtual environment Python dan pasang dependensi:

   ```powershell
   python -m venv .venv
   .\.venv\Scripts\Activate.ps1
   python -m pip install -r requirements.txt
   ```

5. Buat database MySQL/MariaDB dan siapkan skema inti aplikasi beserta data demo yang sudah dianonimkan. Berkas SQL di folder `database/` menambahkan tabel pendukung untuk log aktivitas, periode nilai per kelas, dan detail perhitungan K-Means; berkas tersebut bukan dump lengkap skema inti.
6. Salin `config/database.example.ini` menjadi `config/database.ini`, lalu isi kredensial lokal database. Jangan unggah `database.ini` atau dump yang berisi data nyata.
7. Buka `http://localhost/skripsi-kmeans/` di browser.

Skrip clustering utama menerima nama kelas dan tahun ajaran:

```powershell
python python/kmeans_keseluruhan.py "XI Perhotelan" "2024/2025"
```

Gunakan nama kelas dan tahun ajaran yang memang tersedia di database lokal. Skrip analisis tambahan dapat dijalankan dengan `--help` untuk melihat opsi:

```powershell
python python/uji_centroid.py --help
```

## Struktur folder

```text
auth/       autentikasi dan pemeriksaan hak akses
config/     contoh konfigurasi database
database/   skrip SQL untuk tabel pendukung
includes/   bootstrap, validasi, keamanan, dan komponen bersama
pages/      halaman fitur aplikasi
python/     proses clustering dan analisis
assets/     stylesheet dan aset visual
```

## Privasi dan keamanan

Repositori ini tidak menyertakan database lokal, kredensial, atau backup SQL. Gunakan data demo anonim saat mendemonstrasikan aplikasi. Sebelum membuat repositori publik, periksa kembali berkas yang akan diunggah dan pastikan tidak ada data pribadi, kredensial, log, atau hasil analisis yang tidak boleh dibagikan.

## Catatan deployment

GitHub dapat digunakan untuk menyimpan kode dan dokumentasi proyek. GitHub Pages hanya melayani situs statis dan tidak menjalankan PHP, Python, atau database server. Untuk demo yang bisa digunakan melalui browser, deploy aplikasi ke server yang mendukung PHP, Python, dan MySQL/MariaDB.
