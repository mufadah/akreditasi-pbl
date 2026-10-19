<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncSwaggerFromPostman extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swagger:sync {--postman= : Path ke berkas Postman Collection JSON}';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'Konversi Postman Collection ke OpenAPI 3.0 / Swagger UI sesuai standar referensi';

    /**
     * Deskripsi untuk setiap tag Swagger UI.
     *
     * @var array<string, string>
     */
    private array $tagDescriptions = [
        'Auth' => 'Autentikasi Terpusat via SSO Kelompok 1 (http://akademik.fayra.my.id/api/auth/login)',
        'Jabatan Akademik' => 'Endpoint API Master Jabatan Akademik Dosen',
        'Pendidikan' => 'Endpoint API Master Jenjang Pendidikan',
        'Bidang Keahlian' => 'Endpoint API Master Bidang Keahlian',
        'Mitra' => 'Endpoint API Master Data Mitra',
        'Jenis Kegiatan' => 'Endpoint API Master Jenis Kegiatan Tridharma',
        'Jenis Luaran' => 'Endpoint API Master Jenis Luaran Tridharma',
        'Jenis Kerja Sama' => 'Endpoint API Master Jenis Kerja Sama',
        'Dosen' => 'Endpoint API Manajemen SDM Dosen (CRUD, Import Excel & Restore)',
        'Tenaga Kependidikan' => 'Endpoint API Manajemen SDM Tenaga Kependidikan',
        'Kegiatan' => 'Endpoint API Tridharma Kegiatan (Penelitian, Pengabdian & Pengajaran)',
        'Luaran' => 'Endpoint API Tridharma Luaran (Publikasi Jurnal, Buku, HKI, Prosiding & Prototipe)',
        'Kerja Sama' => 'Endpoint API Dokumen & Perpanjangan Kerja Sama Kemitraan',
        'Aktivitas Kerja Sama' => 'Endpoint API Pelaksanaan & Aktivitas Implementasi Kerja Sama',
        'Evidence' => 'Endpoint API Repository Dokumen, Berkas Fisik, Link Drive & Validasi Kaprodi',
        'Validasi Mahasiswa' => 'Endpoint API Validasi Keabsahan NIM Mahasiswa (Integrasi Sistem K1)',
        'Statistik' => 'Endpoint API Statistik, Agregasi, Rasio Dosen-Mahasiswa & Dashboard LAM',
        'Sertifikasi' => 'Fitur 9: Pengelolaan Data Sertifikasi Pendidik & Kompetensi Profesi',
        'Assessment & Gap Analysis' => 'Fitur 12: Kalkulasi Gap Analysis & Evaluasi Rubrik LAM K3',
        'Konfigurasi Metrik' => 'Fitur 13: Pemetaan Indikator K3 dan Rumus Formula Metrik K2',
        'Notifikasi & Audit Log' => 'Fitur 14 & 16: Notifikasi In-App & Riwayat Audit Log Forensik',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $postmanPath = $this->option('postman') ?: base_path('postman_collection_akreditasi_pbl.json');
        $outputPath = storage_path('api-docs/api-docs.json');

        if (! file_exists($postmanPath)) {
            if (file_exists($outputPath)) {
                $this->warn("Berkas Postman Collection tidak ditemukan di: {$postmanPath}");
                $this->info("Menggunakan berkas OpenAPI/Swagger yang sudah ada di: {$outputPath}");

                return 0;
            }

            $this->error("Berkas Postman Collection tidak ditemukan di: {$postmanPath}");

            return 1;
        }

        $postman = json_decode(file_get_contents($postmanPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Format Postman Collection tidak valid JSON: '.json_last_error_msg());

            return 1;
        }

        $openapi = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'API Tridharma & Akreditasi PBL (LAM-INFOKOM 2.1)',
                'description' => "Dokumentasi REST API Sistem Informasi Akreditasi & Manajemen Tridharma PBL (Kelompok 2: SDM, Penelitian, PkM, & Kerja Sama).\n\n### 🔐 Panduan Autentikasi & Otorisasi Swagger:\n1. **Autentikasi Akun SSO K1**: Autentikasi akun dan penerbitan token JWT dikelola terpusat oleh server SSO Kelompok 1 di endpoint API:\n   👉 **`POST http://akademik.fayra.my.id/api/auth/login`**\n   - **Body JSON**: `{\"email\": \"administrator@polines.ac.id\", \"password\": \"password\"}`\n2. **Uji Coba Langsung di Swagger UI**: Anda dapat langsung mengklik endpoint `POST /api/login` di bawah ini (**Try it out**), masukkan kredensial, lalu klik Execute untuk memperoleh token JWT secara langsung!\n3. **Otorisasi di Swagger**: Salin token dari `data.authorization.access_token`, klik tombol hijau **Authorize 🔓** di pojok kanan atas halaman Swagger, lalu tempel token Anda.\n4. **Verifikasi Token**: Jalankan endpoint `GET /api/me` untuk melihat profil, role (ADMINISTRATOR / KAPRODI / DOSEN / ASESOR), serta permissions Anda.\n5. **Coba Seluruh Endpoint**: Seluruh 61 endpoint fitur K2 kini siap diuji secara interaktif (**Try it out**).",
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'Tim Pengembang Akreditasi PBL',
                    'email' => 'admin@polines.ac.id',
                ],
            ],
            'servers' => [
                [
                    'url' => '/',
                    'description' => 'API Server',
                ],
            ],
            'tags' => [],
            'paths' => [],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description' => 'Masukkan token JWT Anda yang diperoleh setelah login.',
                    ],
                ],
                'schemas' => [
                    'StandardResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'success' => ['type' => 'boolean', 'example' => true],
                            'code' => ['type' => 'integer', 'example' => 200],
                            'message' => ['type' => 'string', 'example' => 'Operasi berhasil dilakukan.'],
                            'data' => ['type' => 'object'],
                        ],
                    ],
                    'ValidationErrorResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'success' => ['type' => 'boolean', 'example' => false],
                            'code' => ['type' => 'integer', 'example' => 422],
                            'message' => ['type' => 'string', 'example' => 'Validasi gagal.'],
                            'errors' => ['type' => 'object'],
                        ],
                    ],
                ],
            ],
        ];

        $tags = [];
        $paths = [];

        $this->parseItems($postman['item'] ?? [], [], $tags, $paths);

        // Hapus endpoint auth bawaan Postman agar digantikan secara presisi oleh endpoint SSO live K1
        unset($paths['/api/login'], $paths['/api/register'], $paths['/api/me'], $paths['/api/logout']);

        // Tambahkan rute fitur lanjutan (Auth SSO K1, Sertifikasi, Reminder, Gap Analysis, Konfigurasi Metrik, Notifikasi & Audit)
        $suppPaths = $this->getSupplementaryPaths();
        foreach ($suppPaths as $suppPath => $suppOps) {
            foreach ($suppOps as $op) {
                foreach ($op['tags'] ?? [] as $t) {
                    if (! in_array($t, $tags)) {
                        $tags[] = $t;
                    }
                }
            }
        }

        $paths = array_merge($paths, $suppPaths);

        $openapi['tags'] = array_map(function ($t) {
            return [
                'name' => $t,
                'description' => $this->tagDescriptions[$t] ?? "Endpoint API {$t}",
            ];
        }, $tags);

        $openapi['paths'] = $paths;

        if (! is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        $jsonResult = json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($outputPath, $jsonResult);

        $this->info('Berhasil mengonversi Postman Collection ke Swagger OpenAPI 3.0!');
        $this->line("Output file : <info>{$outputPath}</info>");
        $this->line('Total Tags  : '.count($tags));
        $this->line('Total Paths : '.count($paths));
        $this->line('Swagger UI  : <comment>'.url('/api/documentation').'</comment>');

        return 0;
    }

    private function parseItems(array $items, array $folders, array &$tags, array &$paths): void
    {
        foreach ($items as $item) {
            if (isset($item['item'])) {
                $this->parseItems($item['item'], array_merge($folders, [$item['name']]), $tags, $paths);
            } elseif (isset($item['request'])) {
                $tag = $this->resolveCleanTag($folders);
                if (! in_array($tag, $tags)) {
                    $tags[] = $tag;
                }
                $req = $item['request'];
                $method = strtolower($req['method']);

                $urlRaw = $req['url']['raw'] ?? '';
                $pathStr = preg_replace('/^\{\{base_url\}\}/', '', $urlRaw);
                $pathStr = explode('?', $pathStr)[0];
                $pathStr = '/'.ltrim($pathStr, '/');
                if (! str_starts_with($pathStr, '/api/')) {
                    $pathStr = '/api'.$pathStr;
                }

                $segments = explode('/', trim($pathStr, '/'));
                $newSegments = [];
                $pathParams = [];

                foreach ($segments as $seg) {
                    if (is_numeric($seg)) {
                        $newSegments[] = '{id}';
                        $pathParams[] = [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'description' => 'ID entitas data',
                            'schema' => [
                                'type' => 'integer',
                                'example' => (int) $seg,
                            ],
                        ];
                    } else {
                        $newSegments[] = $seg;
                    }
                }

                $openApiPath = '/'.implode('/', $newSegments);

                $cleanSummary = preg_replace('/^\d+(\.\d+)*\s*(GET|POST|PUT|DELETE|PATCH)?\s*/i', '', $item['name']);
                $cleanSummary = trim($cleanSummary);

                $operation = [
                    'tags' => [$tag],
                    'summary' => $cleanSummary,
                    'description' => "Endpoint API untuk {$cleanSummary}.",
                    'operationId' => md5($method.'_'.$openApiPath),
                    'responses' => [
                        ($method === 'post' ? '201' : '200') => [
                            'description' => ($method === 'post' ? 'Data berhasil dibuat / diproses.' : 'Operasi berhasil diproses.'),
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/StandardResponse',
                                    ],
                                ],
                            ],
                        ],
                        '400' => [
                            'description' => 'Permintaan tidak valid / Bad Request.',
                        ],
                        '401' => [
                            'description' => 'Unauthenticated: Token JWT tidak valid atau sesi berakhir.',
                        ],
                        '404' => [
                            'description' => 'Data tidak ditemukan.',
                        ],
                    ],
                ];

                if (in_array($method, ['post', 'put', 'patch'])) {
                    $operation['responses']['422'] = [
                        'description' => 'Validasi gagal: format data input tidak sesuai ketentuan.',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/ValidationErrorResponse',
                                ],
                            ],
                        ],
                    ];
                }

                if (in_array($openApiPath, ['/api/login', '/api/register', '/api/k1/validasi-nim', '/api/test-auth'])) {
                    $operation['security'] = [];
                } else {
                    $operation['security'] = [
                        ['bearerAuth' => []],
                    ];
                }

                $params = $pathParams;

                if (! empty($req['url']['query'])) {
                    foreach ($req['url']['query'] as $qp) {
                        $params[] = [
                            'name' => $qp['key'],
                            'in' => 'query',
                            'required' => false,
                            'description' => "Filter query parameter: {$qp['key']}",
                            'schema' => [
                                'type' => 'string',
                                'example' => $qp['value'] ?? '',
                            ],
                        ];
                    }
                }

                if (! empty($params)) {
                    $operation['parameters'] = $params;
                }

                if (isset($req['body'])) {
                    $bodyMode = $req['body']['mode'] ?? '';

                    if ($bodyMode === 'raw' && ! empty($req['body']['raw'])) {
                        $rawJson = json_decode($req['body']['raw'], true);
                        if ($rawJson !== null) {
                            $operation['requestBody'] = [
                                'required' => true,
                                'description' => 'Data payload JSON',
                                'content' => [
                                    'application/json' => [
                                        'schema' => $this->toSchema($rawJson),
                                        'example' => $rawJson,
                                    ],
                                ],
                            ];
                        }
                    } elseif ($bodyMode === 'formdata' && ! empty($req['body']['formdata'])) {
                        $properties = [];
                        foreach ($req['body']['formdata'] as $fd) {
                            $propName = $fd['key'];
                            $propType = $fd['type'] ?? 'text';

                            if ($propType === 'file') {
                                $properties[$propName] = [
                                    'type' => 'string',
                                    'format' => 'binary',
                                    'description' => $fd['description'] ?? 'Berkas yang diunggah',
                                ];
                            } else {
                                $properties[$propName] = [
                                    'type' => 'string',
                                    'example' => $fd['value'] ?? '',
                                    'description' => $fd['description'] ?? '',
                                ];
                            }
                        }

                        $operation['requestBody'] = [
                            'required' => true,
                            'content' => [
                                'multipart/form-data' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => $properties,
                                    ],
                                ],
                            ],
                        ];
                    }
                }

                if (! isset($paths[$openApiPath])) {
                    $paths[$openApiPath] = [];
                }

                if (isset($paths[$openApiPath][$method])) {
                    $existingOp = &$paths[$openApiPath][$method];
                    if (isset($operation['requestBody']['content'])) {
                        foreach ($operation['requestBody']['content'] as $contentType => $bodyConfig) {
                            if (! isset($existingOp['requestBody']['content'][$contentType])) {
                                $existingOp['requestBody']['content'][$contentType] = $bodyConfig;
                            } else {
                                $target = &$existingOp['requestBody']['content'][$contentType];
                                if (! isset($target['examples'])) {
                                    $firstVal = $target['example'] ?? null;
                                    $target['examples'] = [];
                                    if ($firstVal !== null) {
                                        $target['examples'][$existingOp['summary']] = [
                                            'summary' => $existingOp['summary'],
                                            'value' => $firstVal,
                                        ];
                                    }
                                    unset($target['example']);
                                }
                                $newVal = $bodyConfig['example'] ?? null;
                                if ($newVal !== null) {
                                    $target['examples'][$cleanSummary] = [
                                        'summary' => $cleanSummary,
                                        'value' => $newVal,
                                    ];
                                }
                            }
                        }
                    }
                    $cleanSummaryNoSuffix = preg_replace('/\s*-\s*.*$/', '', $existingOp['summary']);
                    $existingOp['summary'] = $cleanSummaryNoSuffix;
                    $existingOp['description'] .= "\n- {$cleanSummary}";
                } else {
                    $paths[$openApiPath][$method] = $operation;
                }
            }
        }
    }

    private function resolveCleanTag(array $folders): string
    {
        $reversed = array_reverse($folders);
        foreach ($reversed as $name) {
            if (preg_match('/Aktivitas Kerja Sama/i', $name)) {
                return 'Aktivitas Kerja Sama';
            }
            if (preg_match('/Tenaga Kependidikan|Tendik/i', $name)) {
                return 'Tenaga Kependidikan';
            }
            if (preg_match('/Pendidikan/i', $name)) {
                return 'Pendidikan';
            }
            if (preg_match('/Jabatan Akademik/i', $name)) {
                return 'Jabatan Akademik';
            }
            if (preg_match('/Bidang Keahlian/i', $name)) {
                return 'Bidang Keahlian';
            }
            if (preg_match('/Jenis Kerja Sama|Jenis Kerjasama/i', $name)) {
                return 'Jenis Kerja Sama';
            }
            if (preg_match('/Kerja Sama|Kemitraan/i', $name)) {
                return 'Kerja Sama';
            }
            if (preg_match('/Mitra/i', $name)) {
                return 'Mitra';
            }
            if (preg_match('/Jenis Kegiatan/i', $name)) {
                return 'Jenis Kegiatan';
            }
            if (preg_match('/Jenis Luaran/i', $name)) {
                return 'Jenis Luaran';
            }
            if (preg_match('/Dosen/i', $name) && ! preg_match('/Rasio|Produktivitas|Statistik/i', $name)) {
                return 'Dosen';
            }
            if (preg_match('/Luaran/i', $name)) {
                return 'Luaran';
            }
            if (preg_match('/Kegiatan/i', $name)) {
                return 'Kegiatan';
            }
            if (preg_match('/Evidence|Repository/i', $name)) {
                return 'Evidence';
            }
            if (preg_match('/Interoperabilitas|Eksternal|Validasi NIM|Mahasiswa/i', $name)) {
                return 'Validasi Mahasiswa';
            }
            if (preg_match('/Statistik|Dashboard/i', $name)) {
                return 'Statistik';
            }
            if (preg_match('/Autentikasi|Auth/i', $name)) {
                return 'Auth';
            }
        }

        return 'General';
    }

    private function toSchema(mixed $val): array
    {
        if (is_array($val)) {
            if (empty($val)) {
                return ['type' => 'array', 'items' => ['type' => 'string']];
            }
            $isAssoc = array_keys($val) !== range(0, count($val) - 1);
            if ($isAssoc) {
                $props = [];
                foreach ($val as $k => $v) {
                    $props[$k] = $this->toSchema($v);
                }

                return [
                    'type' => 'object',
                    'properties' => $props,
                ];
            }

            return [
                'type' => 'array',
                'items' => $this->toSchema($val[0]),
            ];
        }

        if (is_int($val)) {
            return ['type' => 'integer', 'example' => $val];
        }

        if (is_float($val)) {
            return ['type' => 'number', 'example' => $val];
        }

        if (is_bool($val)) {
            return ['type' => 'boolean', 'example' => $val];
        }

        return ['type' => 'string', 'example' => (string) $val];
    }

    /**
     * Definisi OpenAPI untuk endpoint fitur lanjutan yang belum terdaftar di Postman Collection.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getSupplementaryPaths(): array
    {
        return [
            '/api/auth/login' => [
                'post' => [
                    'tags' => ['Auth'],
                    'summary' => 'Login SSO Terpusat (http://akademik.fayra.my.id/api/auth/login)',
                    'description' => "Login akun terpusat via API SSO Kelompok 1 (http://akademik.fayra.my.id/api/auth/login).\n\nEndpoint ini mengeksekusi request langsung ke server SSO Akademik K1 untuk memvalidasi kredensial dan menerbitkan access_token JWT.\n\nSalin token dari `data.authorization.access_token` hasil respons, lalu klik tombol **Authorize 🔓** di pojok kanan atas Swagger UI untuk mulai menguji endpoint lainnya.",
                    'operationId' => 'loginSSOTerpusat',
                    'servers' => [
                        [
                            'url' => 'http://akademik.fayra.my.id',
                            'description' => 'Server SSO Kelompok 1 (http://akademik.fayra.my.id)',
                        ],
                        [
                            'url' => 'https://akademik.fayra.my.id',
                            'description' => 'Server SSO Kelompok 1 (https://akademik.fayra.my.id)',
                        ],
                    ],
                    'security' => [],
                    'requestBody' => [
                        'required' => true,
                        'description' => 'Kredensial login akun SSO Kelompok 1',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['email', 'password'],
                                    'properties' => [
                                        'email' => [
                                            'type' => 'string',
                                            'example' => 'administrator@polines.ac.id',
                                            'description' => 'Email terdaftar pada server akademik (K1)',
                                        ],
                                        'password' => [
                                            'type' => 'string',
                                            'example' => 'password',
                                            'description' => 'Kata sandi akun SSO K1',
                                        ],
                                    ],
                                ],
                                'example' => [
                                    'email' => 'administrator@polines.ac.id',
                                    'password' => 'password',
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Login berhasil, token JWT SSO berhasil diperoleh.',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/StandardResponse',
                                    ],
                                    'example' => [
                                        'success' => true,
                                        'code' => 200,
                                        'message' => 'Login berhasil',
                                        'data' => [
                                            'user' => [
                                                'id' => 3,
                                                'nama' => 'Administrator User',
                                                'email' => 'administrator@polines.ac.id',
                                                'role' => 'ADMINISTRATOR',
                                            ],
                                            'authorization' => [
                                                'access_token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vYWthZGVtaWsuZmF5cmEubXkuaWQvYXBpL2F1dGgvbG9naW4iLCJpYXQiOjE3OTIzMzg0NDUsImV4cCI6MTc5MjM0MjA0NSwibmJmIjoxNzkyMzM4NDQ1LCJqdGkiOiI0T0V5RlFLN3AxRXIzQU1RIiwic3ViIjoiMyIsInBydiI6IjIzYmQ1Yzg5NDlmNjAwYWRiMzllNzAxYzQwMDg3MmRiN2E1OTc2ZjciLCJyb2xlIjoiQURNSU5JU1RSQVRPUiIsInBlcm1pc3Npb25zIjpbImRvc2VuLmNyZWF0ZSIsImRvc2VuLnJlYWQiLCJkb3Nlbi51cGRhdGUiLCJkb3Nlbi5kZWxldGUiLCJrcml0ZXJpYS5jcmVhdGUiLCJrcml0ZXJpYS5yZWFkIiwia3JpdGVyaWEudXBkYXRlIiwia3JpdGVyaWEuZGVsZXRlIiwicGVuZWxpdGlhbi5jcmVhdGUiLCJwZW5lbGl0aWFuLnJlYWQiLCJwZW5lbGl0aWFuLnVwZGF0ZSIsInBlbmVsaXRpYW4uZGVsZXRlIiwicGVuZ2FiZGlhbi5jcmVhdGUiLCJwZW5nYWJkaWFuLnJlYWQiLCJwZW5nYWJkaWFuLnVwZGF0ZSIsInBlbmdhYmRpYW4uZGVsZXRlIiwibGVkLmNyZWF0ZSIsImxlZC5yZWFkIiwibGVkLnVwZGF0ZSIsImxlZC5kZWxldGUiLCJsa3BzLmNyZWF0ZSIsImxrcHMucmVhZCIsImxrcHMudXBkYXRlIiwibGtwcy5kZWxldGUiLCJ1c2VyLmNyZWF0ZSIsInVzZXIucmVhZCIsInVzZXIudXBkYXRlIiwidXNlci5kZWxldGUiLCJyb2xlLmNyZWF0ZSIsInJvbGUucmVhZCIsInJvbGUudXBkYXRlIiwicm9sZS5kZWxldGUiLCJwZXJtaXNzaW9uLmNyZWF0ZSIsInBlcm1pc3Npb24ucmVhZCIsInBlcm1pc3Npb24udXBkYXRlIiwicGVybWlzc2lvbi5kZWxldGUiXX0.MvurHvfVgg9rVhBznGRorzYTonTwkMw-RITDeutApIw',
                                                'token_type' => 'Bearer',
                                                'expires_in' => 3600,
                                                'permissions' => [],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '401' => [
                            'description' => 'Email atau password salah.',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/StandardResponse',
                                    ],
                                    'example' => [
                                        'success' => false,
                                        'code' => 401,
                                        'message' => 'Email atau password salah.',
                                        'errors' => null,
                                        'data' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/api/me' => [
                'get' => [
                    'tags' => ['Auth'],
                    'summary' => 'Introspeksi Profil & Hak Akses Pengguna Aktif',
                    'description' => 'Mengekstrak profil pengguna, peran (ADMINISTRATOR, KAPRODI, DOSEN, ASESOR), dan hak akses (permissions) dari Bearer Token JWT SSO K1.',
                    'operationId' => 'getProfileMe',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Profil dan hak akses berhasil diambil.',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/StandardResponse',
                                    ],
                                    'example' => [
                                        'success' => true,
                                        'code' => 200,
                                        'message' => 'Profil dan hak akses pengguna berhasil diekstrak dari token JWT SSO K1.',
                                        'data' => [
                                            'user' => [
                                                'id' => 1,
                                                'nama' => 'Dr. Budi Santoso, M.Kom.',
                                                'email' => 'dosen@polines.ac.id',
                                                'role' => 'DOSEN',
                                                'permissions' => ['dosen.read', 'luaran.create', 'luaran.read'],
                                            ],
                                            'role' => 'DOSEN',
                                            'permissions' => ['dosen.read', 'luaran.create', 'luaran.read'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '401' => [
                            'description' => 'Unauthenticated / Token Bearer tidak disertakan atau kedaluwarsa.',
                        ],
                    ],
                ],
            ],
            '/api/logout' => [
                'post' => [
                    'tags' => ['Auth'],
                    'summary' => 'Logout Sesi Pengguna',
                    'description' => 'Menghapus sesi token JWT aktif di sisi klien dan mengembalikan tautan portal SSO.',
                    'operationId' => 'logoutAuth',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Logout berhasil diproses.',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/StandardResponse',
                                    ],
                                    'example' => [
                                        'success' => true,
                                        'code' => 200,
                                        'message' => 'Logout berhasil. Sesi token telah dinonaktifkan di sisi klien.',
                                        'login_url' => 'http://akademik.fayra.my.id/api/auth/login',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/api/sertifikasi' => [
                'get' => [
                    'tags' => ['Sertifikasi'],
                    'summary' => 'Daftar Sertifikasi (Dosen & Tendik)',
                    'description' => 'Mengambil daftar data sertifikasi dengan paginasi dan filter lengkap (tipe pemilik dosen vs tendik, status masa berlaku aktif/kedaluwarsa/segera berakhir, tahun, dan pencarian teks luas).',
                    'operationId' => 'getSertifikasiList',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'search', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Pencarian jenis sertifikasi, no. sertifikat, no. sk, penerbit, atau nama/NIDN/NIP pemilik'],
                        ['name' => 'tipe_pemilik', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'enum' => ['dosen', 'tendik']], 'description' => 'Filter berdasarkan pemilik sertifikasi: "dosen" atau "tendik"'],
                        ['name' => 'status_masa_berlaku', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'enum' => ['aktif', 'kedaluwarsa', 'segera_berakhir']], 'description' => 'Filter status masa berlaku: "aktif", "kedaluwarsa", atau "segera_berakhir"'],
                        ['name' => 'hari_kedaluwarsa', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 30], 'description' => 'Ambang batas hari untuk filter segera_berakhir (default: 30 hari)'],
                        ['name' => 'tahun_sertifikasi', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer'], 'description' => 'Filter tahun spesifik penerbitan sertifikasi'],
                        ['name' => 'tahun_mulai', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer'], 'description' => 'Rentang tahun mulai penerbitan'],
                        ['name' => 'tahun_selesai', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer'], 'description' => 'Rentang tahun selesai penerbitan'],
                        ['name' => 'id_dosen', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer'], 'description' => 'Filter ID dosen tertentu'],
                        ['name' => 'id_tendik', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer'], 'description' => 'Filter ID tendik tertentu'],
                        ['name' => 'status_sertifikasi', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Filter status sertifikasi eksplisit (misal: "Aktif", "Kedaluwarsa")'],
                        ['name' => 'per_page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 10], 'description' => 'Jumlah data per halaman'],
                        ['name' => 'page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 1], 'description' => 'Nomor halaman'],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Data sertifikasi berhasil diambil.',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/StandardResponse'],
                                    'example' => [
                                        'success' => true,
                                        'code' => 200,
                                        'message' => 'Data sertifikasi berhasil diambil.',
                                        'data' => [
                                            'current_page' => 1,
                                            'data' => [
                                                [
                                                    'id_sertifikasi' => 1,
                                                    'id_dosen' => 1,
                                                    'id_tendik' => null,
                                                    'jenis_sertifikasi' => 'Sertifikat Pendidik (Serdos)',
                                                    'bidang_studi' => 'Teknik Informatika / Rekayasa Perangkat Lunak',
                                                    'nomor_registrasi_pendidik' => 'REG-DIKTI-2023-8871',
                                                    'nomor_sk' => 'SK-SERTIF-2023-01',
                                                    'nomor_sertifikat' => 'CERT-KOMP-2023-45',
                                                    'lembaga_penerbit' => 'Kemendikbudristek',
                                                    'tahun_sertifikasi' => '2023-08-20',
                                                    'tanggal_kedaluwarsa' => '2028-12-31',
                                                    'status_sertifikasi' => 'Aktif',
                                                    'url_sertifikasi' => 'https://drive.google.com/file/d/1C-vDl0JDYJRJXnlhP4-onXRwUTBf6h1c/view?usp=sharing',
                                                    'dosen' => [
                                                        'id_dosen' => 1,
                                                        'nama_dosen' => 'Dr. Budi Santoso, M.Kom.',
                                                        'nidn' => '0012345678',
                                                    ],
                                                    'evidence' => [
                                                        [
                                                            'id_evidence' => 1,
                                                            'id_sertifikasi' => 1,
                                                            'nama_file' => 'Bukti Sertifikat Serdos.pdf',
                                                            'url_evidence' => 'https://drive.google.com/file/d/1C-vDl0JDYJRJXnlhP4-onXRwUTBf6h1c/view?usp=sharing',
                                                            'status_validasi' => 'Menunggu Validasi',
                                                        ],
                                                    ],
                                                ],
                                            ],
                                            'total' => 1,
                                            'per_page' => 10,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'post' => [
                    'tags' => ['Sertifikasi'],
                    'summary' => 'Tambah Data Sertifikasi Baru',
                    'description' => 'Menambahkan data sertifikasi baru (Dosen atau Tendik) dengan menyertakan tautan url_sertifikasi atau mengunggah berkas sertifikat, dan secara otomatis menyinkronkan ke profil Dosen/Tendik bersangkutan.',
                    'operationId' => 'storeSertifikasi',
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['jenis_sertifikasi', 'tahun_sertifikasi', 'url_sertifikasi'],
                                    'properties' => [
                                        'id_dosen' => ['type' => 'integer', 'nullable' => true, 'example' => 1, 'description' => 'ID Dosen jika sertifikasi milik dosen'],
                                        'id_tendik' => ['type' => 'integer', 'nullable' => true, 'description' => 'ID Tendik jika sertifikasi milik tendik'],
                                        'jenis_sertifikasi' => ['type' => 'string', 'example' => 'Sertifikat Pendidik (Serdos)'],
                                        'bidang_studi' => ['type' => 'string', 'example' => 'Teknik Informatika / Rekayasa Perangkat Lunak'],
                                        'nomor_registrasi_pendidik' => ['type' => 'string', 'nullable' => true, 'example' => 'REG-DIKTI-2023-8871'],
                                        'nomor_sk' => ['type' => 'string', 'nullable' => true, 'example' => 'SK-SERTIF-2023-01'],
                                        'nomor_sertifikat' => ['type' => 'string', 'nullable' => true, 'example' => 'CERT-KOMP-2023-45'],
                                        'lembaga_penerbit' => ['type' => 'string', 'nullable' => true, 'example' => 'Kemendikbudristek / BNSP'],
                                        'tahun_sertifikasi' => ['type' => 'string', 'example' => '2023-08-20', 'description' => 'Tanggal atau tahun sertifikasi (YYYY atau YYYY-MM-DD)'],
                                        'tanggal_kedaluwarsa' => ['type' => 'string', 'format' => 'date', 'nullable' => true, 'example' => '2028-12-31'],
                                        'status_sertifikasi' => ['type' => 'string', 'example' => 'Aktif'],
                                        'url_sertifikasi' => ['type' => 'string', 'example' => 'https://drive.google.com/file/d/1C-vDl0JDYJRJXnlhP4-onXRwUTBf6h1c/view?usp=sharing', 'description' => 'Tautan URL dokumen/berkas sertifikasi (Google Drive, Cloud Storage, dsb.)'],
                                        'url_drive' => ['type' => 'string', 'nullable' => true, 'example' => 'https://drive.google.com/file/d/1C-vDl0JDYJRJXnlhP4-onXRwUTBf6h1c/view?usp=sharing'],
                                    ],
                                ],
                            ],
                            'multipart/form-data' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['jenis_sertifikasi', 'tahun_sertifikasi'],
                                    'properties' => [
                                        'id_dosen' => ['type' => 'integer', 'nullable' => true, 'example' => 1],
                                        'id_tendik' => ['type' => 'integer', 'nullable' => true],
                                        'jenis_sertifikasi' => ['type' => 'string', 'example' => 'Sertifikat Pendidik (Serdos)'],
                                        'bidang_studi' => ['type' => 'string', 'example' => 'Teknik Informatika / Rekayasa Perangkat Lunak'],
                                        'nomor_registrasi_pendidik' => ['type' => 'string', 'nullable' => true, 'example' => 'REG-DIKTI-2023-8871'],
                                        'nomor_sk' => ['type' => 'string', 'nullable' => true, 'example' => 'SK-SERTIF-2023-01'],
                                        'nomor_sertifikat' => ['type' => 'string', 'nullable' => true, 'example' => 'CERT-KOMP-2023-45'],
                                        'lembaga_penerbit' => ['type' => 'string', 'nullable' => true, 'example' => 'Kemendikbudristek / BNSP'],
                                        'tahun_sertifikasi' => ['type' => 'string', 'example' => '2023-08-20', 'description' => 'Tanggal atau tahun sertifikasi (YYYY atau YYYY-MM-DD)'],
                                        'tanggal_kedaluwarsa' => ['type' => 'string', 'format' => 'date', 'nullable' => true, 'example' => '2028-12-31'],
                                        'status_sertifikasi' => ['type' => 'string', 'example' => 'Aktif'],
                                        'url_sertifikasi' => ['type' => 'string', 'example' => 'https://drive.google.com/file/d/1C-vDl0JDYJRJXnlhP4-onXRwUTBf6h1c/view?usp=sharing', 'description' => 'Tautan URL dokumen sertifikasi'],
                                        'evidence_file' => ['type' => 'string', 'format' => 'binary', 'nullable' => true, 'description' => 'Unggah berkas fisik bukti sertifikat (PDF/JPG/PNG max 10MB)'],
                                        'file' => ['type' => 'string', 'format' => 'binary', 'nullable' => true, 'description' => 'Alias unggah berkas fisik'],
                                        'url_drive' => ['type' => 'string', 'nullable' => true, 'example' => 'https://drive.google.com/file/d/1C-vDl0JDYJRJXnlhP4-onXRwUTBf6h1c/view?usp=sharing'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Data sertifikasi berhasil ditambahkan.',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/StandardResponse'],
                                    'example' => [
                                        'success' => true,
                                        'code' => 201,
                                        'message' => 'Data sertifikasi berhasil ditambahkan.',
                                        'data' => [
                                            'id_sertifikasi' => 1,
                                            'id_dosen' => 1,
                                            'id_tendik' => null,
                                            'jenis_sertifikasi' => 'Sertifikat Pendidik (Serdos)',
                                            'bidang_studi' => 'Teknik Informatika / Rekayasa Perangkat Lunak',
                                            'nomor_registrasi_pendidik' => 'REG-DIKTI-2023-8871',
                                            'nomor_sk' => 'SK-SERTIF-2023-01',
                                            'nomor_sertifikat' => 'CERT-KOMP-2023-45',
                                            'lembaga_penerbit' => 'Kemendikbudristek',
                                            'tahun_sertifikasi' => '2023-08-20',
                                            'tanggal_kedaluwarsa' => '2028-12-31',
                                            'status_sertifikasi' => 'Aktif',
                                            'url_sertifikasi' => 'https://drive.google.com/file/d/1C-vDl0JDYJRJXnlhP4-onXRwUTBf6h1c/view?usp=sharing',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '422' => [
                            'description' => 'Validasi input gagal.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidationErrorResponse']]],
                        ],
                    ],
                ],
            ],
            '/api/sertifikasi/{id}' => [
                'get' => [
                    'tags' => ['Sertifikasi'],
                    'summary' => 'Detail Sertifikasi',
                    'description' => 'Mengambil detail satu data sertifikasi beserta relasi pemilik (dosen/tendik) dan dokumen evidence pendukung.',
                    'operationId' => 'showSertifikasi',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer'], 'description' => 'ID sertifikasi'],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Detail sertifikasi berhasil diambil.',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/StandardResponse'],
                                    'example' => [
                                        'success' => true,
                                        'code' => 200,
                                        'message' => 'Detail sertifikasi berhasil diambil.',
                                        'data' => [
                                            'id_sertifikasi' => 1,
                                            'id_dosen' => 1,
                                            'id_tendik' => null,
                                            'jenis_sertifikasi' => 'Sertifikat Pendidik (Serdos)',
                                            'bidang_studi' => 'Teknik Informatika / Rekayasa Perangkat Lunak',
                                            'nomor_registrasi_pendidik' => 'REG-DIKTI-2023-8871',
                                            'nomor_sk' => 'SK-SERTIF-2023-01',
                                            'nomor_sertifikat' => 'CERT-KOMP-2023-45',
                                            'lembaga_penerbit' => 'Kemendikbudristek',
                                            'tahun_sertifikasi' => '2023-08-20',
                                            'tanggal_kedaluwarsa' => '2028-12-31',
                                            'status_sertifikasi' => 'Aktif',
                                            'url_sertifikasi' => 'https://drive.google.com/file/d/1C-vDl0JDYJRJXnlhP4-onXRwUTBf6h1c/view?usp=sharing',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '404' => ['description' => 'Data sertifikasi tidak ditemukan.'],
                    ],
                ],
                'put' => [
                    'tags' => ['Sertifikasi'],
                    'summary' => 'Perbarui Data Sertifikasi',
                    'description' => 'Memperbarui data sertifikasi dan secara otomatis menyinkronkan perubahan ke profil Dosen/Tendik bersangkutan.',
                    'operationId' => 'updateSertifikasi',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer'], 'description' => 'ID sertifikasi'],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id_dosen' => ['type' => 'integer', 'nullable' => true],
                                        'id_tendik' => ['type' => 'integer', 'nullable' => true],
                                        'jenis_sertifikasi' => ['type' => 'string'],
                                        'bidang_studi' => ['type' => 'string'],
                                        'nomor_registrasi_pendidik' => ['type' => 'string', 'nullable' => true],
                                        'nomor_sk' => ['type' => 'string', 'nullable' => true],
                                        'nomor_sertifikat' => ['type' => 'string', 'nullable' => true],
                                        'lembaga_penerbit' => ['type' => 'string', 'nullable' => true],
                                        'tahun_sertifikasi' => ['type' => 'string', 'example' => '2023-08-20', 'description' => 'Tanggal atau tahun sertifikasi (YYYY atau YYYY-MM-DD)'],
                                        'tanggal_kedaluwarsa' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                                        'status_sertifikasi' => ['type' => 'string'],
                                        'url_sertifikasi' => ['type' => 'string', 'example' => 'https://drive.google.com/file/d/1C-vDl0JDYJRJXnlhP4-onXRwUTBf6h1c/view?usp=sharing'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Data sertifikasi berhasil diperbarui.',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/StandardResponse'],
                                    'example' => [
                                        'success' => true,
                                        'code' => 200,
                                        'message' => 'Data sertifikasi berhasil diperbarui.',
                                        'data' => [
                                            'id_sertifikasi' => 1,
                                            'id_dosen' => 1,
                                            'id_tendik' => null,
                                            'jenis_sertifikasi' => 'Sertifikat Pendidik (Serdos)',
                                            'bidang_studi' => 'Teknik Informatika / Rekayasa Perangkat Lunak',
                                            'nomor_registrasi_pendidik' => 'REG-DIKTI-2023-8871',
                                            'nomor_sk' => 'SK-SERTIF-2023-01',
                                            'nomor_sertifikat' => 'CERT-KOMP-2023-45',
                                            'lembaga_penerbit' => 'Kemendikbudristek',
                                            'tahun_sertifikasi' => '2023-08-20',
                                            'tanggal_kedaluwarsa' => '2028-12-31',
                                            'status_sertifikasi' => 'Aktif',
                                            'url_sertifikasi' => 'https://drive.google.com/file/d/1C-vDl0JDYJRJXnlhP4-onXRwUTBf6h1c/view?usp=sharing',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '422' => ['description' => 'Validasi gagal.'],
                    ],
                ],
                'delete' => [
                    'tags' => ['Sertifikasi'],
                    'summary' => 'Hapus Data Sertifikasi',
                    'description' => 'Menghapus data sertifikasi dan otomatis menyinkronkan profil Dosen/Tendik. Ditolak jika sertifikasi masih terikat pada dokumen evidence.',
                    'operationId' => 'destroySertifikasi',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer'], 'description' => 'ID sertifikasi'],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Data sertifikasi berhasil dihapus.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StandardResponse']]],
                        ],
                        '422' => ['description' => 'Luaran Masih Dipakai: Sertifikasi tidak dapat dihapus karena masih terkait dengan dokumen evidence.'],
                    ],
                ],
            ],
            '/api/sertifikasi/sync-profil' => [
                'post' => [
                    'tags' => ['Sertifikasi'],
                    'summary' => 'Sinkronisasi Massal Sertifikasi ke Profil Dosen & Tendik',
                    'description' => 'Memicu sinkronisasi menyeluruh daftar sertifikasi ke kolom `sertifikasi` pada profil master Dosen dan Tendik di database.',
                    'operationId' => 'syncProfilSertifikasi',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Sinkronisasi sertifikasi ke profil Dosen & Tendik berhasil.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StandardResponse']]],
                        ],
                    ],
                ],
            ],
            '/api/kerja-sama/reminder-kedaluwarsa' => [
                'get' => [
                    'tags' => ['Kerja Sama'],
                    'summary' => 'Monitoring Reminder Kedaluwarsa Kerja Sama',
                    'description' => 'Mengambil daftar dokumen kerja sama yang telah kedaluwarsa atau akan kedaluwarsa dalam 30 hari ke depan.',
                    'operationId' => 'reminderKedaluwarsaKerjaSama',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Daftar reminder masa berlaku kerja sama berhasil diambil.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StandardResponse']]],
                        ],
                    ],
                ],
            ],
            '/api/kerja-sama/check-kedaluwarsa' => [
                'post' => [
                    'tags' => ['Kerja Sama'],
                    'summary' => 'Trigger Sinkronisasi Status Kedaluwarsa & Notifikasi Otomatis',
                    'description' => 'Memperbarui status dokumen kerja sama yang lewat masa berlaku menjadi "Kedaluwarsa" dan mengirimkan notifikasi in-app otomatis kepada Administrator dan Kaprodi.',
                    'operationId' => 'checkKedaluwarsaKerjaSama',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Pemeriksaan status kedaluwarsa dan notifikasi berhasil.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StandardResponse']]],
                        ],
                    ],
                ],
            ],
            '/api/assessment/run' => [
                'post' => [
                    'tags' => ['Assessment & Gap Analysis'],
                    'summary' => 'Jalankan Engine Assessment SDM & Gap Analysis K3',
                    'description' => 'Melakukan kalkulasi komputasi gap analysis per kriteria LAM INFOKOM K2 berdasarkan data riil vs target K3 dan menghasilkan rekomendasi taktis.',
                    'operationId' => 'runAssessmentGapAnalysis',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Assessment dan kalkulasi gap analysis berhasil dieksekusi.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StandardResponse']]],
                        ],
                    ],
                ],
            ],
            '/api/assessment/hasil' => [
                'get' => [
                    'tags' => ['Assessment & Gap Analysis'],
                    'summary' => 'Daftar Riwayat Hasil Assessment & Rekomendasi',
                    'description' => 'Mengambil riwayat hasil komputasi assessment SDM dan rekomendasi perbaikan indikator.',
                    'operationId' => 'getAssessmentHasil',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Riwayat hasil assessment berhasil diambil.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StandardResponse']]],
                        ],
                    ],
                ],
            ],
            '/api/konfigurasi-metrik/indikator-k3' => [
                'get' => [
                    'tags' => ['Konfigurasi Metrik'],
                    'summary' => 'Sinkronisasi Indikator Standar LAM INFOKOM dari K3',
                    'description' => 'Mengambil daftar indikator dan kriteria akreditasi standar dari modul K3.',
                    'operationId' => 'getIndikatorK3',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Daftar indikator K3 berhasil diambil.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StandardResponse']]],
                        ],
                    ],
                ],
            ],
            '/api/konfigurasi-metrik' => [
                'get' => [
                    'tags' => ['Konfigurasi Metrik'],
                    'summary' => 'Daftar Konfigurasi Metrik Akreditasi',
                    'description' => 'Mengambil seluruh konfigurasi metrik, bobot, dan ambang batas indikator akreditasi.',
                    'operationId' => 'getKonfigurasiMetrikList',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Daftar konfigurasi metrik berhasil diambil.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StandardResponse']]],
                        ],
                    ],
                ],
                'post' => [
                    'tags' => ['Konfigurasi Metrik'],
                    'summary' => 'Tambah Konfigurasi Metrik',
                    'description' => 'Menyimpan konfigurasi indikator baru.',
                    'operationId' => 'storeKonfigurasiMetrik',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '201' => [
                            'description' => 'Konfigurasi metrik berhasil disimpan.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StandardResponse']]],
                        ],
                    ],
                ],
            ],
            '/api/notifikasi' => [
                'get' => [
                    'tags' => ['Notifikasi & Audit Log'],
                    'summary' => 'Daftar Notifikasi In-App Pengguna',
                    'description' => 'Mengambil daftar notifikasi pengguna dengan filter unread_only.',
                    'operationId' => 'getNotifikasiList',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'unread_only', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean'], 'description' => 'Filter hanya notifikasi yang belum dibaca'],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Daftar notifikasi berhasil diambil.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StandardResponse']]],
                        ],
                    ],
                ],
                'post' => [
                    'tags' => ['Notifikasi & Audit Log'],
                    'summary' => 'Kirim Notifikasi In-App Baru',
                    'description' => 'Mengirim notifikasi ke user atau role tertentu.',
                    'operationId' => 'sendNotifikasi',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '201' => [
                            'description' => 'Notifikasi berhasil dikirim.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StandardResponse']]],
                        ],
                    ],
                ],
            ],
            '/api/notifikasi/{id}/baca' => [
                'post' => [
                    'tags' => ['Notifikasi & Audit Log'],
                    'summary' => 'Tandai Notifikasi Telah Dibaca',
                    'operationId' => 'markNotifikasiAsRead',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Notifikasi ditandai telah dibaca.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StandardResponse']]],
                        ],
                    ],
                ],
            ],
            '/api/notifikasi/baca-semua' => [
                'post' => [
                    'tags' => ['Notifikasi & Audit Log'],
                    'summary' => 'Tandai Seluruh Notifikasi Telah Dibaca',
                    'operationId' => 'markAllNotifikasiAsRead',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Seluruh notifikasi ditandai telah dibaca.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StandardResponse']]],
                        ],
                    ],
                ],
            ],
            '/api/audit-logs' => [
                'get' => [
                    'tags' => ['Notifikasi & Audit Log'],
                    'summary' => 'Riwayat Audit Trail Log',
                    'description' => 'Mengambil catatan audit log forensik aktivitas mutasi data.',
                    'operationId' => 'getAuditLogs',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Daftar audit log berhasil diambil.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StandardResponse']]],
                        ],
                    ],
                ],
            ],
            '/api/audit-logs/{id}' => [
                'get' => [
                    'tags' => ['Notifikasi & Audit Log'],
                    'summary' => 'Detail Forensik Audit Log',
                    'operationId' => 'showAuditLog',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Detail audit log berhasil diambil.',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StandardResponse']]],
                        ],
                    ],
                ],
            ],
        ];
    }
}
