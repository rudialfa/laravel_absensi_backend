@extends('layouts.app')

@section('title', 'Notifikasi')

@section('main')
    @php
        $tz = 'Asia/Jakarta';
        $icons = [
            'chat' => ['fa-comment', 'primary'],
            'approval' => ['fa-check', 'success'],
            'reminder' => ['fa-bell', 'warning'],
            'attendance' => ['fa-user-clock', 'info'],
            'schedule' => ['fa-calendar-alt', 'info'],
            'holiday' => ['fa-umbrella-beach', 'info'],
            'report' => ['fa-chart-bar', 'primary'],
            'payroll' => ['fa-money-bill-wave', 'success'],
            'school' => ['fa-school', 'primary'],
            'subscription' => ['fa-receipt', 'danger'],
            'system' => ['fa-cog', 'secondary'],
        ];
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Notifikasi</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Notifikasi</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="card">
                    <div class="card-header">
                        <h4>Kotak Masuk <span class="badge badge-danger ml-1">{{ $unread }} belum dibaca</span></h4>
                        <div class="card-header-action">
                            @if ($unread)
                                <form method="POST" action="{{ route('superadmin.header.inbox.read-all') }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary"><i class="fas fa-check-double"></i>
                                        Tandai semua dibaca</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.header.inbox') }}" class="form-row">
                            <div class="col-md-4 mb-2">
                                <select name="filter" class="form-control" onchange="this.form.submit()">
                                    <option value="all" @selected($filter === 'all')>Semua</option>
                                    <option value="unread" @selected($filter === 'unread')>Belum dibaca</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <select name="category" class="form-control" onchange="this.form.submit()">
                                    <option value="">Semua kategori</option>
                                    @foreach ($categories as $c)
                                        <option value="{{ $c }}" @selected($category === $c)>{{ ucfirst($c) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            @forelse ($items as $n)
                                @php [$icon, $color] = $icons[$n->category] ?? ['fa-bell', 'secondary']; @endphp
                                <div class="list-group-item d-flex align-items-start {{ $n->read_at ? '' : 'bg-light' }}">
                                    <span
                                        class="rounded-circle bg-{{ $color }} text-white d-flex align-items-center justify-content-center mr-3 flex-shrink-0"
                                        style="width:40px;height:40px">
                                        <i class="fas {{ $icon }}"></i>
                                    </span>
                                    <div class="flex-grow-1" style="min-width:0">
                                        <div class="d-flex justify-content-between flex-wrap">
                                            <b class="{{ $n->read_at ? '' : 'text-dark' }}">{{ $n->title }}</b>
                                            <span
                                                class="text-muted small">{{ $n->created_at->timezone($tz)->format('d M Y H:i') }}</span>
                                        </div>
                                        <div class="text-small text-muted" style="white-space:pre-line">{{ $n->body }}
                                        </div>
                                        <div class="mt-1">
                                            <span class="badge badge-light">{{ ucfirst($n->category) }}</span>
                                            @if (!$n->read_at)
                                                <form method="POST"
                                                    action="{{ route('superadmin.header.inbox.read', $n->id) }}"
                                                    class="d-inline">
                                                    @csrf
                                                    <button class="btn btn-link btn-sm p-0 ml-2">Tandai dibaca</button>
                                                </form>
                                            @else
                                                <span class="text-muted small ml-2"><i class="fas fa-check"></i>
                                                    dibaca</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="p-5 text-center text-muted">
                                    <i class="far fa-bell-slash fa-2x mb-2"></i>
                                    <div>Tidak ada notifikasi{{ $filter === 'unread' ? ' yang belum dibaca' : '' }}.</div>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    @if ($items->hasPages())
                        <div class="card-footer d-flex justify-content-end">{{ $items->links() }}</div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
