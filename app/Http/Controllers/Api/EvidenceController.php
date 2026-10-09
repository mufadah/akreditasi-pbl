<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evidence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceController extends Controller
{
    // Relasi yang selalu dimuat untuk mencegah N+1 query
    private array $relations = [
        'penelitian',
        'pkm',
        'kerjaSama',
    ];

    // Helper format response JSON standar
    private function respond(bool $success, int $code, string $message, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // Aturan validasi data evidence
    private function rules(bool $isUpdate = false): array
    {
        $fileRules = $isUpdate
            ? ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']
            : ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];

        $req = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            'file'            => $fileRules,
            'jenis_dokumen'   => [...$req, 'string', 'max:255'],
            'tanggal_upload'  => ['nullable', 'date'],
            'status_validasi' => ['nullable', 'string', 'max:50'],
            'id_penelitian'   => ['nullable', 'integer', Rule::exists('penelitian', 'id_penelitian')],
            'id_pkm'          => ['nullable', 'integer', Rule::exists('pkm', 'id_pkm')],
            'id_kerjasama'    => ['nullable', 'integer', Rule::exists('kerja_sama', 'id_kerja_sama')],
            'id_kerja_sama'   => ['nullable', 'integer', Rule::exists('kerja_sama', 'id_kerja_sama')],
        ];
    }

    // Ambil daftar evidence dengan filter pencarian dan relasi
    public function index(Request $request): JsonResponse
    {
        // Query dengan relasi penelitian, pkm, dan kerja sama
        $query = Evidence::with($this->relations);

        // Filter pencarian nama file atau jenis dokumen
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_file', 'like', "%{$search}%")
                  ->orWhere('jenis_dokumen', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan foreign key kegiatan
        if ($request->filled('id_penelitian')) {
            $query->where('id_penelitian', $request->query('id_penelitian'));
        }
        if ($request->filled('id_pkm')) {
            $query->where('id_pkm', $request->query('id_pkm'));
        }
        if ($idKerjaSama = $request->query('id_kerja_sama', $request->query('id_kerjasama'))) {
            $query->where('id_kerja_sama', $idKerjaSama);
        }

        // Filter status validasi
        if ($status = $request->query('status_validasi')) {
            $query->where('status_validasi', $status);
        }

        // Paginasi data 10 per halaman
        $evidence = $query->latest()->paginate($request->integer('per_page', 10));

        return $this->respond(true, 200, 'Data evidence berhasil diambil.', $evidence);
    }

    // Upload berkas fisik dan simpan metadata evidence baru
    public function store(Request $request): JsonResponse
    {
        // Validasi input multipart
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        // Normalisasi foreign key kerja sama
        $idKerjaSama = $validated['id_kerja_sama'] ?? $validated['id_kerjasama'] ?? null;

        // Simpan berkas fisik ke disk public di folder evidence
        $uploadedFile = $request->file('file');
        $storedPath   = $uploadedFile->store('evidence', 'public');
        $originalName = $uploadedFile->getClientOriginalName();

        try {
            // Simpan record metadata evidence dalam transaksi DB
            $evidence = DB::transaction(function () use ($validated, $storedPath, $originalName, $idKerjaSama) {
                return Evidence::create([
                    'nama_file'       => $originalName,
                    'path_file'       => $storedPath,
                    'url_drive'       => Storage::disk('public')->url($storedPath),
                    'jenis_dokumen'   => $validated['jenis_dokumen'],
                    'tanggal_upload'  => $validated['tanggal_upload'] ?? now()->toDateString(),
                    'status_validasi' => $validated['status_validasi'] ?? 'Aktif',
                    'id_penelitian'   => $validated['id_penelitian'] ?? null,
                    'id_pkm'          => $validated['id_pkm'] ?? null,
                    'id_kerja_sama'   => $idKerjaSama,
                    'status'          => 'Valid',
                ]);
            });
        } catch (\Throwable $e) {
            // Hapus file fisik jika transaksi DB gagal
            Storage::disk('public')->delete($storedPath);
            return $this->respond(false, 500, 'Gagal menyimpan data evidence: ' . $e->getMessage());
        }

        return $this->respond(true, 201, 'Berkas evidence berhasil diunggah.', $evidence->load($this->relations));
    }

    // Ambil detail satu evidence
    public function show(int $id): JsonResponse
    {
        $evidence = Evidence::with($this->relations)->find($id);

        // Cek data ada atau tidak
        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        return $this->respond(true, 200, 'Detail evidence berhasil diambil.', $evidence);
    }

    // Update metadata evidence atau ganti berkas fisik
    public function update(Request $request, int $id): JsonResponse
    {
        $evidence = Evidence::find($id);

        // Cek data ada atau tidak
        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        // Validasi input parsial
        $validator = Validator::make($request->all(), $this->rules(true));

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $validated = $validator->validated();

        $oldPath = $evidence->path_file;
        $newPath = null;

        // Tangani unggah berkas baru jika dikirimkan
        if ($request->hasFile('file')) {
            $newFile  = $request->file('file');
            $newPath  = $newFile->store('evidence', 'public');
            $validated['nama_file'] = $newFile->getClientOriginalName();
            $validated['path_file'] = $newPath;
            $validated['url_drive'] = Storage::disk('public')->url($newPath);
        }

        // Normalisasi foreign key kerja sama
        if (array_key_exists('id_kerjasama', $validated) || array_key_exists('id_kerja_sama', $validated)) {
            $validated['id_kerja_sama'] = $validated['id_kerja_sama'] ?? $validated['id_kerjasama'] ?? null;
        }

        unset($validated['file'], $validated['id_kerjasama']);

        try {
            // Update metadata dalam transaksi DB
            DB::transaction(function () use ($evidence, $validated) {
                $evidence->update($validated);
            });

            // Hapus berkas fisik lama jika berkas baru berhasil diupdate
            if ($newPath && $oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        } catch (\Throwable $e) {
            // Hapus file baru jika update gagal
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }
            return $this->respond(false, 500, 'Gagal memperbarui evidence: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data evidence berhasil diperbarui.', $evidence->fresh($this->relations));
    }

    // Hapus record evidence beserta berkas fisik di storage
    public function destroy(int $id): JsonResponse
    {
        $evidence = Evidence::find($id);

        // Cek data ada atau tidak
        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        try {
            // Hapus berkas fisik dari storage jika ada
            if ($evidence->path_file && Storage::disk('public')->exists($evidence->path_file)) {
                Storage::disk('public')->delete($evidence->path_file);
            }

            // Hapus record dari database
            $evidence->delete();
        } catch (\Throwable $e) {
            return $this->respond(false, 500, 'Gagal menghapus data evidence: ' . $e->getMessage());
        }

        return $this->respond(true, 200, 'Data evidence dan berkas fisik berhasil dihapus.');
    }

    // Download berkas fisik evidence
    public function download(int $id): StreamedResponse|JsonResponse
    {
        $evidence = Evidence::find($id);

        // Cek record ada atau tidak
        if (! $evidence) {
            return $this->respond(false, 404, 'Data evidence tidak ditemukan.');
        }

        // Cek keberadaan berkas fisik di disk public
        if (! $evidence->path_file || ! Storage::disk('public')->exists($evidence->path_file)) {
            return $this->respond(false, 404, 'Berkas fisik evidence tidak ditemukan di server.');
        }

        // Unduh berkas fisik dengan nama file aslinya
        return Storage::disk('public')->download($evidence->path_file, $evidence->nama_file ?? 'evidence');
    }
}
