<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Support\HelpCenterFormat;
use Illuminate\Http\Request;

class HelpArticleController extends Controller
{
    /**
     * Daftar FAQ yang sudah dipublish (tanpa isi lengkap, ada excerpt).
     * GET /api/help-articles?category=Absensi&q=password&page=1&per_page=20
     */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 20), 5), 50);

        $query = HelpArticle::where('is_published', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('q')) {
            $search = trim($request->q);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $articles = $query->paginate($perPage)
            ->through(fn($a) => HelpCenterFormat::article($a, false));

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil daftar bantuan',
            'data'    => $articles,
        ]);
    }

    /**
     * Daftar kategori (untuk tab/filter di aplikasi).
     * GET /api/help-articles/categories
     */
    public function categories()
    {
        $categories = HelpArticle::where('is_published', true)
            ->selectRaw('category, MIN(sort_order) as first_order')
            ->groupBy('category')
            ->orderBy('first_order')
            ->orderBy('category')
            ->pluck('category');

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil daftar kategori',
            'data'    => $categories,
        ]);
    }

    /**
     * Detail 1 artikel + artikel terkait (kategori sama). view_count bertambah.
     * GET /api/help-articles/{id}
     */
    public function show(int $id)
    {
        $article = HelpArticle::where('is_published', true)->find($id);

        if (! $article) {
            return response()->json([
                'status'  => false,
                'message' => 'Artikel tidak ditemukan',
            ], 404);
        }

        $article->increment('view_count');

        $related = HelpArticle::where('is_published', true)
            ->where('category', $article->category)
            ->where('id', '!=', $article->id)
            ->orderBy('sort_order')
            ->limit(5)
            ->get()
            ->map(fn($a) => HelpCenterFormat::article($a, false));

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil detail artikel',
            'data'    => array_merge(HelpCenterFormat::article($article), [
                'related' => $related,
            ]),
        ]);
    }
}