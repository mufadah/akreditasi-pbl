# Dokumentasi Teknis Revisi ERD - Modul Tridharma

Dokumen teknis internal ini merangkum seluruh perubahan skema database (revisi ERD terbaru), petunjuk konfigurasi, serta spesifikasi endpoint API pada branch `faiz`.

---

## 1. Petunjuk Setup Database & Storage

### A. Menjalankan Migrasi Database
Jalankan seluruh migrasi database (termasuk pembuatan tabel baru dan alter kolom):

```bash
php artisan migrate
```

### B. Menjalankan Master Data Seeders
Seluruh seeder menggunakan method `updateOrCreate` sehingga **idempotent** (aman dijalankan berulang kali tanpa risiko duplikasi data):

```bash
# Menjalankan seluruh seeder aplikasi
php artisan db:seed

# Atau menjalankan seeder master data baru secara spesifik
php artisan db:seed --class=JenisHkiSeeder
php artisan db:seed --class=JenisPkmSeeder
php artisan db:seed --class=JenisKerjaSamaSeeder
```

### C. Konfigurasi Symlink Public Storage
Modul bukti fisik (evidence) mengunggah file ke disk lokal publik (`storage/app/public/evidence`). Pastikan symlink telah dibuat agar file dapat diakses dan diunduh:

```bash
php artisan storage:link
```

---

## 2. Ringkasan Perubahan Skema ERD

### A. Tabel Baru
| Tabel | Primary Key | Deskripsi & Foreign Key |
|:---|:---|:---|
| `buku` | `id_buku` | Transaksi karya buku dosen (`id_dosen` FK ke `dosen`, `judul_buku`, `isbn`, `penerbit`, `tahun`, `anggota`, `bidang_ilmu`, `jenis_buku`, `deskripsi`). |
| `jenis_hki` | `id_jenis_hki` | Master klasifikasi HKI (`nama_hki`). |
| `hki` | `id_hki` | Transaksi Hak Kekayaan Intelektual (`id_dosen` FK ke `dosen`, `id_jenis_hki` FK ke `jenis_hki`, `id_penelitian` FK null ke `penelitian`, `id_evidence` FK null ke `evidence`, `judul_hki`, `nomor_hki`, `tahun`). |
| `jenis_pkm` | `id_jenis_pkm` | Master jenis pengabdian kepada masyarakat (`nama_jenis_pkm`, `jenis_pkm`). |
| `anggota_pkm` | `id_anggota_pkm` | Anggota mahasiswa kegiatan PKM (`id_pkm` FK cascade ke `pkm`, `id_mahasiswa`, `peran`, `semester`). |
| `jenis_kerja_sama` | `id_jenis_kerjasama` | Master jenis kemitraan dokumen (`nama_jenis_kerjasama`). |
| `aktivitas_kerja_sama` | `id_aktivitas` | Pelaksanaan kegiatan kerja sama (`id_kerja_sama` FK cascade ke `kerja_sama`, `judul_aktivitas`, `tanggal_pelaksanaan`, `deskripsi`, `bukti_dokumen`). |
| `evidence` (penambahan field) | `id_evidence` | Penyimpanan berkas upload fisik (`nama_file`, `path_file`, `tanggal_upload`, `status_validasi`, `id_penelitian` FK null, `id_pkm` FK null, `id_kerja_sama` FK null). |

### B. Penambahan Kolom pada Tabel Eksisting
- **`publikasi`**:
  - `jumlah_sitasi` (`INTEGER`, default: `0`, nullable).
- **`kerja_sama`**:
  - `id_jenis_kerjasama` (`BIGINT UNSIGNED`, FK nullable ke `jenis_kerja_sama`).
  - `id_dosen` (`INT UNSIGNED`, FK nullable ke `dosen`).
  - `nomor_dokumen` (`VARCHAR(255)`, nullable).
  - `jenis_dokumen` (`VARCHAR(100)`, nullable).
- **`pkm`**:
  - `id_jenis_pkm` (`BIGINT UNSIGNED`, FK nullable ke `jenis_pkm`).
  - `jenis_pelaksana` (`VARCHAR(255)`, nullable).

---

## 3. Referensi ID Master Data dari Seeder

| Tabel | ID | Nama / Nilai |
|:---|:---:|:---|
| **`jenis_hki`** | `1` | Hak Cipta |
| | `2` | Paten |
| | `3` | Merek |
| | `4` | Desain Industri |
| **`jenis_pkm`** | `1` | Pelatihan |
| | `2` | Penyuluhan |
| | `3` | Pendampingan |
| **`jenis_kerja_sama`** | `1` | MoU |
| | `2` | MoA |
| | `3` | IA |

