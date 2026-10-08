"""Membandingkan K-Means tanpa normalisasi dan dengan Min-Max.
Skrip ini hanya membaca data dari database. Tidak ada hasil pengujian yang
disimpan ke tabel aplikasi sehingga proses clustering utama tetap tidak berubah.
Penggunaan:
    python python/perbandingan_normalisasi.py "XI Perhotelan" "2024/2025"
"""
from __future__ import annotations
import configparser
import io
import math
import re
import sys
import warnings
from contextlib import redirect_stderr
from dataclasses import dataclass
from pathlib import Path
from typing import Any
from urllib.parse import quote_plus
import numpy as np
import pandas as pd
from sklearn.cluster import KMeans
from sklearn.exceptions import ConvergenceWarning
from sklearn.metrics import silhouette_score
from sklearn.preprocessing import MinMaxScaler
from sqlalchemy import create_engine, text
from sqlalchemy.engine import Engine
RANDOM_STATE = 42
N_INIT = 10
NAMA_FITUR_AGAMA = "Pendidikan Agama dan Budi Pekerti"
NAMA_FILE_OUTPUT = "hasil_perbandingan_normalisasi.csv"
class KesalahanPengujian(RuntimeError):
    """Kesalahan yang membuat perbandingan tidak dapat dilanjutkan."""
@dataclass
class HasilK:
    """Hasil evaluasi untuk satu nilai K."""
    k: int
    inertia: float
    penurunan_inertia: float | None
    silhouette: float | None
    jumlah_iterasi: int
    jumlah_cluster_terbentuk: int
    labels: np.ndarray
@dataclass
class HasilKondisi:
    """Ringkasan hasil akhir satu kondisi skala data."""
    kondisi: str
    rentang_data: str
    jumlah_siswa: int
    nama_fitur: list[str]
    rentang_fitur: pd.DataFrame
    kandidat_k: list[int]
    k_terpilih: int
    inertia: float
    silhouette: float
    jumlah_iterasi: int
    anggota_cluster: dict[str, int]
    labels: np.ndarray
    catatan: list[str]
def baca_parameter() -> tuple[str, str]:
    """Membaca parameter yang sama seperti kmeans_keseluruhan.py."""
    if len(sys.argv) != 3:
        raise KesalahanPengujian(
            "Parameter kelas dan tahun ajaran wajib diisi. Contoh: "
            'python python/perbandingan_normalisasi.py "XI Perhotelan" '
            '"2024/2025"'
        )
    kelas = sys.argv[1].strip()
    tahun_ajaran = sys.argv[2].strip()
    if not kelas:
        raise KesalahanPengujian("Parameter kelas tidak boleh kosong.")
    cocok_tahun = re.fullmatch(r"(\d{4})/(\d{4})", tahun_ajaran)
    if (
        not cocok_tahun
        or int(cocok_tahun.group(2)) != int(cocok_tahun.group(1)) + 1
    ):
        raise KesalahanPengujian(
            "Format tahun ajaran tidak valid. Gunakan format YYYY/YYYY."
        )
    return kelas, tahun_ajaran
