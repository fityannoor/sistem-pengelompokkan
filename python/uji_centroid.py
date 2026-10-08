"""
Menampilkan centroid K-Means dan jarak setiap siswa melalui terminal.
Skrip ini hanya membaca database. Tidak ada hasil yang disimpan, diubah,
atau dihapus.
"""
import argparse
import configparser
import os
import re
import sys
import warnings
from urllib.parse import quote_plus
os.environ.setdefault("LOKY_MAX_CPU_COUNT", "1")
warnings.filterwarnings(
    "ignore",
    message="Could not find the number of physical cores.*",
    category=UserWarning,
)
import numpy as np
import pandas as pd
from sqlalchemy import create_engine, text
from sklearn.cluster import KMeans
from sklearn.metrics import silhouette_score
from sklearn.preprocessing import MinMaxScaler
ROOT_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
CONFIG_PATH = os.path.join(ROOT_DIR, "config", "database.ini")
def parse_args():
    parser = argparse.ArgumentParser(
        description=(
            "Uji read-only untuk menampilkan titik centroid dan jarak "
            "Euclidean nilai siswa ke setiap centroid."
        )
    )
    parser.add_argument(
        "kelas",
        help='Nama kelas, contoh: "XI Perhotelan"',
    )
    parser.add_argument(
        "--k",
        type=int,
        help=(
            "Jumlah cluster. Jika tidak diisi, K terbaik ditentukan dengan "
            "cara yang sama seperti proses clustering aplikasi."
        ),
    )
    parser.add_argument(
        "--nama",
        help="Tampilkan jarak siswa yang namanya mengandung teks ini.",
    )
    parser.add_argument(
        "--limit",
        type=int,
        help="Batasi jumlah siswa pada tabel jarak (centroid tetap dihitung dari semua siswa).",
    )
    parser.add_argument(
        "--desimal",
        type=int,
        default=4,
        help="Jumlah angka desimal pada keluaran (default: 4).",
    )
    args = parser.parse_args()
    args.kelas = args.kelas.strip()
    if not args.kelas:
        parser.error("kelas tidak boleh kosong")
    if args.k is not None and args.k < 2:
        parser.error("--k minimal 2")
    if args.limit is not None and args.limit < 1:
        parser.error("--limit minimal 1")
    if not 0 <= args.desimal <= 10:
        parser.error("--desimal harus berada pada rentang 0 sampai 10")
    return args
def create_database_engine():
    database_config = configparser.ConfigParser()
    if not database_config.read(CONFIG_PATH, encoding="utf-8"):
        raise RuntimeError(
            f"Konfigurasi database tidak ditemukan: {CONFIG_PATH}"
        )
    app_config = database_config["application"]
    db_host = app_config.get("host", "localhost")
    db_name = app_config.get("database", "skripsi_kmeans")
    db_user = app_config.get("username", "")
    db_password = app_config.get("password", "")
    return create_engine(
        f"mysql+pymysql://{quote_plus(db_user)}:{quote_plus(db_password)}"
        f"@{db_host}/{quote_plus(db_name)}"
    )
def load_student_scores(engine, kelas):
    query = text(
        """
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
        """
    )
    df_raw = pd.read_sql(query, engine, params={"kelas": kelas})
    if df_raw.empty:
        raise ValueError(f"Data nilai untuk kelas {kelas!r} tidak ditemukan.")
    df = df_raw.pivot_table(
        index=["id", "nama_siswa"],
        columns="nama_mapel",
        values="nilai",
        aggfunc="mean",
    ).reset_index()
    df = df.fillna(0)
    kolom_agama_ada = [
        kolom for kolom in df.columns if "agama" in str(kolom).lower()
    ]
    if kolom_agama_ada:
        df["Pendidikan Agama dan Budi Pekerti"] = (
            df[kolom_agama_ada].sum(axis=1)
        )
        df = df.drop(columns=kolom_agama_ada)
    return df