---

## 4. Standar Response Envelope API

Seluruh controller menggunakan format envelope JSON yang konsisten:

### Respon Sukses (200 / 201)
```json
{
  "success": true,
  "code": 200,
  "message": "Data berhasil diambil.",
  "data": { ... }
}
```

### Respon Validasi Gagal (422)
```json
{
  "success": false,
  "code": 422,
  "message": "Validasi gagal.",
  "data": {
    "judul_buku": [
      "The judul buku field is required."
    ]
  }
}
```

### Respon Data Tidak Ditemukan (404)
```json
{
  "success": false,
  "code": 404,
  "message": "Data tidak ditemukan.",
  "data": null
}
```

---

## 5. Spesifikasi Endpoint API Baru & Penyesuaian

Setiap endpoint dilindungi middleware autentikasi JWT `jwt.role:ADMINISTRATOR` melalui header:
`Authorization: Bearer <TOKEN>`

---

### A. Modul Buku (`/api/buku`)

| Method | Endpoint | Deskripsi |
|:---|:---|:---|
| `GET` | `/api/buku` | Mengambil daftar buku (terpaginasi 10 item). |
| `POST` | `/api/buku` | Menambahkan data buku baru. |
| `GET` | `/api/buku/{id}` | Mengambil detail satu data buku. |
| `PUT/PATCH` | `/api/buku/{id}` | Memperbarui data buku. |
| `DELETE` | `/api/buku/{id}` | Menghapus record buku. |

#### Query Parameters `GET /api/buku`:
- `search`: Pencarian parsial pada `judul_buku`, `penerbit`, atau `isbn`.
- `id_dosen`: Filter eksak ID dosen penulis.
- `tahun`: Filter 4-digit tahun terbit.
- `bidang_ilmu`: Filter bidang keilmuan.
- `jenis_buku`: Filter jenis buku (contoh: Buku Ajar, Monograf, Referensi).
- `per_page`: Jumlah data per halaman (default 10).

#### Payload `POST /api/buku`:
```json
{
  "id_dosen": 1,
  "judul_buku": "Pemrograman Web Modern dengan Laravel",
  "isbn": "978-602-1234-56-7",
  "penerbit": "Informatika Press",
  "tahun": 2025,
  "anggota": "Budi Santoso, Siti Aminah",
  "bidang_ilmu": "Rekayasa Perangkat Lunak",
  "jenis_buku": "Buku Ajar",
  "deskripsi": "Buku panduan praktikum pengembangan web PBL"
}
```

---

### B. Modul HKI (`/api/hki`)

| Method | Endpoint | Deskripsi |
|:---|:---|:---|
| `GET` | `/api/hki` | Mengambil daftar HKI beserta relasi `dosen`, `jenisHki`, `penelitian`, dan `evidence`. |
| `POST` | `/api/hki` | Mendaftarkan data HKI baru. |
| `GET` | `/api/hki/{id}` | Mengambil detail satu data HKI. |
| `PUT/PATCH` | `/api/hki/{id}` | Memperbarui data HKI. |
| `DELETE` | `/api/hki/{id}` | Menghapus data HKI. |

#### Query Parameters `GET /api/hki`:
- `search`: Pencarian parsial pada `judul_hki` atau `nomor_hki`.
- `id_dosen`: Filter eksak ID dosen.
- `id_jenis_hki`: Filter eksak jenis HKI (`1` s/d `4`).
- `id_penelitian`: Filter keterkaitan proyek penelitian.
- `tahun`: Filter 4-digit tahun perolehan.
- `per_page`: Limit data per halaman (default 10).

#### Payload `POST /api/hki`:
```json
{
  "id_dosen": 1,
  "id_jenis_hki": 1,
  "id_penelitian": 3,
  "id_evidence": null,
  "judul_hki": "Sistem Pakar Diagnosa Penyakit Tanaman Padi",
  "nomor_hki": "EC00202500123",
  "tahun": 2025
}
```

---

### C. Modul Evidence (`/api/evidence`)

| Method | Endpoint | Format Konten | Deskripsi |
|:---|:---|:---|:---|
| `GET` | `/api/evidence` | `application/json` | Mengambil daftar bukti fisik terunggah. |
| `POST` | `/api/evidence` | `multipart/form-data` | Mengunggah file bukti fisik dan metadata. |
| `GET` | `/api/evidence/{id}` | `application/json` | Mengambil detail metadata evidence. |
| `PUT/PATCH` | `/api/evidence/{id}` | `application/json` / `multipart` | Memperbarui metadata dan/atau mengganti file. |
| `DELETE` | `/api/evidence/{id}` | `application/json` | Menghapus record dan menghapus file fisik di disk. |
| `GET` | `/api/evidence/{id}/download` | Stream binary | Mengunduh file fisik dengan nama berkas asli. |

