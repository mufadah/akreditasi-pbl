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
        'Auth' => 'Autentikasi Pengguna & Manajemen Token JWT',
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
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $postmanPath = $this->option('postman') ?: base_path('postman_collection_akreditasi_pbl.json');
        $outputPath = storage_path('api-docs/api-docs.json');

        if (! file_exists($postmanPath)) {
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
                'title' => 'API Tridharma & Akreditasi PBL',
                'description' => "Dokumentasi REST API Sistem Informasi Akreditasi & Manajemen Tridharma PBL.\n\n### Panduan Otorisasi:\n1. Buka kelompok endpoint **Auth**, lalu jalankan endpoint **Login**.\n2. Salin token dari `data.authorization.access_token`.\n3. Klik tombol hijau **Authorize** di pojok kanan atas Swagger UI, lalu tempel token JWT Anda.\n4. Sekarang Anda dapat mencoba seluruh endpoint (**Try it out**) secara interaktif.",
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
}
