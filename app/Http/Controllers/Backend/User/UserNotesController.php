<?php

namespace App\Http\Controllers\Backend\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Note;
use Illuminate\Support\Facades\Auth;

class UserNotesController extends Controller
{

    //  public function index()
    // { $notes = Note::where('user_id', Auth::id())
    //         ->orderBy('created_at', 'desc')
    //         ->paginate(10);

    //     return view('pages.user.notes.index', compact('notes'));
    // }

    //code 2 index
    public function index(Request $request)
    {
        $query = Note::where('user_id', $this->userId())
            ->where('company_id', $this->companyId());

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $notes = $query->latest()->paginate(10);

        return view('pages.user.notes.index', compact('notes'));
    }

    public function create()
    {
        return view('pages.user.notes.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'note'  => 'required',
        ]);

        Note::create([
            'user_id' => Auth::id(),
            'title'   => $request->title,
            'note'    => $request->note,
        ]);

        return redirect()->route('user.notes.index')
            ->with('success', 'Catatan berhasil dibuat!');
    }

    public function edit($id)
    {
        $note = Note::where('user_id', Auth::id())->findOrFail($id);

        return view('user.notes.edit', compact('note'));
    }

    public function update(Request $request, $id)
    {
        $note = Note::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'title' => 'required',
            'note'  => 'required',
        ]);

        $note->update([
            'title' => $request->title,
            'note'  => $request->note,
        ]);

        return redirect()->route('user.notes.index')
            ->with('success', 'Catatan berhasil diupdate!');
    }

    public function destroy($id)
    {
        $note = Note::where('user_id', Auth::id())->findOrFail($id);
        $note->delete();

        return back()->with('success', 'Catatan berhasil dihapus!');
    }
}
