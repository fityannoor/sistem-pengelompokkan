from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile
from generate_kmeans_explanation_docx import paragraph, table
ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "Rumus_KMeans_Sesuai_Alur_Website.docx"
def formula(text):
    return paragraph(text, bold=True, font="Cambria Math", size=24, color="1F4E78", before=80, after=120)
def bullet(text):
    return paragraph("• " + text, after=70)
def build_body():
    body = []
    body.append(paragraph("RUMUS K-MEANS SESUAI ALUR WEBSITE", bold=True, size=34, color="1F4E78", after=80))
    body.append(paragraph("Academic Analytics – Clustering Nilai Siswa Keseluruhan", bold=True, size=24, color="2F5597", after=220))
    body.append(paragraph(
        "Dokumen ini menyusun rumus berdasarkan alur aktual halaman clustering.php dan program "
        "kmeans_keseluruhan.py. Website hanya menjalankan analisis keseluruhan nilai untuk satu kelas; "
        "tahun ajaran diambil otomatis dari periode nilai kelas.", italic=True, after=220
    ))
    body.append(paragraph("A. Alur Website dan Posisi Rumus", bold=True, size=28, color="1F4E78", before=160))
    flow = [
        ["Tahap", "Proses pada website", "Rumus/operasi"],
        ["1", "Pengguna memilih kelas; sistem memvalidasi kelas dan mengambil tahun ajaran.", "Validasi input (bukan rumus K-Means)"],
        ["2", "Sistem mengambil nilai tidak NULL, lalu membentuk satu baris untuk setiap siswa.", "Rata-rata jika ada nilai ganda"],
        ["3", "Nilai yang tidak tersedia setelah pivot diisi 0 dan variasi kolom agama digabung.", "Imputasi 0 dan penjumlahan per baris"],
        ["4", "Kolom ID dan nama dikeluarkan; nilai mata pelajaran menjadi fitur.", "Matriks fitur X"],
        ["5", "Setiap fitur dinormalisasi ke rentang 0–1.", "Min-Max Scaling"],
        ["6", "Sistem menentukan rentang k dan menguji setiap k.", "Kmax, K-Means, inertia, silhouette"],
        ["7", "Kandidat elbow disaring, lalu k terbaik dipilih.", "ΔI, rata-rata ΔI, maksimum silhouette"],
        ["8", "K-Means final dijalankan dan hasil disimpan.", "Keanggotaan, centroid, konvergensi"],
        ["9", "Profil dan peringkat relatif cluster dibuat dari nilai asli.", "Rata-rata nilai per cluster"],
    ]
    body.append(table(flow, widths=(900, 5000, 3200)))
    body.append(paragraph("1. Pembentukan Data Nilai Siswa", bold=True, size=26, color="2F5597", before=220))
    body.append(paragraph(
        "Query mengambil ID siswa, nama siswa, mata pelajaran, dan nilai berdasarkan kelas. Nilai NULL tidak "
        "diikutkan. Data kemudian diubah menjadi matriks dengan baris sebagai siswa dan kolom sebagai mata pelajaran."
    ))
    formula("x̄ᵢⱼ = (1 / rᵢⱼ) × Σₗ₌₁ʳⁱʲ xᵢⱼₗ")
    body.append(paragraph(
        "Rumus ini hanya berlaku apabila siswa i mempunyai lebih dari satu nilai pada mata pelajaran j, karena "
        "pivot_table menggunakan aggfunc='mean'. Jika hanya satu nilai, hasilnya tetap nilai tersebut."
    ))
    body.append(table([
        ["Simbol", "Keterangan"],
        ["x̄ᵢⱼ", "Nilai yang dipakai untuk siswa i pada mata pelajaran j."],
        ["rᵢⱼ", "Jumlah catatan nilai siswa i pada mata pelajaran j."],
        ["xᵢⱼₗ", "Catatan nilai ke-l."],
    ], widths=(2200, 6900)))
    body.append(paragraph("2. Penanganan Nilai Kosong dan Kolom Agama", bold=True, size=26, color="2F5597", before=220))
    formula("xᵢⱼ* = 0, jika nilai hasil pivot kosong (NaN)")
    body.append(paragraph(
        "Setelah pivot, seluruh sel kosong diisi 0 oleh df.fillna(0). Ini merupakan aturan implementasi website, "
        "bukan bagian wajib dari algoritma K-Means."
    ))
    formula("xᵢ,agama = Σⱼ∈A xᵢⱼ")
    body.append(paragraph(
        "A adalah kumpulan kolom yang namanya mengandung kata 'agama'. Nilai pada kolom tersebut dijumlahkan "
        "menjadi Pendidikan Agama dan Budi Pekerti, lalu kolom asal dihapus."
    ))
    body.append(paragraph("3. Matriks Fitur", bold=True, size=26, color="2F5597", before=220))
    formula("X = [xᵢⱼ],  i = 1, 2, …, n dan j = 1, 2, …, m")
    body.append(paragraph(
        "n adalah jumlah siswa dan m adalah jumlah mata pelajaran setelah penggabungan kolom agama. ID dan nama "
        "siswa tidak menjadi fitur clustering."
    ))
    body.append(paragraph("4. Normalisasi Min-Max", bold=True, size=26, color="2F5597", before=220))
    formula("zᵢⱼ = (xᵢⱼ − min(xⱼ)) / (max(xⱼ) − min(xⱼ))")
    body.append(paragraph(
        "Normalisasi dilakukan per mata pelajaran menggunakan MinMaxScaler. Nilai hasil normalisasi berada pada "
        "rentang 0–1 sehingga setiap mata pelajaran mempunyai skala yang sebanding. Jika sebuah fitur konstan, "
        "scikit-learn menghasilkan nilai 0 untuk fitur tersebut."
    ))
    body.append(paragraph("5. Penentuan Rentang Jumlah Cluster", bold=True, size=26, color="2F5597", before=220))
    formula("Kₘₐₓ = min(⌊0,5 × n⌋, n − 1)")
    formula("K yang diuji = {2, 3, …, Kₘₐₓ}")
    body.append(paragraph(
        "Website mensyaratkan sekurang-kurangnya tiga siswa. Nilai k tidak boleh sama dengan jumlah siswa karena "
        "Silhouette Score membutuhkan jumlah label antara 2 dan n−1. Dalam kode, max_k juga dijaga minimal 2."
    ))
    body.append(paragraph(
        "Contoh ilustratif: jika n=34, Kₘₐₓ=min(17,33)=17 sehingga sistem menguji k=2 sampai k=17. "
        "Angka ini adalah contoh dan berubah mengikuti jumlah siswa pada kelas yang dipilih.", italic=True
    ))
    body.append(paragraph("6. Proses K-Means untuk Setiap Nilai k", bold=True, size=26, color="2F5597", before=220))
    body.append(paragraph(
        "Untuk setiap k, program menjalankan KMeans(n_clusters=k, random_state=42, n_init=10). random_state=42 "
        "membuat hasil dapat direproduksi, sedangkan n_init=10 mencoba sepuluh inisialisasi dan mengambil hasil "
        "dengan inertia terbaik."
    ))
    body.append(paragraph("6.1 Jarak data ke centroid", bold=True, size=23, color="365F91", before=120))
    formula("d(zᵢ, μₖ) = √(Σⱼ₌₁ᵐ (zᵢⱼ − μₖⱼ)²)")
    body.append(paragraph(
        "Jarak Euclidean pada K-Means dihitung antara data siswa yang sudah dinormalisasi dan centroid cluster, "
        "bukan hanya antara dua siswa."
    ))
    body.append(paragraph("6.2 Penentuan keanggotaan", bold=True, size=23, color="365F91", before=120))
    formula("cᵢ = arg minₖ d(zᵢ, μₖ)")
    body.append(paragraph("Siswa ditempatkan pada cluster dengan centroid terdekat."))
    body.append(paragraph("6.3 Pembaruan centroid", bold=True, size=23, color="365F91", before=120))
    formula("μₖ = (1 / |Cₖ|) × Σᵢ∈Cₖ zᵢ")
    body.append(paragraph("Centroid baru adalah rata-rata data normalisasi seluruh anggota cluster k."))
    body.append(paragraph("6.4 Konvergensi", bold=True, size=23, color="365F91", before=120))
    formula("μₖ⁽ᵗ⁾ = μₖ⁽ᵗ⁻¹⁾  atau perubahan centroid ≤ toleransi")
    body.append(paragraph(
        "Iterasi berhenti ketika model mencapai konvergensi sesuai aturan KMeans scikit-learn. Jumlah iterasi aktual "
        "diambil dari kmeans.n_iter_ dan disimpan pada hasil_cluster; karena itu tidak boleh dinyatakan selalu lima iterasi."
    ))
    body.append(paragraph("7. Inertia atau Sum of Squared Error", bold=True, size=26, color="2F5597", before=220))
    formula("Iₖ = Σᵣ₌₁ᵏ Σᵢ∈Cᵣ ‖zᵢ − μᵣ‖²")
    body.append(paragraph(
        "Inertia mengukur kekompakan cluster pada data yang sudah dinormalisasi. Nilainya cenderung turun ketika k "
        "bertambah, sehingga tidak dipakai sendirian untuk menentukan k terbaik."
    ))
    body.append(paragraph("8. Silhouette Score", bold=True, size=26, color="2F5597", before=220))
    formula("a(i) = rata-rata jarak siswa i ke anggota lain dalam cluster yang sama")
    formula("b(i) = min rata-rata jarak siswa i ke setiap cluster lain")
    formula("s(i) = (b(i) − a(i)) / max(a(i), b(i))")
    formula("S(k) = (1 / n) × Σᵢ₌₁ⁿ s(i)")
    body.append(paragraph(
        "S(k) adalah Silhouette Score untuk hasil pengujian nilai k. Nilai mendekati 1 menunjukkan pemisahan yang "
        "lebih baik, mendekati 0 menunjukkan cluster saling berdekatan, dan nilai negatif mengindikasikan sebagian "
        "data mungkin lebih sesuai berada pada cluster lain."
    ))
    body.append(paragraph("9. Penyaringan Kandidat Elbow", bold=True, size=26, color="2F5597", before=220))
    formula("ΔIₖ = Iₖ₋₁ − Iₖ, untuk k = 3, 4, …, Kₘₐₓ")
    formula("ΔĪ = (1 / q) × Σ ΔIₖ")
    formula("Kandidat = {k | ΔIₖ ≥ ΔĪ}")
    body.append(paragraph(
        "Pandas shift(1) membuat penurunan inertia pada k pertama (k=2) bernilai NaN. Fungsi mean mengabaikan NaN, "
        "sehingga penyaringan normal dimulai dari k=3. Jika tidak ada kandidat—misalnya hanya k=2 yang dapat diuji—"
        "kode memakai seluruh hasil pengujian sebagai fallback."
    ))
    body.append(paragraph("10. Pemilihan k Terbaik", bold=True, size=26, color="2F5597", before=220))
    formula("k* = arg maxₖ∈Kandidat S(k)")
    body.append(paragraph(
        "Website memilih Silhouette Score tertinggi hanya dari k yang lolos penyaringan penurunan inertia. Oleh karena "
        "itu, k terbaik bukan selalu nilai silhouette tertinggi dari seluruh k yang diuji. Nilai kandidat dan hasil "
        "terbaik bergantung pada dataset kelas saat proses dijalankan."
    ))
    body.append(paragraph("11. K-Means Final dan Penyimpanan Hasil", bold=True, size=26, color="2F5597", before=220))
    body.append(paragraph(
        "Model K-Means dijalankan kembali menggunakan k*. Program menyimpan cluster setiap siswa, Silhouette Score "
        "final, jumlah iterasi, tahun ajaran, dan catatan. mapel_id diisi NULL karena website melakukan clustering "
        "keseluruhan mata pelajaran, bukan clustering per mata pelajaran."
    ))
    formula("Kategori siswa i = “Cluster ” + (cᵢ + 1)")
    body.append(paragraph("Penambahan satu dilakukan karena label internal scikit-learn dimulai dari 0."))
    body.append(paragraph("12. Profil dan Peringkat Relatif Cluster", bold=True, size=26, color="2F5597", before=220))
    formula("x̄ₖⱼ = (1 / nₖ) × Σᵢ∈Cₖ xᵢⱼ")
    formula("Rₖ = (1 / m) × Σⱼ₌₁ᵐ x̄ₖⱼ")
    body.append(paragraph(
        "Profil cluster dihitung dari nilai asli (bukan nilai normalisasi) agar hasil tetap mudah dibaca pada skala "
        "nilai sekolah. Dua mata pelajaran dengan rata-rata tertinggi menjadi keunggulan relatif dan dua terendah "
        "menjadi mata pelajaran yang relatif perlu ditingkatkan."
    ))
    body.append(paragraph(
        "Cluster diurutkan menurun berdasarkan Rₖ. Urutan pertama diberi label prestasi relatif tertinggi dan urutan "
        "terakhir prestasi relatif terendah. Nomor Cluster 1, Cluster 2, dan seterusnya tidak secara otomatis "
        "menunjukkan tingkat prestasi."
    ))
    body.append(paragraph("13. Grafik Elbow dan Data Evaluasi", bold=True, size=26, color="2F5597", before=220))
    body.append(paragraph(
        "Grafik SVG menampilkan semua pasangan (k, inertia) yang diuji dan menandai k*. Namun tabel evaluasi_cluster "
        "hanya menyimpan baris kandidat elbow. Perbedaan ini sesuai implementasi program saat ini."
    ))
    body.append(paragraph("14. Cyclomatic Complexity (Pengujian, Bukan Proses Clustering)", bold=True, size=26, color="2F5597", before=220))
    formula("V(G) = E − N + 2P")
    body.append(paragraph(
        "Cyclomatic Complexity digunakan dalam white-box testing untuk menghitung jalur independen pada flow graph. "
        "E adalah jumlah edge, N jumlah node, dan P jumlah komponen terhubung (umumnya P=1). Rumus ini tidak "
        "dijalankan oleh halaman clustering maupun kmeans_keseluruhan.py, sehingga ditempatkan sebagai bagian "
        "pengujian terpisah. Nilai E dan N harus mengikuti flow graph versi kode yang diuji."
    ))
    body.append(paragraph("B. Ringkasan Rumus yang Benar-Benar Dipakai Website", bold=True, size=28, color="1F4E78", before=240))
    summary = [
        ["Rumus/operasi", "Dipakai untuk", "Data yang digunakan"],
        ["Rata-rata nilai ganda", "Agregasi saat pivot", "Nilai asli"],
        ["Imputasi 0", "Mengisi sel hasil pivot yang kosong", "Nilai asli"],
        ["Min-Max", "Menyetarakan skala fitur", "Nilai asli → normalisasi"],
        ["Euclidean dan centroid", "Pembentukan cluster K-Means", "Nilai normalisasi"],
        ["Inertia", "Mengukur kekompakan dan membuat grafik elbow", "Nilai normalisasi"],
        ["Silhouette", "Evaluasi dan pemilihan k terbaik", "Nilai normalisasi"],
        ["Rata-rata profil", "Interpretasi keunggulan dan kelemahan", "Nilai asli"],
        ["Cyclomatic Complexity", "White-box testing saja", "Flow graph kode"],
    ]
    body.append(table(summary, widths=(2700, 3900, 2500)))
    body.append(paragraph("C. Catatan Penting untuk Penulisan Penelitian", bold=True, size=28, color="1F4E78", before=240))
    for note in [
        "Sebutkan bahwa website saat ini hanya memproses clustering keseluruhan berdasarkan kelas.",
        "Tahun ajaran bukan dipilih manual saat clustering; nilainya mengikuti periode dataset kelas.",
        "Jumlah siswa, jumlah fitur, kandidat k, k terbaik, inertia, silhouette, dan iterasi bersifat dinamis.",
        "Jika memakai angka 34 siswa, 10 fitur, k=5, atau skor tertentu, beri label sebagai hasil dataset pengujian tertentu, bukan aturan tetap sistem.",
        "Jelaskan bahwa nilai kosong setelah pivot diisi 0 karena keputusan ini dapat memengaruhi hasil jarak dan cluster.",
        "Bedakan nilai normalisasi untuk pembentukan cluster dari nilai asli untuk interpretasi profil cluster.",
    ]:
        body.append(bullet(note))
    body.append('<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/><w:cols w:space="708"/><w:docGrid w:linePitch="360"/></w:sectPr>')
    return body
