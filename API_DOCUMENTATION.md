# Dokumentasi Lengkap RESTful API
### Sistem Informasi Akreditasi LAM INFOKOM 2.1 — Modul K2
**Kelompok 2: Sumber Daya Manusia (SDM), Penelitian, PkM, & Kerja Sama**

---

## 📌 Gambaran Umum & Konvensi

API ini dibangun menggunakan framework **Laravel 12** untuk melayani pertukaran data akreditasi program studi dengan klien (Web Frontend, Mobile App, maupun Microservice Kelompok 1 & Kelompok 3).

* **Base URL**: `http://localhost:8000/api`
* **Format Data**: JSON (`application/json`)
* **Protokol**: HTTP/1.1 atau HTTP/2
* **Timezone**: Asia/Jakarta (WIB)

### Header Standar Permintaan (Request)

Setiap request ke endpoint yang diproteksi wajib menyertakan header berikut:

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <TOKEN_JWT_ANDA>
```

---

## 🔐 Format Respons Standar

API menggunakan struktur envelope respon JSON yang konsisten di semua endpoint:

### 1. Respons Berhasil (HTTP 200 / 201)
```json
{
  "success": true,
  "code": 200,
  "message": "Pesan deskriptif keberhasilan operasi.",
  "data": { ... }
}
```

### 2. Respons Gagal / Validasi Input (HTTP 422)
```json
{
  "success": false,
  "code": 422,
  "message": "Validasi gagal.",
  "data": {
    "field_name": [
      "Pesan error spesifik pada field terkait."
    ]
  }
}
```

### 3. Respons Tidak Terotorisasi / Token Kedaluwarsa (HTTP 401)
```json
{
  "success": false,
  "code": 401,
  "message": "Token Authorization tidak valid, kedaluwarsa, atau tidak ditemukan.",
  "data": null
}
```

### 4. Respons Peringatan Duplikasi / Konflik (HTTP 409)
```json
{
  "success": false,
  "code": 409,
  "message": "Peringatan: Link Google Drive ini sudah pernah digunakan sebelumnya. Kirim parameter 'konfirmasi_lanjut': true jika ingin tetap menyimpan.",
  "data": {
    "is_duplicate": true
  }
}
```

---

## 📑 Daftar Modul API

1. [Modul 1: Autentikasi & Pengujian Token](#1-modul-autentikasi--token)
2. [Modul 2: Pengelolaan Dosen (DTPS & DTT)](#2-modul-pengelolaan-dosen-sdm)
3. [Modul 3: Master Data Penunjang SDM](#3-modul-master-data-penunjang-sdm)
4. [Modul 4: Kemitraan & Kerja Sama](#4-modul-kemitraan--kerja-sama)
5. [Modul 5: Kegiatan Terpadu (Penelitian & PkM)](#5-modul-kegiatan-terpadu-penelitian--pkm)
6. [Modul 6: Luaran Ilmiah (Jurnal, Prosiding, HKI, Buku, Produk)](#6-modul-luaran-ilmiah)
7. [Modul 7: Repository Evidence & Workflow Validasi](#7-modul-repository-evidence--workflow-validasi)
8. [Modul 8: Interoperabilitas Sistem (API K1 & K3)](#8-modul-interoperabilitas-sistem)
9. [Modul 9: Analitik, Rasio, & Dashboard Akreditasi V1](#9-modul-analitik-rasio--dashboard-v1)

---

## 1. Modul Autentikasi & Token

### 1.1 Login & Dapatkan Token JWT
Mengautentikasi akun pengguna dan menghasilkan token JWT untuk mengakses seluruh endpoint terproteksi.

* **Method**: `POST`
* **Endpoint**: `/api/login`
* **Akses**: Publik

#### Request Body
```json
{
  "email": "admin@polines.ac.id",
  "password": "password123"
}
```
*(Catatan: Anda juga dapat menggunakan akun seeder `kaprodi.trpl@polines.ac.id`, `dosen@polines.ac.id`, atau `admin@pbl.com`)*

#### Response (200 OK)
```json
{
  "success": true,
  "code": 200,
  "message": "Login berhasil",
  "data": {
    "user": {
      "id": 1,
      "nama": "Administrator K2",
      "email": "admin@polines.ac.id",
      "role": "ADMINISTRATOR"
    },
    "authorization": {
      "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOi...",
      "token_type": "Bearer",
      "expires_in": 86400
    }
  }
}
```

### 1.2 Verifikasi Token (Test Auth)
* **Method**: `GET`
* **Endpoint**: `/api/test-auth`
* **Headers**: `Authorization: Bearer <token>`

#### Response (200 OK)
```json
{
  "status": "OK",
  "message": "Middleware berhasil membaca token!",
  "auth_user": {
    "id": "1",
    "role": "ADMINISTRATOR"
  }
}
```

---

## 2. Modul Pengelolaan Dosen (SDM)

### 2.1 Daftar Dosen (Pagination & Search)
* **Method**: `GET`
* **Endpoint**: `/api/dosen`
* **Query Parameters**:
  * `search` (string, opsional): Cari berdasarkan nama dosen atau NIDN.
  * `status` (string, opsional): Filter status (`DTPS`, `DTT`, `NONAKTIF`).
  * `per_page` (integer, default: 10): Jumlah item per halaman.
  * `with_trashed` (boolean, opsional): Sertakan data dosen yang di-soft-delete (`true`/`false`).

#### Response (200 OK)
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id_dosen": 1,
        "id_jabatan": 3,
        "id_pendidikan": 1,
        "nidn": "0012058501",
        "nama_dosen": "Dr. Ir. Budi Santoso, M.T.",
        "email": "budi.santoso@polines.ac.id",
        "status_dosen": "DTPS",
        "sertifikasi": "Sertifikasi Pendidik Dosen",
        "jabatan_akademik": {
          "id_jabatan": 3,
          "nama_jabatan": "Lektor"
        },
        "pendidikan": {
          "id_pendidikan": 1,
          "jenjang": "S2",
          "universitas": "Institut Teknologi Bandung",
          "program_studi": "Teknik Elektro dan Informatika"
        },
        "bidang_keahlian": [
          {
            "id_bidang_keahlian": 1,
            "nama_bidang": "Artificial Intelligence"
          }
        ]
      }
    ],
    "total": 18
  }
}
```

