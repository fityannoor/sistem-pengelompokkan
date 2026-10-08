import os
import re
import sys
import json
import configparser
from urllib.parse import quote_plus
import mysql.connector
import pandas as pd
from chart_utils import save_elbow_chart
from sqlalchemy import create_engine, text
from sklearn.cluster import KMeans
from sklearn.metrics import silhouette_score
from sklearn.preprocessing import MinMaxScaler
config_path = os.path.abspath(
    os.path.join(os.path.dirname(__file__), '..', 'config', 'database.ini')
)
database_config = configparser.ConfigParser()
if not database_config.read(config_path, encoding='utf-8'):
    print("Konfigurasi database tidak ditemukan")
    sys.exit(1)
app_config = database_config['application']
db_host = app_config.get('host', 'localhost')
db_name = app_config.get('database', 'skripsi_kmeans')
db_user = app_config.get('username', '')
db_password = app_config.get('password', '')
engine = create_engine(
    f"mysql+pymysql://{quote_plus(db_user)}:{quote_plus(db_password)}"
    f"@{db_host}/{quote_plus(db_name)}"
)
conn = mysql.connector.connect(
    host=db_host,
    user=db_user,
    password=db_password,
    database=db_name
)
if len(sys.argv) < 3:
    print("Parameter kelas dan tahun ajaran wajib diisi")
    sys.exit(1)
kelas = sys.argv[1].strip()
tahun_ajaran = sys.argv[2].strip()
match_tahun = re.fullmatch(r'(\d{4})/(\d{4})', tahun_ajaran)
if not match_tahun or int(match_tahun.group(2)) != int(match_tahun.group(1)) + 1:
    print("Format tahun ajaran tidak valid")
    sys.exit(1)
def slugify(value):
    value = value.lower()
    value = re.sub(r'[^a-z0-9]+', '_', value)
    value = value.strip('_')
    return value or 'kelas'
query = text("""
SELECT
    siswa.id,
    siswa.nama_siswa,
    mata_pelajaran.nama_mapel,
    nilai_detail.nilai
FROM nilai_detail
JOIN siswa
    ON nilai_detail.siswa_id = siswa.id
JOIN mata_pelajaran
    ON nilai_detail.mapel_id = mata_pelajaran.id
WHERE
    siswa.kelas = :kelas
    AND nilai_detail.nilai IS NOT NULL
""")
df_raw = pd.read_sql(query, engine, params={'kelas': kelas})
if df_raw.empty:
    print("Data kosong")
    sys.exit(0)
df = df_raw.pivot_table(
    index=['id', 'nama_siswa'],
    columns='nama_mapel',
    values='nilai',
    aggfunc='mean'
).reset_index()
df = df.fillna(0)
kolom_agama_ada = [
    kolom for kolom in df.columns
    if 'agama' in str(kolom).lower()
]
if kolom_agama_ada:
    df['Pendidikan Agama dan Budi Pekerti'] = (
        df[kolom_agama_ada].sum(axis=1)
    )
    df = df.drop(columns=kolom_agama_ada)
fitur = df.drop(columns=['id', 'nama_siswa'])
scaler = MinMaxScaler()
data_normal = scaler.fit_transform(fitur)
hasil_uji = []  
jumlah_data = len(df)
max_k = min(
    int(len(df) * 0.5),
    len(df) - 1
)
if jumlah_data < 3:
    print("Data minimal 3 siswa untuk evaluasi jumlah cluster")
    sys.exit(0)
if max_k < 2:
    max_k = 2
k_values = []
inertia_values = []
for k in range(2, max_k + 1):
    kmeans_uji = KMeans(
        n_clusters=k,
        random_state=42,
        n_init=10
    )
    cluster_uji = kmeans_uji.fit_predict(data_normal)
    k_values.append(k)
    inertia_values.append(kmeans_uji.inertia_)
    score = silhouette_score(data_normal, cluster_uji)
    hasil_uji.append({
        'k': k,
        'score': score,
        'inertia': kmeans_uji.inertia_,
    })
hasil_uji_df = pd.DataFrame(hasil_uji)
hasil_uji_df['penurunan_inertia'] = (
    hasil_uji_df['inertia'].shift(1) - hasil_uji_df['inertia']
)
rata_penurunan = hasil_uji_df['penurunan_inertia'].mean()
kandidat_elbow_df = hasil_uji_df[
    hasil_uji_df['penurunan_inertia'] >= rata_penurunan
].copy()
if kandidat_elbow_df.empty:
    kandidat_elbow_df = hasil_uji_df.copy()
