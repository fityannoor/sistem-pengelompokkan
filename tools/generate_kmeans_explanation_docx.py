from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile
from xml.sax.saxutils import escape
ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "python" / "kmeans_keseluruhan.py"
OUTPUT = ROOT / "Penjelasan_Kode_KMeans_Keseluruhan.docx"
OUTPUT_RINGKAS = ROOT / "Penjelasan_Ringkas_Per_Baris_KMeans_Keseluruhan.docx"
SECTIONS = [
    ("1. Import library", 1, 13,
     "Mengimpor modul yang dibutuhkan. os menangani path/folder, re untuk ekspresi reguler, sys untuk argumen dan penghentian program, configparser untuk membaca database.ini, serta quote_plus untuk mengamankan karakter khusus pada URL koneksi. mysql.connector dipakai untuk operasi INSERT/DELETE, pandas untuk pengolahan tabel, SQLAlchemy untuk membaca data, scikit-learn untuk normalisasi, K-Means, dan Silhouette Score, sedangkan save_elbow_chart membuat grafik elbow."),
    ("2. Membaca konfigurasi database", 15, 27,
     "Menyusun path absolut menuju config/database.ini, membaca file sebagai UTF-8, lalu menghentikan program dengan kode status 1 jika konfigurasi tidak ditemukan. Nilai host, nama database, username, dan password diambil dari bagian [application], dengan nilai bawaan untuk host dan nama database."),
    ("3. Membuat dua koneksi database", 29, 39,
     "create_engine membuat koneksi SQLAlchemy dengan driver PyMySQL untuk pd.read_sql. quote_plus mencegah username, password, atau nama database yang mengandung karakter khusus merusak URL. mysql.connector.connect membuat koneksi kedua yang digunakan oleh cursor untuk DELETE dan INSERT."),
    ("4. Membaca parameter kelas dan tahun ajaran", 41, 51,
     "Program wajib dijalankan dengan dua argumen, yaitu kelas dan tahun ajaran. strip menghapus spasi di awal/akhir. re.fullmatch memastikan tahun ajaran berbentuk YYYY/YYYY, kemudian pemeriksaan angka memastikan tahun kedua tepat satu tahun setelah tahun pertama."),
    ("5. Fungsi slugify", 54, 59,
     "Mengubah teks kelas menjadi nama file yang aman: dibuat huruf kecil, karakter selain a-z dan 0-9 diganti garis bawah, lalu garis bawah di tepi dihapus. Jika hasilnya kosong, digunakan kata kelas."),
    ("6. Query nilai siswa", 62, 76,
     "Membentuk query berparameter untuk mengambil ID siswa, nama siswa, nama mata pelajaran, dan nilai. JOIN menghubungkan nilai_detail dengan siswa serta mata_pelajaran. Data dibatasi berdasarkan kelas, dan nilai NULL diabaikan. Parameter :kelas menjaga query lebih aman daripada menyisipkan teks secara langsung."),
    ("7. Membaca dan memeriksa data", 78, 82,
     "pd.read_sql menjalankan query dan menghasilkan DataFrame df_raw. Jika tidak ada baris, program menampilkan Data kosong lalu berhenti normal dengan status 0 karena tidak ada proses clustering yang dapat dilakukan."),
    ("8. Mengubah data menjadi matriks siswa-mapel", 85, 92,
     "pivot_table mengubah data panjang menjadi satu baris per siswa dan satu kolom per mata pelajaran. Jika ada lebih dari satu nilai untuk kombinasi yang sama, aggfunc='mean' mengambil rata-ratanya. reset_index mengembalikan id dan nama_siswa sebagai kolom biasa. Nilai yang kosong kemudian diisi 0."),
    ("9. Menggabungkan kolom mata pelajaran agama", 94, 105,
     "Mencari semua nama kolom yang mengandung kata agama tanpa membedakan huruf besar-kecil. Jika ditemukan, semua nilainya dijumlahkan per siswa ke kolom Pendidikan Agama dan Budi Pekerti, kemudian kolom agama asal dihapus. Tujuannya menyatukan variasi nama mata pelajaran agama menjadi satu fitur."),
    ("10. Menyiapkan dan menormalisasi fitur", 107, 110,
     "Kolom identitas id dan nama_siswa dikeluarkan karena bukan nilai akademik. MinMaxScaler kemudian mengubah setiap fitur ke rentang 0 sampai 1 agar perbedaan skala antarmata pelajaran tidak mendominasi perhitungan jarak K-Means."),
    ("11. Menentukan rentang jumlah cluster", 112, 126,
     "Menyiapkan penampung hasil evaluasi dan menghitung batas maksimum k, yaitu nilai terkecil antara 50% jumlah siswa dan jumlah siswa dikurangi satu. Minimal tiga siswa diperlukan agar evaluasi cluster bermakna. max_k dijaga sekurang-kurangnya 2."),
    ("12. Menguji setiap nilai k", 128, 151,
     "Melakukan perulangan k dari 2 sampai max_k. Untuk setiap k dibuat model KMeans dengan random_state=42 agar hasil dapat direproduksi dan n_init=10 agar algoritma mencoba sepuluh inisialisasi centroid. fit_predict melatih model sekaligus menghasilkan label. Inertia dan Silhouette Score disimpan sebagai bahan pemilihan k."),
    ("13. Menghitung penurunan inertia", 153, 166,
     "Daftar evaluasi diubah menjadi DataFrame. Penurunan inertia dihitung dari inertia k sebelumnya dikurangi inertia k saat ini. Kandidat elbow dipilih apabila penurunannya minimal sebesar rata-rata penurunan. Jika filter tidak menghasilkan kandidat, seluruh hasil uji digunakan sebagai fallback."),
    ("14. Memilih jumlah cluster terbaik", 169, 172,
     "Dari kandidat elbow, baris dengan Silhouette Score terbesar dipilih menggunakan idxmax. Nilai k pada baris tersebut menjadi k_terbaik. Pendekatan ini menggabungkan pertimbangan elbow (penurunan inertia) dan kualitas pemisahan cluster (silhouette)."),
    ("15. Menyimpan evaluasi cluster", 174, 199,
     "Membuat cursor, menghapus evaluasi lama untuk kelas dan jenis keseluruhan, kemudian memasukkan setiap kandidat beserta k, inertia, dan Silhouette Score. Konversi int/float memastikan tipe data Python cocok dengan kolom database. commit menyimpan transaksi secara permanen."),
    ("16. Membuat grafik elbow", 201, 220,
     "Menentukan folder assets/img secara absolut dan membuatnya jika belum ada. save_elbow_chart menyimpan grafik inertia terhadap k dalam format SVG. Nama file mengandung slug kelas dan k terbaik diberikan agar dapat ditandai pada grafik."),
    ("17. Menjalankan K-Means final", 222, 237,
     "Membuat model K-Means dengan k terbaik, melatihnya pada data normal, dan menyimpan label numerik di kolom cluster. Silhouette Score final dihitung. Kolom kategori mengubah label berbasis nol menjadi nama yang mudah dibaca, misalnya label 0 menjadi Cluster 1."),
    ("18. Menyiapkan catatan dan menghapus hasil lama", 240, 251,
     "Membuat catatan rekomendasi umum. Query DELETE menghapus hasil clustering lama milik seluruh siswa pada kelas yang sedang diproses agar hasil baru tidak menumpuk atau terduplikasi."),
    ("19. Menyimpan hasil cluster per siswa", 254, 284,
     "Mendefinisikan query INSERT untuk hasil_cluster. Perulangan iterrows menyusun nilai setiap siswa: ID, mapel_id NULL karena analisis bersifat keseluruhan, nama cluster, kategori, silhouette final, jumlah iterasi model, tahun ajaran, dan catatan. Setelah semua baris dimasukkan, commit menyimpan transaksi."),
    ("20. Menghitung statistik setiap cluster", 286, 304,
     "Memilih kolom numerik, lalu mengecualikan id dan cluster agar hanya nilai mata pelajaran yang dianalisis. groupby menghitung rata-rata setiap mata pelajaran dan jumlah anggota per cluster. Rata-rata keseluruhan dipakai untuk mengurutkan cluster dari prestasi relatif tertinggi ke terendah dan membuat peta peringkat."),
    ("21. Fungsi label prestasi relatif", 307, 320,
     "Menghasilkan label interpretatif berdasarkan posisi cluster. Peringkat pertama disebut tertinggi, peringkat terakhir disebut terendah, dan pada tiga cluster posisi kedua disebut sedang. Untuk jumlah cluster lain, label berisi peringkat dan total cluster."),
    ("22. Menghapus analisis lama", 322, 325,
     "Menghapus interpretasi cluster lama untuk kelas dan jenis keseluruhan sebelum analisis terbaru disimpan."),
    ("23. Menyusun interpretasi tiap cluster", 327, 358,
     "Untuk setiap cluster, mata pelajaran diurutkan berdasarkan nilai rata-rata. Dua tertinggi menjadi mapel unggul dan dua terendah menjadi mapel yang perlu ditingkatkan. Nilai diformat dua angka desimal. Program lalu menyusun kalimat interpretasi berisi jumlah siswa, rata-rata, posisi prestasi relatif, keunggulan, dan kelemahan."),
    ("24. Menyimpan interpretasi cluster", 360, 375,
     "Memasukkan kelas, nama cluster, teks interpretasi, dan jenis keseluruhan ke tabel analisis_cluster. Nomor cluster kembali ditambah satu agar ramah pengguna. commit menyimpan seluruh interpretasi."),
    ("25. Menutup proses dan koneksi", 377, 381,
     "Menampilkan pesan keberhasilan, lalu menutup cursor dan koneksi mysql.connector serta membuang resource engine SQLAlchemy. Penutupan ini mencegah koneksi database tertinggal setelah program selesai."),
]
def run(text, bold=False, italic=False, font="Calibri", size=22, color=None):
    props = [f'<w:rFonts w:ascii="{font}" w:hAnsi="{font}"/>', f'<w:sz w:val="{size}"/>']
    if bold:
        props.append("<w:b/>")
    if italic:
        props.append("<w:i/>")
    if color:
        props.append(f'<w:color w:val="{color}"/>')
    safe_text = escape(text).replace("\n", '</w:t><w:br/><w:t xml:space="preserve">')
    return f'<w:r><w:rPr>{"".join(props)}</w:rPr><w:t xml:space="preserve">{safe_text}</w:t></w:r>'