#### Query Parameters `GET /api/evidence`:
- `search`: Pencarian nama file atau jenis dokumen.
- `id_penelitian`: Filter bukti kegiatan penelitian.
- `id_pkm`: Filter bukti kegiatan PKM.
- `id_kerja_sama` / `id_kerjasama`: Filter bukti dokumen kerja sama.
- `status_validasi`: Filter status (contoh: `Aktif`, `Revisi`).
- `per_page`: Paginasi data (default 10).

#### Request `POST /api/evidence` (`multipart/form-data`):
- `file` *(wajib)*: Berkas fisik (ekstensi: `pdf, jpg, jpeg, png`, max: 10MB / `10240 KB`).
- `jenis_dokumen` *(wajib)*: String deskripsi dokumen (contoh: `"Sertifikat HKI"`, `"Laporan Akhir PKM"`).
- `tanggal_upload` *(opsional)*: Format `YYYY-MM-DD` (default: hari ini).
- `status_validasi` *(opsional)*: Default `"Aktif"`.
- `id_penelitian` *(opsional)*: Integer ID penelitian.
- `id_pkm` *(opsional)*: Integer ID PKM.
- `id_kerjasama` *(opsional)*: Integer ID kerja sama.

---

### D. Modul Aktivitas Kerja Sama (`/api/aktivitas-kerja-sama`)

| Method | Endpoint | Deskripsi |
|:---|:---|:---|
| `GET` | `/api/aktivitas-kerja-sama` | Mengambil daftar aktivitas kegiatan kemitraan. |
| `POST` | `/api/aktivitas-kerja-sama` | Menambahkan aktivitas kegiatan kerja sama baru. |
| `GET` | `/api/aktivitas-kerja-sama/{id}` | Mengambil detail aktivitas kerja sama. |
| `PUT/PATCH` | `/api/aktivitas-kerja-sama/{id}` | Memperbarui aktivitas kerja sama. |
| `DELETE` | `/api/aktivitas-kerja-sama/{id}` | Menghapus data aktivitas. |

#### Query Parameters `GET /api/aktivitas-kerja-sama`:
- `id_kerjasama` / `id_kerja_sama`: Filter kegiatan berdasarkan dokumen induk kerja sama.
- `search`: Filter pencarian pada `judul_aktivitas`.
- `per_page`: Paginasi data (default 10).

#### Payload `POST /api/aktivitas-kerja-sama`:
```json
{
  "id_kerjasama": 2,
  "judul_aktivitas": "Workshop Bersama Industri: Cloud Architecture",
  "tanggal_pelaksanaan": "2025-11-20",
  "deskripsi": "Pelaksanaan transfer knowledge arsitektur sistem cloud",
  "bukti_dokumen": "https://drive.google.com/open?id=xyz"
}
```

---

### E. Penyesuaian Modul PKM Bertingkat (`/api/pkm`)

Endpoint `POST /api/pkm` dan `PUT /api/pkm/{id}` kini mendukung penambahan relasi master `id_jenis_pkm`, atribut pelaksana `jenis_pelaksana`, serta struktur **nested array `anggota` mahasiswa** yang otomatis dikelola di tabel `anggota_pkm` dalam satu transaksi database atomik:

#### Payload `POST /api/pkm`:
```json
{
  "id_dosen": 1,
  "id_tahun_akademik": 1,
  "id_mitra": 1,
  "id_jenis_pkm": 1,
  "jenis_pelaksana": "Kelompok",
  "judul_pkm": "Pelatihan Digital Marketing dan E-Commerce untuk UMKM",
  "lokasi": "Kecamatan Tembalang, Semarang",
  "tahun": 2025,
  "status": "Berjalan",
  "anggota": [
    {
      "id_mahasiswa": 2001,
      "peran": "Ketua",
      "semester": "6"
    },
    {
      "id_mahasiswa": 2002,
      "peran": "Anggota",
      "semester": "4"
    }
  ]
}
```

*Catatan Update (`PUT /api/pkm/{id}`): Menggunakan **replace strategy**. Apabila field `anggota` dikirimkan, data anggota lama akan dihapus dan digantikan seluruhnya dengan data array baru.*