def select_best_k(data_normal):
    jumlah_data = len(data_normal)
    if jumlah_data < 3:
        raise ValueError(
            "Data minimal 3 siswa untuk evaluasi jumlah cluster."
        )
    max_k = min(int(jumlah_data * 0.5), jumlah_data - 1)
    max_k = max(max_k, 2)
    hasil_uji = []
    for k in range(2, max_k + 1):
        model = KMeans(n_clusters=k, random_state=42, n_init=10)
        labels = model.fit_predict(data_normal)
        jumlah_label = np.unique(labels).size
        if jumlah_label < 2 or jumlah_label >= jumlah_data:
            continue
        hasil_uji.append(
            {
                "k": k,
                "score": silhouette_score(data_normal, labels),
                "inertia": model.inertia_,
            }
        )
    if not hasil_uji:
        raise ValueError(
            "Tidak ada kandidat K yang valid. Periksa variasi nilai siswa."
        )
    hasil_uji_df = pd.DataFrame(hasil_uji)
    hasil_uji_df["penurunan_inertia"] = (
        hasil_uji_df["inertia"].shift(1) - hasil_uji_df["inertia"]
    )
    rata_penurunan = hasil_uji_df["penurunan_inertia"].mean()
    kandidat_elbow_df = hasil_uji_df[
        hasil_uji_df["penurunan_inertia"] >= rata_penurunan
    ].copy()
    if kandidat_elbow_df.empty:
        kandidat_elbow_df = hasil_uji_df.copy()
    best_index = kandidat_elbow_df["score"].idxmax()
    return int(kandidat_elbow_df.loc[best_index, "k"])
def cluster_number(label):
    return f"Cluster {int(label) + 1}"
def print_separator(title):
    print(f"\n{'=' * 80}")
    print(title)
    print("=" * 80)
def print_centroids(model, scaler, feature_names, decimals):
    cluster_labels = [
        cluster_number(index) for index in range(model.n_clusters)
    ]
    centroid_normal = pd.DataFrame(
        model.cluster_centers_.T,
        index=feature_names,
        columns=cluster_labels,
    )
    centroid_asli = pd.DataFrame(
        scaler.inverse_transform(model.cluster_centers_).T,
        index=feature_names,
        columns=cluster_labels,
    )
    centroid_normal.index.name = "Mata Pelajaran"
    centroid_asli.index.name = "Mata Pelajaran"
    print_separator("TITIK CENTROID PADA DATA NORMALISASI (DIPAKAI K-MEANS)")
    print(centroid_normal.round(decimals).to_string())
    print_separator("TITIK CENTROID PADA SKALA NILAI ASLI")
    print(centroid_asli.round(decimals).to_string())
def print_cluster_members(df, labels, total_clusters):
    """Menampilkan ID dan nama siswa yang menjadi anggota setiap cluster."""
    data_anggota = df[["id", "nama_siswa"]].copy()
    data_anggota["cluster"] = labels
    print_separator("ANGGOTA SETIAP CLUSTER")
    for cluster_index in range(total_clusters):
        anggota = data_anggota[
            data_anggota["cluster"] == cluster_index
        ][["id", "nama_siswa"]]
        print(
            f"\nCluster {cluster_index + 1} "
            f"({len(anggota)} siswa)"
        )
        print("-" * 80)
        if anggota.empty:
            print("Tidak ada anggota.")
        else:
            print(anggota.to_string(index=False))
def build_distance_table(df, data_normal, model, decimals):
    distances = model.transform(data_normal)
    assigned_labels = model.labels_
    result = df[["id", "nama_siswa"]].copy()
    for cluster_index in range(model.n_clusters):
        result[f"Jarak C{cluster_index + 1}"] = distances[:, cluster_index]
    result["Jarak Terdekat"] = distances.min(axis=1)
    result["Cluster Terpilih"] = [
        cluster_number(label) for label in assigned_labels
    ]
    distance_columns = [
        column for column in result.columns if column.startswith("Jarak")
    ]
    result[distance_columns] = result[distance_columns].round(decimals)
    return result
