# Materi Pembelajaran Rumus K-Means pada Sistem Clustering Nilai Siswa

## 1. Tujuan Pembelajaran

Setelah mempelajari materi ini, pembaca diharapkan mampu:

1. menjelaskan bentuk data yang digunakan dalam proses clustering;
2. menjelaskan alasan identitas siswa tidak dimasukkan sebagai fitur;
3. menghitung normalisasi Min–Max;
4. menghitung jarak Euclidean siswa ke centroid;
5. menghitung titik centroid;
6. menjelaskan proses perpindahan anggota cluster;
7. menghitung dan menjelaskan inertia atau WCSS;
8. menjelaskan pemilihan jumlah cluster dengan Elbow Method;
9. menghitung dan menafsirkan Silhouette Score; dan
10. menjelaskan hubungan setiap rumus dengan kode program sistem.

---

## 2. Gambaran Umum Proses pada Sistem

Alur clustering nilai siswa pada sistem adalah:

```text
Data nilai dari database
        ↓
Satu baris untuk setiap siswa
        ↓
Memisahkan identitas dan fitur nilai
        ↓
Normalisasi Min–Max
        ↓
Menguji beberapa nilai K
        ↓
Menghitung inertia dan Silhouette Score
        ↓
Memilih K terbaik
        ↓
Menjalankan K-Means final
        ↓
Menghitung jarak siswa ke centroid
        ↓
Menentukan anggota setiap cluster
        ↓
Menyimpan hasil dan membuat interpretasi cluster
```

Sumber implementasi utama terdapat pada
[`python/kmeans_keseluruhan.py`](../python/kmeans_keseluruhan.py).
Pengujian centroid dan jarak melalui terminal terdapat pada
[`python/uji_centroid.py`](../python/uji_centroid.py).

---

## 3. Notasi yang Digunakan

