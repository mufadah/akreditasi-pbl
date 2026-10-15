<?php

use App\Models\Dosen;
use App\Models\JenisLuaran;
use App\Models\Luaran;
use App\Models\User;
use App\Services\K1ApiService;
use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('login lokal Postman berhasil mengembalikan Bearer token dan data user untuk administrator@example.com', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'administrator@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'code' => 200,
            'message' => 'Login berhasil',
        ])
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'nama', 'email', 'role'],
                'authorization' => ['access_token', 'token_type', 'expires_in'],
            ],
        ]);
});

test('login dengan akun di luar 6 akun K1 resmi ditolak dengan respons 401', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'admin@polines.ac.id',
        'password' => 'password123',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'code' => 401,
            'message' => 'Email atau password salah.',
        ]);
});

test('login dengan 6 akun default K1 berhasil dan mengembalikan token serta role yang tepat', function () {
    $accounts = [
        'kaprodi@example.com' => 'KAPRODI',
        'dosen@example.com' => 'DOSEN',
        'administrator@example.com' => 'ADMINISTRATOR',
        'operator@example.com' => 'OPERATOR',
        'reviewer@example.com' => 'REVIEWER',
        'pimpinan@example.com' => 'PIMPINAN',
    ];

    foreach ($accounts as $email => $expectedRole) {
        $response = $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => $email,
                        'role' => $expectedRole,
                    ],
                ],
            ]);
    }
});

test('endpoint /api/register menginformasikan bahwa registrasi dikelola terpusat via portal K1 SSO', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'User Test',
        'email' => 'test@polines.ac.id',
        'password' => 'secret123',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'code' => 403,
            'message' => 'Registrasi mandiri dinonaktifkan. Pengelolaan akun pengguna dikelola secara terpusat melalui portal SSO K1.',
        ])
        ->assertJsonStructure(['login_url']);
});

test('request tanpa Bearer Token mengembalikan respons 401 dan informasi login_url K1', function () {
    $response = $this->getJson('/api/me');

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'code' => 401,
            'message' => 'Unauthorized: Token Bearer tidak disertakan.',
        ])
        ->assertJsonStructure(['login_url']);
});

test('token kedaluwarsa mengembalikan respons 401 dan informasi login_url K1', function () {
    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $expiredToken = JWT::encode([
        'sub' => 99,
        'email' => 'expired@k1.polines.ac.id',
        'role' => 'dosen',
        'exp' => time() - 3600,
    ], $secret, 'HS256');

    $response = $this->withToken($expiredToken)->getJson('/api/me');

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'code' => 401,
            'message' => 'Unauthorized: Token sudah kedaluwarsa.',
        ])
        ->assertJsonStructure(['login_url']);
});

test('token JWT dari K1 dengan shared secret diolah secara stateless di memory tanpa query tabel users', function () {
    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $uniqueEmail = 'k1_user_'.uniqid().'@polines.ac.id';

    $token = JWT::encode([
        'sub' => '101',
        'email' => $uniqueEmail,
        'nama' => 'Dosen K1 SSO',
        'role' => 'dosen',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $response = $this->withToken($token)->getJson('/api/me');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'code' => 200,
            'message' => 'Profil pengguna berhasil diambil.',
            'data' => [
                'user' => [
                    'email' => $uniqueEmail,
                    'role' => 'DOSEN',
                ],
            ],
        ]);

    // Memastikan tidak ada data user yang ditulis ke database (stateless in-memory)
    $userInDb = User::where('email', $uniqueEmail)->first();
    expect($userInDb)->toBeNull();
});