def paragraph(text="", style=None, bold=False, italic=False, font="Calibri", size=22, color=None, before=0, after=120):
    ppr = [f'<w:spacing w:before="{before}" w:after="{after}" w:line="276" w:lineRule="auto"/>']
    if style:
        ppr.append(f'<w:pStyle w:val="{style}"/>')
    return f'<w:p><w:pPr>{"".join(ppr)}</w:pPr>{run(text, bold, italic, font, size, color)}</w:p>'
def table(rows, widths=(2600, 6500)):
    xml = ['<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="0" w:type="auto"/><w:tblLook w:val="04A0"/></w:tblPr>']
    for r_index, row in enumerate(rows):
        xml.append('<w:tr>')
        for idx, value in enumerate(row):
            shade = '<w:shd w:fill="D9EAF7"/>' if r_index == 0 else ''
            xml.append(f'<w:tc><w:tcPr><w:tcW w:w="{widths[idx]}" w:type="dxa"/>{shade}<w:vAlign w:val="top"/></w:tcPr>')
            xml.append(paragraph(value, bold=r_index == 0, font="Consolas" if idx == 0 and r_index else "Calibri", size=18 if idx == 0 and r_index else 20, after=60))
            xml.append('</w:tc>')
        xml.append('</w:tr>')
    xml.append('</w:tbl>')
    return ''.join(xml)