| Simbol | Arti |
|---|---|
| \(n\) | jumlah siswa |
| \(m\) | jumlah mata pelajaran atau fitur |
| \(K\) | jumlah cluster |
| \(x_i\) | data nilai siswa ke-\(i\) |
| \(x_{ij}\) | nilai siswa ke-\(i\) pada mata pelajaran ke-\(j\) |
| \(x'_{ij}\) | nilai yang sudah dinormalisasi |
| \(C_k\) | cluster ke-\(k\) |
| \(\mu_k\) | centroid cluster ke-\(k\) |
| \(\mu_{kj}\) | nilai centroid cluster ke-\(k\) pada fitur ke-\(j\) |
| \(d(x_i,\mu_k)\) | jarak siswa ke-\(i\) ke centroid ke-\(k\) |

---

## 4. Pembentukan Matriks Data Siswa

Data awal dari database berbentuk panjang. Satu siswa dapat memiliki banyak
baris karena setiap mata pelajaran disimpan pada baris yang berbeda.

Contoh data awal:

| ID | Nama | Mata pelajaran | Nilai |
|---:|---|---|---:|
| 1 | Andi | Matematika | 80 |
| 1 | Andi | Bahasa Indonesia | 85 |
| 2 | Budi | Matematika | 75 |
| 2 | Budi | Bahasa Indonesia | 78 |

Sistem menggunakan `pivot_table` untuk mengubahnya menjadi:

| ID | Nama | Matematika | Bahasa Indonesia |
|---:|---|---:|---:|
| 1 | Andi | 80 | 85 |
| 2 | Budi | 75 | 78 |

Secara matematis, setiap siswa menjadi sebuah vektor:

\[
x_i = [x_{i1},x_{i2},x_{i3},\ldots,x_{im}]
\]

Jika terdapat lebih dari satu nilai untuk siswa dan mata pelajaran yang sama,
`aggfunc='mean'` menghitung rata-ratanya:

\[
\bar{x}_{ij} =
\frac{1}{p}\sum_{r=1}^{p}x_{ijr}
\]

dengan \(p\) adalah banyak nilai ganda yang ditemukan.

Kode sistem:

```python
df = df_raw.pivot_table(
    index=['id', 'nama_siswa'],
    columns='nama_mapel',
    values='nilai',
    aggfunc='mean'
).reset_index()
```

### Nilai yang tidak tersedia

Setelah pivot, nilai yang kosong diisi dengan nol:

```python
df = df.fillna(0)
```

Artinya, pada sistem saat ini berlaku:

\[
x_{ij} =
\begin{cases}
\text{nilai siswa}, & \text{jika nilai tersedia}\\
0, & \text{jika nilai tidak tersedia}
\end{cases}
\]

Hal ini penting dijelaskan saat pengujian karena nilai nol akan memengaruhi
normalisasi, jarak, dan hasil cluster.

### Penggabungan mata pelajaran agama

Jika terdapat beberapa kolom yang mengandung kata “agama”, sistem membentuk
satu fitur:

\[
x_{i,\text{agama}} =
\sum_{j \in A}x_{ij}
\]

dengan \(A\) adalah himpunan kolom mata pelajaran agama yang ditemukan.

---

## 5. Pemisahan Identitas dan Fitur

Data utama `df` tetap menyimpan:

- `id`;
- `nama_siswa`; dan
- seluruh nilai mata pelajaran.

Namun, data yang masuk ke perhitungan K-Means hanya nilai mata pelajaran:

```python
fitur = df.drop(columns=['id', 'nama_siswa'])
```

Alasannya, ID dan nama bukan ukuran kemampuan akademik. Jika ID dimasukkan,
selisih nomor ID dapat dianggap sebagai jarak oleh algoritma, padahal tidak
memiliki makna akademik.

Urutan baris `df` dan `fitur` tetap sama. Oleh karena itu, label hasil K-Means
dapat ditempelkan kembali ke siswa yang sesuai:

```python
df['cluster'] = kmeans.fit_predict(data_normal)
```

---

## 6. Normalisasi Min–Max

### 6.1 Rumus

Untuk setiap mata pelajaran, sistem menggunakan:

\[
x'_{ij} =
\frac{x_{ij}-x_j^{\min}}
{x_j^{\max}-x_j^{\min}}
\]

dengan:

- \(x_{ij}\) = nilai asli siswa;
- \(x_j^{\min}\) = nilai terendah pada mata pelajaran ke-\(j\);
- \(x_j^{\max}\) = nilai tertinggi pada mata pelajaran ke-\(j\);
- \(x'_{ij}\) = nilai hasil normalisasi.

Hasil normalisasi berada pada rentang:

\[
0 \leq x'_{ij} \leq 1
\]

Jika seluruh nilai suatu fitur sama sehingga
\(x_j^{\max}=x_j^{\min}\), `MinMaxScaler` menangani fitur tersebut tanpa
melakukan pembagian nol dan menghasilkan nilai skala yang sama.

### 6.2 Contoh

Nilai Matematika terendah adalah 60 dan tertinggi adalah 100. Nilai Andi
adalah 80:

\[
x' =
\frac{80-60}{100-60}
=
\frac{20}{40}
=0{,}5
\]

### 6.3 Mengapa dinormalisasi?

Jarak Euclidean sensitif terhadap skala. Normalisasi memastikan setiap mata
pelajaran berada pada rentang yang sama sehingga tidak ada fitur yang
mendominasi hanya karena mempunyai rentang angka lebih besar.

Kode sistem:

```python
scaler = MinMaxScaler()
data_normal = scaler.fit_transform(fitur)
```

---

## 7. Penentuan Rentang Jumlah Cluster

Jumlah cluster yang diuji dimulai dari \(K=2\). Batas atas sistem adalah:

\[
K_{\max} =
\min\left(
\left\lfloor\frac{n}{2}\right\rfloor,
n-1
\right)
\]

Contoh jika terdapat 34 siswa:

\[
K_{\max} =
\min(17,33)
=17
\]

Jadi sistem menguji:

\[
K = 2,3,4,\ldots,17
\]

Kode sistem:

```python
max_k = min(
    int(len(df) * 0.5),
    len(df) - 1
)
```

---

## 8. Algoritma K-Means

K-Means mencari pembagian data yang membuat anggota dalam satu cluster
berdekatan dan antarkluster terpisah.

### 8.1 Inisialisasi centroid

Model membuat \(K\) centroid awal. Sistem menggunakan:

```python
KMeans(
    n_clusters=k,
    random_state=42,
    n_init=10
)
```

Keterangan:

- `n_clusters=k` menentukan jumlah centroid;
- `random_state=42` membuat hasil dapat direproduksi;
- `n_init=10` mencoba sepuluh inisialisasi dan memilih hasil dengan inertia
  terbaik.

### 8.2 Menghitung jarak Euclidean

Jarak siswa \(x_i\) ke centroid \(\mu_k\) adalah:

\[
d(x_i,\mu_k) =
\sqrt{
\sum_{j=1}^{m}
(x'_{ij}-\mu_{kj})^2
}
\]

Jika ada tiga mata pelajaran:

\[
d(x_i,\mu_k) =
\sqrt{
(x'_{i1}-\mu_{k1})^2+
(x'_{i2}-\mu_{k2})^2+
(x'_{i3}-\mu_{k3})^2
}
\]

Jarak pada sistem dihitung menggunakan data normalisasi, bukan nilai asli.

### 8.3 Menentukan cluster siswa

Siswa dimasukkan ke centroid yang mempunyai jarak terkecil:

\[
\operatorname{cluster}(x_i)
=
\arg\min_{k \in \{1,\ldots,K\}}
d(x_i,\mu_k)
\]

Contoh:

| Siswa | Jarak C1 | Jarak C2 | Jarak C3 | Hasil |
|---|---:|---:|---:|---|
| Andi | 0,42 | 0,81 | 1,10 | Cluster 1 |

Andi masuk Cluster 1 karena \(0{,}42\) merupakan jarak terkecil.

Implementasi dan validasi rumus manual terdapat dalam
`uji_centroid.py`:

```python
jarak_manual = np.sqrt(
    (
        (
            data_normal[:, np.newaxis, :]
            - model.cluster_centers_[np.newaxis, :, :]
        )
        ** 2
    ).sum(axis=2)
)
```

### 8.4 Menghitung ulang centroid

Centroid adalah rata-rata setiap fitur dari seluruh anggota cluster:

\[
\mu_{kj} =
\frac{1}{|C_k|}
\sum_{x_i \in C_k}x'_{ij}
\]

dengan \(|C_k|\) adalah jumlah siswa dalam cluster ke-\(k\).

Centroid bukan satu angka. Jika terdapat 10 mata pelajaran, satu centroid
memiliki 10 koordinat:

\[
\mu_k =
[\mu_{k1},\mu_{k2},\ldots,\mu_{k10}]
\]

### 8.5 Iterasi

Proses berikut diulang:

1. menghitung jarak setiap siswa ke semua centroid;
2. memindahkan siswa ke centroid terdekat;
3. menghitung centroid baru dari rata-rata anggota;
4. membandingkan dengan kondisi sebelumnya.

Proses berhenti ketika centroid atau keanggotaan cluster sudah stabil sesuai
kriteria konvergensi model.

---

## 9. Inertia atau Within-Cluster Sum of Squares

### 9.1 Rumus

Inertia adalah jumlah kuadrat jarak setiap siswa ke centroid clusternya:

\[
I =
\sum_{k=1}^{K}
\sum_{x_i \in C_k}
\lVert x_i-\mu_k\rVert^2
\]

Bentuk terperinci:

\[
I =
\sum_{k=1}^{K}
\sum_{x_i \in C_k}
\sum_{j=1}^{m}
(x'_{ij}-\mu_{kj})^2
\]

Istilah lain yang sering digunakan:

- WCSS: Within-Cluster Sum of Squares;
- SSE: Sum of Squared Errors;
- inertia.

Ketiganya merujuk pada gagasan jumlah kuadrat jarak di dalam cluster.

### 9.2 Interpretasi

- inertia kecil berarti anggota cluster relatif dekat dengan centroid;
- inertia selalu cenderung turun ketika \(K\) bertambah;
- inertia nol belum tentu menjadi pilihan terbaik karena dapat terjadi jika
  setiap data dijadikan cluster tersendiri.

Nilainya diambil dari:

```python
kmeans_uji.inertia_
```

---

## 10. Elbow Method pada Sistem

Elbow Method mencari titik ketika penambahan cluster tidak lagi memberikan
penurunan inertia yang besar.

### 10.1 Penurunan inertia

Untuk setiap \(K\):

\[
\Delta I_K = I_{K-1}-I_K
\]

Contoh:

| K | Inertia | Penurunan |
|---:|---:|---:|
| 2 | 20 | – |
| 3 | 14 | \(20-14=6\) |
| 4 | 11 | \(14-11=3\) |
| 5 | 9,5 | \(11-9{,}5=1{,}5\) |

### 10.2 Rata-rata penurunan

\[
\overline{\Delta I}
=
\frac{1}{q}
\sum \Delta I_K
\]

dengan \(q\) adalah jumlah penurunan inertia yang tersedia.

### 10.3 Kandidat elbow sistem

Sistem mengambil kandidat yang memenuhi:

\[
\Delta I_K \geq \overline{\Delta I}
\]

Kode:

```python
hasil_uji_df['penurunan_inertia'] = (
    hasil_uji_df['inertia'].shift(1)
    - hasil_uji_df['inertia']
)

rata_penurunan = hasil_uji_df['penurunan_inertia'].mean()

kandidat_elbow_df = hasil_uji_df[
    hasil_uji_df['penurunan_inertia'] >= rata_penurunan
]
```

Metode pada sistem bukan hanya melihat grafik secara visual. Sistem membuat
kandidat elbow berdasarkan penurunan inertia, kemudian memakai Silhouette
Score untuk memilih kandidat terbaik.

---

## 11. Silhouette Score

Silhouette Score mengukur dua hal:

1. kekompakan siswa terhadap anggota cluster sendiri; dan
2. pemisahan siswa dari cluster lain.

### 11.1 Jarak rata-rata dalam cluster

\[
a(i) =
\frac{1}{|C_i|-1}
\sum_{\substack{x_j \in C_i\\j\neq i}}
d(x_i,x_j)
\]

\(a(i)\) adalah rata-rata jarak siswa \(i\) ke anggota lain dalam cluster yang
sama. Semakin kecil \(a(i)\), semakin kompak clusternya.

### 11.2 Jarak rata-rata ke cluster lain

Untuk setiap cluster lain \(C\):

\[
d(i,C) =
\frac{1}{|C|}
\sum_{x_j \in C}d(x_i,x_j)
\]

Kemudian dipilih jarak rata-rata terkecil:

\[
b(i) = \min_{C\neq C_i}d(i,C)
\]

### 11.3 Silhouette setiap siswa

\[
s(i) =
\frac{b(i)-a(i)}
{\max(a(i),b(i))}
\]

Rentang nilai:

\[
-1 \leq s(i) \leq 1
\]

### 11.4 Silhouette keseluruhan

\[
S =
\frac{1}{n}
\sum_{i=1}^{n}s(i)
\]

Interpretasi umum:

| Nilai | Makna |
|---:|---|
| mendekati 1 | cluster kompak dan terpisah dengan baik |
| mendekati 0 | siswa berada dekat batas antarkluster |
| kurang dari 0 | siswa mungkin lebih dekat ke cluster lain |

Kode sistem:

```python
score = silhouette_score(data_normal, cluster_uji)
```

---

## 12. Pemilihan K Terbaik pada Sistem

Setelah kandidat elbow diperoleh, sistem memilih kandidat dengan Silhouette
Score tertinggi:

\[
K_{\text{terbaik}}
=
\arg\max_{K \in E} S_K
\]

dengan:

- \(E\) = kumpulan kandidat elbow;
- \(S_K\) = Silhouette Score untuk jumlah cluster \(K\).

Kode:

```python
k_terbaik = kandidat_elbow_df.loc[
    kandidat_elbow_df['score'].idxmax(),
    'k'
]
```

Jadi keputusan jumlah cluster pada sistem menggabungkan:

1. penurunan inertia untuk memperoleh kandidat elbow; dan
2. Silhouette Score untuk memilih kandidat dengan pemisahan terbaik.

---

## 13. Contoh Perhitungan Manual Lengkap

Misalkan digunakan dua mata pelajaran:

| Siswa | Matematika | Bahasa Indonesia |
|---|---:|---:|
| A | 70 | 60 |
| B | 80 | 70 |
| C | 90 | 90 |
| D | 100 | 100 |

### 13.1 Normalisasi

Untuk Matematika:

\[
x_{\min}=70,\qquad x_{\max}=100
\]

Untuk Bahasa Indonesia:

\[
x_{\min}=60,\qquad x_{\max}=100
\]

Hasil:

| Siswa | Matematika normal | B. Indonesia normal |
|---|---:|---:|
| A | 0 | 0 |
| B | 0,3333 | 0,25 |
| C | 0,6667 | 0,75 |
| D | 1 | 1 |

### 13.2 Misalkan hasil keanggotaan awal

```text
Cluster 1 = A dan B
Cluster 2 = C dan D
```

### 13.3 Centroid Cluster 1

\[
\mu_1 =
\left[
\frac{0+0{,}3333}{2},
\frac{0+0{,}25}{2}
\right]
\]

\[
\mu_1 = [0{,}1667,\ 0{,}125]
\]

### 13.4 Centroid Cluster 2

\[
\mu_2 =
\left[
\frac{0{,}6667+1}{2},
\frac{0{,}75+1}{2}
\right]
\]

\[
\mu_2 = [0{,}8333,\ 0{,}875]
\]

### 13.5 Jarak siswa A ke Centroid 1

\[
d(A,\mu_1)
=
\sqrt{
(0-0{,}1667)^2+
(0-0{,}125)^2
}
\]

\[
d(A,\mu_1)
\approx 0{,}2083
\]

### 13.6 Jarak siswa A ke Centroid 2

\[
d(A,\mu_2)
=
\sqrt{
(0-0{,}8333)^2+
(0-0{,}875)^2
}
\]

\[
d(A,\mu_2)
\approx 1{,}2083
\]

Karena:

\[
0{,}2083 < 1{,}2083
\]

maka siswa A masuk Cluster 1.

### 13.7 Inertia contoh

Keempat siswa mempunyai jarak sekitar \(0{,}2083\) ke centroid clusternya:

\[
I =
4(0{,}2083^2)
\]

\[
I \approx 0{,}1736
\]

### 13.8 Centroid dalam skala nilai asli

Centroid Cluster 1:

\[
\mu_{1,\text{asli}}
=
\left[
\frac{70+80}{2},
\frac{60+70}{2}
\right]
=
[75,65]
\]

Centroid Cluster 2:

\[
\mu_{2,\text{asli}}
=
\left[
\frac{90+100}{2},
\frac{90+100}{2}
\right]
=
[95,95]
\]

Centroid skala asli mudah dijelaskan sebagai profil rata-rata nilai cluster,
sedangkan jarak K-Means pada sistem tetap dihitung dari centroid normalisasi.

---

## 14. Profil dan Interpretasi Cluster

Setelah cluster terbentuk, sistem menghitung rata-rata nilai asli setiap mata
pelajaran dalam cluster:

\[
\bar{x}_{kj}
=
\frac{1}{|C_k|}
\sum_{x_i\in C_k}x_{ij}
\]

Kode:

```python
hasil_analisis = df.groupby('cluster')[kolom_nilai].mean()
```

Rata-rata keseluruhan cluster:

\[
\bar{x}_k =
\frac{1}{m}
\sum_{j=1}^{m}\bar{x}_{kj}
\]

Kode:

```python
rata_rata_cluster = hasil_analisis.mean(axis=1)
```

Nilai tersebut dipakai untuk mengurutkan profil cluster dari prestasi relatif
tertinggi hingga terendah. Peringkat ini adalah tahap interpretasi setelah
K-Means, bukan variabel yang menentukan pembentukan cluster.

---

## 15. Menghubungkan Rumus dengan Hasil Terminal

Jalankan:

```powershell
python uji_centroid.py "XI Perhotelan"
```

Bagian keluaran:

### Titik centroid data normalisasi

Menampilkan:

\[
\mu_{kj}
=
\frac{1}{|C_k|}
\sum_{x_i\in C_k}x'_{ij}
\]

Nilai inilah yang benar-benar dipakai saat menghitung jarak.

### Titik centroid skala nilai asli

Menampilkan rata-rata nilai asli anggota cluster agar hasil lebih mudah
dipahami oleh manusia.

### Anggota setiap cluster

Label dari K-Means mempunyai urutan baris yang sama dengan data siswa.
Karena itu, sistem dapat memasangkan label dengan ID dan nama siswa walaupun
identitas tidak masuk ke rumus jarak.

### Jarak ke setiap centroid

Kolom `Jarak C1`, `Jarak C2`, dan seterusnya merupakan:

\[
d(x_i,\mu_k)
\]

`Cluster Terpilih` adalah:

\[
\arg\min_k d(x_i,\mu_k)
\]

---

## 16. Ringkasan Rumus

| Proses | Rumus |
|---|---|
| Rata-rata nilai ganda | \(\bar{x}=\frac{1}{p}\sum x_r\) |
| Normalisasi Min–Max | \(x'=\frac{x-x_{\min}}{x_{\max}-x_{\min}}\) |
| Jarak Euclidean | \(d(x,\mu)=\sqrt{\sum_j(x'_j-\mu_j)^2}\) |
| Pemilihan cluster | \(\arg\min_k d(x_i,\mu_k)\) |
| Centroid | \(\mu_{kj}=\frac{1}{|C_k|}\sum_{x_i\in C_k}x'_{ij}\) |
| Inertia/WCSS | \(I=\sum_k\sum_{x_i\in C_k}\lVert x_i-\mu_k\rVert^2\) |
| Penurunan inertia | \(\Delta I_K=I_{K-1}-I_K\) |
| Kandidat elbow sistem | \(\Delta I_K\geq\overline{\Delta I}\) |
| Jarak dalam cluster | \(a(i)=\text{rata-rata jarak ke cluster sendiri}\) |
| Jarak cluster terdekat | \(b(i)=\min_{C\neq C_i}\text{rata-rata jarak ke }C\) |
| Silhouette siswa | \(s(i)=\frac{b(i)-a(i)}{\max(a(i),b(i))}\) |
| Silhouette keseluruhan | \(S=\frac{1}{n}\sum_i s(i)\) |
| K terbaik sistem | \(K_{\text{terbaik}}=\arg\max_{K\in E}S_K\) |
| Profil nilai cluster | \(\bar{x}_{kj}=\frac{1}{|C_k|}\sum_{x_i\in C_k}x_{ij}\) |

---

## 17. Pertanyaan Sidang dan Contoh Jawaban

### Mengapa nama dan ID siswa tidak dimasukkan ke K-Means?

Karena nama dan ID adalah identitas, bukan fitur akademik. Perbedaan ID tidak
menunjukkan kemiripan atau perbedaan kemampuan siswa. Identitas tetap disimpan
untuk memasangkan hasil label cluster dengan siswa yang benar.

### Apakah semua nilai mata pelajaran dijumlahkan menjadi satu nilai?

Tidak. Setiap mata pelajaran tetap menjadi satu dimensi. Jika terdapat 10 mata
pelajaran, setiap siswa dan setiap centroid mempunyai 10 koordinat.

### Centroid itu apa?

Centroid adalah titik pusat cluster yang dihitung dari rata-rata setiap fitur
seluruh anggota cluster.

### Mengapa menggunakan normalisasi?

Supaya setiap fitur berada pada rentang yang sama dan perhitungan jarak tidak
didominasi oleh fitur yang memiliki rentang nilai lebih besar.

### Bagaimana siswa ditentukan masuk Cluster 1?

Sistem menghitung jarak siswa ke seluruh centroid. Siswa masuk ke cluster yang
centroidnya mempunyai jarak Euclidean paling kecil.

### Mengapa label internal Cluster 1 adalah angka 0?

Scikit-learn memberikan indeks label mulai dari 0. Sistem menambahkan 1 saat
menampilkan label, sehingga label 0 ditampilkan sebagai Cluster 1.

### Apa perbedaan centroid normalisasi dan centroid nilai asli?

Centroid normalisasi digunakan dalam perhitungan jarak K-Means. Centroid nilai
asli adalah hasil pengembalian skala atau rata-rata nilai asli yang digunakan
agar profil cluster mudah dijelaskan.

### Apakah inertia yang paling kecil selalu menjadi pilihan terbaik?

Tidak. Inertia selalu turun ketika jumlah cluster bertambah. Sistem mencari
kandidat elbow dari pola penurunan inertia, kemudian memilih kandidat dengan
Silhouette Score tertinggi.

### Apa arti Silhouette Score mendekati nol?

Artinya banyak data berada dekat batas antarkluster sehingga pemisahan cluster
belum terlalu kuat.

### Apakah peringkat prestasi menentukan cluster?

Tidak. Cluster dibentuk terlebih dahulu berdasarkan jarak pada data
normalisasi. Peringkat prestasi dihitung setelah clustering untuk membantu
interpretasi hasil.

---

## 18. Kalimat Penjelasan Singkat untuk Presentasi

> Setiap siswa direpresentasikan sebagai sebuah titik multidimensi, dengan
> setiap mata pelajaran sebagai satu dimensi. Nilai dinormalisasi menggunakan
> Min–Max agar seluruh fitur mempunyai skala yang sama. K-Means menghitung
> jarak Euclidean siswa ke seluruh centroid dan menempatkan siswa pada centroid
> terdekat. Centroid kemudian dihitung ulang dari rata-rata setiap fitur
> seluruh anggota cluster hingga konvergen. Jumlah cluster dipilih dari
> kandidat Elbow Method berdasarkan Silhouette Score tertinggi.

