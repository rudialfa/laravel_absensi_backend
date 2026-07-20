<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\HelpArticle;
use Illuminate\Http\Request;

class SuperAdminHelpArticleController extends Controller
{
    /**
     * List semua artikel bantuan/FAQ.
     */
    public function index(Request $request)
    {
        $query = HelpArticle::latest();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('is_published', $request->status === 'published');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $articles = $query->paginate(15)->withQueryString();

        // Buat filter dropdown kategori
        $categories = HelpArticle::select('category')->distinct()->pluck('category');

        return view('pages.superadmin.help-articles.index', compact('articles', 'categories'));
    }

    public function create()
    {
        return view('pages.superadmin.help-articles.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = auth()->id();

        $article = HelpArticle::create($data);

        $this->log('create_help_article', $article, "Membuat artikel bantuan \"{$article->title}\"");

        return redirect()
            ->route('superadmin.help-articles.index')
            ->with('success', 'Artikel bantuan berhasil dibuat.');
    }

    public function edit(string $id)
    {
        $article = HelpArticle::findOrFail($id);

        return view('pages.superadmin.help-articles.edit', compact('article'));
    }

    public function update(Request $request, string $id)
    {
        $article = HelpArticle::findOrFail($id);
        $data = $this->validated($request);

        $article->update($data);

        $this->log('update_help_article', $article, "Mengubah artikel bantuan \"{$article->title}\"");

        return redirect()
            ->route('superadmin.help-articles.index')
            ->with('success', 'Artikel bantuan berhasil diperbarui.');
    }

    public function destroy(string $id)
    {
        $article = HelpArticle::findOrFail($id);
        $title = $article->title;
        $article->delete();

        $this->log('delete_help_article', null, "Menghapus artikel bantuan \"{$title}\"", ['article_id' => $id]);

        return redirect()
            ->route('superadmin.help-articles.index')
            ->with('success', 'Artikel bantuan berhasil dihapus.');
    }

    /**
     * Publish / unpublish cepat tanpa buka form edit.
     */
    public function togglePublish(string $id)
    {
        $article = HelpArticle::findOrFail($id);
        $article->is_published = ! $article->is_published;
        $article->save();

        $status = $article->is_published ? 'dipublish' : 'disimpan sebagai draft';
        $this->log('toggle_help_article', $article, "Artikel \"{$article->title}\" {$status}");

        return back()->with('success', "Artikel berhasil {$status}.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data['is_published'] = $request->boolean('is_published', true);

        return $data;
    }

    private function log(string $action, mixed $subject, string $description, array $meta = []): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject->id ?? null,
            'description' => $description,
            'meta' => $meta ?: null,
            'ip_address' => request()->ip(),
        ]);
    }
}