test('role checking di RoleMiddleware bersifat case-insensitive untuk admin', function () {
    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    // Role huruf kecil 'admin' dari K1
    $adminToken = JWT::encode([
        'user_id' => 102,
        'email' => 'admin_case_test@polines.ac.id',
        'name' => 'Admin K1 Case Insensitive',
        'role' => 'admin',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    // Endpoint yang diproteksi jwt.role:ADMINISTRATOR
    $response = $this->withToken($adminToken)->getJson('/api/jabatan-akademik');

    $response->assertStatus(200);
});

test('role dosen yang mengakses endpoint khusus administrator mengembalikan 403 Forbidden', function () {
    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $dosenToken = JWT::encode([
        'sub' => 103,
        'email' => 'dosen_access_test@polines.ac.id',
        'name' => 'Dosen Test',
        'role' => 'dosen',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $response = $this->withToken($dosenToken)->getJson('/api/jabatan-akademik');

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'code' => 403,
            'message' => 'Forbidden: Anda tidak memiliki hak akses untuk mengakses endpoint ini.',
        ])
        ->assertJsonStructure([
            'error' => ['required_roles', 'current_role'],
        ]);
});

test('K1ApiService verifyToken fallback memvalidasi token via HTTP request ke server K1', function () {
    Http::fake([
        '*auth/me*' => Http::response([
            'success' => true,
            'message' => 'Profil pengguna berhasil diambil',
            'data' => [
                'user' => [
                    'id' => 201,
                    'nama' => 'Opaque Token User',
                    'email' => 'opaque@k1.polines.ac.id',
                    'role' => 'kaprodi',
                ],
            ],
        ], 200),
    ]);

    $service = app(K1ApiService::class);
    $result = $service->verifyToken('dummy-sanctum-opaque-token');

    expect($result)->not->toBeNull()
        ->and($result['email'])->toBe('opaque@k1.polines.ac.id')
        ->and($result['role'])->toBe('kaprodi');
});

// ==============================================================================
// TEST SESUAI FLOWCHART FITUR 1: HAK AKSES
// ==============================================================================

test('token dengan signature palsu ditolak dengan 401 (Tanda tangan JWT valid? -> Tidak -> Tolak 401)', function () {
    $wrongSecret = 'secret-key-yang-salah-dan-palsu-1234567890';
    $tamperedToken = JWT::encode([
        'sub' => 999,
        'email' => 'tampered@polines.ac.id',
        'role' => 'administrator',
        'exp' => time() + 3600,
    ], $wrongSecret, 'HS256');

    $response = $this->withToken($tamperedToken)->getJson('/api/me');

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'code' => 401,
            'message' => 'Unauthorized: Tanda tangan JWT tidak valid atau token tidak sah.',
        ]);
});

test('role di luar 4 role sistem K2 ditolak dengan 403 (Role: admin, kaprodi, dosen, atau asesor? -> Tidak -> Tolak 403)', function () {
    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $operatorToken = JWT::encode([
        'sub' => 888,
        'email' => 'operator@example.com',
        'role' => 'OPERATOR',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $response = $this->withToken($operatorToken)->getJson('/api/v1/dashboard');

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'code' => 403,
            'message' => 'Forbidden: Role tidak diizinkan mengakses sistem ini.',
        ]);
});