### 2.2 Tambah Dosen Baru
* **Method**: `POST`
* **Endpoint**: `/api/dosen`

#### Request Body
```json
{
  "id_jabatan": 2,
  "id_pendidikan": 1,
  "nidn": "0028049001",
  "nama_dosen": "Siti Nurhaliza, M.Kom.",
  "email": "siti.nurhaliza@polines.ac.id",
  "status_dosen": "DTPS",
  "sertifikasi": "Pendidik Profesional (Pekerti/AA)",
  "bidang_keahlian_ids": [1, 4]
}
```

#### Response (201 Created)
```json
{
  "success": true,
  "message": "Data dosen berhasil ditambahkan.",
  "data": {
    "id_dosen": 2,
    "nidn": "0028049001",
    "nama_dosen": "Siti Nurhaliza, M.Kom.",
    "email": "siti.nurhaliza@polines.ac.id",
    "status_dosen": "DTPS"
  }
}
```

### 2.3 Detail Profil Dosen & Riwayat Tri Dharma
* **Method**: `GET`
* **Endpoint**: `/api/dosen/{id}`

### 2.4 Perbarui Data Dosen
* **Method**: `PUT`
* **Endpoint**: `/api/dosen/{id}`

### 2.5 Hapus Data Dosen (Soft Delete)
Mengubah status dosen menjadi `NONAKTIF` dan mencatat tanggal `deleted_at`.
* **Method**: `DELETE`
* **Endpoint**: `/api/dosen/{id}`

