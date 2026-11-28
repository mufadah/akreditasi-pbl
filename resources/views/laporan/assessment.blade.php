@php
    $fmt = fn ($n) => is_numeric($n) ? rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',') : $n;
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
    <p class="meta">Tahun penilaian {{ $tahun }} | Jenjang {{ $jenjang }} | Dibuat {{ $dibuat }}</p>

    @foreach($bidang as $nama => $hasil)
        <h2>{{ $label[$nama] }}</h2>
        <p>
            Nilai bidang <strong>{{ $fmt($hasil['ringkasan']['nilai_bidang']) }}</strong> dari 4.
            Indikator terpenuhi {{ $hasil['ringkasan']['indikator_terpenuhi'] }},
            gap terdeteksi {{ $hasil['ringkasan']['gap_terdeteksi'] }}.
        </p>

        @if(isset($hasil['rincian']))
            <p>
                Kerja sama yang berlaku: total {{ $hasil['rincian']['total_berlaku'] }},
                internasional {{ $hasil['rincian']['internasional'] }},
                nasional {{ $hasil['rincian']['nasional'] }},
                lainnya {{ $hasil['rincian']['lainnya'] }},
                mitra unik {{ $hasil['rincian']['mitra_unik'] }}.
            </p>
        @endif

        <table>
            <thead>
                <tr>
                    <th>Kode</th><th>Indikator</th><th>Nilai</th><th>Bobot</th>
                    <th>Skor</th><th>Target</th><th>Status</th><th>Rekomendasi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($hasil['indikator'] as $item)
                    <tr>
                        <td>{{ $item['kode'] }}</td>
                        <td>{{ $item['nama'] }}</td>
                        <td class="angka">{{ $fmt($item['nilai_metrik']) }}</td>
                        <td class="angka">{{ $fmt($item['bobot']) }}</td>
                        <td class="angka">{{ $item['skor'] }}</td>
                        <td class="angka">{{ $fmt($item['skor_target']) }}</td>
                        <td class="{{ $item['status'] === 'Terpenuhi' ? 'ok' : 'gap' }}">{{ $item['status'] }}</td>
                        <td>{{ $item['rekomendasi'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <h2>Gap Kompetensi Dosen ({{ $gap_kompetensi['populasi'] }}, {{ $gap_kompetensi['total_dosen'] }} dosen)</h2>
    <table>
        <thead>
            <tr>
                <th>Kode</th><th>Indikator</th><th>Jumlah</th><th>Persentase</th>
                <th>Standar (%)</th><th>Selisih (%)</th><th>Kekurangan</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($gap_kompetensi['indikator'] as $item)
                <tr>
                    <td>{{ $item['kode'] }}</td>
                    <td>{{ $item['nama'] }}</td>
                    <td class="angka">{{ $item['jumlah_dosen'] }}</td>
                    <td class="angka">{{ $fmt($item['persentase']) }}</td>
                    <td class="angka">{{ isset($item['standar_persen']) ? $fmt($item['standar_persen']) : 'belum diatur' }}</td>
                    <td class="angka">{{ isset($item['selisih_persen']) ? $fmt($item['selisih_persen']) : '-' }}</td>
                    <td class="angka">{{ $item['kekurangan_dosen'] ?? '-' }}</td>
                    <td class="{{ $item['status'] === 'belum_terpenuhi' ? 'gap' : ($item['status'] === 'terpenuhi' ? 'ok' : '') }}">{{ str_replace('_', ' ', $item['status']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Rekomendasi Perbaikan</h2>
    @forelse($rekomendasi as $item)
        <p><strong>{{ $loop->iteration }}. {{ $item['sumber'] }}</strong><br>{{ $item['rekomendasi'] }}</p>
    @empty
        <p>Tidak ada gap yang terdeteksi.</p>
    @endforelse

    @if(count($catatan) > 0)
        <h2>Catatan</h2>
        <ul>
            @foreach($catatan as $teks)
                <li>{{ $teks }}</li>
            @endforeach
        </ul>
    @endif
</body>
</html>