test('role reviewer otomatis diakui sebagai asesor (Role: admin, kaprodi, dosen, atau asesor? -> Ya)', function () {
    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $reviewerToken = JWT::encode([
        'sub' => 777,
        'email' => 'reviewer@example.com',
        'role' => 'REVIEWER',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $response = $this->withToken($reviewerToken)->getJson('/api/v1/dashboard');

    $response->assertStatus(200);
});

test('role dosen dilarang memvalidasi evidence (Role boleh melakukan aksi pada menu ini? -> Tidak -> Tolak 403)', function () {
    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $dosenToken = JWT::encode([
        'sub' => 1,
        'email' => 'dosen@example.com',
        'role' => 'DOSEN',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $response = $this->withToken($dosenToken)->postJson('/api/evidence/1/validasi', [
        'status_validasi' => 'Valid',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'code' => 403,
            'message' => 'Forbidden: Anda tidak memiliki hak akses untuk mengakses endpoint ini.',
        ]);
});

test('role dosen mengakses detail data luaran miliknya sendiri berhasil (Role Dosen? -> Ya -> Data milik dosen tersebut? -> Ya -> Proses request)', function () {
    $dosen1 = Dosen::firstOrCreate(
        ['email' => 'dosen@example.com'],
        [
            'id_jabatan' => 1,
            'id_pendidikan' => 1,
            'nidn' => '0012345678',
            'nama_dosen' => 'Dosen Milik Sendiri',
            'status_dosen' => 'DTPS',
        ]
    );

    $jenisLuaran = JenisLuaran::first();

    $luaran = Luaran::create([
        'id_dosen' => $dosen1->id_dosen,
        'id_jenis_luaran' => $jenisLuaran->id_jenis_luaran,
        'judul_buku' => 'Buku Monograf Dosen 1',
        'tahun_terbit' => 2026,
    ]);

    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $tokenDosen1 = JWT::encode([
        'sub' => $dosen1->id_dosen,
        'email' => 'dosen@example.com',
        'role' => 'DOSEN',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $response = $this->withToken($tokenDosen1)->getJson("/api/luaran/{$luaran->id_luaran}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'code' => 200,
            'data' => [
                'id_luaran' => $luaran->id_luaran,
                'id_dosen' => $dosen1->id_dosen,
            ],
        ]);
});

test('role dosen mengakses detail data luaran milik dosen lain ditolak 403 (Role Dosen? -> Ya -> Data milik dosen tersebut? -> Tidak -> Tolak 403)', function () {
    $dosen2 = Dosen::firstOrCreate(
        ['email' => 'dosen_lain@example.com'],
        [
            'id_jabatan' => 1,
            'id_pendidikan' => 1,
            'nidn' => '0098765432',
            'nama_dosen' => 'Dosen Lain',
            'status_dosen' => 'DTPS',
        ]
    );

    $jenisLuaran = JenisLuaran::first();

    $luaranDosenLain = Luaran::create([
        'id_dosen' => $dosen2->id_dosen,
        'id_jenis_luaran' => $jenisLuaran->id_jenis_luaran,
        'judul_buku' => 'Buku Monograf Dosen Lain',
        'tahun_terbit' => 2026,
    ]);

    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    // Login sebagai dosen@example.com yang mencoba melihat data milik dosen_lain@example.com
    $tokenDosen1 = JWT::encode([
        'sub' => 1,
        'email' => 'dosen@example.com',
        'role' => 'DOSEN',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $response = $this->withToken($tokenDosen1)->getJson("/api/luaran/{$luaranDosenLain->id_luaran}");

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'code' => 403,
            'message' => 'Forbidden: Data bukan milik dosen yang bersangkutan.',
        ]);
});

test('role administrator bebas mengakses data luaran dosen mana pun (Role Dosen? -> Tidak -> Proses request)', function () {
    $dosen2 = Dosen::firstOrCreate(
        ['email' => 'dosen_lain@example.com'],
        [
            'id_jabatan' => 1,
            'id_pendidikan' => 1,
            'nidn' => '0098765432',
            'nama_dosen' => 'Dosen Lain',
            'status_dosen' => 'DTPS',
        ]
    );

    $jenisLuaran = JenisLuaran::first();
    $luaran = Luaran::create([
        'id_dosen' => $dosen2->id_dosen,
        'id_jenis_luaran' => $jenisLuaran->id_jenis_luaran,
        'judul_buku' => 'Buku Monograf Dosen Lain',
        'tahun_terbit' => 2026,
    ]);

    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $adminToken = JWT::encode([
        'sub' => 999,
        'email' => 'administrator@example.com',
        'role' => 'ADMINISTRATOR',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $response = $this->withToken($adminToken)->getJson("/api/luaran/{$luaran->id_luaran}");

    $response->assertStatus(200);
});

test('role dosen dilarang membuat luaran atas nama dosen lain (Data milik dosen tersebut? -> Tidak -> Tolak 403)', function () {
    $dosen2 = Dosen::firstOrCreate(
        ['email' => 'dosen_lain@example.com'],
        [
            'id_jabatan' => 1,
            'id_pendidikan' => 1,
            'nidn' => '0098765432',
            'nama_dosen' => 'Dosen Lain',
            'status_dosen' => 'DTPS',
        ]
    );
    $jenisLuaran = JenisLuaran::first();

    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $tokenDosen1 = JWT::encode([
        'sub' => 1,
        'email' => 'dosen@example.com',
        'role' => 'DOSEN',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $response = $this->withToken($tokenDosen1)->postJson('/api/luaran', [
        'id_dosen' => $dosen2->id_dosen, // Mencoba mendaftarkan id_dosen milik orang lain
        'id_jenis_luaran' => $jenisLuaran->id_jenis_luaran,
        'judul_buku' => 'Buku Ilegal Dosen Lain',
        'tahun_terbit' => 2026,
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'code' => 403,
            'message' => 'Forbidden: Data bukan milik dosen yang bersangkutan.',
        ]);
});

test('role dosen berhasil membuat luaran dengan id_dosen miliknya sendiri (Data milik dosen tersebut? -> Ya -> Proses request)', function () {
    $dosen1 = Dosen::where('email', 'dosen@example.com')->first();
    $jenisLuaranBuku = JenisLuaran::where('nama_jenis_luaran', 'like', '%Buku%')->first();

    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $tokenDosen1 = JWT::encode([
        'sub' => $dosen1->id_dosen,
        'email' => 'dosen@example.com',
        'role' => 'DOSEN',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $response = $this->withToken($tokenDosen1)->postJson('/api/luaran', [
        'id_dosen' => $dosen1->id_dosen,
        'id_jenis_luaran' => $jenisLuaranBuku->id_jenis_luaran,
        'judul_buku' => 'Buku Legal Milik Sendiri',
        'tahun_terbit' => 2026,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'code' => 201,
            'message' => 'Data luaran berhasil ditambahkan.',
        ]);
});

test('role dosen hanya melihat data luaran miliknya sendiri pada endpoint index (Scoping data dosen)', function () {
    $dosen1 = Dosen::where('email', 'dosen@example.com')->first();
    $dosen2 = Dosen::firstOrCreate(
        ['email' => 'dosen_lain@example.com'],
        [
            'id_jabatan' => 1,
            'id_pendidikan' => 1,
            'nidn' => '0098765432',
            'nama_dosen' => 'Dosen Lain',
            'status_dosen' => 'DTPS',
        ]
    );

    $jenisBuku = JenisLuaran::where('nama_jenis_luaran', 'like', '%Buku%')->first();

    // Buat data untuk Dosen 1 dan Dosen 2
    Luaran::create([
        'id_dosen' => $dosen1->id_dosen,
        'id_jenis_luaran' => $jenisBuku->id_jenis_luaran,
        'judul_buku' => 'Buku Milik Dosen 1',
        'tahun_terbit' => 2026,
    ]);

    Luaran::create([
        'id_dosen' => $dosen2->id_dosen,
        'id_jenis_luaran' => $jenisBuku->id_jenis_luaran,
        'judul_buku' => 'Buku Milik Dosen 2',
        'tahun_terbit' => 2026,
    ]);

    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $tokenDosen1 = JWT::encode([
        'sub' => $dosen1->id_dosen,
        'email' => 'dosen@example.com',
        'role' => 'DOSEN',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    // Dosen 1 mengambil daftar luaran
    $response = $this->withToken($tokenDosen1)->getJson('/api/luaran');

    $response->assertStatus(200);
    $data = $response->json('data.data');

    // Semua data yang dikembalikan harus milik Dosen 1
    foreach ($data as $item) {
        expect($item['id_dosen'])->toBe($dosen1->id_dosen);
    }
});

test('role dosen mengakses filter query ?id_dosen milik dosen lain ditolak 403 (Data milik dosen tersebut? -> Tidak -> Tolak 403)', function () {
    $dosen1 = Dosen::where('email', 'dosen@example.com')->first();
    $dosen2 = Dosen::firstOrCreate(
        ['email' => 'dosen_lain@example.com'],
        [
            'id_jabatan' => 1,
            'id_pendidikan' => 1,
            'nidn' => '0098765432',
            'nama_dosen' => 'Dosen Lain',
            'status_dosen' => 'DTPS',
        ]
    );

    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $tokenDosen1 = JWT::encode([
        'sub' => $dosen1->id_dosen,
        'email' => 'dosen@example.com',
        'role' => 'DOSEN',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $response = $this->withToken($tokenDosen1)->getJson("/api/luaran?id_dosen={$dosen2->id_dosen}");

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'code' => 403,
            'message' => 'Forbidden: Data bukan milik dosen yang bersangkutan.',
        ]);
});

test('role dosen dilarang mengubah (update) data luaran milik dosen lain ditolak 403', function () {
    $dosen1 = Dosen::where('email', 'dosen@example.com')->first();
    $dosen2 = Dosen::firstOrCreate(
        ['email' => 'dosen_lain@example.com'],
        [
            'id_jabatan' => 1,
            'id_pendidikan' => 1,
            'nidn' => '0098765432',
            'nama_dosen' => 'Dosen Lain',
            'status_dosen' => 'DTPS',
        ]
    );

    $jenisBuku = JenisLuaran::where('nama_jenis_luaran', 'like', '%Buku%')->first();
    $luaranDosen2 = Luaran::create([
        'id_dosen' => $dosen2->id_dosen,
        'id_jenis_luaran' => $jenisBuku->id_jenis_luaran,
        'judul_buku' => 'Buku Milik Dosen 2',
        'tahun_terbit' => 2026,
    ]);

    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $tokenDosen1 = JWT::encode([
        'sub' => $dosen1->id_dosen,
        'email' => 'dosen@example.com',
        'role' => 'DOSEN',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $response = $this->withToken($tokenDosen1)->putJson("/api/luaran/{$luaranDosen2->id_luaran}", [
        'judul_buku' => 'Buku Diubah Secara Ilegal',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'code' => 403,
            'message' => 'Forbidden: Data bukan milik dosen yang bersangkutan.',
        ]);
});

test('role dosen dilarang menghapus (delete) data luaran milik dosen lain ditolak 403', function () {
    $dosen1 = Dosen::where('email', 'dosen@example.com')->first();
    $dosen2 = Dosen::firstOrCreate(
        ['email' => 'dosen_lain@example.com'],
        [
            'id_jabatan' => 1,
            'id_pendidikan' => 1,
            'nidn' => '0098765432',
            'nama_dosen' => 'Dosen Lain',
            'status_dosen' => 'DTPS',
        ]
    );

    $jenisBuku = JenisLuaran::where('nama_jenis_luaran', 'like', '%Buku%')->first();
    $luaranDosen2 = Luaran::create([
        'id_dosen' => $dosen2->id_dosen,
        'id_jenis_luaran' => $jenisBuku->id_jenis_luaran,
        'judul_buku' => 'Buku Milik Dosen 2 Untuk Delete',
        'tahun_terbit' => 2026,
    ]);

    $secret = config('jwt.secret', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes');
    $tokenDosen1 = JWT::encode([
        'sub' => $dosen1->id_dosen,
        'email' => 'dosen@example.com',
        'role' => 'DOSEN',
        'exp' => time() + 3600,
    ], $secret, 'HS256');

    $response = $this->withToken($tokenDosen1)->deleteJson("/api/luaran/{$luaranDosen2->id_luaran}");

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'code' => 403,
            'message' => 'Forbidden: Data bukan milik dosen yang bersangkutan.',
        ]);
});