### 2.6 Restore Dosen Terhapus
Mengaktifkan kembali dosen yang di-soft-delete dan mengembalikan status ke `DTPS`.
* **Method**: `POST`
* **Endpoint**: `/api/dosen/{id}/restore`

### 2.7 Impor Dosen via Spreadsheet (Excel/CSV)
* **Method**: `POST`
* **Endpoint**: `/api/dosen/import`
* **Content-Type**: `multipart/form-data`
* **Body Form-Data**: `file` (File: `.xlsx`, `.xls`, `.csv`, max 5MB).

---

## 3. Modul Master Data Penunjang SDM

API ini menyediakan endpoint standar CRUD Resource (`GET`, `POST`, `GET {id}`, `PUT {id}`, `DELETE {id}`) untuk master data penunjang akreditasi:

| Entitas | Base Route | Parameter Utama |
|:---|:---|:---|
| **Jabatan Akademik** | `/api/jabatan-akademik` | `nama_jabatan` (*Tenaga Pengajar, Asisten Ahli, Lektor, Lektor Kepala, Guru Besar*) |
| **Pendidikan Dosen/Tendik** | `/api/pendidikan` | `jenjang` (*D4/S1, S2, S3*), `universitas`, `program_studi`, `tahun_lulus` |
| **Bidang Keahlian** | `/api/bidang-keahlian` | `nama_bidang` (*AI, IoT, Cybersecurity, Software Engineering, Cloud Computing*) |
| **Tenaga Kependidikan (Tendik)** | `/api/tendik` | `nama_tendik`, `nip`, `posisi_pekerjaan`, `kualifikasi_pendidikan` |

---

## 4. Modul Kemitraan & Kerja Sama

### 4.1 Master Data Mitra Industri & Instansi
* **Resource Route**: `/api/mitra`
* **Fields**: `nama_mitra`, `jenis_mitra`, `tingkat` (*Lokal, Nasional, Internasional*), `kontak_person`, `email_mitra`, `alamat`.

### 4.2 Dokumen Kerja Sama (MoU, MoA, IA)
* **Method**: `GET`
* **Endpoint**: `/api/kerja-sama`
* **Query Parameters**: `search`, `id_mitra`, `id_jenis_kerjasama`, `id_dosen`, `status`, `per_page`.

#### Tambah Kerja Sama Baru
* **Method**: `POST`
* **Endpoint**: `/api/kerja-sama`

```json
{
  "id_mitra": 1,
  "id_jenis_kerjasama": 2,
  "id_dosen": 1,
  "nomor_dokumen": "042/MoA/POLINES-PTINTI/2026",
  "jenis_dokumen": "MoA (Memorandum of Agreement)",
  "tanggal_mulai": "2026-01-15",
  "tanggal_selesai": "2028-01-15",
  "status": "Aktif"
}
```

### 4.3 Aktivitas Implementasi Kerja Sama
Sesuai instrumen LAM INFOKOM, setiap dokumen kerja sama harus dibuktikan dengan implementasi riil berkala.

#### Ambil Riwayat Aktivitas
* **Method**: `GET`
* **Endpoint**: `/api/kerja-sama/{id}/aktivitas`

#### Tambah Aktivitas Implementasi
* **Method**: `POST`
* **Endpoint**: `/api/kerja-sama/{id}/aktivitas`

```json
{
  "judul_aktivitas": "Kuliah Tamu Industri: Arsitektur Cloud Skala Besar",
  "tanggal_pelaksanaan": "2026-03-20",
  "deskripsi": "Diikuti oleh 120 mahasiswa semester 4 dan 6 dosen prodi.",
  "bukti_dokumen": "https://drive.google.com/file/d/1Xyz987654/view"
}
```

### 4.4 Perpanjangan Masa Berlaku Kerja Sama
Memperbarui tanggal kedaluwarsa dokumen kemitraan dan otomatis mencatat riwayat perpanjangan ke log aktivitas.

* **Method**: `POST`
* **Endpoint**: `/api/kerja-sama/{id}/perpanjang`

