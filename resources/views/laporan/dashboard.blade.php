@php
    $fmt = fn ($n) => is_numeric($n) ? rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',') : $n;
    $rasio = $rasio ?? [];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }}</title>
    @include('laporan._gaya')
</head>
<body>
    <h1>{{ $judul }}</h1>
    <p class="meta">Tahun {{ $tahun }} | Dibuat {{ $dibuat }}</p>

    <h2>Ringkasan Kegiatan</h2>
    <table>
        <thead><tr><th>Kategori</th><th>Total</th><th>Berjalan</th><th>Selesai</th><th>Usulan</th><th>Lainnya</th></tr></thead>
        <tbody>
            @foreach(['penelitian' => 'Penelitian', 'pkm' => 'PkM'] as $kunci => $nama)
                <tr>
                    <td>{{ $nama }}</td>
                    <td class="angka">{{ data_get($ringkasan, "kegiatan.{$kunci}.total", 0) }}</td>
                    @foreach(['berjalan', 'selesai', 'usulan', 'lainnya'] as $status)
                        <td class="angka">{{ data_get($ringkasan, "kegiatan.{$kunci}.by_status.{$status}", 0) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <p>Total luaran: <strong>{{ data_get($ringkasan, 'luaran.total', 0) }}</strong> | Grand total: <strong>{{ data_get($ringkasan, 'grand_total', 0) }}</strong></p>
    <table>
        <thead><tr><th>Jenis Luaran</th><th>Jumlah</th></tr></thead>
        <tbody>
            @forelse(data_get($ringkasan, 'luaran.by_jenis', []) as $jenis => $jumlah)
                <tr><td>{{ $jenis }}</td><td class="angka">{{ $jumlah }}</td></tr>
            @empty
                <tr><td colspan="2">Belum ada luaran.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Produktivitas Dosen</h2>
    <p>
        DTPS {{ $produktivitas['total_dtps'] ?? 0 }} |
        Rata-rata penelitian {{ $fmt($produktivitas['rata_penelitian_dtps'] ?? 0) }} |
        Rata-rata PkM {{ $fmt($produktivitas['rata_pkm_dtps'] ?? 0) }} |
        Rata-rata luaran {{ $fmt($produktivitas['rata_luaran_dtps'] ?? 0) }} |
        Skor rata-rata <strong>{{ $fmt($produktivitas['skor_produktivitas_avg'] ?? 0) }}</strong>
    </p>
    <table>
        <thead><tr><th>Dosen</th><th>NIDN</th><th>Jumlah Luaran</th></tr></thead>
        <tbody>
            @forelse($produktivitas['daftar_dosen'] ?? [] as $dosen)
                <tr><td>{{ $dosen['nama_dosen'] }}</td><td>{{ $dosen['nidn'] }}</td><td class="angka">{{ $dosen['jumlah_luaran'] }}</td></tr>
            @empty
                <tr><td colspan="3">Belum ada data dosen.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Rasio Dosen dan Mahasiswa</h2>
    @if(count($rasio) > 0)
        <p>
            Program studi {{ $rasio['program_studi'] ?? '-' }} |
            Total mahasiswa {{ $rasio['total_mahasiswa_k1'] ?? 'tidak tersedia' }} |
            Total DTPS {{ $rasio['total_dtps'] ?? 0 }} |
            Rasio mahasiswa per DTPS <strong>{{ $rasio['rasio_dosen_mahasiswa'] ?? 'tidak tersedia' }}</strong><br>
            Status: {{ $rasio['status_rasio'] ?? '-' }}
        </p>
        <table>
            <thead><tr><th>Jabatan Akademik</th><th>Total Dosen</th></tr></thead>
            <tbody>
                @foreach($rasio['sebaran_jabatan'] ?? [] as $jabatan)
                    <tr><td>{{ $jabatan['nama_jabatan'] }}</td><td class="angka">{{ $jabatan['total_dosen'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>Data rasio tidak tersedia saat laporan dibuat.</p>
    @endif

    <h2>Tren Tri Dharma</h2>
    <table>
        <thead><tr><th>Periode</th><th>Tahun</th><th>Penelitian</th><th>PkM</th><th>Luaran</th><th>Kerja Sama</th><th>Total</th></tr></thead>
        <tbody>
            @foreach(data_get($tren, 'tren_data', []) as $baris)
                <tr>
                    <td>{{ $baris['label'] }}</td>
                    <td>{{ $baris['tahun'] }}</td>
                    <td class="angka">{{ $baris['penelitian'] }}</td>
                    <td class="angka">{{ $baris['pkm'] }}</td>
                    <td class="angka">{{ $baris['luaran'] }}</td>
                    <td class="angka">{{ $baris['kerjasama'] }}</td>
                    <td class="angka">{{ $baris['total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Early Warning</h2>
    <table>
        <thead><tr><th>Indikator</th><th>Jumlah Peringatan</th></tr></thead>
        <tbody>
            @foreach($early_warning['indikator'] as $indikator)
                <tr>
                    <td>{{ $indikator['nama'] }}</td>
                    <td class="angka {{ $indikator['jumlah_peringatan'] > 0 ? 'gap' : 'ok' }}">{{ $indikator['jumlah_peringatan'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if(count($early_warning_baris) > 0)
        <table>
            <thead><tr><th>Indikator</th><th>Dosen</th><th>NIDN</th><th>Keterangan</th></tr></thead>
            <tbody>
                @foreach($early_warning_baris as $baris)
                    <tr>
                        <td>{{ $baris[0] }}</td><td>{{ $baris[1] }}</td><td>{{ $baris[2] }}</td><td>{{ $baris[3] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>