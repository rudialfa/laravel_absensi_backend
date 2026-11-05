@extends('layouts.app')

@section('title', 'Kebijakan Aplikasi')

@section('main')
    @php
        $tz = 'Asia/Jakarta';
        $statusOf = fn($p) => $p->is_active ? ['Aktif', 'success'] : ($p->published_at ? ['Versi lama', 'light'] : ['Draft', 'secondary']);
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Kebijakan Aplikasi</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.app-policies.create', array_filter(['type' => request('type')])) }}" class="btn btn-primary"><i class="fas fa-plus"></i> Buat Versi Baru</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Konten &amp; Dukungan</div>
                    <div class="breadcrumb-item">Kebijakan Aplikasi</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">
                    Versi baru disimpan sebagai <b>draft</b> dan baru tampil di aplikasi setelah di-<b>Publish</b>. Hanya 1 versi aktif per jenis.
                    Versi yang pernah tayang tidak bisa diubah atau dihapus (riwayat yang disetujui pengguna).
                </p>

                {{-- Versi aktif per jenis --}}
                <div class="row">
                    @foreach ($typeLabels as $type => $label)
                        @php $a = $active[$type] ?? null; @endphp
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="card {{ $a ? '' : 'border border-warning' }}">
                                <div class="card-body">
                                    <div class="text-small text-muted">{{ $label }}</div>
                                    @if ($a)
                                        <a href="{{ route('superadmin.app-policies.show', $a->id) }}" class="h6 d-block mb-1">Versi {{ $a->version }}</a>
                                        <div class="text-small text-muted">Aktif sejak {{ optional($a->published_at)->timezone($tz)->format('d M Y') }}</div>
                                    @else
                                        <div class="h6 mb-1 text-warning">Belum ada versi aktif</div>
                                        <div class="text-small text-muted">Aplikasi tidak menampilkan apa pun.</div>
                                    @endif
                                    <div class="mt-2">
                                        @if (($drafts[$type] ?? 0) > 0)
                                            <a href="{{ route('superadmin.app-policies.index', ['type' => $type, 'status' => 'draft']) }}" class="badge badge-secondary">{{ $drafts[$type] }} draft</a>
                                        @endif
                                        <a href="{{ route('superadmin.app-policies.create', $a ? ['from' => $a->id] : ['type' => $type]) }}" class="badge badge-primary">+ versi baru</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4>Semua Versi</h4>
                        <div class="card-header-form">
                            <form method="GET" action="{{ route('superadmin.app-policies.index') }}" class="form-inline">
                                <select name="type" class="form-control mr-2" onchange="this.form.submit()">
                                    <option value="">Semua Jenis</option>
                                    @foreach ($typeLabels as $value => $label)
                                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select name="status" class="form-control" onchange="this.form.submit()">
                                    <option value="">Semua Status</option>
                                    <option value="active" @selected(request('status') === 'active')>Aktif</option>
                                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                                    <option value="old" @selected(request('status') === 'old')>Versi lama</option>
                                </select>
                            </form>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr><th>Jenis</th><th>Judul</th><th>Versi</th><th>Status</th><th>Tayang</th><th>Diubah</th><th class="text-right">Aksi</th></tr>
                                </thead>
                                <tbody>
                                    @forelse ($policies as $p)
                                        @php [$sLabel, $sBadge] = $statusOf($p); @endphp
                                        <tr>
                                            <td>{{ $typeLabels[$p->type] ?? $p->type }}</td>
                                            <td><a href="{{ route('superadmin.app-policies.show', $p->id) }}" class="font-weight-bold">{{ $p->title }}</a></td>
                                            <td>{{ $p->version }}</td>
                                            <td><span class="badge badge-{{ $sBadge }}">{{ $sLabel }}</span></td>
                                            <td class="text-small">
                                                @if ($p->published_at)
                                                    {{ $p->published_at->timezone($tz)->format('d M Y H:i') }}
                                                    <div class="text-muted">{{ $p->publishedBy->name ?? '-' }}</div>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="text-small">{{ optional($p->updated_at)->diffForHumans() }}</td>
                                            <td class="text-right text-nowrap">
                                                <a href="{{ route('superadmin.app-policies.show', $p->id) }}" class="btn btn-sm btn-outline-primary" title="Pratinjau"><i class="fas fa-eye"></i></a>
                                                @if (!$p->published_at)
                                                    <a href="{{ route('superadmin.app-policies.edit', $p->id) }}" class="btn btn-sm btn-outline-info" title="Edit"><i class="fas fa-pen"></i></a>
                                                @endif
                                                <a href="{{ route('superadmin.app-policies.create', ['from' => $p->id]) }}" class="btn btn-sm btn-outline-secondary" title="Buat versi baru dari ini"><i class="fas fa-code-branch"></i></a>
                                                @if (!$p->is_active)
                                                    <form action="{{ route('superadmin.app-policies.publish', $p->id) }}" method="POST" class="d-inline"
                                                        onsubmit="return confirm('{{ $p->published_at ? 'Tayangkan ulang versi lama ini' : 'Publish versi ini' }}? Versi aktif sekarang akan dinonaktifkan.')">
                                                        @csrf
                                                        <button class="btn btn-sm btn-outline-success" title="{{ $p->published_at ? 'Tayangkan ulang' : 'Publish' }}"><i class="fas fa-upload"></i></button>
                                                    </form>
                                                @endif
                                                @if (!$p->published_at)
                                                    <form action="{{ route('superadmin.app-policies.destroy', $p->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus draft ini?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-sm btn-outline-danger" title="Hapus draft"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada kebijakan. Klik "Buat Versi Baru".</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if ($policies->hasPages())
                        <div class="card-footer text-right">{{ $policies->links() }}</div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