def explain_line(number, line):
    """Create a concise explanation for one physical source-code line."""
    s = line.strip()
    section = next((title for title, start, end, _ in SECTIONS if start <= number <= end), "Bagian program")
    context = section.split(". ", 1)[-1].lower()
    exact = {
        "import os": "Mengimpor modul os untuk menangani path dan direktori.",
        "import re": "Mengimpor modul regular expression untuk validasi pola dan pembuatan slug.",
        "import sys": "Mengimpor sys untuk membaca argumen command line dan menghentikan program.",
        "import configparser": "Mengimpor pembaca file konfigurasi berformat INI.",
        "from urllib.parse import quote_plus": "Mengimpor quote_plus untuk mengodekan karakter khusus pada URL database.",
        "import mysql.connector": "Mengimpor driver MySQL yang menyediakan koneksi dan cursor.",
        "import pandas as pd": "Mengimpor pandas dengan alias pd untuk pengolahan DataFrame.",
        "from chart_utils import save_elbow_chart": "Mengimpor fungsi lokal pembuat grafik elbow.",
        "from sqlalchemy import create_engine, text": "Mengimpor pembuat engine SQLAlchemy dan pembungkus query text.",
        "from sklearn.cluster import KMeans": "Mengimpor algoritma clustering K-Means.",
        "from sklearn.metrics import silhouette_score": "Mengimpor metrik Silhouette Score untuk evaluasi cluster.",
        "from sklearn.preprocessing import MinMaxScaler": "Mengimpor normalisasi Min-Max untuk menyetarakan skala fitur.",
        "database_config = configparser.ConfigParser()": "Membuat objek parser yang akan menampung konfigurasi database.",
        "sys.exit(1)": "Menghentikan program dengan status 1 yang menandakan terjadi kesalahan.",
        "sys.exit(0)": "Menghentikan program secara normal karena proses tidak dapat atau tidak perlu dilanjutkan.",
        "kelas = sys.argv[1].strip()": "Mengambil argumen pertama sebagai kelas dan menghapus spasi di tepinya.",
        "tahun_ajaran = sys.argv[2].strip()": "Mengambil argumen kedua sebagai tahun ajaran dan menghapus spasi di tepinya.",
        "value = value.lower()": "Mengubah teks menjadi huruf kecil agar format slug konsisten.",
        "value = value.strip('_')": "Menghapus garis bawah yang tersisa di awal atau akhir slug.",
        "return value or 'kelas'": "Mengembalikan slug; jika kosong, menggunakan nilai cadangan kelas.",
        "df = df.fillna(0)": "Mengganti nilai kosong pada DataFrame dengan angka 0.",
        "scaler = MinMaxScaler()": "Membuat objek normalisasi Min-Max.",
        "data_normal = scaler.fit_transform(fitur)": "Mempelajari nilai minimum/maksimum fitur lalu mengubahnya ke skala 0–1.",
        "hasil_uji = []": "Membuat list kosong untuk menampung evaluasi setiap nilai k.",
        "jumlah_data = len(df)": "Menghitung jumlah siswa atau baris data.",
        "k_values = []": "Membuat list penampung nilai k untuk grafik elbow.",
        "inertia_values = []": "Membuat list penampung inertia untuk grafik elbow.",
        "cluster_uji = kmeans_uji.fit_predict(data_normal)": "Melatih model uji dan menghasilkan label cluster setiap siswa.",
        "k_values.append(k)": "Menambahkan k saat ini ke daftar sumbu horizontal grafik.",
        "inertia_values.append(kmeans_uji.inertia_)": "Menambahkan inertia model saat ini ke daftar evaluasi.",
        "score = silhouette_score(data_normal, cluster_uji)": "Menghitung Silhouette Score dari hasil cluster uji.",
        "hasil_uji_df = pd.DataFrame(hasil_uji)": "Mengubah kumpulan hasil evaluasi menjadi DataFrame.",
        "cursor = conn.cursor()": "Membuat cursor untuk menjalankan perintah SQL melalui mysql.connector.",
        "conn.commit()": "Menyimpan perubahan transaksi database secara permanen.",
        "os.makedirs(output_dir, exist_ok=True)": "Membuat folder output jika belum ada tanpa error bila sudah ada.",
        "df['cluster'] = kmeans.fit_predict(data_normal)": "Melatih K-Means final dan menyimpan label cluster ke DataFrame.",
        "kolom_nilai = df.select_dtypes(include=['number']).columns.tolist()": "Mengambil daftar semua kolom yang bertipe numerik.",
        "hasil_analisis = df.groupby('cluster')[kolom_nilai].mean()": "Menghitung rata-rata setiap nilai numerik untuk masing-masing cluster.",
        "jumlah_siswa_cluster = df.groupby('cluster').size()": "Menghitung jumlah siswa yang menjadi anggota setiap cluster.",
        "jumlah_cluster = len(urutan_cluster)": "Menghitung banyak cluster yang terbentuk.",
        "cursor.execute(sql, value)": "Menjalankan query INSERT menggunakan data siswa pada tuple value.",
        "print(\"\\nData clustering keseluruhan berhasil disimpan\")": "Menampilkan pemberitahuan bahwa seluruh proses berhasil.",
        "cursor.close()": "Menutup cursor database.",
        "conn.close()": "Menutup koneksi mysql.connector.",
        "engine.dispose()": "Melepaskan pool dan resource koneksi SQLAlchemy.",
    }
    if s in exact:
        return exact[s]
    if not s:
        return "Baris kosong yang memisahkan blok kode agar lebih mudah dibaca."
    if s.startswith("#"):
        return "Komentar kode: " + s.lstrip("# ")
    if s.startswith("def "):
        return f"Mendefinisikan fungsi {s[4:s.find('(')]} beserta parameter masukannya."
    if s.startswith("if "):
        return f"Memulai pemeriksaan kondisi pada bagian {context}; blok di bawahnya dijalankan jika kondisi benar."
    if s.startswith("for "):
        return f"Memulai perulangan pada bagian {context} untuk memproses setiap item secara berurutan."
    if s.startswith("return "):
        return "Mengembalikan hasil dari fungsi kepada kode yang memanggilnya."
    if s.startswith("print("):
        return "Menampilkan pesan tersebut ke output agar pemanggil mengetahui kondisi program."
    if s.startswith("cursor.execute"):
        return f"Menjalankan perintah SQL pada bagian {context}."
    if s.startswith("DELETE") or s.startswith("WHERE ") or s.startswith("FROM ") or s.startswith("JOIN ") or s.startswith("SELECT") or s.startswith("INSERT INTO") or s.startswith("VALUES") or s.startswith("ON ") or s.startswith("AND "):
        return f"Merupakan klausa SQL yang menyusun operasi database pada bagian {context}."
    if s.startswith("lambda "):
        return "Membuat fungsi singkat tanpa nama untuk mengubah setiap nilai yang diproses."
    if s.startswith("f\"") or s.startswith("f'"):
        return f"Menyusun bagian teks dinamis dengan f-string pada proses {context}."
    if s.startswith("'") or s.startswith('"'):
        return f"Menyediakan nilai teks/argumen lanjutan untuk proses {context}."
    if s in {"(", ")", "[", "]", "{", "}", "))", ")))", "})", "])"} or s.startswith(")") or s.startswith("]") or s.startswith("}"):
        return f"Menutup susunan argumen, koleksi, atau pemanggilan fungsi pada bagian {context}."
    if s.endswith("(") or s.endswith("[") or s.endswith("{"):
        return f"Memulai susunan nilai atau pemanggilan multi-baris pada bagian {context}."
    if "=" in s and "==" not in s and ">=" not in s and "<=" not in s:
        name = s.split("=", 1)[0].strip()
        return f"Mengisi atau memperbarui variabel/kolom {name} untuk kebutuhan {context}."
    if s.endswith(","):
        return f"Memberikan argumen atau elemen lanjutan untuk operasi {context}."
    return f"Menjalankan bagian dari proses {context}."
