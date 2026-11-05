@extends('layouts.app')

@section('title', 'Keuangan Sekolah')

@section('main')
    @php
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
        $tabUrl = fn($key, $extra = []) => route(
            'superadmin.school.finance.index',
            array_filter(
                ['tab' => $key, 'company_id' => request('company_id')] + $extra,
                fn($v) => $v !== null && $v !== '',
            ),
        );
        $hasFilter = collect(request()->except(['tab', 'page']))
            ->filter(fn($v) => $v !== null && $v !== '')
            ->isNotEmpty();
        $studentUrl = fn($id) => Route::has('superadmin.school.students.show')
            ? route('superadmin.school.students.show', $id)
            : null;
        $delBtn = fn(
            $tabKey,
            $id,
            $label,
            $note = '',
        ) => '<button type="button" class="btn btn-sm btn-outline-danger" title="Hapus" data-toggle="modal" data-target="#schoolActionModal"' .
            ' data-action="' .
            e(route('superadmin.school.finance.destroy', [$tabKey, $id])) .
            '" data-label="' .
            e('Hapus ' . $label) .
            '" data-note="' .
            e($note) .
            '">
<i class="fas fa-trash"></i></button>';
        $periodOptions = collect(range(0, 11))->mapWithKeys(
            fn($i) => [
                now()->startOfMonth()->subMonthsNoOverflow($i)->format('Y-n') => now()
                    ->startOfMonth()
                    ->subMonthsNoOverflow($i)
                    ->translatedFormat('F Y'),
            ],
        );
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Keuangan Sekolah</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Monitoring Sekolah</div>
                    <div class="breadcrumb-item">Keuangan Sekolah</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">
                    Tagihan murid (SPP, buku, asrama, dll) dan pembayarannya di tiap sekolah. Ini uang milik sekolah, bukan
                    langganan aplikasi.
                    Superadmin memantau dan mengoreksi data yang salah catat.
                </p>

                <ul class="nav nav-tabs mb-3">
                    @foreach ($tabs as $key => $label)
                        <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                                href="{{ $tabUrl($key) }}">{{ $label }}</a></li>
                    @endforeach
                </ul>

                {{-- ================= RINGKASAN ================= --}}
                @php
                    $cards = match ($tab) {
                        'recap' => [
                            ['Total Tagihan', $rupiah($totals['billed']), 'primary', 'fa-file-invoice', null],
                            ['Sudah Dibayar', $rupiah($totals['paid']), 'success', 'fa-check-circle', null],
                            [
                                'Piutang',
                                $rupiah($totals['outstanding']),
                                $totals['outstanding'] ? 'warning' : 'success',
                                'fa-hourglass-half',
                                ['tab' => 'bills', 'status' => 'unpaid'],
                            ],
                            [
                                'Lewat Jatuh Tempo',
                                $rupiah($totals['overdue']),
                                $totals['overdue'] ? 'danger' : 'success',
                                'fa-exclamation-triangle',
                                ['tab' => 'bills', 'status' => 'overdue'],
                            ],
                        ],
                        'bills' => [
                            ['Tagihan', number_format((int) $summary->total), 'primary', 'fa-file-invoice', null],
                            [
                                'Total / Terbayar',
                                $rupiah($summary->billed) . ' / ' . $rupiah($summary->paid),
                                'info',
                                'fa-coins',
                                null,
                            ],
                            [
                                'Lunas',
                                number_format((int) $summary->lunas),
                                'success',
                                'fa-check',
                                ['tab' => 'bills', 'status' => 'lunas'],
                            ],
                            [
                                'Lewat Jatuh Tempo',
                                number_format((int) $summary->overdue),
                                $summary->overdue ? 'danger' : 'success',
                                'fa-exclamation-triangle',
                                ['tab' => 'bills', 'status' => 'overdue'],
                            ],
                        ],
                        'payments' => [
                            ['Transaksi', number_format((int) $summary->total), 'primary', 'fa-receipt', null],
                            ['Total Masuk', $rupiah($summary->amount), 'success', 'fa-coins', null],
                            [
                                'Tunai',
                                $rupiah($summary->cash),
                                'info',
                                'fa-money-bill',
                                ['tab' => 'payments', 'method' => 'cash'],
                            ],
                            [
                                'Transfer',
                                $rupiah($summary->transfer),
                                'info',
                                'fa-university',
                                ['tab' => 'payments', 'method' => 'transfer'],
                            ],
                        ],
                        default => [],
                    };
                @endphp
                @if ($cards)
                    <div class="row">
                        @foreach ($cards as [$label, $value, $color, $icon, $filter])
                            <div class="col-6 col-lg-3">
                                @if ($filter)
                                    <a href="{{ $tabUrl($filter['tab'], \Illuminate\Support\Arr::except($filter, 'tab')) }}"
                                        class="text-decoration-none">
                                @endif
                                <div class="card card-statistic-1">
                                    <div class="card-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i>
                                    </div>
                                    <div class="card-wrap">
                                        <div class="card-header">
                                            <h4>{{ $label }}</h4>
                                        </div>
                                        <div class="card-body" style="font-size:16px">{{ $value }}</div>
                                    </div>
                                </div>
                                @if ($filter)
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="card">
                    {{-- ================= FILTER ================= --}}
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.school.finance.index') }}">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control"
                                        onchange="['class_id','bill_type_id'].forEach(n => this.form[n] && (this.form[n].value='')); this.form.submit()">
                                        <option value="">Semua Sekolah</option>
                                        @foreach ($schools as $s)
                                            <option value="{{ $s->id }}" @selected((string) request('company_id') === (string) $s->id)>
                                                {{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                @if (in_array($tab, ['bills', 'payments'], true))
                                    <div class="col-md-2 mb-3">
                                        <select name="class_id" class="form-control" @disabled($classOptions->isEmpty())>
                                            <option value="">
                                                {{ $classOptions->isEmpty() ? 'Pilih sekolah dulu' : 'Semua Kelas' }}
                                            </option>
                                            @foreach ($classOptions as $c)
                                                <option value="{{ $c->id }}" @selected((string) request('class_id') === (string) $c->id)>
                                                    {{ $c->name }} · {{ $c->academic_year }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <select name="bill_type_id" class="form-control" @disabled($typeOptions->isEmpty())>
                                            <option value="">
                                                {{ $typeOptions->isEmpty() ? 'Pilih sekolah dulu' : 'Semua Jenis' }}
                                            </option>
                                            @foreach ($typeOptions as $t)
                                                <option value="{{ $t->id }}" @selected((string) request('bill_type_id') === (string) $t->id)>
                                                    {{ $t->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                @if ($tab === 'bills')
                                    <div class="col-md-2 mb-3">
                                        <select name="status" class="form-control">
                                            <option value="">Semua Status</option>
                                            <option value="unpaid" @selected(request('status') === 'unpaid')>Belum lunas (semua)</option>
                                            <option value="overdue" @selected(request('status') === 'overdue')>Lewat jatuh tempo</option>
                                            @foreach ($billStatus as $v => $l)
                                                <option value="{{ $v }}" @selected(request('status') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                            <option value="mismatch" @selected(request('status') === 'mismatch')>Status tidak sesuai
                                                pembayaran</option>
                                        </select>
                                    </div>
                                @elseif ($tab === 'payments')
                                    <div class="col-md-2 mb-3">
                                        <select name="method" class="form-control">
                                            <option value="">Semua Metode</option>
                                            @foreach ($methods as $v => $l)
                                                <option value="{{ $v }}" @selected(request('method') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif ($tab === 'types')
                                    <div class="col-md-2 mb-3">
                                        <select name="periode" class="form-control">
                                            <option value="">Semua Periode</option>
                                            @foreach ($periods as $v => $l)
                                                <option value="{{ $v }}" @selected(request('periode') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                @if ($tab !== 'recap')
                                    <div class="col-md mb-3">
                                        <input type="text" name="search" value="{{ request('search') }}"
                                            class="form-control"
                                            placeholder="{{ ['bills' => 'Nama / NIS murid, judul tagihan', 'payments' => 'No. kwitansi / nama murid', 'types' => 'Nama jenis tagihan'][$tab] }}">
                                    </div>
                                @endif

                                <div class="col-auto mb-3">
                                    <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                    <a href="{{ route('superadmin.school.finance.export', request()->query() + ['tab' => $tab]) }}"
                                        class="btn btn-outline-success" title="Unduh CSV"><i
                                            class="fas fa-file-excel"></i></a>
                                </div>
                            </div>

                            @if ($tab === 'bills')
                                <div class="form-row">
                                    <div class="col-md-3 mb-3">
                                        <select name="period" class="form-control form-control-sm">
                                            <option value="">Semua periode bulan</option>
                                            @foreach ($periodOptions as $v => $l)
                                                <option value="{{ $v }}" @selected(request('period') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @elseif ($tab === 'payments')
                                <div class="form-row">
                                    <div class="col-md-2 mb-3"><input type="date" name="date_from"
                                            value="{{ request('date_from') }}" class="form-control form-control-sm"
                                            title="Dari tanggal bayar"></div>
                                    <div class="col-md-2 mb-3"><input type="date" name="date_to"
                                            value="{{ request('date_to') }}" class="form-control form-control-sm"
                                            title="Sampai tanggal bayar"></div>
                                </div>
                            @endif

                            @if ($hasFilter)
                                <div class="mb-3"><a
                                        href="{{ route('superadmin.school.finance.index', ['tab' => $tab]) }}"
                                        class="text-small"><i class="fas fa-times"></i> Hapus filter</a></div>
                            @endif
                        </form>
                    </div>

                    {{-- ================= TABEL ================= --}}
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">

                                {{-- ---------- REKAP ---------- --}}
                                @if ($tab === 'recap')
                                    <thead>
                                        <tr>
                                            <th>Sekolah</th>
                                            <th class="text-right">Total Tagihan</th>
                                            <th class="text-right">Terbayar</th>
                                            <th style="width:150px">% Terbayar</th>
                                            <th class="text-right">Piutang</th>
                                            <th class="text-right">Lewat Jatuh Tempo</th>
                                            <th class="text-right">Masuk 30 Hari</th>
                                            <th>Bayar Terakhir</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($recap as $r)
                                            @php $color = $r['rate'] === null ? 'secondary' : ($r['rate'] >= 90 ? 'success' : ($r['rate'] >= 70 ? 'warning' : 'danger')); @endphp
                                            <tr>
                                                <td>
                                                    <a href="{{ $tabUrl('bills', ['company_id' => $r['id']]) }}"
                                                        class="font-weight-bold">{{ $r['name'] }}</a>
                                                    <div class="text-small text-muted">{{ number_format($r['bills']) }}
                                                        tagihan{{ $r['students_owing'] ? ' · ' . $r['students_owing'] . ' murid menunggak' : '' }}
                                                    </div>
                                                </td>
                                                <td class="text-right">{{ $rupiah($r['billed']) }}</td>
                                                <td class="text-right text-success">{{ $rupiah($r['paid']) }}</td>
                                                <td>
                                                    @if ($r['rate'] !== null)
                                                        <div class="d-flex align-items-center">
                                                            <div class="progress flex-fill mr-2" style="height:8px">
                                                                <div class="progress-bar bg-{{ $color }}"
                                                                    style="width:{{ min(100, $r['rate']) }}%"></div>
                                                            </div>
                                                            <span class="text-small">{{ $r['rate'] }}%</span>
                                                        </div>
                                                    @else
                                                        <span class="text-muted text-small">Belum ada tagihan</span>
                                                    @endif
                                                </td>
                                                <td class="text-right">
                                                    {{ $r['outstanding'] ? $rupiah($r['outstanding']) : '-' }}</td>
                                                <td class="text-right">
                                                    @if ($r['overdue'])
                                                        <a href="{{ $tabUrl('bills', ['company_id' => $r['id'], 'status' => 'overdue']) }}"
                                                            class="text-danger">{{ $rupiah($r['overdue_amount']) }}</a>
                                                        <div class="text-small text-muted">{{ $r['overdue'] }} tagihan
                                                        </div>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td class="text-right">
                                                    {{ $r['income30'] ? $rupiah($r['income30']) : '-' }}</td>
                                                <td class="text-small">{{ $fmtDate($r['last_paid']) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">Tidak ada sekolah.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    {{-- ---------- TAGIHAN ---------- --}}
                                @elseif ($tab === 'bills')
                                    <thead>
                                        <tr>
                                            <th>Murid</th>
                                            <th>Tagihan</th>
                                            <th>Jatuh Tempo</th>
                                            <th class="text-right">Nominal</th>
                                            <th class="text-right">Terbayar</th>
                                            <th>Status</th>
                                            <th class="text-right" style="width:90px">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $b)
                                            @php
                                                $paid = (float) $b->paid_total;
                                                $overdue =
                                                    $b->status !== 'lunas' && $b->due_date && $b->due_date->lt(today());
                                                $computed =
                                                    $paid <= 0
                                                        ? 'belum_lunas'
                                                        : ($paid >= (float) $b->amount
                                                            ? 'lunas'
                                                            : 'sebagian');
                                            @endphp
                                            <tr>
                                                <td>
                                                    @if ($b->student)
                                                        @if ($studentUrl($b->student->id))
                                                            <a
                                                                href="{{ $studentUrl($b->student->id) }}">{{ $b->student->name }}</a>
                                                        @else
                                                            {{ $b->student->name }}
                                                        @endif
                                                        <div class="text-small text-muted">
                                                            {{ $b->student->classRoom->name ?? 'tanpa kelas' }} ·
                                                            {{ $companyNames[$b->company_id] ?? '' }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <a
                                                        href="{{ route('superadmin.school.finance.show', $b->id) }}">{{ $b->title }}</a>
                                                    <div class="text-small text-muted">
                                                        {{ $b->billType->name ?? '-' }}{{ $b->period_month ? ' · ' . ($months[$b->period_month] ?? '') . ' ' . $b->period_year : '' }}
                                                    </div>
                                                </td>
                                                <td class="text-nowrap {{ $overdue ? 'text-danger' : '' }}">
                                                    {{ $fmtDate($b->due_date) }}
                                                    @if ($overdue)
                                                        <div class="text-small">lewat
                                                            {{ $b->due_date->diffInDays(today()) }} hari</div>
                                                    @endif
                                                </td>
                                                <td class="text-right">{{ $rupiah($b->amount) }}</td>
                                                <td class="text-right">{{ $paid ? $rupiah($paid) : '-' }}@if ($b->payments_count)
                                                        <div class="text-small text-muted">{{ $b->payments_count }}x bayar
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge badge-{{ $billBadge[$b->status] ?? 'light' }}">{{ $billStatus[$b->status] ?? $b->status }}</span>
                                                    @if ($computed !== $b->status)
                                                        <div class="text-small text-warning"
                                                            title="Seharusnya: {{ $billStatus[$computed] }}">status tidak
                                                            sesuai</div>
                                                    @endif
                                                </td>
                                                <td class="text-right text-nowrap">
                                                    <a href="{{ route('superadmin.school.finance.show', $b->id) }}"
                                                        class="btn btn-sm btn-outline-primary"><i
                                                            class="fas fa-eye"></i></a>
                                                    @if (!$b->payments_count)
                                                        {!! $delBtn('bills', $b->id, 'tagihan ' . $b->title . ' (' . ($b->student->name ?? '') . ')') !!}
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Tidak ada tagihan.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    {{-- ---------- PEMBAYARAN ---------- --}}
                                @elseif ($tab === 'payments')
                                    <thead>
                                        <tr>
                                            <th>Kwitansi</th>
                                            <th>Tanggal</th>
                                            <th>Murid</th>
                                            <th>Tagihan</th>
                                            <th class="text-right">Nominal</th>
                                            <th>Metode</th>
                                            <th>Dicatat</th>
                                            <th class="text-right" style="width:90px">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $p)
                                            @php $b = $p->studentBill; @endphp
                                            <tr>
                                                <td class="text-monospace text-small">{{ $p->receipt_number }}</td>
                                                <td class="text-nowrap">{{ $fmtDate($p->paid_at) }}</td>
                                                <td>
                                                    {{ $b->student->name ?? '-' }}
                                                    <div class="text-small text-muted">
                                                        {{ $b->student->classRoom->name ?? '' }} ·
                                                        {{ $companyNames[$b->company_id ?? 0] ?? '' }}</div>
                                                </td>
                                                <td>
                                                    @if ($b)
                                                        <a
                                                            href="{{ route('superadmin.school.finance.show', $b->id) }}">{{ $b->title }}</a>
                                                    @endif
                                                    <div class="text-small text-muted">{{ $b->billType->name ?? '' }}
                                                    </div>
                                                </td>
                                                <td class="text-right font-weight-bold">{{ $rupiah($p->amount) }}</td>
                                                <td>{{ $methods[$p->method] ?? $p->method }}</td>
                                                <td class="text-small">{{ $p->recordedBy->name ?? '-' }}<div
                                                        class="text-muted">
                                                        {{ optional($p->created_at)->format('d M H:i') }}</div>
                                                </td>
                                                <td class="text-right">
                                                    {!! $delBtn(
                                                        'payments',
                                                        $p->id,
                                                        'pembayaran ' . $p->receipt_number,
                                                        'Status tagihan akan dihitung ulang. Hapus hanya pembayaran yang tercatat dobel atau salah.',
                                                    ) !!}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">Tidak ada
                                                    pembayaran.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    {{-- ---------- JENIS TAGIHAN ---------- --}}
                                @else
                                    <thead>
                                        <tr>
                                            <th>Jenis Tagihan</th>
                                            <th>Sekolah</th>
                                            <th>Periode</th>
                                            <th class="text-right">Nominal Default</th>
                                            <th class="text-center">Dipakai</th>
                                            <th>Periode Terakhir</th>
                                            <th>Status</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $t)
                                            <tr class="{{ $t->is_active ? '' : 'text-muted' }}">
                                                <td class="font-weight-bold">{{ $t->name }}</td>
                                                <td class="text-small">{{ $companyNames[$t->company_id] ?? '-' }}</td>
                                                <td>{{ $periods[$t->periode] ?? $t->periode }}</td>
                                                <td class="text-right">{{ $rupiah($t->default_amount) }}</td>
                                                <td class="text-center">
                                                    @if ($t->bills_count)
                                                        <a
                                                            href="{{ $tabUrl('bills', ['company_id' => $t->company_id, 'bill_type_id' => $t->id]) }}">{{ number_format($t->bills_count) }}</a>
                                                        <div class="text-small text-muted">{{ $rupiah($t->billed_total) }}
                                                        </div>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-small">
                                                    @if ($t->last_period)
                                                        @php [$ly, $lm] = explode('-', $t->last_period); @endphp
                                                        {{ ($months[(int) $lm] ?? $lm) . ' ' . $ly }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td><span
                                                        class="badge badge-{{ $t->is_active ? 'success' : 'secondary' }}">{{ $t->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                                </td>
                                                <td class="text-right">
                                                    @if (!$t->bills_count)
                                                        {!! $delBtn('types', $t->id, 'jenis tagihan ' . $t->name) !!}
                                                    @else
                                                        <span class="text-small text-muted"
                                                            title="Sudah dipakai, tidak bisa dihapus">terpakai</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">Tidak ada jenis
                                                    tagihan.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                @endif
                            </table>
                        </div>
                    </div>

                    @if ($tab !== 'recap' && $items->hasPages())
                        <div class="card-footer text-right">{{ $items->links() }}</div>
                    @endif
                </div>
            </div>
        </section>
    </div>

    @include('pages.superadmin.school._delete')
@endsection
