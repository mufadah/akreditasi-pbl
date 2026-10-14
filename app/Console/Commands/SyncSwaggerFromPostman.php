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
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Konversi Postman Collection ke OpenAPI 3.0 / Swagger UI di /api/documentation';

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
                'title' => 'Sistem Informasi Akreditasi & Tridharma PBL API',
                'description' => "Dokumentasi API Interaktif (OpenAPI 3.0 / Swagger UI) untuk Sistem Informasi Akreditasi dan Manajemen Tridharma PBL Politeknik Negeri Semarang.\n\n### Panduan Otorisasi:\n1. Eksekusi endpoint **0.1 POST Login** pada folder **0. Autentikasi**.\n2. Salin token dari `data.authorization.access_token`.\n3. Klik tombol hijau **Authorize** di pojok kanan atas Swagger UI, lalu masukkan: `Bearer <token>`.\n4. Sekarang Anda dapat mencoba seluruh endpoint (**Try it out**) secara interaktif.",
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'Tim Pengembang Sistem Akreditasi PBL',
                    'email' => 'admin@polines.ac.id',
                ],
            ],
            'servers' => [
                [
                    'url' => url('/api'),
                    'description' => 'Current Environment Server',
                ],
                [
                    'url' => 'http://localhost:8000/api',
                    'description' => 'Local Development Server',
                ],
            ],
            'tags' => [],
            'paths' => [],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'apiKey',
                        'name' => 'Authorization',
                        'in' => 'header',
                        'description' => 'Masukkan token JWT dalam format: `Bearer <token>`',
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

        $this->parseItems($postman['item'] ?? [], null, $tags, $paths);

        $openapi['tags'] = array_map(fn ($t) => ['name' => $t], $tags);
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

    private function parseItems(array $items, ?string $parentTag, array &$tags, array &$paths): void
    {
        foreach ($items as $item) {
            if (isset($item['item'])) {
                $tagForChildren = $parentTag ?? $item['name'];
                $this->parseItems($item['item'], $tagForChildren, $tags, $paths);
            } elseif (isset($item['request'])) {
                $tag = $parentTag ?? 'General';
                if (! in_array($tag, $tags)) {
                    $tags[] = $tag;
                }
                $req = $item['request'];
                $method = strtolower($req['method']);

                $urlRaw = $req['url']['raw'] ?? '';
                $pathStr = preg_replace('/^\{\{base_url\}\}/', '', $urlRaw);
                $pathStr = explode('?', $pathStr)[0];
                $pathStr = '/'.ltrim($pathStr, '/');

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

                $operation = [
                    'tags' => [$tag],
                    'summary' => $item['name'],
                    'description' => "Endpoint untuk {$item['name']}.",
                    'responses' => [
                        ($method === 'post' ? '201' : '200') => [
                            'description' => 'Operasi berhasil diproses.',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/StandardResponse',
                                    ],
                                ],
                            ],
                        ],
                        '401' => [
                            'description' => 'Unauthorized: Token Bearer tidak valid atau sesi berakhir.',
                        ],
                    ],
                ];

                if (in_array($openApiPath, ['/login', '/register', '/test-auth'])) {
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
                                    $target['examples'][$item['name']] = [
                                        'summary' => $item['name'],
                                        'value' => $newVal,
                                    ];
                                }
                            }
                        }
                    }
                    $cleanSummary = preg_replace('/\s*-\s*.*$/', '', $existingOp['summary']);
                    $existingOp['summary'] = $cleanSummary;
                    $existingOp['description'] .= "\n- {$item['name']}";
                } else {
                    $paths[$openApiPath][$method] = $operation;
                }
            }
        }
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
        } elseif (is_int($val)) {
            return ['type' => 'integer', 'example' => $val];
        } elseif (is_float($val)) {
            return ['type' => 'number', 'example' => $val];
        } elseif (is_bool($val)) {
            return ['type' => 'boolean', 'example' => $val];
        }

        return ['type' => 'string', 'example' => (string) $val];
    }
}