def create_docx():
    document = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
        + ''.join(build_body()) + '</w:body></w:document>'
    )
    content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>'
    rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>'
    doc_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
    styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style><w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/><w:tblPr><w:tblBorders><w:top w:val="single" w:sz="4" w:color="B4C6E7"/><w:left w:val="single" w:sz="4" w:color="B4C6E7"/><w:bottom w:val="single" w:sz="4" w:color="B4C6E7"/><w:right w:val="single" w:sz="4" w:color="B4C6E7"/><w:insideH w:val="single" w:sz="4" w:color="B4C6E7"/><w:insideV w:val="single" w:sz="4" w:color="B4C6E7"/></w:tblBorders></w:tblPr></w:style></w:styles>'
    core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Rumus K-Means Sesuai Alur Website</dc:title><dc:creator>Codex</dc:creator><dc:subject>Penyesuaian rumus dengan implementasi website</dc:subject></cp:coreProperties>'
    app = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Microsoft Office Word</Application></Properties>'
    with ZipFile(OUTPUT, "w", ZIP_DEFLATED) as docx:
        docx.writestr("[Content_Types].xml", content_types)
        docx.writestr("_rels/.rels", rels)
        docx.writestr("word/document.xml", document)
        docx.writestr("word/styles.xml", styles)
        docx.writestr("word/_rels/document.xml.rels", doc_rels)
        docx.writestr("docProps/core.xml", core)
        docx.writestr("docProps/app.xml", app)
if __name__ == "__main__":
    create_docx()
    print(OUTPUT)
