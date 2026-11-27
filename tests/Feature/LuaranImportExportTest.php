<?php

use App\Models\Dosen;
use App\Models\Luaran;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

/**
 * Membuat file Excel dengan lima sheet Luaran.
 */
function createLuaranTestWorkbook(bool $invalidBuku = false): UploadedFile
{
    $spreadsheet = new Spreadsheet;

    $sheets = [
        'Buku' => [
            'fields' => ['judul_buku', 'tahun_terbit'],
            'data' => [
                $invalidBuku ? '' : 'Buku Test Import',
                2026,
            ],
        ],
        'Jurnal' => [
            'fields' => ['nama_jurnal'],
            'data' => ['Jurnal Test Import'],
        ],
        'Prosiding' => [
            'fields' => ['nama_seminar'],
            'data' => ['Seminar Test Import'],
        ],
        'HKI' => [
            'fields' => ['judul_hak_cipta'],
            'data' => ['Hak Cipta Test Import'],
        ],
        'Produk' => [
            'fields' => ['nama_produk'],
            'data' => ['Produk Test Import'],
        ],
    ];

    $commonHeadings = [
        'nidn',
        'id_kegiatan',
        'nama_jenis_luaran',
        'indeks',
    ];

    foreach ($sheets as $index => $config) {
        $sheet = $index === 'Buku'
            ? $spreadsheet->getActiveSheet()
            : $spreadsheet->createSheet();

        $sheet->setTitle($index);

        $headings = array_merge(
            $commonHeadings,
            $config['fields']
        );

        $values = array_merge(
            [
                '0012345678',
                null,
                $index,
                $index === 'Jurnal' || $index === 'Prosiding'
                    ? 'Scopus'
                    : null,
            ],
            $config['data']
        );

        $sheet->fromArray($headings, null, 'A1');
        $sheet->fromArray($values, null, 'A2');
    }

    $writer = new Xlsx($spreadsheet);

    ob_start();
    $writer->save('php://output');
    $content = ob_get_clean();

    $spreadsheet->disconnectWorksheets();

    return UploadedFile::fake()->createWithContent(
        'luaran.xlsx',
        $content
    );
}

beforeEach(function () {
    $this->seed();

    $secret = config(
        'jwt.secret',
        'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes'
    );

    $this->adminToken = JWT::encode([
        'sub' => 1,
        'email' => 'administrator@example.com',
        'name' => 'Administrator',
        'role' => 'ADMINISTRATOR',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $this->dosen = Dosen::where('nidn', '0012345678')->firstOrFail();
});

/*
|--------------------------------------------------------------------------
| Export Luaran
|--------------------------------------------------------------------------
*/

it('dapat mengekspor data luaran ke Excel', function () {
    $response = $this->withToken($this->adminToken)
        ->getJson('/api/luaran/export');

    $response->assertOk();

    expect($response->headers->get('content-disposition'))
        ->toContain('data-luaran.xlsx');
});

/*
|--------------------------------------------------------------------------
| Import Luaran
|--------------------------------------------------------------------------
*/

it('dapat mengimpor lima kategori luaran dari Excel', function () {
    $beforeCount = Luaran::count();

    $file = createLuaranTestWorkbook();

    $response = $this->withToken($this->adminToken)
        ->post('/api/luaran/import', [
            'file' => $file,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'message',
            'Data luaran berhasil diimport.'
        );

    expect(Luaran::count())->toBe($beforeCount + 5);

    $this->assertDatabaseHas('luaran', [
        'judul_buku' => 'Buku Test Import',
    ]);

    $this->assertDatabaseHas('luaran', [
        'nama_jurnal' => 'Jurnal Test Import',
    ]);

    $this->assertDatabaseHas('luaran', [
        'nama_seminar' => 'Seminar Test Import',
    ]);

    $this->assertDatabaseHas('luaran', [
        'judul_hak_cipta' => 'Hak Cipta Test Import',
    ]);

    $this->assertDatabaseHas('luaran', [
        'nama_produk' => 'Produk Test Import',
    ]);
});

it('menolak import jika data wajib buku tidak diisi', function () {
    $beforeCount = Luaran::count();

    $file = createLuaranTestWorkbook(invalidBuku: true);

    $response = $this->withToken($this->adminToken)
        ->post('/api/luaran/import', [
            'file' => $file,
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false);

    expect(Luaran::count())->toBe($beforeCount);
});

it('menolak import jika file tidak disertakan', function () {
    $response = $this->withToken($this->adminToken)
        ->post('/api/luaran/import', []);

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});

it('menolak import jika format file tidak didukung', function () {
    $file = UploadedFile::fake()->create(
        'luaran.pdf',
        100,
        'application/pdf'
    );

    $response = $this->withToken($this->adminToken)
        ->post('/api/luaran/import', [
            'file' => $file,
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});
