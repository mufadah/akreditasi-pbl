# Domain Knowledge Kelompok 2 (SDM & Tri Dharma)

Sebagai AI Agent untuk Kelompok 2, **HANYA** fokus pada entitas dan logika berikut:
- **Master Data:** Dosen, Tenaga Kependidikan (Tendik), Jabatan Akademik, Pendidikan, Bidang Keahlian, Jenis Penelitian/Publikasi/PkM/HKI, Mitra, Jenis Kerja Sama.
- **Transaksi:** Pelaksanaan Penelitian, Publikasi, HKI, PkM, dan MoU/MoA/IA Kerja Sama.
- **Evidence:** Pengelolaan file PDF/dokumen bukti terkait SDM dan Tri Dharma.
- **Analytics:** Menghitung rasio dosen, produktivitas penelitian/PkM per dosen, gap kompetensi.

## Batasan (Do NOT Do This):
- DILARANG membuat/memodifikasi tabel Mahasiswa, Lulusan, atau Kurikulum (Itu tugas Kelompok 1).
- DILARANG membuat/memodifikasi tabel Master LAM INFOKOM, Keuangan, Sarpras, atau algoritma Self Assessment Akreditasi (Itu tugas Kelompok 3).

## Interoperabilitas API (Cross-Group Communication):
Jika fitur Kelompok 2 membutuhkan data Mahasiswa untuk menghitung rasio:
- JANGAN *query* ke tabel database mahasiswa.
- GUNAKAN HTTP Client Laravel untuk memanggil `GET /api/v1/mahasiswa/statistik` dari Kelompok 1.

Jika Kelompok 3 meminta data Dosen/Penelitian:
- Buat dan sediakan endpoint `GET /api/v1/dosen/statistik` dan `GET /api/v1/tridharma/rekap`.   