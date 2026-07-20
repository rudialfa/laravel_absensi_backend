@extends('layouts.app')

@section('title', 'Voucher / Diskon')

@push('style')
    <link rel="stylesheet" href="{{ asset('backend/asset/library/selectric/public/selectric.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Voucher / Diskon</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.discounts.create') }}" class="btn btn-primary">Add New</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item">Voucher / Diskon</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <h2 class="section-title">Voucher / Diskon Langganan</h2>
                <p class="section-lead">
                    Kelola voucher/kode diskon untuk paket langganan tenant.
                </p>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Semua Voucher</h4>
                            </div>
                            <div class="card-body">

                                <div class="float-right">
                                    <form method="GET" action="{{ route('superadmin.discounts.index') }}"
                                        class="form-inline">
                                        <select name="status" class="form-control mr-2" onchange="this.form.submit()">
                                            <option value="">Semua status</option>
                                            <option value="active" @selected(request('status') === 'active')>Aktif</option>
                                            <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                                        </select>
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Cari nama / kode"
                                                name="search" value="{{ request('search') }}">
                                            <div class="input-group-append">
                                                <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <div class="clearfix mb-3"></div>

                                <div class="table-responsive">
                                    <table class="table-striped table">
                                        <thead>
                                            <tr>
                                                <th>Nama</th>
                                                <th>Kode</th>
                                                <th>Tipe</th>
                                                <th>Nilai</th>
                                                <th>Plan</th>
                                                <th>Masa Berlaku</th>
                                                <th>Pemakaian</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($discounts as $discount)
                                                <tr>
                                                    <td>{{ $discount->name }}</td>
                                                    <td><code>{{ $discount->code ?? '-' }}</code></td>
                                                    <td>{{ $discount->discount_type === 'percent' ? 'Persen' : 'Nominal' }}
                                                    </td>
                                                    <td>
                                                        {{ $discount->discount_type === 'percent' ? $discount->discount_value . '%' : 'Rp' . number_format($discount->discount_value, 0, ',', '.') }}
                                                    </td>
                                                    <td>{{ $discount->plan->name ?? 'Semua Paket' }}</td>
                                                    <td>
                                                        @if ($discount->valid_from || $discount->valid_until)
                                                            {{ optional($discount->valid_from)->format('d/m/Y') ?? '-' }}
                                                            &ndash;
                                                            {{ optional($discount->valid_until)->format('d/m/Y') ?? '-' }}
                                                        @else
                                                            Tidak dibatasi
                                                        @endif
                                                    </td>
                                                    <td>{{ $discount->used_count }}{{ $discount->max_usage ? ' / ' . $discount->max_usage : '' }}
                                                    </td>
                                                    <td>
                                                        <div
                                                            class="badge badge-{{ $discount->is_active ? 'success' : 'secondary' }}">
                                                            {{ $discount->is_active ? 'Aktif' : 'Nonaktif' }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex justify-content-center">
                                                            <a href="{{ route('superadmin.discounts.edit', $discount->id) }}"
                                                                class="btn btn-sm btn-info btn-icon">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>

                                                            <form
                                                                action="{{ route('superadmin.discounts.toggle-active', $discount->id) }}"
                                                                method="POST" class="ml-2">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button class="btn btn-sm btn-warning btn-icon">
                                                                    <i class="fas fa-power-off"></i>
                                                                    {{ $discount->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                                </button>
                                                            </form>

                                                            <form
                                                                action="{{ route('superadmin.discounts.destroy', $discount->id) }}"
                                                                method="POST" class="ml-2">
                                                                <input type="hidden" name="_method" value="DELETE" />
                                                                <input type="hidden" name="_token"
                                                                    value="{{ csrf_token() }}" />
                                                                <button
                                                                    class="btn btn-sm btn-danger btn-icon confirm-delete">
                                                                    <i class="fas fa-times"></i> Delete
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="9" class="text-center">Belum ada voucher</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $discounts->withQueryString()->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
@endpush
