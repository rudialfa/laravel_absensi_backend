@extends('layouts.app')

@section('title', 'Voucher / Diskon')

@section('main')
    @php
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $fmtValue = fn($d) => $d->discount_type === 'percent'
            ? rtrim(rtrim(number_format((float) $d->discount_value, 2, ',', '.'), '0'), ',') . '%'
            : $rupiah($d->discount_value);
        $fmtDate = fn($v) => $v ? \Carbon\Carbon::parse($v)->format('d M Y') : null;
        $statusOptions = [
            'running'   => 'Sedang berlaku',
            'scheduled' => 'Belum mulai',
            'expired'   => 'Kedaluwarsa',
            'exhausted' => 'Kuota habis',
            'inactive'  => 'Nonaktif',
        ];
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Voucher / Diskon</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.discounts.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Voucher</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Voucher</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @foreach ($warnings as $warning)
                    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> {{ $warning }}</div>
                @endforeach

                <h2 class="section-title">Voucher Langganan</h2>
                <p class="section-lead">Kode diskon yang bisa dipakai tenant saat membayar langganan. Voucher tanpa kode = diskon otomatis.</p>

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.discounts.index') }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Nama atau kode">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <select name="status" class="form-control">
                                        <option value="">Semua Status</option>
                                        @foreach ($statusOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="kind" class="form-control">
                                        <option value="">Semua Jenis</option>
                                        <option value="code" @selected(request('kind') === 'code')>Pakai kode</option>
                                        <option value="auto" @selected(request('kind') === 'auto')>Otomatis</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <select name="plan_id" class="form-control">
                                        <option value="">Semua Paket</option>
                                        <option value="all" @selected(request('plan_id') === 'all')>Berlaku semua paket</option>
                                        @foreach ($plans as $plan)
                                            <option value="{{ $plan->id }}" @selected((string) request('plan_id') === (string) $plan->id)>Khusus {{ $plan->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-1 mb-3">
                                    <button class="btn btn-primary btn-block" title="Cari"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Voucher</th>
                                        <th>Potongan</th>
                                        <th>Paket</th>
                                        <th>Masa Berlaku</th>
                                        <th>Pemakaian</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width:190px">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($discounts as $discount)
                                        @php $state = \App\Http\Controllers\Web\SuperAdmin\SuperAdminDiscountController::state($discount); @endphp
                                        <tr>
                                            <td>
                                                <a href="{{ route('superadmin.discounts.show', $discount->id) }}" class="font-weight-bold">{{ $discount->name }}</a>
                                                <div>
                                                    @if ($discount->code)
                                                        <code>{{ $discount->code }}</code>
                                                    @else
                                                        <span class="badge badge-light">Otomatis</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <strong>{{ $fmtValue($discount) }}</strong>
                                                @if ($discount->discount_type === 'percent' && $discount->max_discount_amount)
                                                    <div class="text-small text-muted">maks {{ $rupiah($discount->max_discount_amount) }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $discount->plan->name ?? 'Semua paket' }}</td>
                                            <td class="text-small">
                                                @if ($discount->valid_from || $discount->valid_until)
                                                    {{ $fmtDate($discount->valid_from) ?? 'sekarang' }} – {{ $fmtDate($discount->valid_until) ?? 'seterusnya' }}
                                                @else
                                                    Tanpa batas
                                                @endif
                                            </td>
                                            <td>
                                                {{ $discount->used_count }}{{ $discount->max_usage ? ' / ' . $discount->max_usage : '' }}
                                                @if ($discount->max_usage)
                                                    <div class="progress mt-1" style="height:4px">
                                                        <div class="progress-bar" style="width: {{ min(100, round($discount->used_count / $discount->max_usage * 100)) }}%"></div>
                                                    </div>
                                                @endif
                                            </td>
                                            <td><span class="badge badge-{{ $state['badge'] }}">{{ $state['label'] }}</span></td>
                                            <td class="text-center">
                                                <a href="{{ route('superadmin.discounts.show', $discount->id) }}" class="btn btn-sm btn-info" title="Detail"><i class="fas fa-eye"></i></a>
                                                <a href="{{ route('superadmin.discounts.edit', $discount->id) }}" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                                <a href="{{ route('superadmin.discounts.create', ['from' => $discount->id]) }}" class="btn btn-sm btn-light" title="Salin"><i class="fas fa-copy"></i></a>
                                                <form action="{{ route('superadmin.discounts.toggle-active', $discount->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="btn btn-sm {{ $discount->is_active ? 'btn-warning' : 'btn-success' }}"
                                                        title="{{ $discount->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                        <i class="fas fa-power-off"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">Belum ada voucher</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($discounts->hasPages())
                        <div class="card-footer text-right">{{ $discounts->links() }}</div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
