@extends('layouts.app')

@section('title', $article->title)

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.help-articles.index', ['category' => $article->category]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Pratinjau Artikel</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.help-articles.edit', $article->id) }}" class="btn btn-primary"><i
                            class="fas fa-pen"></i> Edit</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.help-articles.index') }}">Bantuan / FAQ</a>
                    </div>
                    <div class="breadcrumb-item">#{{ $article->id }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @unless ($article->is_published)
                    <div class="alert alert-warning"><i class="fas fa-pencil-alt"></i> Artikel ini masih <b>draft</b> dan belum
                        terlihat oleh pengguna.</div>
                @endunless

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <span class="badge badge-light mb-2">{{ $article->category }}</span>
                                    <h4 class="mb-0" style="font-size:20px">{{ $article->title }}</h4>
                                </div>
                            </div>
                            <div class="card-body help-article-content" style="font-size:15px;line-height:1.7">
                                {!! $html !!}
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Info</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Status</th>
                                        <td class="text-right"><span
                                                class="badge badge-{{ $article->is_published ? 'success' : 'secondary' }}">{{ $article->is_published ? 'Tayang' : 'Draft' }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Urutan</th>
                                        <td class="text-right">{{ $article->sort_order }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibaca</th>
                                        <td class="text-right">{{ number_format((int) $article->view_count) }} kali</td>
                                    </tr>
                                    <tr>
                                        <th>Penulis</th>
                                        <td class="text-right">{{ $creator->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat</th>
                                        <td class="text-right">{{ optional($article->created_at)->format('d M Y H:i') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Diubah</th>
                                        <td class="text-right">{{ optional($article->updated_at)->format('d M Y H:i') }}
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="card-footer">
                                <form action="{{ route('superadmin.help-articles.toggle-publish', $article->id) }}"
                                    method="POST" class="mb-2">
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        class="btn btn-outline-{{ $article->is_published ? 'warning' : 'success' }} btn-block">
                                        <i class="fas fa-{{ $article->is_published ? 'eye-slash' : 'upload' }}"></i>
                                        {{ $article->is_published ? 'Jadikan Draft' : 'Tayangkan' }}
                                    </button>
                                </form>
                                <a href="{{ route('superadmin.help-articles.create', ['copy' => $article->id]) }}"
                                    class="btn btn-outline-secondary btn-block"><i class="fas fa-copy"></i> Duplikat</a>
                                <form action="{{ route('superadmin.help-articles.destroy', $article->id) }}" method="POST"
                                    class="mt-2"
                                    onsubmit="return confirm('Hapus artikel ini? Isinya tetap tersimpan di Audit Log.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-block"><i class="fas fa-trash"></i>
                                        Hapus</button>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Artikel Lain di "{{ $article->category }}"</h4>
                            </div>
                            <div class="card-body">
                                @forelse ($siblings as $s)
                                    <div class="d-flex justify-content-between mb-1 text-small">
                                        <a href="{{ route('superadmin.help-articles.show', $s->id) }}">{{ $s->sort_order }}.
                                            {{ \Illuminate\Support\Str::limit($s->title, 34) }}</a>
                                        @unless ($s->is_published)
                                            <span class="badge badge-secondary">draft</span>
                                        @endunless
                                    </div>
                                @empty
                                    <p class="text-muted mb-0 text-small">Belum ada.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
