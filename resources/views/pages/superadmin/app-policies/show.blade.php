@extends('layouts.app')

@section('title', $policy->title . ' v' . $policy->version)

@section('main')
    @php
        $tz = 'Asia/Jakarta';
        [$sLabel, $sBadge] = $policy->is_active
            ? ['Aktif', 'success']
            : ($policy->published_at
                ? ['Versi lama', 'light']
                : ['Draft', 'secondary']);
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.app-policies.index', ['type' => $policy->type]) }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ $typeLabels[$policy->type] ?? $policy->type }} v{{ $policy->version }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.app-policies.index') }}">Kebijakan
                            Aplikasi</a></div>
                    <div class="breadcrumb-item">v{{ $policy->version }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @if (!$policy->published_at)
                    <div class="alert alert-warning">
                        <i class="fas fa-pencil-alt"></i> Ini masih <b>draft</b> dan belum terlihat pengguna.
                        @if ($current)
                            Versi aktif sekarang: <a href="{{ route('superadmin.app-policies.show', $current->id) }}"
                                class="alert-link">v{{ $current->version }}</a>
                            @if ($diff)
                                — draft ini menambah {{ $diff['added'] }} baris dan menghapus/mengubah
                                {{ $diff['removed'] }} baris.
                            @endif
                        @endif
                    </div>
                @elseif (!$policy->is_active)
                    <div class="alert alert-light border"><i class="fas fa-history"></i> Versi lama — tidak lagi tampil di
                        aplikasi.</div>
                @endif

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4 style="font-size:20px">{{ $policy->title }}</h4>
                                <div class="card-header-action"><span
                                        class="badge badge-{{ $sBadge }}">{{ $sLabel }}</span></div>
                            </div>
                            <div class="card-body policy-content" style="font-size:15px;line-height:1.75">
                                {!! $html !!}
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Aksi</h4>
                            </div>
                            <div class="card-body">
                                @if (!$policy->is_active)
                                    <form action="{{ route('superadmin.app-policies.publish', $policy->id) }}"
                                        method="POST" class="mb-2"
                                        onsubmit="return confirm('{{ $policy->published_at ? 'Tayangkan ulang versi lama ini' : 'Publish versi ini' }}? Versi aktif sekarang akan dinonaktifkan.')">
                                        @csrf
                                        <button class="btn btn-success btn-block"><i class="fas fa-upload"></i>
                                            {{ $policy->published_at ? 'Tayangkan Ulang' : 'Publish' }}</button>
                                    </form>
                                @endif
                                @if (!$policy->published_at)
                                    <a href="{{ route('superadmin.app-policies.edit', $policy->id) }}"
                                        class="btn btn-outline-info btn-block mb-2"><i class="fas fa-pen"></i> Edit
                                        Draft</a>
                                @endif
                                <a href="{{ route('superadmin.app-policies.create', ['from' => $policy->id]) }}"
                                    class="btn btn-outline-secondary btn-block mb-2"><i class="fas fa-code-branch"></i> Buat
                                    Versi Baru dari Ini</a>
                                @if (!$policy->published_at)
                                    <form action="{{ route('superadmin.app-policies.destroy', $policy->id) }}"
                                        method="POST" onsubmit="return confirm('Hapus draft ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger btn-block"><i class="fas fa-trash"></i> Hapus
                                            Draft</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Info</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Jenis</th>
                                        <td class="text-right">{{ $typeLabels[$policy->type] ?? $policy->type }}</td>
                                    </tr>
                                    <tr>
                                        <th>Versi</th>
                                        <td class="text-right">{{ $policy->version }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat</th>
                                        <td class="text-right">
                                            {{ optional($policy->created_at)->timezone($tz)->format('d M Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Tayang</th>
                                        <td class="text-right">
                                            {{ $policy->published_at ? $policy->published_at->timezone($tz)->format('d M Y H:i') : '-' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Oleh</th>
                                        <td class="text-right">{{ $policy->publishedBy->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Panjang</th>
                                        <td class="text-right">
                                            {{ number_format(str_word_count(strip_tags($policy->content))) }} kata</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Riwayat Versi</h4>
                            </div>
                            <div class="card-body">
                                @foreach ($versions as $v)
                                    <div
                                        class="d-flex justify-content-between mb-1 {{ $v->id === $policy->id ? 'font-weight-bold' : '' }}">
                                        <a
                                            href="{{ route('superadmin.app-policies.show', $v->id) }}">v{{ $v->version }}</a>
                                        <span>
                                            <span
                                                class="text-small text-muted">{{ optional($v->published_at ?? $v->created_at)->timezone($tz)->format('d M Y') }}</span>
                                            @if ($v->is_active)
                                                <span class="badge badge-success">aktif</span>
                                            @elseif (!$v->published_at)
                                                <span class="badge badge-secondary">draft</span>
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
