<?php

use App\Models\Dosen;
use App\Models\JenisLuaran;
use App\Models\Luaran;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('pda publication sync imports matching lecturer publications and uses the required request headers', function () {
    $dosen = Dosen::create([
        'id_jabatan' => 1,
        'id_pendidikan' => 1,
        'nama_dosen' => 'Dosen Publikasi Test',
        'nidn' => '0011223344',
        'nuptk' => '198001012005011001',
        'email' => 'publikasi-test@polines.ac.id',
        'status_dosen' => 'DTPS',
    ]);

    Http::fake(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        if ((int) ($query['page'] ?? 0) === 1) {
            return Http::response([
                'data' => [
                    'data' => [[
                        'judul' => 'Publikasi Uji PDA',
                        'jenis_publikasi' => 'Jurnal',
                        'indeksasi' => 'SCOPUS Q2',
                        'tahun' => 2024,
                        'tanggal_terbit' => '17/05/2024',
                        'doi' => '10.1234/pda.test',
                        'url' => 'https://example.test/publikasi',
                        'authors' => [[
                            'nama' => 'Dosen Publikasi Test',
                            'nip' => '198001012005011001',
                        ]],
                    ]],
                ],
            ]);
        }

        return Http::response(['data' => ['data' => []]]);
    });

    $this->artisan('pda:sync-publikasi --pages=3 --year=2024')
        ->assertSuccessful();

    $luaran = Luaran::query()->where('nama_jurnal', 'Publikasi Uji PDA')->firstOrFail();

    expect($luaran->id_dosen)->toBe($dosen->id_dosen)
        ->and($luaran->tahun_terbit)->toBe(2024)
        ->and($luaran->tanggal_terbit->format('Y-m-d'))->toBe('2024-05-17')
        ->and($luaran->doi)->toBe('10.1234/pda.test')
        ->and($luaran->indeksasi)->toBe('SCOPUS Q2')
        ->and($luaran->tautan_jurnal)->toBe('https://example.test/publikasi')
        ->and(JenisLuaran::find($luaran->id_jenis_luaran)->nama_jenis_luaran)->toBe('Jurnal Internasional Bereputasi');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'page=1')
        && str_contains($request->url(), 'limit=50')
        && str_contains($request->url(), 'sort=tanggal')
        && str_contains($request->url(), 'direction=desc')
        && $request->hasHeader('Referer', 'https://pda.polines.ac.id/publikasi')
        && $request->hasHeader('X-Requested-With', 'XMLHttpRequest'));
});

test('pda publication sync matches authors by nidn and name and skips outsiders and duplicate titles', function () {
    $nidnDosen = Dosen::create([
        'id_jabatan' => 1,
        'id_pendidikan' => 1,
        'nama_dosen' => 'Dosen NIDN Test',
        'nidn' => '0022334455',
        'email' => 'nidn-test@polines.ac.id',
        'status_dosen' => 'DTPS',
    ]);
    $nameDosen = Dosen::create([
        'id_jabatan' => 1,
        'id_pendidikan' => 1,
        'nama_dosen' => 'Dosen Name Test',
        'nidn' => '0033445566',
        'email' => 'name-test@polines.ac.id',
        'status_dosen' => 'DTPS',
    ]);

    $publications = [
        [
            'judul' => 'Publikasi Penulis NIDN',
            'jenis' => 'Jurnal',
            'indeksasi' => 'SINTA 2',
            'tahun' => 2024,
            'authors' => [['nidn' => '0022334455']],
        ],
        [
            'judul' => 'Publikasi Penulis Nama',
            'jenis' => 'Jurnal',
            'indeksasi' => 'SINTA 2',
            'tahun' => 2024,
            'authors' => ['Dosen Name Test'],
        ],
        [
            'judul' => 'Publikasi Penulis Luar',
            'jenis' => 'Jurnal',
            'indeksasi' => 'SINTA 2',
            'tahun' => 2024,
            'authors' => ['Penulis Bukan Dosen TRK'],
        ],
        [
            'judul' => 'Publikasi Penulis NIDN',
            'jenis' => 'Jurnal',
            'indeksasi' => 'SINTA 2',
            'tahun' => 2024,
            'authors' => [['nidn' => '0033445566']],
        ],
    ];

    Http::fake(function (Request $request) use ($publications) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return Http::response([
            'data' => [
                'data' => (int) ($query['page'] ?? 0) === 1 ? $publications : [],
            ],
        ]);
    });

    $this->artisan('pda:sync-publikasi --pages=2')
        ->assertSuccessful();
    $this->artisan('pda:sync-publikasi --pages=2')
        ->assertSuccessful();

    expect(Luaran::query()->where('nama_jurnal', 'Publikasi Penulis NIDN')->firstOrFail()->id_dosen)->toBe($nidnDosen->id_dosen)
        ->and(Luaran::query()->where('nama_jurnal', 'Publikasi Penulis Nama')->firstOrFail()->id_dosen)->toBe($nameDosen->id_dosen)
        ->and(Luaran::query()->whereIn('nama_jurnal', [
            'Publikasi Penulis NIDN',
            'Publikasi Penulis Nama',
        ])->count())->toBe(2);
});

test('pda publication sync rejects invalid options and reports remote errors', function () {
    $this->artisan('pda:sync-publikasi --pages=0')
        ->assertExitCode(2);

    Http::fake([
        'https://pda.polines.ac.id/internal/publikasi/list*' => Http::response([], 503),
    ]);

    $this->artisan('pda:sync-publikasi --pages=1')
        ->expectsOutputToContain('PDA mengembalikan HTTP 503')
        ->assertExitCode(1);
});