k_terbaik = kandidat_elbow_df.loc[
    kandidat_elbow_df['score'].idxmax(),
    'k'
]
cursor = conn.cursor()
cursor.execute(
    "DELETE FROM evaluasi_cluster WHERE kelas = %s AND jenis = %s",
    (kelas, 'keseluruhan')
)
for _, row in kandidat_elbow_df.iterrows():
    cursor.execute("""
        INSERT INTO evaluasi_cluster(
            kelas,
            k,
            inertia,
            silhouette_score,
            jenis
        )
        VALUES(%s,%s,%s,%s,%s)
    """, (
        kelas,
        int(row['k']),
        float(row['inertia']),
        float(row['score']),
        'keseluruhan'
    ))
conn.commit()
output_dir = os.path.abspath(
    os.path.join(
        os.path.dirname(__file__),
        '..',
        'assets',
        'img'
    )
)
os.makedirs(output_dir, exist_ok=True)
save_elbow_chart(
    k_values,
    inertia_values,
    os.path.join(
        output_dir,
        f'elbow_method_keseluruhan_{slugify(kelas)}.svg'
    ),
    int(k_terbaik)
)
kmeans = KMeans(
    n_clusters=int(k_terbaik),
    random_state=42,
    n_init=10
)
df['cluster'] = kmeans.fit_predict(data_normal)
jarak_centroid = kmeans.transform(data_normal)
silhouette = silhouette_score(
    data_normal,
    df['cluster']
)
df['kategori'] = df['cluster'].apply(
    lambda x: f"Cluster {x + 1}"
)
catatan = (
    "Gunakan interpretasi cluster sebagai dasar guru menyusun strategi pembelajaran."
)
cursor.execute("""
DELETE hasil_cluster
FROM hasil_cluster
JOIN siswa
    ON hasil_cluster.siswa_id = siswa.id
WHERE siswa.kelas = %s
""", (kelas,))
sql = """
    INSERT INTO hasil_cluster(
        siswa_id,
        mapel_id,
        cluster,
        kategori,
        silhouette_score,
        iterasi,
        tahun_ajaran,
        catatan
    )
    VALUES(%s,%s,%s,%s,%s,%s,%s,%s)
"""
for _, row in df.iterrows():
    cluster_name = f"Cluster {int(row['cluster']) + 1}"
    value = (
        int(row['id']),
        None,
        cluster_name,
        row['kategori'],
        float(silhouette),
        int(kmeans.n_iter_),
        tahun_ajaran,
        catatan
    )
    cursor.execute(sql, value)
conn.commit()
nama_fitur = [str(nama) for nama in fitur.columns]
minimum_fitur = {
    nama: float(nilai)
    for nama, nilai in zip(nama_fitur, scaler.data_min_)
}
maksimum_fitur = {
    nama: float(nilai)
    for nama, nilai in zip(nama_fitur, scaler.data_max_)
}
centroid_normal = []
centroid_asli = []
centroid_asli_array = scaler.inverse_transform(kmeans.cluster_centers_)
for cluster_index in range(kmeans.n_clusters):
    centroid_normal.append({
        nama: float(nilai)
        for nama, nilai in zip(
            nama_fitur,
            kmeans.cluster_centers_[cluster_index]
        )
    })
    centroid_asli.append({
        nama: float(nilai)
        for nama, nilai in zip(
            nama_fitur,
            centroid_asli_array[cluster_index]
        )
    })
