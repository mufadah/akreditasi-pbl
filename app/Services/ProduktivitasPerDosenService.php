<?php

namespace App\Services;

use App\Models\Dosen;
use App\Models\Luaran;
use Illuminate\Support\Facades\DB;

class ProduktivitasPerDosenService
{
    private const LUARAN_KOSONG = ['total' => 0, 'scopus' => 0, 'wos' => 0, 'non_indeks' => 0];

    public function hitung($tahun = null, $idDosen = null)
    {
        $dosen = Dosen::query()
            ->where('status_dosen', 'DTPS')
            ->when($idDosen, fn ($q) => $q->where('id_dosen', $idDosen))
            ->orderBy('nama_dosen')
            ->get(['id_dosen', 'nama_dosen', 'nidn']);

        $ids = $dosen->pluck('id_dosen')->all();
        $kegiatan = $this->kegiatanPerDosen($ids, $tahun);
        $luaran = $this->luaranPerDosen($ids, $tahun);

        $daftar = $dosen->map(function ($d) use ($kegiatan, $luaran) {
            $k = $kegiatan[$d->id_dosen] ?? [];
            $l = $luaran[$d->id_dosen] ?? [];
            $tahunList = array_unique(array_merge(array_keys($k), array_keys($l)));
            sort($tahunList);

            $perTahun = array_map(function ($t) use ($k, $l) {
                $penelitian = $k[$t]['penelitian'] ?? 0;
                $pkm = $k[$t]['pkm'] ?? 0;
                $luaranTahun = $l[$t] ?? self::LUARAN_KOSONG;

                return [
                    'tahun' => $t,
                    'jumlah_penelitian' => $penelitian,
                    'jumlah_pkm' => $pkm,
                    'luaran' => $luaranTahun,
                    'skor_produktivitas' => $penelitian + $pkm + $luaranTahun['total'],
                ];
            }, $tahunList);

            return [
                'id_dosen' => $d->id_dosen,
                'nama_dosen' => $d->nama_dosen,
                'nidn' => $d->nidn,
                'per_tahun' => $perTahun,
                'total' => [
                    'jumlah_penelitian' => array_sum(array_column($perTahun, 'jumlah_penelitian')),
                    'jumlah_pkm' => array_sum(array_column($perTahun, 'jumlah_pkm')),
                    'jumlah_luaran' => array_sum(array_map(fn ($p) => $p['luaran']['total'], $perTahun)),
                    'skor_produktivitas' => array_sum(array_column($perTahun, 'skor_produktivitas')),
                ],
            ];
        })->values()->all();

        return [
            'filter' => ['tahun' => $tahun ? (int) $tahun : null, 'id_dosen' => $idDosen ? (int) $idDosen : null],
            'populasi' => 'Dosen tetap (DTPS)',
            'rumus_skor' => 'jumlah_penelitian + jumlah_pkm + jumlah_luaran',
            'total_dosen' => count($daftar),
            'daftar_dosen' => $daftar,
        ];
    }

    private function kegiatanPerDosen($ids, $tahun)
    {
        $rows = DB::table('anggota_kegiatan as a')
            ->join('kegiatan as k', 'k.id_kegiatan', '=', 'a.id_kegiatan')
            ->join('jenis_kegiatan as j', 'j.id_jenis_kegiatan', '=', 'k.id_jenis_kegiatan')
            ->whereIn('a.id_dosen', $ids)
            ->whereNotNull('k.tahun_kegiatan')
            ->when($tahun, fn ($q) => $q->where('k.tahun_kegiatan', $tahun))
            ->groupBy('a.id_dosen', 'k.tahun_kegiatan', 'j.nama_jenis_kegiatan')
            ->select('a.id_dosen', 'k.tahun_kegiatan as tahun', 'j.nama_jenis_kegiatan as jenis', DB::raw('COUNT(DISTINCT k.id_kegiatan) as jumlah'))
            ->get();

        $hasil = [];

        foreach ($rows as $row) {
            $jenis = strtolower($row->jenis);

            if (in_array($jenis, ['penelitian', 'pkm'], true)) {
                $hasil[$row->id_dosen][(int) $row->tahun][$jenis] = (int) $row->jumlah;
            }
        }

        return $hasil;
    }

    private function luaranPerDosen($ids, $tahun)
    {
        $daftar = Luaran::query()
            ->whereIn('id_dosen', $ids)
            ->with('kegiatan:id_kegiatan,tahun_kegiatan')
            ->get(['id_luaran', 'id_dosen', 'id_kegiatan', 'indeksasi', 'created_at']);

        $hasil = [];

        foreach ($daftar as $luaran) {
            $tahunLuaran = (int) ($luaran->kegiatan?->tahun_kegiatan ?: $luaran->created_at->year);

            if ($tahun && $tahunLuaran !== (int) $tahun) {
                continue;
            }

            $hasil[$luaran->id_dosen][$tahunLuaran] ??= self::LUARAN_KOSONG;
            $hasil[$luaran->id_dosen][$tahunLuaran]['total']++;
            $hasil[$luaran->id_dosen][$tahunLuaran][$this->kategoriIndeks($luaran->indeksasi)]++;
        }

        return $hasil;
    }

    private function kategoriIndeks($indeksasi)
    {
        $teks = strtolower((string) $indeksasi);

        return match (true) {
            str_contains($teks, 'scopus') => 'scopus',
            str_contains($teks, 'wos'), str_contains($teks, 'web of science') => 'wos',
            default => 'non_indeks',
        };
    }
}