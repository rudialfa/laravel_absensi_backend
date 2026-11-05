@extends('layouts.app')

@section('title', 'Bantuan / FAQ')

@section('main')
    @php
        $hasFilter = collect(request()->except(['page']))->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty();
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Bantuan / FAQ</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.help-articles.create', array_filter(['category' => request('category')])) }}" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Artikel</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Konten &amp; Dukungan</div>
                    <div class="breadcrumb-item">Bantuan / FAQ</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">Artikel bantuan yang tampil di menu Bantuan aplikasi semua tenant. Hanya artikel berstatus <b>Tayang</b> yang terlihat pengguna.</p>

                <div class="row">
                    @foreach ([
                        ['Artikel', (int) $summary->total, 'primary', 'fa-book', []],
                        ['Tayang', (int) $summary->published, 'success', 'fa-eye', ['status' => 'published']],
                        ['Draft', (int) $summary->total - (int) $summary->published, 'secondary', 'fa-pencil-alt', ['status' => 'draft']],
                        ['Total Dibaca', number_format((int) $summary->views), 'info', 'fa-chart-bar', ['sort' => 'popular']],
                    ] as [$label, $value, $color, $icon, $filter])
                        <div class="col-6 col-lg-3">
                            <a href="{{ route('superadmin.help-articles.index', $filter) }}" class="text-decoration-none">
                                <div class="card card-statistic-1">
                                    <div class="card-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i></div>
                                    <div class="card-wrap"><div class="card-header"><h4>{{ $label }}</h4></div><div class="card-body" style="font-size:18px">{{ $value }}</div></div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>

                <div class="row">
                    <div class="col-12 col-lg-9">
                        <div class="card">
                            <div class="card-body pb-0">
                                <form method="GET" action="{{ route('superadmin.help-articles.index') }}">
                                    <div class="form-row">
                                        <div class="col-md-3 mb-3">
                                            <select name="category" class="form-control">
                                                <option value="">Semua Kategori</option>
                                                @foreach ($categories as $cat => $n)
                                                    <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }} ({{ $n }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2 mb-3">
                                            <select name="status" class="form-control">
                                                <option value="">Semua Status</option>
                                                <option value="published" @selected(request('status') === 'published')>Tayang</option>
                                                <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 mb-3">
                                            <select name="sort" class="form-control">
                                                <option value="">Urut kategori</option>
                                                <option value="popular" @selected(request('sort') === 'popular')>Paling dibaca</option>
                                            </select>
                                        </div>
                                        <div class="col-md mb-3"><input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari judul / isi"></div>
                                        <div class="col-auto mb-3"><button class="btn btn-primary"><i class="fas fa-search"></i></button></div>
                                    </div>
                                    @if ($hasFilter)
                                        <div class="mb-3"><a href="{{ route('superadmin.help-articles.index') }}" class="text-small"><i class="fas fa-times"></i> Hapus filter</a></div>
                                    @endif
                                </form>
                            </div>

                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0">
                                        <thead>
                                            <tr><th style="width:60px">Urut</th><th>Judul / Pertanyaan</th><th>Kategori</th><th class="text-center">Dibaca</th><th>Status</th><th class="text-right" style="width:190px">Aksi</th></tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($articles as $a)
                                                <tr class="{{ $a->is_published ? '' : 'text-muted' }}">
                                                    <td class="text-center">{{ $a->sort_order }}</td>
                                                    <td>
                                                        <a href="{{ route('superadmin.help-articles.show', $a->id) }}" class="font-weight-bold">{{ $a->title }}</a>
                                                        <div class="text-small text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($a->content), 90) }}</div>
                                                        <div class="text-small text-muted">Diubah {{ optional($a->updated_at)->diffForHumans() }}{{ $a->created_by && isset($creators[$a->created_by]) ? ' · ditulis ' . $creators[$a->created_by] : '' }}</div>
                                                    </td>
                                                    <td><a href="{{ route('superadmin.help-articles.index', ['category' => $a->category]) }}" class="badge badge-light">{{ $a->category }}</a></td>
                                                    <td class="text-center">{{ number_format((int) $a->view_count) }}</td>
                                                    <td><span class="badge badge-{{ $a->is_published ? 'success' : 'secondary' }}">{{ $a->is_published ? 'Tayang' : 'Draft' }}</span></td>
                                                    <td class="text-right text-nowrap">
                                                        <a href="{{ route('superadmin.help-articles.show', $a->id) }}" class="btn btn-sm btn-outline-primary" title="Pratinjau"><i class="fas fa-eye"></i></a>
                                                        <a href="{{ route('superadmin.help-articles.edit', $a->id) }}" class="btn btn-sm btn-outline-info" title="Edit"><i class="fas fa-pen"></i></a>
                                                        <a href="{{ route('superadmin.help-articles.create', ['copy' => $a->id]) }}" class="btn btn-sm btn-outline-secondary" title="Duplikat"><i class="fas fa-copy"></i></a>
                                                        <form action="{{ route('superadmin.help-articles.toggle-publish', $a->id) }}" method="POST" class="d-inline">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button class="btn btn-sm btn-outline-{{ $a->is_published ? 'warning' : 'success' }}" title="{{ $a->is_published ? 'Jadikan draft' : 'Tayangkan' }}"><i class="fas fa-{{ $a->is_published ? 'eye-slash' : 'upload' }}"></i></button>
                                                        </form>
                                                        <form action="{{ route('superadmin.help-articles.destroy', $a->id) }}" method="POST" class="d-inline"
                                                            onsubmit="return confirm('Hapus artikel &quot;{{ addslashes($a->title) }}&quot;? Isinya tetap tersimpan di Audit Log.')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada artikel bantuan.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            @if ($articles->hasPages())
                                <div class="card-footer text-right">{{ $articles->links() }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="col-12 col-lg-3">
                        <div class="card">
                            <div class="card-header"><h4>Kategori</h4></div>
                            <div class="card-body">
                                @forelse ($categories as $cat => $n)
                                    <a href="{{ route('superadmin.help-articles.index', ['category' => $cat]) }}" class="d-flex justify-content-between mb-1 {{ request('category') === $cat ? 'font-weight-bold' : '' }}">
                                        <span>{{ $cat }}</span><span class="badge badge-light">{{ $n }}</span>
                                    </a>
                                @empty
                                    <p class="text-muted mb-0">Belum ada.</p>
                                @endforelse
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header"><h4>Paling Dibaca</h4></div>
                            <div class="card-body">
                                @forelse ($popular as $p)
                                    <div class="d-flex justify-content-between mb-1 text-small">
                                        <a href="{{ route('superadmin.help-articles.show', $p->id) }}">{{ \Illuminate\Support\Str::limit($p->title, 32) }}</a>
                                        <span class="text-muted">{{ number_format($p->view_count) }}</span>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0 text-small">Belum ada yang dibaca.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
