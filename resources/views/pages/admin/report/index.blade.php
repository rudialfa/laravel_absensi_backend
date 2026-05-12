@extends('layouts.app')

@section('title', 'Management Report & Analytics')

@push('style')
    <link rel="stylesheet" href="{{ asset('backend/asset/library/selectric/public/selectric.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">

            {{-- HEADER --}}
            <div class="section-header">
                <h1>Management Report & Analytics</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item">Reports</div>
                    <div class="breadcrumb-item">All Reports</div>
                </div>
            </div>

            <div class="section-body">

                {{-- ALERT --}}
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <h2 class="section-title">Reports Summary</h2>
                <p class="section-lead">Report & Analytics untuk kebutuhan monitoring sistem.</p>

                <div class="row mt-4">
                    <div class="col-12">

                        {{-- ================= FILTER ================= --}}
                        <div class="card">
                            <div class="card-header">
                                <h4>Filter Reports</h4>
                            </div>
                            <div class="card-body">

                                <form method="GET" action="{{ route('admin.reports.index') }}" class="row">

                                    {{-- 🔥 PENTING --}}
                                    <input type="hidden" name="tab" value="{{ request('tab', 'absensi') }}">

                                    <div class="col-md-3">
                                        <label>User</label>
                                        <select name="user_id" class="form-control">
                                            <option value="">-- Semua User --</option>
                                            @foreach ($users as $u)
                                                <option value="{{ $u->id }}"
                                                    {{ request('user_id') == $u->id ? 'selected' : '' }}>
                                                    {{ $u->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <label>Company</label>
                                        <select name="company_id" class="form-control">
                                            <option value="">-- Semua Company --</option>
                                            @foreach ($companies as $c)
                                                <option value="{{ $c->id }}"
                                                    {{ request('company_id') == $c->id ? 'selected' : '' }}>
                                                    {{ $c->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <label>Tanggal</label>
                                        <input type="date" name="date" value="{{ request('date') }}"
                                            class="form-control">
                                    </div>

                                    <div class="col-md-3 mt-4">
                                        <button class="btn btn-primary mt-2">Filter</button>
                                        <a href="{{ route('admin.reports.index') }}"
                                            class="btn btn-secondary mt-2">Reset</a>
                                    </div>

                                </form>

                            </div>
                        </div>

                        {{-- ================= TAB MENU ================= --}}
                        <ul class="nav nav-tabs mb-4">

                            <li class="nav-item">
                                <a class="nav-link {{ request('tab', 'absensi') == 'absensi' ? 'active' : '' }}"
                                    href="?tab=absensi&user_id={{ request('user_id') }}&company_id={{ request('company_id') }}&date={{ request('date') }}">
                                    Absensi
                                </a>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link {{ request('tab') == 'permissions' ? 'active' : '' }}"
                                    href="?tab=permissions&user_id={{ request('user_id') }}&company_id={{ request('company_id') }}&date={{ request('date') }}">
                                    Permissions
                                </a>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link {{ request('tab') == 'payroll' ? 'active' : '' }}"
                                    href="?tab=payroll&user_id={{ request('user_id') }}&company_id={{ request('company_id') }}&date={{ request('date') }}">
                                    Payrolls
                                </a>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link {{ request('tab') == 'loan' ? 'active' : '' }}"
                                    href="?tab=loan&user_id={{ request('user_id') }}&company_id={{ request('company_id') }}&date={{ request('date') }}">
                                    Loans
                                </a>
                            </li>

                        </ul>

                        {{-- ================= CONTENT ================= --}}

                        {{-- ABSENSI --}}
                        @if (request('tab', 'absensi') == 'absensi')
                            <div class="card">
                                <div class="card-header">
                                    <h4>Absensi</h4>
                                </div>
                                <div class="card-body table-responsive">

                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>User</th>
                                                <th>Status</th>
                                                <th>Time In</th>
                                                <th>Time Out</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($absensi as $a)
                                                <tr>
                                                    <td>{{ $a->date }}</td>
                                                    <td>{{ $a->user->name }}</td>
                                                    <td>{{ ucfirst($a->status) }}</td>
                                                    <td>{{ $a->time_in }}</td>
                                                    <td>{{ $a->time_out }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center">Tidak ada data</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>

                                    {{ $absensi->appends(request()->query())->links() }}

                                </div>
                            </div>
                        @endif

                        {{-- PERMISSIONS --}}
                        @if (request('tab') == 'permissions')
                            <div class="card">
                                <div class="card-header">
                                    <h4>Permissions</h4>
                                </div>
                                <div class="card-body table-responsive">

                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>User</th>
                                                <th>Alasan</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($permissions as $p)
                                                <tr>
                                                    <td>{{ $p->date_permission }}</td>
                                                    <td>{{ $p->user->name }}</td>
                                                    <td>{{ $p->reason }}</td>
                                                    <td>{{ $p->is_approved ? 'Approved' : 'Pending' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center">Tidak ada data</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>

                                    {{ $permissions->appends(request()->query())->links() }}

                                </div>
                            </div>
                        @endif

                        {{-- PAYROLL --}}
                        @if (request('tab') == 'payroll')
                            <div class="card">
                                <div class="card-header">
                                    <h4>Payrolls</h4>
                                </div>
                                <div class="card-body table-responsive">

                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Period</th>
                                                <th>Net Pay</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($payrolls as $p)
                                                <tr>
                                                    <td>{{ $p->user->name }}</td>
                                                    <td>{{ $p->period_start }} → {{ $p->period_end }}</td>
                                                    <td>Rp {{ number_format($p->net_pay) }}</td>
                                                    <td>{{ ucfirst($p->status) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center">Tidak ada data</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>

                                    {{ $payrolls->appends(request()->query())->links() }}

                                </div>
                            </div>
                        @endif

                        {{-- LOANS --}}
                        @if (request('tab') == 'loan')
                            <div class="card">
                                <div class="card-header">
                                    <h4>Loans (Kasbon)</h4>
                                </div>
                                <div class="card-body table-responsive">

                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Amount</th>
                                                <th>Balance</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($loans as $l)
                                                <tr>
                                                    <td>{{ $l->user->name }}</td>
                                                    <td>Rp {{ number_format($l->amount) }}</td>
                                                    <td>Rp {{ number_format($l->balance) }}</td>
                                                    <td>{{ ucfirst($l->status) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center">Tidak ada data</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>

                                    {{ $loans->appends(request()->query())->links() }}

                                </div>
                            </div>
                        @endif

                    </div>
                </div>

            </div>

        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('backend/asset/library/selectric/public/jquery.selectric.min.js') }}"></script>
@endpush
