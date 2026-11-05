@php
    $role = Auth::user()->role;

    // Link aman: kalau route belum terdaftar, kembalikan '#' (tidak error)
    $safeRoute = fn(string $name) => \Illuminate\Support\Facades\Route::has($name) ? route($name) : '#';

    /*
    |--------------------------------------------------------------------------
    | MENU SUPERADMIN
    |--------------------------------------------------------------------------
    | Satu item  : ['route', 'active', 'icon', 'label', 'badge'(opsional)]
    | Dropdown   : ['dropdown' => true, 'icon', 'label', 'items' => [...]]
    |
    | Item yang route-nya BELUM ada otomatis disembunyikan, jadi aman
    | menambah menu satu per satu. Header grup juga ikut hilang kalau
    | semua item di dalamnya belum ada.
    */
    $superadminMenu = [];

    if ($role === 'superadmin') {
        $openTickets = \Illuminate\Support\Facades\Route::has('superadmin.support-tickets.index')
            ? \App\Models\SupportTicket::where('status', 'open')->count()
            : 0;

        $superadminMenu = [
            'Utama' => [
                ['route' => 'superadmin.dashboard', 'active' => 'superadmin.dashboard', 'icon' => 'fa-fire', 'label' => 'Dashboard'],
                ['route' => 'superadmin.analytics', 'active' => 'superadmin.analytics*', 'icon' => 'fa-chart-line', 'label' => 'Analytics'],
                ['route' => 'superadmin.global-search', 'active' => 'superadmin.global-search*', 'icon' => 'fa-search', 'label' => 'Global Search'],
            ],

            'Tenant & Pengguna' => [
                ['route' => 'superadmin.tenants.index', 'active' => 'superadmin.tenants.*', 'icon' => 'fa-building', 'label' => 'Tenant / Lembaga'],
                ['route' => 'superadmin.users.index', 'active' => 'superadmin.users.*', 'icon' => 'fa-users', 'label' => 'Pengguna'],
                ['route' => 'superadmin.staff.index', 'active' => 'superadmin.staff.*', 'icon' => 'fa-user-shield', 'label' => 'Staff Superadmin'],
            ],

            'Langganan & Pembayaran' => [
                ['route' => 'superadmin.plans.index', 'active' => 'superadmin.plans.*', 'icon' => 'fa-layer-group', 'label' => 'Paket Langganan'],
                ['route' => 'superadmin.discounts.index', 'active' => 'superadmin.discounts.*', 'icon' => 'fa-percentage', 'label' => 'Voucher / Diskon'],
                ['route' => 'superadmin.subscriptions.index', 'active' => 'superadmin.subscriptions.*', 'icon' => 'fa-sync-alt', 'label' => 'Langganan Tenant'],
                ['route' => 'superadmin.invoices.index', 'active' => 'superadmin.invoices.*', 'icon' => 'fa-file-invoice-dollar', 'label' => 'Invoice'],
                ['route' => 'superadmin.va-payments.index', 'active' => 'superadmin.va-payments.*', 'icon' => 'fa-money-check-alt', 'label' => 'VA Payment'],
                ['route' => 'superadmin.va-logs.index', 'active' => 'superadmin.va-logs.*', 'icon' => 'fa-exchange-alt', 'label' => 'Log Callback Bank'],
            ],

            'Monitoring Data Tenant' => [
                [
                    'dropdown' => true, 'icon' => 'fa-briefcase', 'label' => 'Karyawan',
                    'items' => [
                        ['route' => 'superadmin.monitoring.attendances.index', 'active' => 'superadmin.monitoring.attendances.*', 'label' => 'Absensi'],
                        ['route' => 'superadmin.monitoring.requests.index', 'active' => 'superadmin.monitoring.requests.*', 'label' => 'Izin, Cuti & Lembur'],
                        ['route' => 'superadmin.monitoring.shifts.index', 'active' => 'superadmin.monitoring.shifts.*', 'label' => 'Shift & Jadwal'],
                        ['route' => 'superadmin.monitoring.holidays.index', 'active' => 'superadmin.monitoring.holidays.*', 'label' => 'Hari Libur'],
                        ['route' => 'superadmin.monitoring.payrolls.index', 'active' => 'superadmin.monitoring.payrolls.*', 'label' => 'Payroll & Pinjaman'],
                        ['route' => 'superadmin.monitoring.reports.index', 'active' => 'superadmin.monitoring.reports.*', 'label' => 'Laporan & Kinerja'],
                    ],
                ],
                [
                    'dropdown' => true, 'icon' => 'fa-school', 'label' => 'Sekolah',
                    'items' => [
                        ['route' => 'superadmin.school.classes.index', 'active' => 'superadmin.school.classes.*', 'label' => 'Kelas, Murid & Wali'],
                        ['route' => 'superadmin.school.devices.index', 'active' => 'superadmin.school.devices.*', 'label' => 'Perangkat Kiosk'],
                        ['route' => 'superadmin.school.attendances.index', 'active' => 'superadmin.school.attendances.*', 'label' => 'Absen & Izin Murid'],
                        ['route' => 'superadmin.school.academics.index', 'active' => 'superadmin.school.academics.*', 'label' => 'Akademik'],
                        ['route' => 'superadmin.school.finance.index', 'active' => 'superadmin.school.finance.*', 'label' => 'Keuangan Sekolah'],
                        ['route' => 'superadmin.school.ppdb.index', 'active' => 'superadmin.school.ppdb.*', 'label' => 'PPDB & Mutasi'],
                        ['route' => 'superadmin.school.health.index', 'active' => 'superadmin.school.health.*', 'label' => 'Kesehatan / UKS'],
                        ['route' => 'superadmin.school.extracurriculars.index', 'active' => 'superadmin.school.extracurriculars.*', 'label' => 'Ekskul & Prestasi'],
                    ],
                ],
                [
                    'dropdown' => true, 'icon' => 'fa-mosque', 'label' => 'Pesantren & Asrama',
                    'items' => [
                        ['route' => 'superadmin.boarding.dormitories.index', 'active' => 'superadmin.boarding.dormitories.*', 'label' => 'Asrama & Kamar'],
                        ['route' => 'superadmin.boarding.mutabaah.index', 'active' => 'superadmin.boarding.mutabaah.*', 'label' => "Mutaba'ah & Tahfidz"],
                        ['route' => 'superadmin.boarding.permissions.index', 'active' => 'superadmin.boarding.permissions.*', 'label' => 'Izin Asrama'],
                    ],
                ],
            ],

            'Konten & Dukungan' => [
                ['route' => 'superadmin.support-tickets.index', 'active' => 'superadmin.support-tickets.*', 'icon' => 'fa-headset', 'label' => 'Tiket Bantuan', 'badge' => $openTickets],
                ['route' => 'superadmin.help-articles.index', 'active' => 'superadmin.help-articles.*', 'icon' => 'fa-question-circle', 'label' => 'Bantuan / FAQ'],
                ['route' => 'superadmin.app-policies.index', 'active' => 'superadmin.app-policies.*', 'icon' => 'fa-file-contract', 'label' => 'Kebijakan Aplikasi'],
                ['route' => 'superadmin.notifications.index', 'active' => 'superadmin.notifications.*', 'icon' => 'fa-bell', 'label' => 'Kirim Notifikasi'],
            ],

            'Sistem' => [
                ['route' => 'superadmin.prayers.index', 'active' => 'superadmin.prayers.*', 'icon' => 'fa-clock', 'label' => 'Jadwal Sholat'],
                ['route' => 'superadmin.audit-logs', 'active' => 'superadmin.audit-logs*', 'icon' => 'fa-history', 'label' => 'Audit Log'],
                ['route' => 'superadmin.jobs.index', 'active' => 'superadmin.jobs.*', 'icon' => 'fa-tasks', 'label' => 'Antrian Job'],
                ['route' => 'superadmin.settings.show', 'active' => 'superadmin.settings.*', 'icon' => 'fa-cog', 'label' => 'Pengaturan Sistem'],
            ],
        ];

        // Buang item/dropdown yang route-nya belum ada, lalu buang grup yang kosong
        foreach ($superadminMenu as $group => $items) {
            $items = array_map(function ($item) {
                if (!empty($item['dropdown'])) {
                    $item['items'] = array_values(array_filter($item['items'], fn($c) => \Illuminate\Support\Facades\Route::has($c['route'])));
                    return count($item['items']) ? $item : null;
                }
                return \Illuminate\Support\Facades\Route::has($item['route']) ? $item : null;
            }, $items);

            $items = array_values(array_filter($items));

            if (count($items)) {
                $superadminMenu[$group] = $items;
            } else {
                unset($superadminMenu[$group]);
            }
        }
    }