def main():
    args = parse_args()
    engine = None
    try:
        engine = create_database_engine()
        df = load_student_scores(engine, args.kelas)
        fitur = df.drop(columns=["id", "nama_siswa"])
        scaler = MinMaxScaler()
        data_normal = scaler.fit_transform(fitur)
        jumlah_siswa = len(df)
        if args.k is not None:
            if args.k >= jumlah_siswa:
                raise ValueError(
                    f"--k harus lebih kecil dari jumlah siswa ({jumlah_siswa})."
                )
            jumlah_cluster = args.k
            sumber_k = "parameter --k"
        else:
            jumlah_cluster = select_best_k(data_normal)
            sumber_k = "pemilihan otomatis aplikasi"
        model = KMeans(
            n_clusters=jumlah_cluster,
            random_state=42,
            n_init=10,
        )
        labels = model.fit_predict(data_normal)
        if np.unique(labels).size < 2:
            raise ValueError(
                "Model hanya membentuk satu cluster. Periksa variasi nilai siswa."
            )
        jarak_sklearn = model.transform(data_normal)
        jarak_manual = np.sqrt(
            (
                (
                    data_normal[:, np.newaxis, :]
                    - model.cluster_centers_[np.newaxis, :, :]
                )
                ** 2
            ).sum(axis=2)
        )
        if not np.allclose(jarak_sklearn, jarak_manual):
            raise RuntimeError("validasi perhitungan jarak Euclidean gagal")
        if not np.array_equal(labels, np.argmin(jarak_manual, axis=1)):
            raise RuntimeError("validasi pemilihan centroid terdekat gagal")
        score = silhouette_score(data_normal, labels)
        print_separator("PENGUJIAN CENTROID DAN JARAK K-MEANS (READ-ONLY)")
        print(f"Kelas              : {args.kelas}")
        print(f"Jumlah siswa       : {jumlah_siswa}")
        print(f"Jumlah fitur/mapel : {fitur.shape[1]}")
        print(f"Jumlah cluster (K) : {jumlah_cluster} ({sumber_k})")
        print(f"Jumlah iterasi     : {model.n_iter_}")
        print(f"Silhouette Score   : {score:.{args.desimal}f}")
        print("Rumus jarak        : d(x,c) = sqrt(sum((x_i - c_i)^2))")
        print("Validasi jarak     : OK (rumus manual = hasil model)")
        print("Status database    : hanya dibaca; tidak ada data yang diubah")
        print_separator("URUTAN FITUR DALAM PERHITUNGAN")
        for index, feature_name in enumerate(fitur.columns, start=1):
            print(f"{index:>2}. {feature_name}")
        print_centroids(
            model,
            scaler,
            fitur.columns,
            args.desimal,
        )
        print_cluster_members(
            df,
            labels,
            jumlah_cluster,
        )
        distance_table = build_distance_table(
            df,
            data_normal,
            model,
            args.desimal,
        )
        if args.nama:
            mask = distance_table["nama_siswa"].str.contains(
                re.escape(args.nama),
                case=False,
                na=False,
            )
            distance_table = distance_table[mask]
        if args.limit is not None:
            distance_table = distance_table.head(args.limit)
        print_separator("JARAK NILAI SISWA KE SETIAP CENTROID (DATA NORMALISASI)")
        if distance_table.empty:
            print("Tidak ada siswa yang cocok dengan filter --nama.")
        else:
            print(distance_table.to_string(index=False))
        print(
            "\nKeterangan: cluster terpilih adalah centroid dengan jarak "
            "Euclidean paling kecil."
        )
        return 0
    except (KeyError, RuntimeError, ValueError) as error:
        print(f"ERROR: {error}", file=sys.stderr)
        return 1
    except Exception as error:
        print(f"ERROR: gagal menjalankan pengujian: {error}", file=sys.stderr)
        return 1
    finally:
        if engine is not None:
            engine.dispose()
if __name__ == "__main__":
    sys.exit(main())
