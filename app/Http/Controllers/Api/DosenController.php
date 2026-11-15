<?php

namespace App\Http\Controllers\Api;

use App\Exports\DosenExport;
use App\Http\Controllers\Controller;
use App\Imports\DosenImport;
use App\Models\BidangKeahlian;
use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\Kegiatan;
use App\Models\Pendidikan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[OA\OpenApi(
    info: new OA\Info(title: 'API Tridharma & Akreditasi PBL', version: '1.0.0'),
    components: new OA\Components(
        securitySchemes: [
            new OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer', bearerFormat: 'JWT'),
        ],
    ),
)]
class DosenController extends Controller
{
    // Ambil daftar dosen dengan filter pencarian dan status
    #[OA\Get(
        path: '/api/dosen',
        tags: ['Dosen'],
        summary: 'Semua Data Dosen',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar dosen berhasil diambil.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'data',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'nidn', type: 'string', example: '0015088501'),
                                            new OA\Property(property: 'nama_dosen', type: 'string', example: 'Dr. Ahmad Dahlan'),
                                            new OA\Property(property: 'pendidikan_terakhir', type: 'string', nullable: true, example: 'S3'),
                                            new OA\Property(
                                                property: 'jabatan_akademik',
                                                type: 'object',
                                                nullable: true,
                                                properties: [
                                                    new OA\Property(property: 'nama_jabatan', type: 'string', example: 'Lektor Kepala'),
                                                ],
                                            ),
                                            new OA\Property(property: 'sertifikasi_dosen', type: 'string', nullable: true, example: 'Tersertifikasi'),
                                        ],
                                    ),
                                ),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'total', type: 'integer', example: 25),
                            ],
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        // Query dengan relasi jabatan, pendidikan, dan bidang keahlian
        $query = Dosen::with(['jabatanAkademik', 'pendidikan', 'bidangKeahlian']);

        // Filter pencarian nama, NIDN, atau NUPTK
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_dosen', 'like', "%{$search}%")
                    ->orWhere('nidn', 'like', "%{$search}%")
                    ->orWhere('nuptk', 'like', "%{$search}%");
            });
        }

        // Filter status dosen (DTPS, DTT, NONAKTIF)
        if ($status = $request->query('status')) {
            $query->where('status_dosen', $status);
        }

        // Sertakan data yang di-soft-delete jika parameter with_trashed ada
        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        // Paginasi data 10 per halaman
        $dosen = $query->paginate($request->integer('per_page', 10));
        $dosen->getCollection()->each(fn (Dosen $lecturer) => $this->addAcademicSummaryAttributes($lecturer));

        return response()->json([
            'success' => true,
            'data' => $dosen,
        ]);
    }

    #[OA\Get(
        path: '/api/dosen/export',
        tags: ['Dosen'],
        summary: 'Export seluruh data master dosen ke format Excel (.xlsx)',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'File Excel seluruh data master dosen.',
                content: new OA\MediaType(
                    mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    schema: new OA\Schema(type: 'string', format: 'binary'),
                ),
            ),
        ],
    )]
    public function exportExcel(): BinaryFileResponse
    {
        return Excel::download(new DosenExport, 'dosen.xlsx');
    }

    // Tambah data dosen baru (Aksi 2: Input NIDN, NUPTK, Nama, Jabatan, Bidang Keahlian, Status DTPS atau DTT)
    #[OA\Post(
        path: '/api/dosen',
        tags: ['Dosen'],
        summary: 'Tambah Data Dosen',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                example: [
                    'nidn' => '0015088501',
                    'nama_dosen' => 'Dr. Ahmad Dahlan',
                    'pendidikan_terakhir' => 'S3 Ilmu Komputer',
                    'jabatan_akademik' => 'Lektor Kepala',
                    'sertifikasi_dosen' => 'Tersertifikasi',
                ],
                properties: [
                    new OA\Property(property: 'nidn', type: 'string', example: '0015088501'),
                    new OA\Property(property: 'nama_dosen', type: 'string', example: 'Dr. Ahmad Dahlan'),
                    new OA\Property(property: 'pendidikan_terakhir', type: 'string', nullable: true, example: 'S3 Ilmu Komputer'),
                    new OA\Property(property: 'jabatan_akademik', type: 'string', nullable: true, example: 'Lektor Kepala'),
                    new OA\Property(property: 'sertifikasi_dosen', type: 'string', nullable: true, example: 'Tersertifikasi'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Data dosen berhasil ditambahkan.'),
        ],
    )]
    public function store(Request $request): JsonResponse
    {
        $input = $this->mapAcademicAliases($request->all());

        // Mapping nama -> nama_dosen
        if ($request->filled('nama') && ! $request->filled('nama_dosen')) {
            $input['nama_dosen'] = $request->input('nama');
        }

        // Resolusi jabatan (ID atau string nama jabatan)
        if (! empty($input['jabatan']) && empty($input['id_jabatan'])) {
            $jabatanVal = $input['jabatan'];
            if (is_numeric($jabatanVal)) {
                $input['id_jabatan'] = (int) $jabatanVal;
            } else {
                $jabatan = JabatanAkademik::where('nama_jabatan', $jabatanVal)->first();
                $input['id_jabatan'] = $jabatan ? $jabatan->id_jabatan : 1;
            }
        } elseif (empty($input['id_jabatan'])) {
            $input['id_jabatan'] = 1;
        }

        // Resolusi pendidikan (ID atau string jenjang)
        if (! empty($input['pendidikan']) && empty($input['id_pendidikan'])) {
            $pendidikanVal = $input['pendidikan'];
            if (is_numeric($pendidikanVal)) {
                $input['id_pendidikan'] = (int) $pendidikanVal;
            } else {
                $pendidikan = Pendidikan::where('jenjang', $pendidikanVal)->first();
                $input['id_pendidikan'] = $pendidikan ? $pendidikan->id_pendidikan : 1;
            }
        } elseif (empty($input['id_pendidikan'])) {
            $input['id_pendidikan'] = 1;
        }

        // Email otomatis jika tidak disediakan
        if (! $request->filled('email') && $request->filled('nidn')) {
            $input['email'] = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $request->input('nidn'))).'@polines.ac.id';
        }

        // Status DTPS atau DTT
        if ($request->filled('status_dosen')) {
            $statusUpper = strtoupper((string) $request->input('status_dosen'));
            $input['status_dosen'] = in_array($statusUpper, ['DTT', 'NONAKTIF']) ? $statusUpper : 'DTPS';
        } else {
            $input['status_dosen'] = 'DTPS';
        }

        // Resolusi bidang keahlian jika dikirim array nama bidang
        if ($request->filled('bidang_keahlian') && ! $request->filled('bidang_keahlian_ids')) {
            $bidangVal = $request->input('bidang_keahlian');
            $bidangIds = [];
            foreach ((array) $bidangVal as $b) {
                if (is_numeric($b)) {
                    $bidangIds[] = (int) $b;
                } else {
                    $item = BidangKeahlian::where('nama_bidang', $b)->first();
                    if ($item) {
                        $bidangIds[] = $item->id_bidang_keahlian;
                    }
                }
            }
            $input['bidang_keahlian_ids'] = $bidangIds;
        }

        // Validasi input data dosen
        $validator = Validator::make($input, [
            'id_jabatan' => 'required|exists:jabatan_akademik,id_jabatan',
            'id_pendidikan' => 'required|exists:pendidikan,id_pendidikan',
            'nidn' => 'required|string|unique:dosen,nidn|max:30',
            'nuptk' => 'nullable|string|max:50',
            'nama_dosen' => 'required|string|max:255',
            'email' => 'required|email|unique:dosen,email|max:255',
            'status_dosen' => 'nullable|string|in:DTPS,DTT,NONAKTIF',
            'sertifikasi' => 'nullable|string|max:255',
            'bidang_keahlian_ids' => 'nullable|array',
            'bidang_keahlian_ids.*' => 'exists:bidang_keahlian,id_bidang_keahlian',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $dosen = DB::transaction(function () use ($validated): Dosen {
            $dosenAttributes = $validated;
            unset($dosenAttributes['bidang_keahlian_ids']);

            $dosen = Dosen::create($dosenAttributes);
            $this->syncExpertise($dosen, $validated['bidang_keahlian_ids'] ?? []);
            $dosen->recordHistory('MANUAL');

            return $dosen;
        });

        // Tampilkan Pesan Berhasil
        return response()->json([
            'success' => true,
            'message' => 'Data dosen berhasil ditambahkan.',
            'data' => $this->addAcademicSummaryAttributes($dosen->load(['jabatanAkademik', 'pendidikan', 'bidangKeahlian'])),
        ], 201);
    }

    // Ambil detail satu dosen beserta riwayat Tridharma (Screen: Detail Dosen)
    #[OA\Get(
        path: '/api/dosen/{id}',
        tags: ['Dosen'],
        summary: 'Detail Dosen',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detail dosen berhasil diambil.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'nidn', type: 'string', example: '0015088501'),
                                new OA\Property(property: 'nama_dosen', type: 'string', example: 'Dr. Ahmad Dahlan'),
                                new OA\Property(property: 'pendidikan_terakhir', type: 'string', nullable: true, example: 'S3'),
                                new OA\Property(
                                    property: 'jabatan_akademik',
                                    type: 'object',
                                    nullable: true,
                                    properties: [
                                        new OA\Property(property: 'nama_jabatan', type: 'string', example: 'Lektor Kepala'),
                                    ],
                                ),
                                new OA\Property(property: 'sertifikasi_dosen', type: 'string', nullable: true, example: 'Tersertifikasi'),
                            ],
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function show(int $id): JsonResponse
    {
        $dosen = Dosen::with([
            'jabatanAkademik',
            'pendidikan',
            'bidangKeahlian',
            'penelitian.jenisKegiatan',
            'pkm.jenisKegiatan',
            'luaran.jenisLuaran',
            'buku',
            'hki',
            'sertifikasi',
        ])->find($id);

        // Cek data ada atau tidak
        if (! $dosen) {
            return response()->json(['success' => false, 'message' => 'Data dosen tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->addAcademicSummaryAttributes($dosen)]);
    }

    // Ubah Data Dosen (Aksi 4: Ubah Data Dosen -> Update Data di Database -> Tampilkan Pesan Berhasil)
    #[OA\Put(
        path: '/api/dosen/{id}',
        tags: ['Dosen'],
        summary: 'Update Data Dosen',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                example: [
                    'pendidikan_terakhir' => 'S3 Ilmu Komputer',
                    'jabatan_akademik' => 'Lektor Kepala',
                    'sertifikasi_dosen' => 'Tersertifikasi',
                ],
                properties: [
                    new OA\Property(property: 'pendidikan_terakhir', type: 'string', nullable: true, example: 'S3 Ilmu Komputer'),
                    new OA\Property(property: 'jabatan_akademik', type: 'string', nullable: true, example: 'Lektor Kepala'),
                    new OA\Property(property: 'sertifikasi_dosen', type: 'string', nullable: true, example: 'Tersertifikasi'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Data dosen berhasil diperbarui.'),
        ],
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $dosen = Dosen::find($id);

        // Cek data ada atau tidak
        if (! $dosen) {
            return response()->json(['success' => false, 'message' => 'Data dosen tidak ditemukan.'], 404);
        }

        $input = $this->mapAcademicAliases($request->all());

        // Mapping nama -> nama_dosen
        if ($request->filled('nama') && ! $request->filled('nama_dosen')) {
            $input['nama_dosen'] = $request->input('nama');
        }

        // Resolusi jabatan
        if (! empty($input['jabatan']) && empty($input['id_jabatan'])) {
            $jabatanVal = $input['jabatan'];
            if (is_numeric($jabatanVal)) {
                $input['id_jabatan'] = (int) $jabatanVal;
            } else {
                $jabatan = JabatanAkademik::where('nama_jabatan', $jabatanVal)->first();
                if ($jabatan) {
                    $input['id_jabatan'] = $jabatan->id_jabatan;
                }
            }
        }

        // Resolusi pendidikan
        if (! empty($input['pendidikan']) && empty($input['id_pendidikan'])) {
            $pendidikanVal = $input['pendidikan'];
            if (is_numeric($pendidikanVal)) {
                $input['id_pendidikan'] = (int) $pendidikanVal;
            } else {
                $pendidikan = Pendidikan::where('jenjang', $pendidikanVal)->first();
                if ($pendidikan) {
                    $input['id_pendidikan'] = $pendidikan->id_pendidikan;
                }
            }
        }

        // Cek keterkaitan jika status hendak diubah ke NONAKTIF
        if ($request->filled('status_dosen')) {
            $statusUpper = strtoupper((string) $request->input('status_dosen'));
            $input['status_dosen'] = in_array($statusUpper, ['DTT', 'TIDAK TETAP', 'NONAKTIF'])
                ? ($statusUpper === 'TIDAK TETAP' ? 'DTT' : $statusUpper)
                : 'DTPS';

            if ($input['status_dosen'] === 'NONAKTIF') {
                if ($this->isDosenUsedInTridharma($id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Dosen Masih Aktif: Data dosen tidak dapat dinonaktifkan karena sedang digunakan dalam kegiatan Penelitian atau PkM.',
                    ], 422);
                }
            }
        }

        // Resolusi bidang keahlian
        if ($request->has('bidang_keahlian') && ! $request->has('bidang_keahlian_ids')) {
            $bidangVal = $request->input('bidang_keahlian');
            $bidangIds = [];
            foreach ((array) $bidangVal as $b) {
                if (is_numeric($b)) {
                    $bidangIds[] = (int) $b;
                } else {
                    $item = BidangKeahlian::where('nama_bidang', $b)->first();
                    if ($item) {
                        $bidangIds[] = $item->id_bidang_keahlian;
                    }
                }
            }
            $input['bidang_keahlian_ids'] = $bidangIds;
        }

        // Validasi input data dosen untuk update
        $validator = Validator::make($input, [
            'id_jabatan' => 'sometimes|required|exists:jabatan_akademik,id_jabatan',
            'id_pendidikan' => 'sometimes|required|exists:pendidikan,id_pendidikan',
            'nidn' => 'sometimes|required|string|max:30|unique:dosen,nidn,'.$id.',id_dosen',
            'nuptk' => 'nullable|string|max:50',
            'nama_dosen' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:dosen,email,'.$id.',id_dosen',
            'status_dosen' => 'nullable|string|in:DTPS,DTT,NONAKTIF',
            'sertifikasi' => 'nullable|string|max:255',
            'bidang_keahlian_ids' => 'nullable|array',
            'bidang_keahlian_ids.*' => 'exists:bidang_keahlian,id_bidang_keahlian',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $dosen = DB::transaction(function () use ($dosen, $validated, $input): Dosen {
            $dosenAttributes = $validated;
            unset($dosenAttributes['bidang_keahlian_ids']);

            $dosen->update($dosenAttributes);

            if (isset($validated['bidang_keahlian_ids']) || array_key_exists('bidang_keahlian_ids', $input)) {
                $this->syncExpertise($dosen, $validated['bidang_keahlian_ids'] ?? []);
            }

            if ($dosen->wasChanged(['id_jabatan', 'id_pendidikan', 'sertifikasi'])) {
                $dosen->recordHistory('MANUAL');
            }

            return $dosen;
        });

        // Tampilkan Pesan Berhasil
        return response()->json([
            'success' => true,
            'message' => 'Data dosen berhasil diperbarui.',
            'data' => $this->addAcademicSummaryAttributes($dosen->fresh(['jabatanAkademik', 'pendidikan', 'bidangKeahlian'])),
        ]);
    }

    // Aksi 5: Nonaktifkan dosen (Cek Keterkaitan ke Penelitian dan PkM -> Data Sedang Digunakan?)
    public function destroy(int $id): JsonResponse
    {
        $dosen = Dosen::find($id);

        // Cek data ada atau tidak
        if (! $dosen) {
            return response()->json(['success' => false, 'message' => 'Data dosen tidak ditemukan.'], 404);
        }

        // Cek Keterkaitan ke Penelitian dan PkM
        // Data Sedang Digunakan?
        if ($this->isDosenUsedInTridharma($id)) {
            // Ya -> Tampilkan Pesan Error: Dosen Masih Aktif
            return response()->json([
                'success' => false,
                'message' => 'Dosen Masih Aktif: Data dosen tidak dapat dinonaktifkan karena sedang digunakan dalam kegiatan Penelitian atau PkM.',
            ], 422);
        }

        // Tidak -> Nonaktifkan dosen (status_dosen = NONAKTIF, soft delete)
        $dosen->update(['status_dosen' => 'NONAKTIF']);
        $dosen->delete();

        // Tampilkan Pesan Berhasil
        return response()->json([
            'success' => true,
            'message' => 'Data dosen berhasil dinonaktifkan.',
        ]);
    }

    // Pulihkan data dosen yang terhapus (restore)
    public function restore(int $id): JsonResponse
    {
        // Cari data dosen yang di-soft-delete
        $dosen = Dosen::onlyTrashed()->find($id);

        // Cek data ada atau tidak
        if (! $dosen) {
            return response()->json(['success' => false, 'message' => 'Dosen terhapus tidak ditemukan.'], 404);
        }

        // Restore dan ubah status kembali ke DTPS
        $dosen->restore();
        $dosen->update(['status_dosen' => 'DTPS']);

        return response()->json([
            'success' => true,
            'message' => 'Data dosen berhasil diaktifkan kembali.',
            'data' => $dosen,
        ]);
    }

    // Aksi 3: Impor data dosen dari file Excel atau CSV
    public function importExcel(Request $request): JsonResponse
    {
        // Validasi file upload (wajib file spreadsheet Excel atau CSV, maks 5MB)
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120',
        ]);

        try {
            // Proses import file Excel atau CSV
            Excel::import(new DosenImport, $request->file('file'));

            // Tampilkan Pesan Berhasil
            return response()->json([
                'success' => true,
                'message' => 'Data dosen berhasil diimpor dari file Excel atau CSV.',
            ]);
        } catch (ValidationException $e) {
            // Tangkap error jika ada baris yang tidak valid
            $failures = $e->failures();
            $errors = [];

            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }

            return response()->json([
                'success' => false,
                'message' => 'Terdapat kesalahan validasi pada data Excel atau CSV.',
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            // Tangkap error umum lainnya
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor file: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Map frontend-friendly names to the persisted lecturer attributes.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function mapAcademicAliases(array $input): array
    {
        foreach ([
            'jabatan_akademik' => 'jabatan',
            'pendidikan_terakhir' => 'pendidikan',
            'sertifikasi_dosen' => 'sertifikasi',
        ] as $alias => $attribute) {
            if (array_key_exists($alias, $input) && ! array_key_exists($attribute, $input)) {
                $input[$attribute] = $input[$alias];
            }
        }

        return $input;
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    private function syncExpertise(Dosen $dosen, array $ids): void
    {
        $syncData = [];
        if ($ids !== []) {
            $bidangItems = BidangKeahlian::whereIn('id_bidang_keahlian', $ids)->get();
            foreach ($bidangItems as $item) {
                $syncData[$item->id_bidang_keahlian] = [
                    'nama_bidang_keahlian_dosen' => $item->nama_bidang,
                ];
            }
        }

        $dosen->bidangKeahlian()->sync($syncData);
    }

    private function addAcademicSummaryAttributes(Dosen $dosen): Dosen
    {
        return $dosen
            ->setAttribute('pendidikan_terakhir', $dosen->pendidikan?->jenjang)
            ->setAttribute('sertifikasi_dosen', $dosen->sertifikasi);
    }

    /**
     * Cek keterkaitan dosen ke kegiatan Penelitian dan PkM
     */
    private function isDosenUsedInTridharma(int $id): bool
    {
        // 1. Cek pada kegiatan terpadu (kegiatan & anggota_kegiatan)
        $penelitianKegiatanExists = Kegiatan::whereHas('jenisKegiatan', function ($q) {
            $q->where('nama_jenis_kegiatan', 'like', '%Penelitian%');
        })->whereHas('anggotaKegiatan', function ($q) use ($id) {
            $q->where('id_dosen', $id);
        })->exists();

        if ($penelitianKegiatanExists) {
            return true;
        }

        $pkmKegiatanExists = Kegiatan::whereHas('jenisKegiatan', function ($q) {
            $q->where('nama_jenis_kegiatan', 'like', '%PkM%');
        })->whereHas('anggotaKegiatan', function ($q) use ($id) {
            $q->where('id_dosen', $id);
        })->exists();

        if ($pkmKegiatanExists) {
            return true;
        }

        // 2. Cek juga pada tabel mandiri jika ada
        if (Schema::hasTable('penelitian')) {
            $penelitianTableExists = DB::table('penelitian')->where('id_dosen', $id)->exists()
                || (Schema::hasTable('anggota_penelitian') && DB::table('anggota_penelitian')->where('id_dosen', $id)->exists());

            if ($penelitianTableExists) {
                return true;
            }
        }

        if (Schema::hasTable('pkm')) {
            $pkmTableExists = DB::table('pkm')->where('id_dosen', $id)->exists()
                || (Schema::hasTable('anggota_pkm') && DB::table('anggota_pkm')->where('id_dosen', $id)->exists());

            if ($pkmTableExists) {
                return true;
            }
        }

        return false;
    }
}