def make_document(compact=False):
    source_lines = SOURCE.read_text(encoding="utf-8").splitlines()
    body = []
    body.append(paragraph("PENJELASAN KODE" if not compact else "PENJELASAN RINGKAS KODE", bold=True, size=36, color="1F4E78", after=80))
    body.append(paragraph("kmeans_keseluruhan.py", bold=True, font="Consolas", size=30, color="2F5597", after=240))
    body.append(paragraph("Dokumen ini menjelaskan fungsi setiap bagian kode program clustering nilai siswa secara keseluruhan menggunakan algoritma K-Means.", italic=True, size=22, after=240))
    body.append(paragraph("A. Tujuan Program", style="Heading1", bold=True, size=28, color="1F4E78", before=160))
    body.append(paragraph("Program mengambil nilai seluruh mata pelajaran siswa dalam satu kelas, membentuk fitur nilai per siswa, melakukan normalisasi, menentukan jumlah cluster terbaik, menjalankan K-Means, lalu menyimpan hasil evaluasi, hasil cluster siswa, grafik elbow, dan interpretasi setiap cluster ke database."))
    body.append(paragraph("B. Alur Singkat", style="Heading1", bold=True, size=28, color="1F4E78", before=160))
    flow = [
        "1. Membaca konfigurasi dan parameter kelas/tahun ajaran.",
        "2. Mengambil nilai siswa dari database dan membentuk matriks siswa × mata pelajaran.",
        "3. Menormalisasi nilai dengan Min-Max Scaling.",
        "4. Menguji beberapa nilai k menggunakan inertia dan Silhouette Score.",
        "5. Menjalankan K-Means final dengan k terbaik.",
        "6. Menyimpan hasil, membuat grafik elbow, dan membentuk interpretasi cluster.",
    ]
    for item in flow:
        body.append(paragraph(item, after=60))
    body.append(paragraph("C. Penjelasan Tiap Bagian Kode", style="Heading1", bold=True, size=28, color="1F4E78", before=200))
    if compact:
        summary_rows = [["Bagian", "Fungsi ringkas"]]
        for title, _, _, explanation in SECTIONS:
            short = explanation.split(". ", 1)[0].rstrip(".") + "."
            summary_rows.append([title, short])
        body.append(table(summary_rows, widths=(3100, 6000)))
    else:
        for title, start, end, explanation in SECTIONS:
            code = "\n".join(f"{n:03d} | {source_lines[n-1]}" for n in range(start, min(end, len(source_lines)) + 1))
            body.append(paragraph(title, style="Heading2", bold=True, size=24, color="2F5597", before=160, after=80))
            body.append(table([["Kode (dengan nomor baris)", "Penjelasan"], [code, explanation]]))
    body.append(paragraph("D. Penjelasan Per Baris", style="Heading1", bold=True, size=28, color="1F4E78", before=240))
    body.append(paragraph("Tabel berikut menjelaskan setiap baris kode secara singkat." if compact else "Tabel berikut menjelaskan setiap baris fisik pada file sumber. Baris kosong tetap dicantumkan karena berfungsi sebagai pemisah visual antarblok."))
    per_line_rows = [["Baris dan kode", "Penjelasan"]]
    for number, source_line in enumerate(source_lines, 1):
        if compact and not source_line.strip():
            continue
        shown = source_line if source_line else "(baris kosong)"
        explanation = explain_line(number, source_line)
        if compact:
            explanation = (explanation
                .replace("Mengimpor ", "Import ")
                .replace("Membuat ", "Membuat ")
                .replace("Menjalankan bagian dari proses ", "Bagian proses ")
                .replace("Merupakan klausa SQL yang menyusun operasi database pada bagian ", "Klausa SQL untuk ")
                .replace("Memberikan argumen atau elemen lanjutan untuk operasi ", "Argumen lanjutan untuk ")
                .replace("Menutup susunan argumen, koleksi, atau pemanggilan fungsi pada bagian ", "Menutup blok ")
                .replace("Memulai susunan nilai atau pemanggilan multi-baris pada bagian ", "Membuka blok ")
                .replace("Mengisi atau memperbarui variabel/kolom ", "Mengisi ")
                .replace(" untuk kebutuhan ", " untuk "))
        per_line_rows.append([f"{number:03d} | {shown}", explanation])
    body.append(table(per_line_rows, widths=(3900, 5200)))
    body.append(paragraph("E. Arti Parameter Penting", style="Heading1", bold=True, size=28, color="1F4E78", before=240))
    params = [
        ["Parameter", "Arti"],
        ["n_clusters", "Jumlah kelompok yang akan dibentuk oleh K-Means."],
        ["random_state=42", "Menetapkan seed agar hasil pengacakan konsisten ketika program dijalankan ulang."],
        ["n_init=10", "Menjalankan K-Means sepuluh kali dengan centroid awal berbeda dan memilih hasil terbaik."],
        ["inertia", "Jumlah kuadrat jarak setiap data ke centroid cluster; lebih kecil berarti cluster lebih rapat."],
        ["silhouette_score", "Ukuran kualitas pemisahan cluster, umumnya antara -1 dan 1; nilai lebih tinggi lebih baik."],
        ["MinMaxScaler", "Mengubah setiap fitur ke skala 0–1 agar fitur dapat dibandingkan secara adil."],
        ["mapel_id = None", "Menandakan bahwa hasil cluster mencakup semua mata pelajaran, bukan satu mapel tertentu."],
    ]
    body.append(table(params, widths=(2500, 6600)))
    body.append(paragraph("F. Catatan Teknis", style="Heading1", bold=True, size=28, color="1F4E78", before=240))
    notes = [
        "Nomor cluster internal scikit-learn dimulai dari 0, tetapi tampilan pengguna dimulai dari 1.",
        "Kategori Cluster 1, Cluster 2, dan seterusnya bukan otomatis berarti terbaik atau terburuk; urutan prestasi ditentukan kembali dari rata-rata nilai cluster.",
        "Pengisian nilai kosong dengan 0 dapat memengaruhi jarak clustering dan perlu sesuai dengan kebijakan pengolahan data sekolah.",
        "Program menggunakan transaksi commit, tetapi belum menampilkan blok try/except/finally untuk rollback dan penutupan koneksi apabila terjadi kesalahan di tengah proses.",
    ]
    for note in notes:
        body.append(paragraph("• " + note, after=70))
    body.append('<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/><w:cols w:space="708"/><w:docGrid w:linePitch="360"/></w:sectPr>')
    document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' + ''.join(body) + '</w:body></w:document>'
    content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>'
    rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>'
    doc_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
    styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style><w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:qFormat/></w:style><w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:qFormat/></w:style><w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/><w:tblPr><w:tblBorders><w:top w:val="single" w:sz="4" w:color="B4C6E7"/><w:left w:val="single" w:sz="4" w:color="B4C6E7"/><w:bottom w:val="single" w:sz="4" w:color="B4C6E7"/><w:right w:val="single" w:sz="4" w:color="B4C6E7"/><w:insideH w:val="single" w:sz="4" w:color="B4C6E7"/><w:insideV w:val="single" w:sz="4" w:color="B4C6E7"/></w:tblBorders></w:tblPr></w:style></w:styles>'
    core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Penjelasan Kode KMeans Keseluruhan</dc:title><dc:creator>Codex</dc:creator><dc:subject>Dokumentasi kmeans_keseluruhan.py</dc:subject></cp:coreProperties>'
    app = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Microsoft Office Word</Application></Properties>'
    target = OUTPUT_RINGKAS if compact else OUTPUT
    with ZipFile(target, "w", ZIP_DEFLATED) as docx:
        docx.writestr("[Content_Types].xml", content_types)
        docx.writestr("_rels/.rels", rels)
        docx.writestr("word/document.xml", document)
        docx.writestr("word/styles.xml", styles)
        docx.writestr("word/_rels/document.xml.rels", doc_rels)
        docx.writestr("docProps/core.xml", core)
        docx.writestr("docProps/app.xml", app)
if __name__ == "__main__":
    make_document()
    make_document(compact=True)
    print(OUTPUT_RINGKAS)
