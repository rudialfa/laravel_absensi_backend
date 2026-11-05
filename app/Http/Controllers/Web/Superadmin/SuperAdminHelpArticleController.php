<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\HelpArticle;
use Illuminate\Http\Request;

/**
 * Artikel Bantuan / FAQ (help_articles) yang tampil di aplikasi semua tenant
 * lewat Api\Public\HelpArticleController. CRUD lengkap + publish/draft + duplikat.
 */
class SuperAdminHelpArticleController extends Controller
{
    /** Saran kategori (tetap bisa ketik kategori baru) */
    public const SUGGESTED_CATEGORIES = ['Umum', 'Akun & Login', 'Absensi', 'Izin & Cuti', 'Payroll', 'Sekolah', 'Pesantren', 'Langganan & Pembayaran', 'Notifikasi'];

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $articles = HelpArticle::query()
            ->when($request->category, fn($q, $c) => $q->where('category', $c))
            ->when($request->status === 'published', fn($q) => $q->where('is_published', true))
            ->when($request->status === 'draft', fn($q) => $q->where('is_published', false))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                $q->where(fn($w) => $w->where('title', 'like', $like)->orWhere('content', 'like', $like));
            })
            ->when($request->sort === 'popular', fn($q) => $q->orderByDesc('view_count'), fn($q) => $q->orderBy('category')->orderBy('sort_order')->orderBy('title'))
            ->paginate(25)->withQueryString();

        $summary = HelpArticle::query()->toBase()
            ->selectRaw('count(*) as total, sum(is_published = 1) as published, coalesce(sum(view_count), 0) as views, count(distinct category) as categories')
            ->first();

        return view('pages.superadmin.help-articles.index', [
            'articles'   => $articles,
            'summary'    => $summary,
            'categories' => HelpArticle::query()->selectRaw('category, count(*) as total')->groupBy('category')->orderBy('category')->pluck('total', 'category'),
            'creators'   => \App\Models\User::whereIn('id', HelpArticle::whereNotNull('created_by')->select('created_by'))->pluck('name', 'id'),
            'popular'    => HelpArticle::where('is_published', true)->where('view_count', '>', 0)->orderByDesc('view_count')->limit(5)->get(['id', 'title', 'view_count']),
        ]);
    }

    // =========================================================
    // CREATE / STORE
    // =========================================================
    public function create(Request $request)
    {
        // ?copy=ID untuk menduplikat artikel
        $article = (new HelpArticle())->forceFill(['is_published' => true, 'sort_order' => 0]);
        if ($request->copy && ($src = HelpArticle::find($request->copy))) {
            $article->forceFill($src->only(['category', 'content', 'sort_order']));
            $article->title = $src->title . ' (salinan)';
            $article->is_published = false;
        }

        return view('pages.superadmin.help-articles.create', $this->formData($article));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $article = new HelpArticle();
        $article->forceFill($data + ['created_by' => auth()->id(), 'view_count' => 0])->save();

        AuditLog::record('create_help_article', $article, "Membuat artikel bantuan \"{$article->title}\"");

        return $this->redirectAfterSave($request, $article, 'Artikel bantuan dibuat' . ($article->is_published ? ' dan sudah tayang.' : ' sebagai draft.'));
    }

    // =========================================================
    // SHOW (pratinjau seperti di aplikasi)
    // =========================================================
    public function show(string $id)
    {
        $article = HelpArticle::findOrFail($id);

        return view('pages.superadmin.help-articles.show', [
            'article'  => $article,
            'html'     => self::renderContent($article->content),
            'creator'  => $article->created_by ? \App\Models\User::find($article->created_by, ['id', 'name']) : null,
            'siblings' => HelpArticle::where('category', $article->category)->where('id', '!=', $article->id)
                ->orderBy('sort_order')->orderBy('title')->get(['id', 'title', 'is_published', 'sort_order']),
        ]);
    }

    // =========================================================
    // EDIT / UPDATE
    // =========================================================
    public function edit(string $id)
    {
        return view('pages.superadmin.help-articles.edit', $this->formData(HelpArticle::findOrFail($id)));
    }

    public function update(Request $request, string $id)
    {
        $article = HelpArticle::findOrFail($id);
        $before = $article->only(['category', 'title', 'sort_order', 'is_published']);

        $article->forceFill($this->validated($request))->save();

        AuditLog::record('update_help_article', $article, "Mengubah artikel bantuan \"{$article->title}\"", [
            'before' => $before,
            'after'  => $article->only(array_keys($before)),
            'content_changed' => $article->wasChanged('content'),
        ]);

        return $this->redirectAfterSave($request, $article, 'Artikel bantuan diperbarui.');
    }

    // =========================================================
    // DESTROY
    // =========================================================
    public function destroy(string $id)
    {
        $article = HelpArticle::findOrFail($id);
        $snapshot = $article->getAttributes();
        $article->delete();

        // Simpan isi lengkap di audit log supaya bisa dipulihkan manual kalau salah hapus
        AuditLog::record('delete_help_article', null, "Menghapus artikel bantuan \"{$snapshot['title']}\"", $snapshot);

        return redirect()->route('superadmin.help-articles.index')->with('success', 'Artikel bantuan dihapus. Isinya tersimpan di Audit Log.');
    }

    /** Publish / draft cepat */
    public function togglePublish(string $id)
    {
        $article = HelpArticle::findOrFail($id);
        $article->forceFill(['is_published' => !$article->is_published])->save();

        $status = $article->is_published ? 'ditayangkan' : 'dijadikan draft';
        AuditLog::record('toggle_help_article', $article, "Artikel \"{$article->title}\" {$status}");

        return back()->with('success', "Artikel {$status}.");
    }

    // =========================================================
    // HELPER
    // =========================================================
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category'     => 'required|string|max:100',
            'title'        => 'required|string|max:255',
            'content'      => 'required|string|max:60000',
            'sort_order'   => 'nullable|integer|min:0|max:65535',
            'is_published' => 'nullable|boolean',
        ], [], [
            'category' => 'kategori',
            'title' => 'judul',
            'content' => 'isi jawaban',
            'sort_order' => 'urutan',
        ]);

        $data['category'] = trim($data['category']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        // Form selalu mengirim hidden is_published=0, jadi checkbox kosong = draft
        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }

    private function formData(HelpArticle $article): array
    {
        return [
            'article'    => $article,
            'categories' => collect(self::SUGGESTED_CATEGORIES)->merge(HelpArticle::distinct()->pluck('category'))->unique()->sort()->values(),
        ];
    }

    private function redirectAfterSave(Request $request, HelpArticle $article, string $message)
    {
        return $request->input('after') === 'preview'
            ? redirect()->route('superadmin.help-articles.show', $article->id)->with('success', $message)
            : redirect()->route('superadmin.help-articles.index', ['category' => $article->category])->with('success', $message);
    }

    /**
     * Isi artikel untuk ditampilkan: kalau berisi HTML, hanya tag aman yang dipertahankan
     * (atribut on*, style & javascript: dibuang). Kalau teks biasa, baris baru jadi <br>.
     */
    public static function renderContent(?string $content): string
    {
        $content = (string) $content;

        if ($content === strip_tags($content)) {
            return nl2br(e($content));
        }

        $html = strip_tags($content, '<p><br><b><strong><i><em><u><ul><ol><li><a><h3><h4><h5><blockquote><code><pre><hr><img>');
        $html = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html);
        $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1="#"', $html);

        return $html;
    }
}