@endphp

<div class="main-sidebar sidebar-style-2">
    <aside id="sidebar-wrapper">
        <div class="sidebar-brand">
            <a href="{{ $role === 'superadmin' ? $safeRoute('superadmin.dashboard') : '#' }}">Absen Pintar</a>
        </div>
        <div class="sidebar-brand sidebar-brand-sm">
            <a href="{{ $role === 'superadmin' ? $safeRoute('superadmin.dashboard') : '#' }}">AP</a>
        </div>

        <ul class="sidebar-menu">

            {{-- ============================= --}}
            {{-- SUPERADMIN --}}
            {{-- ============================= --}}
            @if ($role === 'superadmin')
                @foreach ($superadminMenu as $group => $items)
                    <li class="menu-header">{{ $group }}</li>

                    @foreach ($items as $item)
                        @if (!empty($item['dropdown']))
                            @php
                                $isOpen = collect($item['items'])->contains(fn($c) => request()->routeIs($c['active']));
                            @endphp
                            <li class="nav-item dropdown {{ $isOpen ? 'active' : '' }}">
                                <a href="#" class="nav-link has-dropdown">
                                    <i class="fas {{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                                </a>
                                <ul class="dropdown-menu" @if ($isOpen) style="display:block" @endif>
                                    @foreach ($item['items'] as $child)
                                        <li class="{{ request()->routeIs($child['active']) ? 'active' : '' }}">
                                            <a class="nav-link" href="{{ route($child['route']) }}">{{ $child['label'] }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            <li class="{{ request()->routeIs($item['active']) ? 'active' : '' }}">
                                <a href="{{ route($item['route']) }}" class="nav-link">
                                    <i class="fas {{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                                    @if (!empty($item['badge']))
                                        <span class="badge badge-danger" style="margin-left:auto">{{ $item['badge'] }}</span>
                                    @endif
                                </a>
                            </li>
                        @endif
                    @endforeach
                @endforeach
            @endif


            {{-- ============================= --}}
            {{-- COMPANY / HR --}}
            {{-- ============================= --}}
            @if ($role === 'hr')
                <li class="menu-header">Dashboard</li>
                <li class="{{ request()->is('company/dashboard') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('company.dashboard') }}" class="nav-link">
                        <i class="fas fa-building"></i><span>Dashboard Company</span>
                    </a>
                </li>

                <li class="menu-header">Company Management</li>
                <li class="{{ request()->is('company/attendances*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('company.attendances.index') }}" class="nav-link">
                        <i class="fas fa-clock"></i><span>Attendances</span>
                    </a>
                </li>
                <li class="{{ request()->is('company/employees*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('company.employees.index') }}" class="nav-link">
                        <i class="fas fa-users"></i><span>Employees</span>
                    </a>
                </li>
                <li class="{{ request()->is('company/permissions*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('company.permissions.index') }}" class="nav-link">
                        <i class="fas fa-user-check"></i><span>Permissions</span>
                    </a>
                </li>
                <li class="{{ request()->is('company/shifts*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('company.shifts.index') }}" class="nav-link">
                        <i class="fas fa-calendar-alt"></i><span>Shifts</span>
                    </a>
                </li>
                <li class="{{ request()->is('company/schedules*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('company.schedules.index') }}" class="nav-link">
                        <i class="fas fa-tasks"></i><span>Schedules</span>
                    </a>
                </li>
                <li class="{{ request()->is('company/holidays*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('company.holidays.index') }}" class="nav-link">
                        <i class="fas fa-umbrella-beach"></i><span>Holidays</span>
                    </a>
                </li>
                <li class="{{ request()->is('company/payrolls*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('company.payrolls.index') }}" class="nav-link">
                        <i class="fas fa-money-check-alt"></i><span>Payroll</span>
                    </a>
                </li>
                <li class="{{ request()->is('company/loans*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('company.loans.index') }}" class="nav-link">
                        <i class="fas fa-hand-holding-usd"></i><span>Loans</span>
                    </a>
                </li>
            @endif


            {{-- ============================= --}}
            {{-- PESANTREN / USTADZ --}}
            {{-- ============================= --}}
            @if ($role === 'ustadz')
                <li class="menu-header">Dashboard</li>
                <li class="{{ request()->is('pesantren/dashboard') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('pesantren.dashboard') }}" class="nav-link">
                        <i class="fas fa-user"></i><span>Dashboard Ustadz</span>
                    </a>
                </li>

                <li class="menu-header">Pesantren Management</li>
                <li class="{{ request()->is('pesantren/attendances*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('pesantren.attendances.index') }}" class="nav-link">
                        <i class="fas fa-clock"></i><span>Attendances</span>
                    </a>
                </li>
                <li class="{{ request()->is('pesantren/santri*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('pesantren.santri.index') }}" class="nav-link">
                        <i class="fas fa-users"></i><span>Santri</span>
                    </a>
                </li>
                <li class="{{ request()->is('pesantren/mutabaah*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('pesantren.mutabaah.index') }}" class="nav-link">
                        <i class="fas fa-book-open"></i><span>Mutaba'ah</span>
                    </a>
                </li>
                <li class="{{ request()->is('pesantren/schedules*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('pesantren.schedules.index') }}" class="nav-link">
                        <i class="fas fa-calendar-alt"></i><span>Schedules</span>
                    </a>
                </li>
            @endif


            {{-- ============================= --}}
            {{-- EMPLOYEE --}}
            {{-- ============================= --}}
            @if ($role === 'employee')
                <li class="menu-header">Dashboard</li>
                <li class="{{ request()->is('employee/dashboard') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('employee.dashboard') }}" class="nav-link">
                        <i class="fas fa-user"></i><span>Dashboard Employee</span>
                    </a>
                </li>

                <li class="menu-header">My Menu</li>
                <li class="{{ request()->is('employee/attendances*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('employee.attendances.index') }}" class="nav-link">
                        <i class="fas fa-clock"></i><span>Absensi Saya</span>
                    </a>
                </li>
                <li class="{{ request()->is('employee/notes*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('employee.notes.index') }}" class="nav-link">
                        <i class="fas fa-sticky-note"></i><span>Catatan Saya</span>
                    </a>
                </li>
                <li class="{{ request()->is('employee/permissions*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('employee.permissions.index') }}" class="nav-link">
                        <i class="fas fa-plane"></i><span>Izin & Dinas</span>
                    </a>
                </li>
                <li class="{{ request()->is('employee/schedules*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('employee.schedules.index') }}" class="nav-link">
                        <i class="fas fa-calendar-alt"></i><span>Jadwal & Reminder</span>
                    </a>
                </li>
                <li class="{{ request()->is('employee/payrolls*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('employee.payrolls.index') }}" class="nav-link">
                        <i class="fas fa-file-invoice-dollar"></i><span>Slip Gaji</span>
                    </a>
                </li>
                <li class="{{ request()->is('employee/loans*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('employee.loans.index') }}" class="nav-link">
                        <i class="fas fa-hand-holding-usd"></i><span>Kasbon Saya</span>
                    </a>
                </li>
            @endif


            {{-- ============================= --}}
            {{-- SANTRI --}}
            {{-- ============================= --}}
            @if ($role === 'santri')
                <li class="menu-header">Dashboard</li>
                <li class="{{ request()->is('santri/dashboard') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('santri.dashboard') }}" class="nav-link">
                        <i class="fas fa-user"></i><span>Dashboard Santri</span>
                    </a>
                </li>

                <li class="menu-header">My Menu</li>
                <li class="{{ request()->is('santri/attendances*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('santri.attendances.index') }}" class="nav-link">
                        <i class="fas fa-clock"></i><span>Absensi Saya</span>
                    </a>
                </li>
                <li class="{{ request()->is('santri/notes*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('santri.notes.index') }}" class="nav-link">
                        <i class="fas fa-sticky-note"></i><span>Catatan Saya</span>
                    </a>
                </li>
                <li class="{{ request()->is('santri/mutabaah*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('santri.mutabaah.index') }}" class="nav-link">
                        <i class="fas fa-book-open"></i><span>Mutaba'ah</span>
                    </a>
                </li>
                <li class="{{ request()->is('santri/permissions*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('santri.permissions.index') }}" class="nav-link">
                        <i class="fas fa-plane"></i><span>Izin</span>
                    </a>
                </li>
                <li class="{{ request()->is('santri/schedules*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('santri.schedules.index') }}" class="nav-link">
                        <i class="fas fa-calendar-alt"></i><span>Jadwal</span>
                    </a>
                </li>
                <li class="{{ request()->is('santri/prayers*') ? 'active' : '' }}">
                    <a href="{{ $safeRoute('santri.prayers.index') }}" class="nav-link">
                        <i class="fas fa-mosque"></i><span>Jadwal Adzan</span>
                    </a>
                </li>
            @endif

        </ul>
    </aside>
</div>
