@extends('layouts.app')

@section('title', 'Global Search')

@push('style')
    <style>
        .search-chips .btn { margin: 0 6px 6px 0; }
        mark { background: #fff3cd; padding: 0 2px; border-radius: 2px; }
    </style>
@endpush

@section('main')
    @php
        $link = fn(string $route, ...$params) => \Illuminate\Support\Facades\Route::has($route) ? route($route, $params) : null;
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

        // Tandai kata kunci di hasil (aman dari XSS: teks di-escape dulu)
        $hl = function ($text) use ($keyword) {
            $text = (string) $text;
            if ($keyword === '' || $text === '') {
                return e($text);
            }
            // Pecah teks asli di posisi kata kunci, escape tiap potongan, lalu bungkus yang cocok
            $parts = preg_split('/(' . preg_quote($keyword, '/') . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            $html = '';
            foreach ($parts as $i => $part) {
                $html .= $i % 2 ? '<mark>' . e($part) . '</mark>' : e($part);
            }
            return $html;
        };

        $typeLabels = [
            'company'   => 'Perusahaan',
            'school'    => 'Sekolah',
            'pesantren' => 'Pesantren',
            'tpq'       => 'TPQ',
            'kuliah'    => 'Kampus / Kuliah',
        ];
        $invBadge = ['paid' => 'success', 'pending' => 'warning', 'expired' => 'secondary', 'cancelled' => 'danger'];
        $invLabel = ['paid' => 'Lunas', 'pending' => 'Menunggu', 'expired' => 'Kedaluwarsa', 'cancelled' => 'Dibatalkan'];
        $ticketBadge = ['open' => 'info', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'secondary'];
        $ticketLabel = ['open' => 'Baru', 'in_progress' => 'Diproses', 'resolved' => 'Selesai', 'closed' => 'Ditutup'];

        $categories = [
            'companies' => ['label' => 'Tenant', 'icon' => 'fa-building', 'count' => $companies->count()],
            'users'     => ['label' => 'Pengguna', 'icon' => 'fa-users', 'count' => $users->count()],
            'invoices'  => ['label' => 'Invoice', 'icon' => 'fa-file-invoice-dollar', 'count' => $invoices->count()],
            'vas'       => ['label' => 'VA Payment', 'icon' => 'fa-money-check-alt', 'count' => $vas->count()],
            'tickets'   => ['label' => 'Tiket', 'icon' => 'fa-headset', 'count' => $tickets->count()],
        ];

        $show = fn(string $key) => !$only || $only === $key;
        $hasSearch = $keyword !== '' && !$tooShort;
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Global Search</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Global Search</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <h2 class="section-title">Cari Lintas Tenant</h2>
                <p class="section-lead">
                    Cari tenant, pengguna, invoice, nomor VA, atau tiket bantuan dari satu tempat.
                </p>

                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="{{ route('superadmin.global-search') }}">
                            @if ($only)
                                <input type="hidden" name="only" value="{{ $only }}">
                            @endif
                            <div class="input-group input-group-lg">
                                <input type="text" name="q" value="{{ $keyword }}" class="form-control"
                                    placeholder="Nama tenant, email/HP pengguna, no. invoice, no. VA, atau #nomor tiket"
                                    autofocus>
                                <div class="input-group-append">
                                    <button class="btn btn-primary"><i class="fas fa-search"></i> Cari</button>
                                </div>
                            </div>
                        </form>

                        @if ($tooShort)
                            <div class="text-warning mt-2">
                                <i class="fas fa-exclamation-triangle"></i> Ketik minimal {{ $minLength }} karakter.
                            </div>
                        @endif

                        @if ($hasSearch)
                            <div class="search-chips mt-3">
                                <a href="{{ route('superadmin.global-search', ['q' => $keyword]) }}"
                                    class="btn btn-sm {{ !$only ? 'btn-primary' : 'btn-outline-primary' }}">
                                    Semua @if (!$only) <span class="badge badge-light">{{ $totalFound }}</span> @endif
                                </a>
                                @foreach ($categories as $key => $cat)
                                    <a href="{{ route('superadmin.global-search', ['q' => $keyword, 'only' => $key]) }}"
                                        class="btn btn-sm {{ $only === $key ? 'btn-primary' : 'btn-outline-primary' }}">
                                        <i class="fas {{ $cat['icon'] }}"></i> {{ $cat['label'] }}
                                        @if ($show($key))
                                            <span class="badge badge-light">{{ $cat['count'] }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                @if ($hasSearch && $totalFound === 0)
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-search fa-2x text-muted mb-3"></i>
                            <p class="mb-0">Tidak ada hasil untuk <strong>"{{ $keyword }}"</strong>.</p>
                        </div>
                    </div>
                @endif

                {{-- ── TENANT ─────────────────────────────────────── --}}
                @if ($hasSearch && $show('companies') && $companies->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-building"></i> Tenant ({{ $companies->count() }}{{ $companies->count() >= $limit ? '+' : '' }})</h4>
                            @if ($companies->count() >= $limit && ($url = $link('superadmin.tenants.index')))
                                <div class="card-header-action">
                                    <a href="{{ $url }}?name={{ urlencode($keyword) }}" class="btn btn-sm btn-primary">Lihat semua</a>
                                </div>
                            @endif
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>Jenis</th>
                                            <th>Kota</th>
                                            <th>Pengguna</th>
                                            <th>Status</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($companies as $company)
                                            <tr>
                                                <td>
                                                    {!! $hl($company->name) !!}
                                                    <br><small class="text-muted">{!! $hl($company->email) !!}</small>
                                                </td>
                                                <td>{{ $typeLabels[$company->type] ?? ucfirst($company->type ?? '-') }}</td>
                                                <td>{!! $company->city ? $hl($company->city) : '-' !!}</td>
                                                <td>{{ $company->users_count }}</td>
                                                <td>
                                                    <span class="badge badge-{{ $company->is_active ? 'success' : 'danger' }}">
                                                        {{ $company->is_active ? 'Aktif' : 'Disuspend' }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('superadmin.tenants.show', $company->id) }}"
                                                        class="btn btn-sm btn-info btn-icon">
                                                        <i class="fas fa-eye"></i> Detail
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ── PENGGUNA ───────────────────────────────────── --}}
                @if ($hasSearch && $show('users') && $users->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-users"></i> Pengguna ({{ $users->count() }}{{ $users->count() >= $limit ? '+' : '' }})</h4>
                            @if ($users->count() >= $limit && ($url = $link('superadmin.users.index')))
                                <div class="card-header-action">
                                    <a href="{{ $url }}?search={{ urlencode($keyword) }}" class="btn btn-sm btn-primary">Lihat semua</a>
                                </div>
                            @endif
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>No. HP</th>
                                            <th>Peran</th>
                                            <th>Tenant</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($users as $user)
                                            @php
                                                if ($user->role === 'superadmin') {
                                                    $userUrl = $link('superadmin.staff.edit', $user->id);
                                                    $userBtn = 'Edit Staff';
                                                } elseif ($url = $link('superadmin.users.show', $user->id)) {
                                                    $userUrl = $url;
                                                    $userBtn = 'Detail';
                                                } else {
                                                    $userUrl = $user->company_id ? $link('superadmin.tenants.show', $user->company_id) : null;
                                                    $userBtn = 'Lihat Tenant';
                                                }
                                            @endphp
                                            <tr>
                                                <td>
                                                    {!! $hl($user->name) !!}
                                                    <br><small class="text-muted">{!! $hl($user->email) !!}</small>
                                                </td>
                                                <td>{!! $user->phone ? $hl($user->phone) : '-' !!}</td>
                                                <td><span class="badge badge-light">{{ ucfirst($user->role) }}</span></td>
                                                <td>{{ $user->company->name ?? '-' }}</td>
                                                <td class="text-center">
                                                    @if ($userUrl)
                                                        <a href="{{ $userUrl }}" class="btn btn-sm btn-info btn-icon">
                                                            <i class="fas fa-eye"></i> {{ $userBtn }}
                                                        </a>
                                                    @else
                                                        <span class="text-muted small">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ── INVOICE ────────────────────────────────────── --}}
                @if ($hasSearch && $show('invoices') && $invoices->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-file-invoice-dollar"></i> Invoice ({{ $invoices->count() }}{{ $invoices->count() >= $limit ? '+' : '' }})</h4>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>No. Invoice</th>
                                            <th>Tenant</th>
                                            <th>Paket</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Diterbitkan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($invoices as $inv)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('superadmin.invoices.show', $inv->id) }}">{!! $hl($inv->invoice_number) !!}</a>
                                                </td>
                                                <td>{!! $hl($inv->company->name ?? '-') !!}</td>
                                                <td>{{ $inv->plan->name ?? '-' }}</td>
                                                <td>{{ $rupiah($inv->total_amount) }}</td>
                                                <td>
                                                    <span class="badge badge-{{ $invBadge[$inv->status] ?? 'secondary' }}">
                                                        {{ $invLabel[$inv->status] ?? ucfirst($inv->status) }}
                                                    </span>
                                                </td>
                                                <td>{{ optional($inv->issued_at)->format('d M Y') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ── VA PAYMENT ─────────────────────────────────── --}}
                @if ($hasSearch && $show('vas') && $vas->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-money-check-alt"></i> VA Payment ({{ $vas->count() }}{{ $vas->count() >= $limit ? '+' : '' }})</h4>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>No. VA</th>
                                            <th>Bank</th>
                                            <th>Tenant</th>
                                            <th>Invoice</th>
                                            <th>Nominal</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($vas as $va)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('superadmin.va-payments.show', $va->id) }}">{!! $hl($va->va_number) !!}</a>
                                                    <br><small class="text-muted">{!! $hl($va->va_name) !!}</small>
                                                </td>
                                                <td>{{ strtoupper($va->bank) }}</td>
                                                <td>{{ $va->company->name ?? '-' }}</td>
                                                <td>{{ $va->invoice->invoice_number ?? '-' }}</td>
                                                <td>{{ $rupiah($va->amount) }}</td>
                                                <td>
                                                    <span class="badge badge-{{ $invBadge[$va->status] ?? 'secondary' }}">
                                                        {{ $invLabel[$va->status] ?? ucfirst($va->status) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ── TIKET ──────────────────────────────────────── --}}
                @if ($hasSearch && $show('tickets') && $tickets->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-headset"></i> Tiket Bantuan ({{ $tickets->count() }}{{ $tickets->count() >= $limit ? '+' : '' }})</h4>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Subjek</th>
                                            <th>Pengirim</th>
                                            <th>Status</th>
                                            <th>Dibuat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($tickets as $ticket)
                                            <tr>
                                                <td>#{{ $ticket->id }}</td>
                                                <td>
                                                    <a href="{{ route('superadmin.support-tickets.show', $ticket->id) }}">{!! $hl(\Illuminate\Support\Str::limit($ticket->subject, 60)) !!}</a>
                                                </td>
                                                <td>
                                                    {!! $hl($ticket->user->name ?? '-') !!}
                                                    <br><small class="text-muted">{!! $hl($ticket->company->name ?? '-') !!}</small>
                                                </td>
                                                <td>
                                                    <span class="badge badge-{{ $ticketBadge[$ticket->status] ?? 'secondary' }}">
                                                        {{ $ticketLabel[$ticket->status] ?? ucfirst($ticket->status) }}
                                                    </span>
                                                </td>
                                                <td>{{ $ticket->created_at->format('d M Y H:i') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($hasSearch && $totalFound > 0)
                    <p class="text-muted small">
                        <i class="fas fa-info-circle"></i> Maksimal {{ $limit }} hasil per kategori. Pakai kata kunci yang
                        lebih spesifik atau klik kategori di atas untuk mempersempit pencarian.
                    </p>
                @endif
            </div>
        </section>
    </div>
@endsection