#### Request Body
```json
{
  "tanggal_selesai_baru": "2029-01-15",
  "keterangan": "Perpanjangan adendum periode kedua selama 1 tahun."
}
```

---

## 5. Modul Kegiatan Terpadu (Penelitian & PkM)

Sesuai Revisi ERD terbaru, seluruh kegiatan riset dan pengabdian dikonsolidasi ke dalam tabel terpadu `kegiatan` dengan relasi pendanaan dan keanggotaan dosen/mahasiswa.

### 5.1 Ambil Daftar Kegiatan
* **Method**: `GET`
* **Endpoint**: `/api/kegiatan`
* **Query Filters**: `search`, `id_jenis_kegiatan` (1: Penelitian, 2: PkM), `tahun_kegiatan`, `status_kegiatan`.

### 5.2 Tambah Kegiatan Baru
* **Method**: `POST`
* **Endpoint**: `/api/kegiatan`

#### Request Body
```json
{
  "id_jenis_kegiatan": 1,
  "judul_kegiatan": "Pengembangan Sistem Monitoring Emisi Karbon IoT Berbasis Edge Computing",
  "afiliasi": "Jurusan Teknik Elektro",
  "lokasi_kegiatan": "Laboratorium IoT Polines",
  "jenis_skim": "Penelitian Terapan Unggulan Perguruan Tinggi",
  "tahun_usulan": 2025,
  "tahun_kegiatan": 2026,
  "tahun_pelaksanaan": 2026,
  "lama_kegiatan": 1,
  "tahun_pelaksanaan_ke": 1,
  "nomor_sk_penugasan": "845/PL4.6.1/PT/2026",
  "tanggal_sk_penugasan": "2026-02-10",
  "status_kegiatan": "Berjalan"
}
```

### 5.3 Detail, Update, & Hapus Kegiatan
* **Detail**: `GET /api/kegiatan/{id}` (Menyertakan relasi `jenisKegiatan`, `pendanaan`, `anggota.dosen`, `luaran`, dan `evidence`)
* **Update**: `PUT /api/kegiatan/{id}`
* **Hapus**: `DELETE /api/kegiatan/{id}`

---

## 6. Modul Luaran Ilmiah

Menampung seluruh hasil luaran kegiatan dosen: Buku Ajar/Monograf, Jurnal Internasional/Nasional, Prosiding Seminar, Hak Cipta/Paten (HKI), dan Produk Teknologi (TKT).

### 6.1 Daftar Luaran
* **Method**: `GET`
* **Endpoint**: `/api/luaran`
* **Query Parameters**: `id_dosen`, `id_kegiatan`, `id_jenis_luaran`, `per_page`.

### 6.2 Tambah Luaran Baru
* **Method**: `POST`
* **Endpoint**: `/api/luaran`

#### Contoh 1: Publikasi Jurnal Scopus / SINTA
```json
{
  "id_dosen": 1,
  "id_jenis_luaran": 1,
  "id_kegiatan": 1,
  "nama_jurnal": "IEEE Transactions on Industrial Informatics",
  "issn": "1941-0050",
  "doi": "10.1109/TII.2026.3129841",
  "indeksasi": "Scopus Q1"
}
```

#### Contoh 2: Sertifikat Hak Cipta / HKI
```json
{
  "id_dosen": 1,
  "id_jenis_luaran": 4,
  "id_kegiatan": 1,
  "jenis_hki": "Hak Cipta Perangkat Lunak",
  "judul_hak_cipta": "Program Komputer Algoritma Edge-AI Smart Sensor v1.0",
  "nomor_pendaftaran_hki": "EC00202619482",
  "nomor_sertifikat_hki": "000492812"
}
```

#### Contoh 3: Produk Teknologi / Prototipe
```json
{
  "id_dosen": 1,
  "id_jenis_luaran": 6,
  "id_kegiatan": 1,
  "nama_produk": "CarbonEdge: Smart IoT Gateway",
  "tingkat_kesiapanterapan": 7
}
```

