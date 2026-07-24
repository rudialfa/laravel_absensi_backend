<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HelpArticleController extends Controller
{
    /**
     * List artikel bantuan/FAQ yang sudah dipublish.
     * GET /api/help-articles?category=Absensi&q=password&page=1
     */
    public function index(Request $request)
    {
        $query = HelpArticle::where('is_published', true)
            ->orderBy('category')
            ->orderBy('sort_order');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $articles = $query->paginate(20);

        return response()->json([
            'status' => true,
            'message' => 'Berhasil mengambil daftar artikel bantuan',
            'data' => $articles,
        ]);
    }

    /**
     * List kategori yang tersedia — buat filter/tab di app.
     * GET /api/help-articles/categories
     */
    public function categories()
    {
        $categories = HelpArticle::where('is_published', true)
            ->select('category')
            ->distinct()
            ->pluck('category');

        return response()->json([
            'status' => true,
            'message' => 'Berhasil mengambil daftar kategori',
            'data' => $categories,
        ]);
    }

    /**
     * Detail 1 artikel + otomatis tambah view_count.
     * GET /api/help-articles/{id}
     */
    public function show(int $id)
    {
        $article = HelpArticle::where('is_published', true)->find($id);

        if (! $article) {
            return response()->json([
                'status' => false,
                'message' => 'Artikel tidak ditemukan',
            ], 404);
        }

        $article->increment('view_count');

        return response()->json([
            'status' => true,
            'message' => 'Berhasil mengambil detail artikel',
            'data' => $article,
        ]);
    }
}
