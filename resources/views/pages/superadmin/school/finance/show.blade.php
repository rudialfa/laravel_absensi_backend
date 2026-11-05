@extends('layouts.app')

@section('title', 'Tagihan ' . $bill->title)

@section('main')
    @php
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $student = $bill->student;
        $studentUrl =
            $student && Route::has('superadmin.school.students.show')
                ? route('superadmin.school.students.show', $student->id)
                : null;
        $paid = (float) $bill->payments->sum('amount');
        $remaining = max(0, (float) $bill->amount - $paid);
        $overdue = $bill->status !== 'lunas' && $bill->due_date && $bill->due_date->lt(today());
        $pct = (float) $bill->amount > 0 ? min(100, round(($paid / (float) $bill->amount) * 100)) : 100;
        $receiptCounts = $bill->payments->countBy('receipt_number');
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.school.finance.index', ['tab' => 'bills', 'company_id' => $bill->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ $bill->title }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.school.finance.index', ['tab' => 'bills']) }}">Tagihan Murid</a>
                    </div>
                    <div class="breadcrumb-item">#{{ $bill->id }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @if ($computedStatus !== $bill->status)
                    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <i class="fas fa-exclamation-triangle"></i>
                            Status tersimpan <b>{{ $billStatus[$bill->status] ?? $bill->status }}</b>, tapi dari total
                            pembayaran seharusnya <b>{{ $billStatus[$computedStatus] }}</b>.
                        </div>
                        <form action="{{ route('superadmin.school.finance.resync', $bill->id) }}" method="POST"
                            class="mt-2 mt-md-0">
                            @csrf
                            @method('PATCH')
                            <button class="btn btn-light btn-sm"><i class="fas fa-sync"></i> Perbaiki status</button>
                        </form>
                    </div>
                @endif

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>Rincian Tagihan</h4>
                                <div class="card-header-action">
                                    <span
                                        class="badge badge-{{ $billBadge[$bill->status] ?? 'light' }}">{{ $billStatus[$bill->status] ?? $bill->status }}</span>
                                    @if ($overdue)
                                        <span class="badge badge-danger">lewat jatuh tempo</span>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-3">
                                    <div class="col">
                                        <div class="text-small text-muted">Nominal</div>
                                        <div class="h5 mb-0">{{ $rupiah($bill->amount) }}</div>
                                    </div>
                                    <div class="col">
                                        <div class="text-small text-muted">Terbayar</div>
                                        <div class="h5 mb-0 text-success">{{ $rupiah($paid) }}</div>
                                    </div>
                                    <div class="col">
                                        <div class="text-small text-muted">Sisa</div>
                                        <div class="h5 mb-0 {{ $remaining ? 'text-danger' : '' }}">
                                            {{ $rupiah($remaining) }}</div>
                                    </div>
                                </div>
                                <div class="progress mb-3" style="height:10px">
                                    <div class="progress-bar bg-success" style="width:{{ $pct }}%"></div>
                                </div>

                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th style="width:160px">Jenis</th>
                                        <td>{{ $bill->billType->name ?? '-' }} <span
                                                class="text-small text-muted">({{ $periods[$bill->billType->periode ?? ''] ?? '-' }})</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Periode</th>
                                        <td>{{ $bill->period_month ? ($months[$bill->period_month] ?? $bill->period_month) . ' ' . $bill->period_year : '-' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Jatuh tempo</th>
                                        <td class="{{ $overdue ? 'text-danger' : '' }}">
                                            {{ $fmtDate($bill->due_date) }}{{ $overdue ? ' (lewat ' . $bill->due_date->diffInDays(today()) . ' hari)' : '' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat oleh</th>
                                        <td>{{ $bill->createdBy->name ?? '-' }} ·
                                            {{ optional($bill->created_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Riwayat Pembayaran</h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Kwitansi</th>
                                                <th>Tanggal</th>
                                                <th class="text-right">Nominal</th>
                                                <th>Metode</th>
                                                <th>Dicatat</th>
                                                <th>Catatan</th>
                                                <th class="text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($bill->payments as $p)
                                                <tr>
                                                    <td class="text-monospace text-small">
                                                        {{ $p->receipt_number }}
                                                        @if (($receiptCounts[$p->receipt_number] ?? 0) > 1)
                                                            <span class="badge badge-warning">dobel</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $fmtDate($p->paid_at) }}</td>
                                                    <td class="text-right">{{ $rupiah($p->amount) }}</td>
                                                    <td>{{ $methods[$p->method] ?? $p->method }}</td>
                                                    <td class="text-small">{{ $p->recordedBy->name ?? '-' }}<div
                                                            class="text-muted">
                                                            {{ optional($p->created_at)->format('d M Y H:i') }}</div>
                                                    </td>
                                                    <td class="text-small">{{ $p->notes ?: '-' }}</td>
                                                    <td class="text-right">
                                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                            data-toggle="modal" data-target="#schoolActionModal"
                                                            data-action="{{ route('superadmin.school.finance.destroy', ['payments', $p->id]) }}"
                                                            data-label="Hapus pembayaran {{ $p->receipt_number }}"
                                                            data-note="Status tagihan akan dihitung ulang. Hapus hanya pembayaran yang tercatat dobel atau salah."><i
                                                                class="fas fa-trash"></i></button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted py-3">Belum ada
                                                        pembayaran.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Murid</h4>
                            </div>
                            <div class="card-body">
                                @if ($student)
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <th>Nama</th>
                                            <td class="text-right">
                                                @if ($studentUrl)
                                                    <a href="{{ $studentUrl }}">{{ $student->name }}</a>
                                                @else
                                                    {{ $student->name }}
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>NIS</th>
                                            <td class="text-right">{{ $student->nis }}</td>
                                        </tr>
                                        <tr>
                                            <th>Kelas</th>
                                            <td class="text-right">{{ $student->classRoom->name ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Sekolah</th>
                                            <td class="text-right">
                                                @if ($bill->company)
                                                    <a
                                                        href="{{ route('superadmin.tenants.show', $bill->company->id) }}">{{ $bill->company->name }}</a>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                    @if ($student->guardians->isNotEmpty())
                                        <hr>
                                        <div class="text-small text-muted mb-1">Wali</div>
                                        @foreach ($student->guardians as $g)
                                            <div>{{ $g->name }} <span
                                                    class="text-small text-muted">{{ $g->phone }}</span></div>
                                        @endforeach
                                    @endif
                                    @if ($student->trashed())
                                        <div class="alert alert-light border small mt-2 mb-0">Murid ini sudah dihapus.</div>
                                    @endif
                                @endif
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Tagihan Lain</h4>
                            </div>
                            <div class="card-body">
                                @forelse ($others as $o)
                                    @php $oPaid = (float) $o->payments->sum('amount'); @endphp
                                    <div class="d-flex justify-content-between mb-2">
                                        <div>
                                            <a
                                                href="{{ route('superadmin.school.finance.show', $o->id) }}">{{ \Illuminate\Support\Str::limit($o->title, 28) }}</a>
                                            <div class="text-small text-muted">{{ $fmtDate($o->due_date) }}</div>
                                        </div>
                                        <div class="text-right">
                                            <span
                                                class="badge badge-{{ $billBadge[$o->status] ?? 'light' }}">{{ $billStatus[$o->status] ?? $o->status }}</span>
                                            @if ($o->status !== 'lunas')
                                                <div class="text-small text-danger">
                                                    {{ $rupiah(max(0, (float) $o->amount - $oPaid)) }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">Tidak ada.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="card border border-danger">
                            <div class="card-body">
                                <h6 class="text-danger mb-1">Hapus Tagihan</h6>
                                @if ($bill->payments->isNotEmpty())
                                    <p class="small text-muted mb-0">Tagihan yang sudah ada pembayarannya tidak bisa
                                        dihapus. Hapus dulu pembayaran yang salah satu per satu.</p>
                                @else
                                    <p class="small text-muted">Untuk tagihan ganda atau salah buat. Belum ada pembayaran.
                                    </p>
                                    <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                        data-target="#schoolActionModal"
                                        data-action="{{ route('superadmin.school.finance.destroy', ['bills', $bill->id]) }}"
                                        data-label="Hapus tagihan {{ $bill->title }}"><i class="fas fa-trash"></i>
                                        Hapus</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    @include('pages.superadmin.school._delete')
@endsection