---

## 7. Modul Repository Evidence & Workflow Validasi

Modul bukti dukung (*evidence*) dilengkapi verifikasi tautan Google Drive, deteksi duplikasi, workflow approval berjenjang, dan riwayat pergantian URL dokumen.

### 7.1 Daftar Repository Evidence (Filter Multi-Kriteria)
* **Method**: `GET`
* **Endpoint**: `/api/evidence`
* **Query Filters**: `jenis_dokumen`, `status_validasi` (*Menunggu Validasi, Valid, Ditolak, Perlu Perbaikan*), `id_kerjasama`, `id_sertifikasi`, `id_kegiatan`, `id_luaran`.

### 7.2 Input Link Google Drive (Validasi URL & Deteksi Duplikasi)
* **Method**: `POST`
* **Endpoint**: `/api/evidence`

> [!NOTE]
> Format URL wajib diawali dengan `https://drive.google.com` atau `https://docs.google.com`. Jika URL sudah pernah digunakan, sistem akan mengembalikan kode HTTP `409 Conflict`. Untuk memaksa menyimpan, sertakan `"konfirmasi_lanjut": true`.

#### Request Body
```json
{
  "nama_file": "https://drive.google.com/file/d/1aB2cD3eF4gH5iJ6kL7mN8oP/view?usp=sharing",
  "jenis_dokumen": "Sertifikat HKI Hak Cipta Perangkat Lunak",
  "id_luaran": 1,
  "konfirmasi_lanjut": false
}
```

#### Response (201 Created)
```json
{
  "success": true,
  "code": 201,
  "message": "Link evidence berhasil disimpan dan menunggu validasi.",
  "data": {
    "id_evidence": 5,
    "nama_file": "https://drive.google.com/file/d/1aB2cD3eF4gH5iJ6kL7mN8oP/view?usp=sharing",
    "jenis_dokumen": "Sertifikat HKI Hak Cipta Perangkat Lunak",
    "status_validasi": "Menunggu Validasi",
    "tanggal_upload": "2026-10-14"
  }
}
```

### 7.3 Workflow Validasi Evidence oleh Kaprodi / Validator
Mengubah status verifikasi bukti dukung. Jika status dipilih `Perlu Perbaikan`, sistem otomatis membuat notifikasi in-app kepada pengunggah.

* **Method**: `POST`
* **Endpoint**: `/api/evidence/{id}/validasi`

#### Request Body
```json
{
  "status_validasi": "Valid",
  "catatan_validasi": "Dokumen lengkap dan telah diverifikasi sesuai instrumen C.4."
}
```
*(Opsi status: `Valid`, `Ditolak`, `Perlu Perbaikan`)*

### 7.4 Fitur Ganti Link Drive (Arsip Riwayat Otomatis)
Mengganti URL dokumen bukti jika terjadi revisi dokumen, mengarsipkan tautan lama ke tabel `evidence_histories`, dan mereset status validasi ke `Menunggu Validasi`.

* **Method**: `POST`
* **Endpoint**: `/api/evidence/{id}/ganti-link`

#### Request Body
```json
{
  "url_drive_baru": "https://drive.google.com/file/d/1RevisiBaru999999999/view",
  "alasan_perubahan": "Pembaruan lembar pengesahan tanda tangan dekan."
}
```

#### Response (200 OK)
```json
{
  "success": true,
  "code": 200,
  "message": "Link Google Drive berhasil diganti, link lama diarsipkan, dan status di-reset ke Menunggu Validasi.",
  "data": {
    "id_evidence": 5,
    "nama_file": "https://drive.google.com/file/d/1RevisiBaru999999999/view",
    "status_validasi": "Menunggu Validasi",
    "history": [
      {
        "id_evidence_history": 1,
        "url_lama": "https://drive.google.com/file/d/1aB2cD3eF4gH5iJ6kL7mN8oP/view?usp=sharing",
        "url_baru": "https://drive.google.com/file/d/1RevisiBaru999999999/view",
        "alasan_perubahan": "Pembaruan lembar pengesahan tanda tangan dekan.",
        "created_at": "2026-10-14T10:15:00.000000Z"
      }
    ]
  }
}
```