cursor.execute(
    "DELETE FROM ringkasan_perhitungan_kmeans "
    "WHERE kelas = %s AND jenis = %s",
    (kelas, 'keseluruhan')
)
cursor.execute(
    "DELETE FROM detail_perhitungan_kmeans "
    "WHERE kelas = %s AND jenis = %s",
    (kelas, 'keseluruhan')
)
cursor.execute("""
    INSERT INTO ringkasan_perhitungan_kmeans(
        kelas,
        tahun_ajaran,
        jenis,
        fitur_json,
        minimum_json,
        maksimum_json,
        centroid_normal_json,
        centroid_asli_json,
        jumlah_iterasi
    )
    VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s)
""", (
    kelas,
    tahun_ajaran,
    'keseluruhan',
    json.dumps(nama_fitur, ensure_ascii=False),
    json.dumps(minimum_fitur, ensure_ascii=False),
    json.dumps(maksimum_fitur, ensure_ascii=False),
    json.dumps(centroid_normal, ensure_ascii=False),
    json.dumps(centroid_asli, ensure_ascii=False),
    int(kmeans.n_iter_)
))
sql_detail_perhitungan = """
    INSERT INTO detail_perhitungan_kmeans(
        kelas,
        tahun_ajaran,
        jenis,
        siswa_id,
        nilai_asli_json,
        nilai_normalisasi_json,
        jarak_centroid_json,
        cluster_terpilih,
        jarak_terdekat
    )
    VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s)
"""
for posisi, (_, row) in enumerate(df.iterrows()):
    cluster_index = int(row['cluster'])
    nilai_asli = {
        nama: float(nilai)
        for nama, nilai in zip(nama_fitur, fitur.iloc[posisi].to_numpy())
    }
    nilai_normalisasi = {
        nama: float(nilai)
        for nama, nilai in zip(nama_fitur, data_normal[posisi])
    }
    jarak_siswa = {
        f"Cluster {index + 1}": float(nilai)
        for index, nilai in enumerate(jarak_centroid[posisi])
    }
    cursor.execute(sql_detail_perhitungan, (
        kelas,
        tahun_ajaran,
        'keseluruhan',
        int(row['id']),
        json.dumps(nilai_asli, ensure_ascii=False),
        json.dumps(nilai_normalisasi, ensure_ascii=False),
        json.dumps(jarak_siswa, ensure_ascii=False),
        f"Cluster {cluster_index + 1}",
        float(jarak_centroid[posisi, cluster_index])
    ))
conn.commit()
kolom_nilai = df.select_dtypes(include=['number']).columns.tolist()
kolom_nilai = [
    kolom for kolom in kolom_nilai
    if kolom not in ['id', 'cluster']
]
hasil_analisis = df.groupby('cluster')[kolom_nilai].mean()
jumlah_siswa_cluster = df.groupby('cluster').size()
rata_rata_cluster = hasil_analisis.mean(axis=1)
urutan_cluster = rata_rata_cluster.sort_values(
    ascending=False
).index.tolist()
peringkat_cluster = {
    cluster_id: posisi + 1
    for posisi, cluster_id in enumerate(urutan_cluster)
}
jumlah_cluster = len(urutan_cluster)
def label_prestasi_relatif(peringkat, total_cluster):
    if peringkat == 1:
        return "prestasi relatif tertinggi"
    if peringkat == total_cluster:
        return "prestasi relatif terendah"
    if total_cluster == 3:
        return "prestasi relatif sedang"
    return (
        f"prestasi relatif peringkat {peringkat} "
        f"dari {total_cluster} cluster"
    )
cursor.execute("""
DELETE FROM analisis_cluster
WHERE kelas = %s AND jenis = %s
""", (kelas, 'keseluruhan'))
for cluster_id, row in hasil_analisis.iterrows():
    nilai_tertinggi = row.sort_values(ascending=False)
    nilai_terendah = row.sort_values(ascending=True)
    mapel_unggul = nilai_tertinggi.head(2)
    mapel_lemah = nilai_terendah.head(2)
    teks_unggul = ", ".join(
        f"{mapel} ({nilai:.2f})"
        for mapel, nilai in mapel_unggul.items()
    )
    teks_lemah = ", ".join(
        f"{mapel} ({nilai:.2f})"
        for mapel, nilai in mapel_lemah.items()
    )
    peringkat = peringkat_cluster[cluster_id]
    label_prestasi = label_prestasi_relatif(
        peringkat,
        jumlah_cluster
    )
    interpretasi = (
        f"Beranggotakan {int(jumlah_siswa_cluster[cluster_id])} siswa "
        f"dengan rata-rata keseluruhan "
        f"{rata_rata_cluster[cluster_id]:.2f}, sehingga berada pada "
        f"{label_prestasi}. Keunggulan utama terdapat pada "
        f"{teks_unggul}. Mata pelajaran yang relatif paling perlu "
        f"ditingkatkan adalah {teks_lemah}."
    )
    cursor.execute("""
        INSERT INTO analisis_cluster(
            kelas,
            cluster,
            interpretasi,
            jenis
        )
        VALUES(%s,%s,%s,%s)
    """, (
        kelas,
        f"Cluster {int(cluster_id) + 1}",
        interpretasi,
        'keseluruhan'
    ))
conn.commit()
print("\nData clustering keseluruhan berhasil disimpan")
cursor.close()
conn.close()
engine.dispose()
