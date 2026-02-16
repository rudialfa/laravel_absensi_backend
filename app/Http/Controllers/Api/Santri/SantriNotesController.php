<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Note;

class SantriNotesController extends Controller
{
    private function ensureSantri(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'santri') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus santri)',
            ], 403));
        }
    }

    private function companyId(): int
    {
        $companyId = auth()->user()->company_id ?? null;

        if (!$companyId) {
            abort(response()->json([
                'status' => false,
                'message' => 'Company ID tidak ditemukan',
            ], 422));
        }

        return $companyId;
    }

    public function index()
    {
        $this->ensureSantri();

        $data = Note::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'status' => true,
            'message' => 'List notes santri',
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureSantri();

        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'note'  => 'required|string',
        ]);

        $note = Note::create([
            'company_id' => $this->companyId(),
            'user_id'    => auth()->id(),
            'title'      => $validated['title'],
            'note'       => $validated['note'],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Note dibuat',
            'data' => $note,
        ], 201);
    }

    public function show($id)
    {
        $this->ensureSantri();

        $note = Note::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Detail note',
            'data' => $note,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->ensureSantri();

        $note = Note::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:120',
            'note'  => 'sometimes|required|string',
        ]);

        if (array_key_exists('title', $validated)) $note->title = $validated['title'];
        if (array_key_exists('note', $validated))  $note->note  = $validated['note'];

        $note->save();

        return response()->json([
            'status' => true,
            'message' => 'Note diupdate',
            'data' => $note,
        ]);
    }

    public function destroy($id)
    {
        $this->ensureSantri();

        $note = Note::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $note->delete();

        return response()->json([
            'status' => true,
            'message' => 'Note dihapus',
        ]);
    }
}