---

## 8. Modul Interoperabilitas Sistem

### 8.1 Validasi NIM Mahasiswa ke Sistem Kelompok 1 (K1)
Memvalidasi apakah mahasiswa yang dilibatkan dalam penelitian/PkM berstatus aktif dan terdaftar secara sah di sistem K1.

* **Method**: `POST`
* **Endpoint**: `/api/k1/validasi-nim`
* **Akses**: Publik / Internal Service

#### Request Body
```json
{
  "nim": "3.32.22.0.15"
}
```

#### Response (200 OK — NIM Valid)
```json
{
  "success": true,
  "code": 200,
  "message": "NIM valid dan aktif di sistem K1.",
  "data": {
    "valid": true,
    "nim": "3.32.22.0.15"
  }
}
```

#### Response (422 Unprocessable — NIM Tidak Ditemukan)
```json
{
  "success": false,
  "code": 422,
  "message": "NIM Tidak Valid: '3.99.99.9.99' tidak terdaftar di sistem K1.",
  "data": {
    "valid": false
  }
}
```

---

## 9. Modul Analitik, Rasio, & Dashboard V1

Seluruh endpoint analitik berada di bawah prefix `/api/v1/` dan telah dioptimalkan untuk visualisasi diagram chart dashboard.

### 9.1 Dashboard Terpadu (4 Tab Utama)
* **Method**: `GET`
* **Endpoint**: `/api/v1/dashboard`
* **Query Parameters**:
  * `tab`: `ringkasan` (default), `produktivitas`, `rasio_jabatan`, atau `tren`.

### 9.2 Statistik Demografi & Pendidikan Dosen
* **Method**: `GET`
* **Endpoint**: `/api/v1/dosen/statistik`

#### Response (200 OK)
```json
{
  "success": true,
  "code": 200,
  "message": "Statistik data dosen berhasil dihitung.",
  "data": {
    "total_dosen": 18,
    "total_aktif": 15,
    "sebaran_status": {
      "DTPS": 15,
      "DTT": 3
    },
    "sebaran_jabatan": [
      { "id_jabatan": 1, "nama_jabatan": "Tenaga Pengajar", "total_dosen": 2 },
      { "id_jabatan": 2, "nama_jabatan": "Asisten Ahli", "total_dosen": 5 },
      { "id_jabatan": 3, "nama_jabatan": "Lektor", "total_dosen": 7 },
      { "id_jabatan": 4, "nama_jabatan": "Lektor Kepala", "total_dosen": 3 },
      { "id_jabatan": 5, "nama_jabatan": "Guru Besar", "total_dosen": 1 }
    ],
    "sebaran_pendidikan": [
      { "jenjang": "S2", "total_dosen": 14 },
      { "jenjang": "S3", "total_dosen": 4 }
    ]
  }
}
```

### 9.3 Kalkulasi Rasio Dosen : Mahasiswa (Tugas 7)
Mengambil agregasi total mahasiswa aktif dari API K1 dan menghitung rasio terhadap total Dosen Tetap Program Studi (DTPS).

* **Method**: `GET`
* **Endpoint**: `/api/v1/rasio-dosen-mahasiswa`
* **Query Parameters**: `tahun` (contoh: 2026), `prodi` (contoh: "Teknologi Rekayasa Komputer").

