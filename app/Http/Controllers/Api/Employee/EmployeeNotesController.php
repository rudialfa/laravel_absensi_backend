<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Note;

class EmployeeNotesController extends Controller
{
    private function ensureEmployee()
    {
        if (!auth()->check() || auth()->user()->role !== 'employee') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus employee)'], 403));
        }
    }

    private function companyId()
    {
        return auth()->user()->company_id ?? null;
    }

    public function index()
    {
        $this->ensureEmployee();

        $data = Note::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['status' => true, 'message' => 'List notes saya', 'data' => $data]);
    }

    public function store(Request $request)
    {
        $this->ensureEmployee();

        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'content' => 'required|string',
        ]);

        $note = Note::create([
            'company_id' => $this->companyId(),
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'content' => $validated['content'],
        ]);

        return response()->json(['status' => true, 'message' => 'Note dibuat', 'data' => $note], 201);
    }

    public function show($id)
    {
        $this->ensureEmployee();

        $note = Note::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json(['status' => true, 'message' => 'Detail note', 'data' => $note]);
    }

    public function update(Request $request, $id)
    {
        $this->ensureEmployee();

        $note = Note::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:120',
            'content' => 'sometimes|required|string',
        ]);

        if (array_key_exists('title', $validated)) $note->title = $validated['title'];
        if (array_key_exists('content', $validated)) $note->content = $validated['content'];

        $note->save();

        return response()->json(['status' => true, 'message' => 'Note diupdate', 'data' => $note]);
    }

    public function destroy($id)
    {
        $this->ensureEmployee();

        $note = Note::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $note->delete();

        return response()->json(['status' => true, 'message' => 'Note dihapus']);
    }
}