def buat_engine() -> Engine:
    """Membuat koneksi baca menggunakan konfigurasi aplikasi utama."""
    config_path = Path(__file__).resolve().parent.parent / "config" / "database.ini"
    database_config = configparser.ConfigParser()
    if not database_config.read(config_path, encoding="utf-8"):
        raise KesalahanPengujian(
            f"Konfigurasi database tidak ditemukan: {config_path}"
        )
    if "application" not in database_config:
        raise KesalahanPengujian(
            "Bagian [application] tidak ditemukan pada konfigurasi database."
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
def ambil_dan_siapkan_fitur(
    engine: Engine, kelas: str
) -> tuple[pd.DataFrame, pd.DataFrame, list[str]]:
    """Mengambil, mem-pivot, dan menyeleksi fitur seperti skrip utama."""
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
        raise KesalahanPengujian(
            f"Data nilai untuk kelas {kelas!r} tidak tersedia."
        )
    kolom_wajib = {"id", "nama_siswa", "nama_mapel", "nilai"}
    kolom_hilang = kolom_wajib.difference(df_raw.columns)
    if kolom_hilang:
        raise KesalahanPengujian(
            "Struktur hasil query tidak lengkap. Kolom yang hilang: "
            + ", ".join(sorted(kolom_hilang))
        )
    nilai_asli = df_raw["nilai"].copy()
    df_raw["nilai"] = pd.to_numeric(df_raw["nilai"], errors="coerce")
    nilai_tidak_valid = nilai_asli.notna() & df_raw["nilai"].isna()
    if nilai_tidak_valid.any():
        raise KesalahanPengujian(
            f"Ditemukan {int(nilai_tidak_valid.sum())} nilai bukan numerik. "
            "Perbaiki data sebelum pengujian."
        )
    if np.isinf(df_raw["nilai"].to_numpy(dtype=float)).any():
        raise KesalahanPengujian(
            "Ditemukan nilai tak hingga (inf) pada data mata pelajaran."
        )
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
        df[NAMA_FITUR_AGAMA] = df[kolom_agama_ada].sum(axis=1)
        df = df.drop(columns=kolom_agama_ada)
    fitur = df.drop(columns=["id", "nama_siswa"], errors="raise")
    if fitur.empty or fitur.shape[1] == 0:
        raise KesalahanPengujian("Fitur mata pelajaran kosong.")
    fitur = fitur.apply(pd.to_numeric, errors="coerce")
    if fitur.isna().any().any():
        posisi = np.argwhere(fitur.isna().to_numpy())
        contoh_baris, contoh_kolom = posisi[0]
        raise KesalahanPengujian(
            "Ditemukan data fitur kosong atau bukan numerik pada siswa "
            f"baris data ke-{contoh_baris + 1}, fitur "
            f"{fitur.columns[contoh_kolom]!r}."
        )
    data_fitur = fitur.to_numpy(dtype=float)
    if not np.isfinite(data_fitur).all():
        raise KesalahanPengujian(
            "Fitur mengandung nilai NaN atau tak hingga setelah seleksi."
        )
    if len(fitur) < 3:
        raise KesalahanPengujian(
            "Data minimal 3 siswa untuk evaluasi jumlah cluster."
        )
    fitur_konstan = [
        str(kolom) for kolom in fitur.columns if fitur[kolom].nunique() <= 1
    ]
    if len(fitur_konstan) == fitur.shape[1]:
        raise KesalahanPengujian(
            "Seluruh fitur memiliki nilai yang sama. K-Means hanya akan "
            "membentuk satu cluster dan Silhouette Score tidak dapat dihitung."
        )
    return df, fitur, fitur_konstan
def rentang_setiap_fitur(data: np.ndarray, nama_fitur: list[str]) -> pd.DataFrame:
    """Menghasilkan nilai minimum dan maksimum untuk setiap fitur."""
    return pd.DataFrame(
        {
            "Fitur": nama_fitur,
            "Minimum": np.min(data, axis=0),
            "Maksimum": np.max(data, axis=0),
        }
    )
def buat_daftar_k(jumlah_data: int) -> list[int]:
    """Membentuk rentang K sesuai rumus yang digunakan dalam penelitian."""
    max_k = min(math.floor(0.5 * jumlah_data), jumlah_data - 1)
    daftar_k = list(range(2, max_k + 1))
    if not daftar_k:
        raise KesalahanPengujian(
            "Tidak ada nilai K yang memenuhi rentang 2 sampai "
            "min(floor(0,5 x jumlah_data), jumlah_data - 1)."
        )
    return daftar_k
def uji_semua_k(data: np.ndarray, daftar_k: list[int]) -> list[HasilK]:
    """Menghitung inertia dan Silhouette Score untuk seluruh K."""
    hasil: list[HasilK] = []
    inertia_sebelumnya: float | None = None
    jumlah_data = data.shape[0]
    for k in daftar_k:
        model = KMeans(
            n_clusters=k,
            random_state=RANDOM_STATE,
            n_init=N_INIT,
        )
        with warnings.catch_warnings(), redirect_stderr(io.StringIO()):
            warnings.simplefilter("ignore", category=ConvergenceWarning)
            labels = model.fit_predict(data)
        jumlah_cluster = int(np.unique(labels).size)
        score: float | None = None
        if 2 <= jumlah_cluster <= jumlah_data - 1:
            try:
                score = float(silhouette_score(data, labels))
            except ValueError:
                score = None
        inertia = float(model.inertia_)
        penurunan = (
            None
            if inertia_sebelumnya is None
            else float(inertia_sebelumnya - inertia)
        )
        hasil.append(
            HasilK(
                k=k,
                inertia=inertia,
                penurunan_inertia=penurunan,
                silhouette=score,
                jumlah_iterasi=int(model.n_iter_),
                jumlah_cluster_terbentuk=jumlah_cluster,
                labels=labels,
            )
        )
        inertia_sebelumnya = inertia
    return hasil
def pilih_kandidat_elbow(hasil_semua_k: list[HasilK]) -> tuple[list[HasilK], list[str]]:
    """Menerapkan rata-rata penurunan inertia seperti skrip utama."""
    catatan: list[str] = []
    daftar_penurunan = [
        item.penurunan_inertia
        for item in hasil_semua_k
        if item.penurunan_inertia is not None
    ]
    if daftar_penurunan:
        rata_penurunan = float(np.mean(daftar_penurunan))
        kandidat_elbow = [
            item
            for item in hasil_semua_k
            if item.penurunan_inertia is not None
            and item.penurunan_inertia >= rata_penurunan
        ]
    else:
        kandidat_elbow = []
    if not kandidat_elbow:
        kandidat_elbow = list(hasil_semua_k)
        catatan.append(
            "Filter Elbow tidak menghasilkan kandidat; seluruh K digunakan."
        )
    kandidat_valid = [
        item for item in kandidat_elbow if item.silhouette is not None
    ]
    if not kandidat_valid:
        kandidat_valid = [
            item for item in hasil_semua_k if item.silhouette is not None
        ]
        if kandidat_valid:
            catatan.append(
                "Kandidat Elbow tidak memiliki Silhouette Score yang valid; "
                "pemilihan dialihkan ke seluruh K yang valid."
            )
    if not kandidat_valid:
        raise KesalahanPengujian(
            "Tidak ada nilai K yang memenuhi syarat Silhouette Score. "
            "Setiap hasil harus memiliki minimal 2 cluster dan maksimal "
            "jumlah_data - 1 cluster."
        )
    return kandidat_valid, catatan
def evaluasi_kondisi(
    kondisi: str,
    rentang_data: str,
    data: np.ndarray,
    nama_fitur: list[str],
    daftar_k: list[int],
    fitur_konstan: list[str],
) -> HasilKondisi:
    """Menjalankan alur Elbow dan Silhouette untuk satu kondisi."""
    hasil_semua_k = uji_semua_k(data, daftar_k)
    kandidat, catatan = pilih_kandidat_elbow(hasil_semua_k)
    terbaik = max(
        kandidat,
        key=lambda item: (
            float(item.silhouette)
            if item.silhouette is not None
            else float("-inf")
        ),
    )
    if terbaik.jumlah_cluster_terbentuk < 2:
        raise KesalahanPengujian(
            f"K={terbaik.k} hanya membentuk satu cluster pada kondisi {kondisi}."
        )
    if fitur_konstan:
        catatan.append(
            "Fitur bernilai konstan tetap dipertahankan agar dataset kedua "
            "kondisi identik: " + ", ".join(fitur_konstan)
        )
    anggota = {
        f"Cluster {int(label) + 1}": int(jumlah)
        for label, jumlah in zip(*np.unique(terbaik.labels, return_counts=True))
    }
    return HasilKondisi(
        kondisi=kondisi,
        rentang_data=rentang_data,
        jumlah_siswa=data.shape[0],
        nama_fitur=nama_fitur,
        rentang_fitur=rentang_setiap_fitur(data, nama_fitur),
        kandidat_k=[item.k for item in kandidat],
        k_terpilih=terbaik.k,
        inertia=terbaik.inertia,
        silhouette=float(terbaik.silhouette),
        jumlah_iterasi=terbaik.jumlah_iterasi,
        anggota_cluster=anggota,
        labels=terbaik.labels,
        catatan=catatan,
    )
def format_daftar(nilai: list[Any]) -> str:
    """Membuat daftar ringkas untuk terminal dan CSV."""
    return ", ".join(str(item) for item in nilai)
def format_anggota(anggota: dict[str, int]) -> str:
    """Membuat teks jumlah anggota per cluster."""
    return "; ".join(f"{cluster}: {jumlah}" for cluster, jumlah in anggota.items())
def format_rentang_csv(rentang: pd.DataFrame) -> str:
    """Membuat seluruh rentang fitur dalam satu sel CSV."""
    return "; ".join(
        f"{row.Fitur}: {row.Minimum:.6g}-{row.Maksimum:.6g}"
        for row in rentang.itertuples(index=False)
    )
def buat_tabel_ringkasan(hasil: list[HasilKondisi]) -> pd.DataFrame:
    """Membentuk tabel lengkap yang akan disimpan sebagai CSV."""
    return pd.DataFrame(
        [
            {
                "Kondisi Data": item.kondisi,
                "Rentang Data": item.rentang_data,
                "Jumlah Siswa": item.jumlah_siswa,
                "Jumlah Fitur": len(item.nama_fitur),
                "Nama Fitur": format_daftar(item.nama_fitur),
                "Rentang Setiap Fitur": format_rentang_csv(item.rentang_fitur),
                "Kandidat K": format_daftar(item.kandidat_k),
                "K Terpilih": item.k_terpilih,
                "Inertia": item.inertia,
                "Silhouette Score": item.silhouette,
                "Jumlah Iterasi": item.jumlah_iterasi,
                "Jumlah Anggota Cluster": format_anggota(item.anggota_cluster),
                "Catatan": " | ".join(item.catatan),
            }
            for item in hasil
        ]
    )
def tampilkan_detail(hasil: HasilKondisi) -> None:
    """Menampilkan detail satu kondisi ke terminal."""
    print("\n" + "=" * 78)
    print(hasil.kondisi.upper())
    print("=" * 78)
    print(f"Jumlah siswa       : {hasil.jumlah_siswa}")
    print(f"Jumlah fitur       : {len(hasil.nama_fitur)}")
    print(f"Nama fitur         : {format_daftar(hasil.nama_fitur)}")
    print(f"Kandidat K         : {format_daftar(hasil.kandidat_k)}")
    print(f"K terpilih         : {hasil.k_terpilih}")
    print(f"Inertia            : {hasil.inertia:.6f}")
    print(f"Silhouette Score   : {hasil.silhouette:.6f}")
    print(f"Jumlah iterasi     : {hasil.jumlah_iterasi}")
    print(f"Anggota cluster    : {format_anggota(hasil.anggota_cluster)}")
    print("\nRentang setiap fitur:")
    tabel_rentang = hasil.rentang_fitur.copy()
    tabel_rentang["Minimum"] = tabel_rentang["Minimum"].map(
        lambda nilai: f"{nilai:.6f}"
    )
    tabel_rentang["Maksimum"] = tabel_rentang["Maksimum"].map(
        lambda nilai: f"{nilai:.6f}"
    )
    print(tabel_rentang.to_string(index=False))
    for catatan in hasil.catatan:
        print(f"Catatan             : {catatan}")
def buat_analisis_otomatis(
    tanpa_normalisasi: HasilKondisi,
    dengan_normalisasi: HasilKondisi,
) -> list[str]:
    """Menyusun kesimpulan berdasarkan angka hasil aktual."""
    analisis: list[str] = []
    if tanpa_normalisasi.k_terpilih == dengan_normalisasi.k_terpilih:
        analisis.append(
            "K terpilih tidak berubah; kedua kondisi memilih "
            f"K={tanpa_normalisasi.k_terpilih}."
        )
    else:
        analisis.append(
            "K terpilih berubah dari "
            f"K={tanpa_normalisasi.k_terpilih} menjadi "
            f"K={dengan_normalisasi.k_terpilih} setelah normalisasi."
        )
    selisih_silhouette = (
        dengan_normalisasi.silhouette - tanpa_normalisasi.silhouette
    )
    toleransi = 1e-12
    if selisih_silhouette > toleransi:
        analisis.append(
            "Silhouette Score meningkat sebesar "
            f"{selisih_silhouette:.6f} setelah normalisasi "
            f"({tanpa_normalisasi.silhouette:.6f} menjadi "
            f"{dengan_normalisasi.silhouette:.6f})."
        )
    elif selisih_silhouette < -toleransi:
        analisis.append(
            "Silhouette Score menurun sebesar "
            f"{abs(selisih_silhouette):.6f} setelah normalisasi "
            f"({tanpa_normalisasi.silhouette:.6f} menjadi "
            f"{dengan_normalisasi.silhouette:.6f})."
        )
    else:
        analisis.append(
            "Silhouette Score tidak berubah sampai enam angka desimal "
            f"({tanpa_normalisasi.silhouette:.6f})."
        )
    selisih_iterasi = (
        dengan_normalisasi.jumlah_iterasi - tanpa_normalisasi.jumlah_iterasi
    )
    if selisih_iterasi > 0:
        analisis.append(
            "Jumlah iterasi bertambah "
            f"{selisih_iterasi}, dari {tanpa_normalisasi.jumlah_iterasi} "
            f"menjadi {dengan_normalisasi.jumlah_iterasi}."
        )
    elif selisih_iterasi < 0:
        analisis.append(
            "Jumlah iterasi berkurang "
            f"{abs(selisih_iterasi)}, dari {tanpa_normalisasi.jumlah_iterasi} "
            f"menjadi {dengan_normalisasi.jumlah_iterasi}."
        )
    else:
        analisis.append(
            "Jumlah iterasi tidak berubah, yaitu "
            f"{tanpa_normalisasi.jumlah_iterasi} iterasi."
        )
    analisis.append(
        "Min-Max mengubah setiap fitur ke rentang 0-1 sehingga perbedaan "
        "rentang antarmata pelajaran tidak memberi bobot jarak yang berbeda "
        "hanya karena skala. Dampak kualitas cluster tetap dinilai dari "
        "Silhouette Score aktual di atas."
    )
    if selisih_silhouette > toleransi:
        analisis.append(
            "Pada dataset ini, kondisi Min-Max Normalization memiliki "
            "Silhouette Score lebih tinggi."
        )
    elif selisih_silhouette < -toleransi:
        analisis.append(
            "Pada dataset ini, kondisi tanpa normalisasi memiliki "
            "Silhouette Score lebih tinggi."
        )
    else:
        analisis.append(
            "Pada dataset ini, kedua kondisi memiliki Silhouette Score yang sama."
        )
    return analisis
def tampilkan_tabel_akhir(tabel: pd.DataFrame) -> None:
    """Menampilkan tabel ringkas sesuai format kebutuhan penelitian."""
    kolom = [
        "Kondisi Data",
        "Rentang Data",
        "K Terpilih",
        "Inertia",
        "Silhouette Score",
        "Jumlah Iterasi",
    ]
    tampilan = tabel[kolom].copy()
    tampilan["Inertia"] = tampilan["Inertia"].map(lambda nilai: f"{nilai:.6f}")
    tampilan["Silhouette Score"] = tampilan["Silhouette Score"].map(
        lambda nilai: f"{nilai:.6f}"
    )
    print("\n" + "=" * 78)
    print("TABEL PERBANDINGAN AKHIR")
    print("=" * 78)
    print(tampilan.to_string(index=False))
def main() -> int:
    """Menjalankan seluruh pengujian tanpa mengubah database aplikasi."""
    engine: Engine | None = None
    try:
        kelas, tahun_ajaran = baca_parameter()
        engine = buat_engine()
        _, fitur, fitur_konstan = ambil_dan_siapkan_fitur(engine, kelas)
        nama_fitur = [str(kolom) for kolom in fitur.columns]
        data_asli = fitur.to_numpy(dtype=float)
        daftar_k = buat_daftar_k(len(fitur))
        scaler = MinMaxScaler(feature_range=(0, 1))
        data_minmax = scaler.fit_transform(data_asli)
        tanpa_normalisasi = evaluasi_kondisi(
            kondisi="Tanpa Normalisasi",
            rentang_data="Nilai asli",
            data=data_asli,
            nama_fitur=nama_fitur,
            daftar_k=daftar_k,
            fitur_konstan=fitur_konstan,
        )
        dengan_normalisasi = evaluasi_kondisi(
            kondisi="Min-Max Normalization",
            rentang_data="0-1",
            data=data_minmax,
            nama_fitur=nama_fitur,
            daftar_k=daftar_k,
            fitur_konstan=fitur_konstan,
        )
        print("PERBANDINGAN K-MEANS TANPA NORMALISASI DAN MIN-MAX")
        print(f"Kelas              : {kelas}")
        print(f"Tahun ajaran       : {tahun_ajaran}")
        print(f"Parameter K-Means  : random_state={RANDOM_STATE}, n_init={N_INIT}")
        print(f"Rentang K diuji    : {format_daftar(daftar_k)}")
        tampilkan_detail(tanpa_normalisasi)
        tampilkan_detail(dengan_normalisasi)
        tabel = buat_tabel_ringkasan(
            [tanpa_normalisasi, dengan_normalisasi]
        )
        tampilkan_tabel_akhir(tabel)
        analisis = buat_analisis_otomatis(
            tanpa_normalisasi, dengan_normalisasi
        )
        print("\nANALISIS OTOMATIS")
        for nomor, kalimat in enumerate(analisis, start=1):
            print(f"{nomor}. {kalimat}")
        output_path = Path(__file__).resolve().parent / NAMA_FILE_OUTPUT
        tabel_output = tabel.copy()
        tabel_output["Analisis Otomatis"] = " ".join(analisis)
        tabel_output.to_csv(output_path, index=False, encoding="utf-8-sig")
        print(f"\nHasil CSV tersimpan: {output_path}")
        print("Database dan hasil clustering utama tidak diubah.")
        return 0
    except KesalahanPengujian as exc:
        print(f"Pengujian tidak dapat dijalankan: {exc}", file=sys.stderr)
        return 1
    except Exception as exc:          print(
            "Pengujian gagal karena koneksi database atau kesalahan tak terduga: "
            f"{exc}",
            file=sys.stderr,
        )
        return 1
    finally:
        if engine is not None:
            engine.dispose()
if __name__ == "__main__":
    sys.exit(main())