#### Response (200 OK)
```json
{
  "success": true,
  "code": 200,
  "message": "Kalkulasi rasio dosen:mahasiswa dan sebaran jabatan berhasil diambil.",
  "data": {
    "tahun": 2026,
    "program_studi": "Teknologi Rekayasa Komputer",
    "total_mahasiswa_k1": 375,
    "total_dtps": 15,
    "total_dosen_aktif": 18,
    "rasio_dosen_mahasiswa": 25.0,
    "status_rasio": "Tersedia",
    "sebaran_jabatan": [
      { "nama_jabatan": "Asisten Ahli", "total_dosen": 5 },
      { "nama_jabatan": "Lektor", "total_dosen": 7 },
      { "nama_jabatan": "Lektor Kepala", "total_dosen": 3 }
    ]
  }
}
```

### 9.4 Kalkulasi Produktivitas Dosen (Tugas 8)
Menghitung rata-rata luaran penelitian, pengabdian (PkM), dan publikasi ilmiah per dosen DTPS.

* **Method**: `GET`
* **Endpoint**: `/api/v1/produktivitas-dosen`
* **Query Parameters**: `tahun` (opsional).

#### Response (200 OK)
```json
{
  "success": true,
  "code": 200,
  "message": "Kalkulasi produktivitas dosen berhasil dihitung.",
  "data": {
    "tahun": 2026,
    "total_dtps": 15,
    "rata_penelitian_dtps": 1.4,
    "rata_pkm_dtps": 0.8,
    "rata_luaran_dtps": 2.1,
    "skor_produktivitas_avg": 4.3,
    "daftar_dosen": [
      {
        "id_dosen": 1,
        "nama_dosen": "Dr. Ir. Budi Santoso, M.T.",
        "nidn": "0012058501",
        "jumlah_luaran": 4
      }
    ]
  }
}
```

### 9.5 Analitik Tren TS s/d TS-n (Tugas 10)
Visualisasi tren 5 tahun (*TS-4 s/d TS*) untuk seluruh kegiatan Tri Dharma dan kemitraan aktif.

* **Method**: `GET`
* **Endpoint**: `/api/v1/tren-tridharma`
* **Query Parameters**: `ts` (default: tahun berjalan), `rentang` (default: 4 tahun ke belakang).

#### Response (200 OK)
```json
{
  "success": true,
  "code": 200,
  "message": "Analitik tren TS sampai TS-n berhasil dihitung.",
  "data": {
    "tahun_sekarang": 2026,
    "rentang_tahun": 4,
    "tren_data": [
      { "label": "TS-4", "tahun": 2022, "penelitian": 8, "pkm": 5, "luaran": 10, "kerjasama": 3, "total": 23 },
      { "label": "TS-3", "tahun": 2023, "penelitian": 11, "pkm": 7, "luaran": 14, "kerjasama": 5, "total": 32 },
      { "label": "TS-2", "tahun": 2024, "penelitian": 14, "pkm": 9, "luaran": 19, "kerjasama": 7, "total": 42 },
      { "label": "TS-1", "tahun": 2025, "penelitian": 18, "pkm": 12, "luaran": 25, "kerjasama": 10, "total": 55 },
      { "label": "TS", "tahun": 2026, "penelitian": 21, "pkm": 15, "luaran": 31, "kerjasama": 12, "total": 67 }
    ]
  }
}
```

### 9.6 Daftar Kerja Sama Aktif
* **Method**: `GET`
* **Endpoint**: `/api/v1/kerjasama/aktif`
* **Query Parameters**: `search`, `per_page`.

---

## 🛠 Contoh Pengujian Cepat dengan cURL

### 1. Login
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@polines.ac.id","password":"password123"}'
```

### 2. Mengambil Data Dosen dengan Token
```bash
curl -X GET http://localhost:8000/api/dosen \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN_HASIL_LOGIN>"
```

### 3. Mengambil Dashboard Analitik Rasio
```bash
curl -X GET "http://localhost:8000/api/v1/rasio-dosen-mahasiswa?tahun=2026" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN_HASIL_LOGIN>"
```

---

*Disusun secara terstruktur untuk memenuhi standar evaluasi PBL Akreditasi LAM INFOKOM 2.1 — Jurusan Teknik Elektro Politeknik Negeri Semarang.*