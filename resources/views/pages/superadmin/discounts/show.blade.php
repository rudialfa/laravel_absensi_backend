@extends('layouts.app')

@section('title', 'Voucher ' . $discount->name)

@section('main')
    @php
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $fmtDate = fn($v) => $v ? \Carbon\Carbon::parse($v)->format('d M Y H:i') : null;
        $value =
            $discount->discount_type === 'percent'
                ? rtrim(rtrim(number_format((float) $discount->discount_value, 2, ',', '.'), '0'), ',') . '%'
                : $rupiah($discount->discount_value);
        $invLabel = [
            'paid' => 'Lunas',
            'pending' => 'Menunggu',
            'expired' => 'Kedaluwarsa',
            'cancelled' => 'Dibatalkan',
        ];
        $invBadge = ['paid' => 'success', 'pending' => 'warning', 'expired' => 'secondary', 'cancelled' => 'danger'];
        $paid = $stats['paid'] ?? null;
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.discounts.index') }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Detail Voucher</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.discounts.edit', $discount->id) }}" class="btn btn-primary"><i
                            class="fas fa-edit"></i> Edit</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.discounts.index') }}">Voucher</a></div>
                    <div class="breadcrumb-item">{{ $discount->name }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-body text-center pt-4">
                                <div class="text-muted small mb-1">{{ $discount->name }}</div>
                                @if ($discount->code)
                                    <div class="h4 mb-2"><code id="voucher-code">{{ $discount->code }}</code>
                                        <button type="button" class="btn btn-sm btn-light ml-1" id="copy-code"
                                            title="Salin kode"><i class="fas fa-copy"></i></button>
                                    </div>
                                @else
                                    <div class="h5 mb-2"><span class="badge badge-light">Diskon otomatis</span></div>
                                @endif
                                <div class="h2 text-primary mb-1">{{ $value }}</div>
                                @if ($discount->discount_type === 'percent' && $discount->max_discount_amount)
                                    <div class="text-muted">maksimal {{ $rupiah($discount->max_discount_amount) }}</div>
                                @endif
                                <div class="mt-2"><span
                                        class="badge badge-{{ $state['badge'] }}">{{ $state['label'] }}</span></div>
                            </div>
                            <div class="card-body border-top">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Paket</th>
                                        <td class="text-right">
                                            @if ($discount->plan)
                                                <a
                                                    href="{{ route('superadmin.plans.show', $discount->plan_id) }}">{{ $discount->plan->name }}</a>
                                            @else
                                                Semua paket
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Mulai</th>
                                        <td class="text-right">{{ $fmtDate($discount->valid_from) ?? 'Langsung' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Berakhir</th>
                                        <td class="text-right">{{ $fmtDate($discount->valid_until) ?? 'Tanpa batas' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Pemakaian</th>
                                        <td class="text-right">{{ $discount->used_count }} /
                                            {{ $discount->max_usage ?: '∞' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat</th>
                                        <td class="text-right">{{ optional($discount->created_at)->format('d M Y') }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="card-footer bg-whitesmoke">
                                <a href="{{ route('superadmin.discounts.edit', $discount->id) }}"
                                    class="btn btn-primary btn-block"><i class="fas fa-edit"></i> Edit</a>
                                <a href="{{ route('superadmin.discounts.create', ['from' => $discount->id]) }}"
                                    class="btn btn-outline-primary btn-block"><i class="fas fa-copy"></i> Salin Jadi Voucher
                                    Baru</a>
                                <form action="{{ route('superadmin.discounts.toggle-active', $discount->id) }}"
                                    method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        class="btn btn-block {{ $discount->is_active ? 'btn-warning' : 'btn-success' }}">
                                        <i class="fas fa-power-off"></i>
                                        {{ $discount->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                                @if ($canDelete)
                                    <form action="{{ route('superadmin.discounts.destroy', $discount->id) }}"
                                        method="POST"
                                        onsubmit="return confirm('Hapus voucher {{ addslashes($discount->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger btn-block"><i class="fas fa-trash"></i>
                                            Hapus</button>
                                    </form>
                                @else
                                    <small class="text-muted d-block text-center mt-2">Sudah dipakai, tidak bisa
                                        dihapus.</small>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-8">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card card-statistic-1">
                                    <div class="card-icon bg-primary"><i class="fas fa-ticket-alt"></i></div>
                                    <div class="card-wrap">
                                        <div class="card-header">
                                            <h4>Dipakai (lunas)</h4>
                                        </div>
                                        <div class="card-body">{{ $paid->total ?? 0 }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card card-statistic-1">
                                    <div class="card-icon bg-danger"><i class="fas fa-percentage"></i></div>
                                    <div class="card-wrap">
                                        <div class="card-header">
                                            <h4>Total Potongan</h4>
                                        </div>
                                        <div class="card-body" style="font-size:16px">
                                            {{ $rupiah($paid->discount_sum ?? 0) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card card-statistic-1">
                                    <div class="card-icon bg-success"><i class="fas fa-money-bill-wave"></i></div>
                                    <div class="card-wrap">
                                        <div class="card-header">
                                            <h4>Pendapatan</h4>
                                        </div>
                                        <div class="card-body" style="font-size:16px">{{ $rupiah($paid->amount_sum ?? 0) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Riwayat Pemakaian ({{ $usage->total() }} invoice)</h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Invoice</th>
                                                <th>Tenant</th>
                                                <th>Paket</th>
                                                <th class="text-right">Potongan</th>
                                                <th class="text-right">Dibayar</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($usage as $inv)
                                                <tr>
                                                    <td>
                                                        <a
                                                            href="{{ route('superadmin.invoices.show', $inv->id) }}">{{ $inv->invoice_number }}</a>
                                                        <div class="text-small text-muted">
                                                            {{ optional($inv->issued_at)->format('d M Y') }}</div>
                                                    </td>
                                                    <td>
                                                        @if ($inv->company)
                                                            <a
                                                                href="{{ route('superadmin.tenants.show', $inv->company_id) }}">{{ $inv->company->name }}</a>
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>{{ $inv->plan->name ?? '-' }}</td>
                                                    <td class="text-right text-danger">
                                                        −{{ $rupiah($inv->discount_amount) }}</td>
                                                    <td class="text-right">{{ $rupiah($inv->total_amount) }}</td>
                                                    <td><span
                                                            class="badge badge-{{ $invBadge[$inv->status] ?? 'secondary' }}">{{ $invLabel[$inv->status] ?? ucfirst($inv->status) }}</span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">Voucher ini
                                                        belum pernah dipakai</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @if ($usage->hasPages())
                                <div class="card-footer text-right">{{ $usage->links() }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('copy-code')?.addEventListener('click', function() {
            navigator.clipboard?.writeText(document.getElementById('voucher-code').textContent.trim())
                .then(() => this.innerHTML = '<i class="fas fa-check"></i>');
        });
    </script>
@endpush
