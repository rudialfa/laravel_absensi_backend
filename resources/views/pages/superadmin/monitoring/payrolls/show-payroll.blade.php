@extends('layouts.app')

@section('title', 'Detail Payroll')

@section('main')
    @php
        $p = $payroll;
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $additions = $p->components->where('type', 'addition');
        $deductions = $p->components->where('type', 'deduction');
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.monitoring.payrolls.index', ['tab' => 'payrolls', 'company_id' => $p->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Detail Payroll</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.monitoring.payrolls.index') }}">Payroll</a>
                    </div>
                    <div class="breadcrumb-item">#{{ $p->id }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ $p->user->name ?? '-' }} · {{ $fmtDate($p->period_start) }} –
                                    {{ $fmtDate($p->period_end) }}</h4>
                                <div class="card-header-action">
                                    <span
                                        class="badge badge-{{ $badge[$p->status] ?? 'light' }}">{{ $statuses[$p->status] ?? $p->status }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <td>Gaji pokok</td>
                                        <td class="text-right">{{ $rupiah($p->base_salary) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Tunjangan</td>
                                        <td class="text-right text-success">+ {{ $rupiah($p->allowance) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Upah lembur</td>
                                        <td class="text-right text-success">+ {{ $rupiah($p->overtime_pay) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Bonus</td>
                                        <td class="text-right text-success">+ {{ $rupiah($p->bonus) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Potongan</td>
                                        <td class="text-right text-danger">− {{ $rupiah($p->deductions) }}</td>
                                    </tr>
                                    <tr class="font-weight-bold" style="font-size:16px">
                                        <td>Gaji bersih</td>
                                        <td class="text-right">{{ $rupiah($p->net_pay) }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Rincian Komponen ({{ $p->components->count() }})</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-striped mb-0">
                                    @forelse ($additions->concat($deductions) as $c)
                                        <tr>
                                            <td class="pl-4">
                                                {{ $c->name }}
                                                @if ($c->note)
                                                    <div class="text-small text-muted">{{ $c->note }}</div>
                                                @endif
                                            </td>
                                            <td
                                                class="pr-4 text-right {{ $c->type === 'addition' ? 'text-success' : 'text-danger' }}">
                                                {{ $c->type === 'addition' ? '+' : '−' }} {{ $rupiah($c->amount) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-center text-muted">Tidak ada komponen tambahan</td>
                                        </tr>
                                    @endforelse
                                </table>
                            </div>
                        </div>

                        @if ($loanPayments->isNotEmpty())
                            <div class="card">
                                <div class="card-header">
                                    <h4>Cicilan Pinjaman Dipotong dari Payroll Ini</h4>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm table-striped mb-0">
                                        @foreach ($loanPayments as $lp)
                                            <tr>
                                                <td class="pl-4"><a
                                                        href="{{ route('superadmin.monitoring.payrolls.show', ['loans', $lp->loan_id]) }}">Pinjaman
                                                        #{{ $lp->loan_id }}</a></td>
                                                <td class="pr-4 text-right">{{ $rupiah($lp->amount_paid) }}</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Karyawan</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Nama</th>
                                        <td class="text-right">{{ $p->user->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Jabatan</th>
                                        <td class="text-right">{{ $p->user->position ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Departemen</th>
                                        <td class="text-right">{{ $p->user->department ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Gaji pokok saat ini</th>
                                        <td class="text-right">{{ $rupiah($p->user->salary ?? 0) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Perusahaan</th>
                                        <td class="text-right">
                                            @if ($company)
                                                <a
                                                    href="{{ route('superadmin.tenants.show', $company->id) }}">{{ $company->name }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat</th>
                                        <td class="text-right">{{ $fmtDate($p->created_at) }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Payroll Sebelumnya</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-striped mb-0">
                                    @forelse ($history as $h)
                                        <tr>
                                            <td class="pl-3 text-small"><a
                                                    href="{{ route('superadmin.monitoring.payrolls.show', ['payrolls', $h->id]) }}">{{ \Carbon\Carbon::parse($h->period_start)->translatedFormat('M Y') }}</a>
                                            </td>
                                            <td class="text-right">{{ $rupiah($h->net_pay) }}</td>
                                            <td class="pr-3 text-right"><span
                                                    class="badge badge-{{ $badge[$h->status] ?? 'light' }}">{{ $statuses[$h->status] ?? $h->status }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-center text-muted">Belum ada</td>
                                        </tr>
                                    @endforelse
                                </table>
                            </div>
                        </div>

                        @if ($p->status === 'draft' && $loanPayments->isEmpty())
                            <div class="card border border-danger">
                                <div class="card-body">
                                    <h6 class="text-danger mb-1">Hapus Draft</h6>
                                    <p class="small text-muted">Komponennya ikut terhapus. HR bisa membuat ulang dari
                                        aplikasi.</p>
                                    <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                        data-target="#deleteModal"><i class="fas fa-trash"></i> Hapus</button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </div>

    @include('pages.superadmin.monitoring.payrolls._delete', [
        'action' => route('superadmin.monitoring.payrolls.destroy', ['payrolls', $p->id]),
        'label' => 'payroll draft',
    ])
@endsection
