<div class="main-sidebar sidebar-style-2">
    <aside id="sidebar-wrapper">
        <div class="sidebar-brand">
            <a href="#">Absen Pintar</a>
        </div>
        <div class="sidebar-brand sidebar-brand-sm">
            <a href="#">AP</a>
        </div>

        <ul class="sidebar-menu">

            {{-- DASHBOARD --}}
            <li class="menu-header">Dashboard</li>

            @if(Auth::user()->role == 'superadmin')
                <li class="{{ request()->is('superadmin/dashboard') ? 'active' : '' }}">
                    <a href="{{ route('superadmin.dashboard') }}" class="nav-link">
                        <i class="fas fa-fire"></i><span>Dashboard Superadmin</span>
                    </a>
                </li>
            @elseif(Auth::user()->role == 'hr')
                <li class="{{ request()->is('company/dashboard') ? 'active' : '' }}">
                    <a href="{{ route('company.dashboard') }}" class="nav-link">
                        <i class="fas fa-building"></i><span>Dashboard Company</span>
                    </a>
                </li>
            @elseif(Auth::user()->role == 'employee')
                <li class="{{ request()->is('employee/dashboard') ? 'active' : '' }}">
                    <a href="{{ Route::has('employee.dashboard') ? route('employee.dashboard') : '#' }}" class="nav-link">
                        <i class="fas fa-user"></i><span>Dashboard Employee</span>
                    </a>
                </li>
            @elseif(Auth::user()->role == 'santri')
                <li class="{{ request()->is('santri/dashboard') ? 'active' : '' }}">
                    <a href="{{ Route::has('santri.dashboard') ? route('santri.dashboard') : '#' }}" class="nav-link">
                        <i class="fas fa-user"></i><span>Dashboard Santri</span>
                    </a>
                </li>
            @elseif(Auth::user()->role == 'ustadz')
                <li class="{{ request()->is('pesantren/dashboard') ? 'active' : '' }}">
                    <a href="{{ route('pesantren.dashboard') }}" class="nav-link">
                        <i class="fas fa-user"></i><span>Dashboard Ustadz</span>
                    </a>
                </li>
            @else
                <li class="{{ request()->is('user/dashboard') ? 'active' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="fas fa-user"></i><span>Dashboard User</span>
                    </a>
                </li>
            @endif


            {{-- ============================= --}}
            {{-- SUPERADMIN MENU --}}
            {{-- ============================= --}}
            @if(Auth::user()->role == 'superadmin')
                <li class="menu-header">Superadmin Management</li>

                <li class="{{ request()->is('superadmin/global-search*') ? 'active' : '' }}">
                    <a href="{{ route('superadmin.global-search') }}" class="nav-link">
                        <i class="fas fa-search"></i><span>Global Search</span>
                    </a>
                </li>

                <li class="{{ request()->is('superadmin/tenants*') ? 'active' : '' }}">
                    <a href="{{ route('superadmin.tenants.index') }}" class="nav-link">
                        <i class="fas fa-building"></i><span>Tenants</span>
                    </a>
                </li>

                <li class="{{ request()->is('superadmin/plans*') ? 'active' : '' }}">
                    <a href="{{ route('superadmin.plans.index') }}" class="nav-link">
                        <i class="fas fa-layer-group"></i><span>Plans</span>
                    </a>
                </li>

                <li class="{{ request()->is('superadmin/discounts*') ? 'active' : '' }}">
                    <a href="{{ route('superadmin.discounts.index') }}" class="nav-link">
                        <i class="fas fa-percentage"></i><span>Voucher / Diskon</span>
                    </a>
                </li>

                <li class="{{ request()->is('superadmin/subscriptions*') ? 'active' : '' }}">
                    <a href="{{ route('superadmin.subscriptions.index') }}" class="nav-link">
                        <i class="fas fa-sync-alt"></i><span>Langganan Tenant</span>
                    </a>
                </li>

                <li class="{{ request()->is('superadmin/invoices*') ? 'active' : '' }}">
                    <a href="{{ route('superadmin.invoices.index') }}" class="nav-link">
                        <i class="fas fa-file-invoice-dollar"></i><span>Invoices</span>
                    </a>
                </li>

                <li class="{{ request()->is('superadmin/va-payments*') ? 'active' : '' }}">
                    <a href="{{ route('superadmin.va-payments.index') }}" class="nav-link">
                        <i class="fas fa-money-check-alt"></i><span>Monitoring VA Payment</span>
                    </a>
                </li>

                <li class="{{ request()->is('superadmin/analytics*') ? 'active' : '' }}">
                    <a href="{{ route('superadmin.analytics') }}" class="nav-link">
                        <i class="fas fa-chart-line"></i><span>Analytics</span>
                    </a>
                </li>

                <li class="{{ request()->is('superadmin/staff*') ? 'active' : '' }}">
                    <a href="{{ route('superadmin.staff.index') }}" class="nav-link">
                        <i class="fas fa-users"></i><span>Staff</span>
                    </a>
                </li>

                <li class="{{ request()->is('superadmin/support-tickets*') ? 'active' : '' }}">
                    <a href="{{ route('superadmin.support-tickets.index') }}" class="nav-link">
                        <i class="fas fa-headset"></i><span>Tiket Bantuan</span>
                    </a>
                </li>

                <li class="{{ request()->is('superadmin/help-articles*') ? 'active' : '' }}">
                    <a href="{{ route('superadmin.help-articles.index') }}" class="nav-link">
                        <i class="fas fa-question-circle"></i><span>Bantuan / FAQ</span>
                    </a>
                </li>

                <li class="{{ request()->is('superadmin/app-policies*') ? 'active' : '' }}">
                    <a href="{{ route('superadmin.app-policies.index') }}" class="nav-link">
                        <i class="fas fa-file-contract"></i><span>Kebijakan Aplikasi</span>
                    </a>
                </li>

                <li class="{{ request()->is('superadmin/audit-logs*') ? 'active' : '' }}">
                    <a href="{{ route('superadmin.audit-logs') }}" class="nav-link">
                        <i class="fas fa-history"></i><span>Audit Logs</span>
                    </a>
                </li>

                <li class="{{ request()->is('superadmin/settings*') ? 'active' : '' }}">
                    <a href="{{ route('superadmin.settings.show') }}" class="nav-link">
                        <i class="fas fa-cog"></i><span>Settings</span>
                    </a>
                </li>
            @endif


            {{-- ============================= --}}
            {{-- COMPANY / HR MENU --}}
            {{-- ============================= --}}
            @if(Auth::user()->role == 'hr')
                <li class="menu-header">Company Management</li>

                <li class="{{ request()->is('company/attendances*') ? 'active' : '' }}">
                    <a href="{{ route('company.attendances.index') }}" class="nav-link">
                        <i class="fas fa-clock"></i><span>Attendances</span>
                    </a>
                </li>

                <li class="{{ request()->is('company/employees*') ? 'active' : '' }}">
                    <a href="{{ route('company.employees.index') }}" class="nav-link">
                        <i class="fas fa-users"></i><span>Employees</span>
                    </a>
                </li>

                <li class="{{ request()->is('company/permissions*') ? 'active' : '' }}">
                    <a href="{{ route('company.permissions.index') }}" class="nav-link">
                        <i class="fas fa-user-check"></i><span>Permissions</span>
                    </a>
                </li>

                <li class="{{ request()->is('company/shifts*') ? 'active' : '' }}">
                    <a href="{{ route('company.shifts.index') }}" class="nav-link">
                        <i class="fas fa-calendar-alt"></i><span>Shifts</span>
                    </a>
                </li>

                <li class="{{ request()->is('company/schedules*') ? 'active' : '' }}">
                    <a href="{{ route('company.schedules.index') }}" class="nav-link">
                        <i class="fas fa-tasks"></i><span>Schedules</span>
                    </a>
                </li>

                <li class="{{ request()->is('company/holidays*') ? 'active' : '' }}">
                    <a href="{{ route('company.holidays.index') }}" class="nav-link">
                        <i class="fas fa-umbrella-beach"></i><span>Holidays</span>
                    </a>
                </li>

                <li class="{{ request()->is('company/payrolls*') ? 'active' : '' }}">
                    <a href="{{ route('company.payrolls.index') }}" class="nav-link">
                        <i class="fas fa-money-check-alt"></i><span>Payroll</span>
                    </a>
                </li>

                <li class="{{ request()->is('company/loans*') ? 'active' : '' }}">
                    <a href="{{ route('company.loans.index') }}" class="nav-link">
                        <i class="fas fa-hand-holding-usd"></i><span>Loans</span>
                    </a>
                </li>
            @endif


            {{-- ============================= --}}
            {{-- PESANTREN / USTADZ MENU --}}
            {{-- ============================= --}}
            @if(Auth::user()->role == 'ustadz')
                <li class="menu-header">Pesantren Management</li>

                <li class="{{ request()->is('pesantren/attendances*') ? 'active' : '' }}">
                    <a href="{{ route('pesantren.attendances.index') }}" class="nav-link">
                        <i class="fas fa-clock"></i><span>Attendances</span>
                    </a>
                </li>

                <li class="{{ request()->is('pesantren/santri*') ? 'active' : '' }}">
                    <a href="{{ route('pesantren.santri.index') }}" class="nav-link">
                        <i class="fas fa-users"></i><span>Santri</span>
                    </a>
                </li>

                <li class="{{ request()->is('pesantren/mutabaah*') ? 'active' : '' }}">
                    <a href="{{ route('pesantren.mutabaah.index') }}" class="nav-link">
                        <i class="fas fa-book-open"></i><span>Mutaba'ah</span>
                    </a>
                </li>

                <li class="{{ request()->is('pesantren/schedules*') ? 'active' : '' }}">
                    <a href="{{ route('pesantren.schedules.index') }}" class="nav-link">
                        <i class="fas fa-calendar-alt"></i><span>Schedules</span>
                    </a>
                </li>
            @endif


            {{-- ============================= --}}
            {{-- EMPLOYEE MENU --}}
            {{-- ============================= --}}
            @if(Auth::user()->role == 'employee')
                <li class="menu-header">My Menu</li>

                <li class="{{ request()->is('employee/attendances*') ? 'active' : '' }}">
                    <a href="{{ Route::has('employee.attendances.index') ? route('employee.attendances.index') : '#' }}" class="nav-link">
                        <i class="fas fa-clock"></i><span>Absensi Saya</span>
                    </a>
                </li>

                <li class="{{ request()->is('employee/notes*') ? 'active' : '' }}">
                    <a href="{{ Route::has('employee.notes.index') ? route('employee.notes.index') : '#' }}" class="nav-link">
                        <i class="fas fa-sticky-note"></i><span>Catatan Saya</span>
                    </a>
                </li>

                <li class="{{ request()->is('employee/permissions*') ? 'active' : '' }}">
                    <a href="{{ Route::has('employee.permissions.index') ? route('employee.permissions.index') : '#' }}" class="nav-link">
                        <i class="fas fa-plane"></i><span>Izin & Dinas</span>
                    </a>
                </li>

                <li class="{{ request()->is('employee/schedules*') ? 'active' : '' }}">
                    <a href="{{ Route::has('employee.schedules.index') ? route('employee.schedules.index') : '#' }}" class="nav-link">
                        <i class="fas fa-calendar-alt"></i><span>Jadwal & Reminder</span>
                    </a>
                </li>

                <li class="{{ request()->is('employee/payrolls*') ? 'active' : '' }}">
                    <a href="{{ Route::has('employee.payrolls.index') ? route('employee.payrolls.index') : '#' }}" class="nav-link">
                        <i class="fas fa-file-invoice-dollar"></i><span>Slip Gaji</span>
                    </a>
                </li>

                <li class="{{ request()->is('employee/loans*') ? 'active' : '' }}">
                    <a href="{{ Route::has('employee.loans.index') ? route('employee.loans.index') : '#' }}" class="nav-link">
                        <i class="fas fa-hand-holding-usd"></i><span>Kasbon Saya</span>
                    </a>
                </li>
            @endif


            {{-- ============================= --}}
            {{-- SANTRI MENU --}}
            {{-- ============================= --}}
            @if(Auth::user()->role == 'santri')
                <li class="menu-header">My Menu</li>

                <li class="{{ request()->is('santri/attendances*') ? 'active' : '' }}">
                    <a href="{{ Route::has('santri.attendances.index') ? route('santri.attendances.index') : '#' }}" class="nav-link">
                        <i class="fas fa-clock"></i><span>Absensi Saya</span>
                    </a>
                </li>

                <li class="{{ request()->is('santri/notes*') ? 'active' : '' }}">
                    <a href="{{ Route::has('santri.notes.index') ? route('santri.notes.index') : '#' }}" class="nav-link">
                        <i class="fas fa-sticky-note"></i><span>Catatan Saya</span>
                    </a>
                </li>

                <li class="{{ request()->is('santri/mutabaah*') ? 'active' : '' }}">
                    <a href="{{ Route::has('santri.mutabaah.index') ? route('santri.mutabaah.index') : '#' }}" class="nav-link">
                        <i class="fas fa-book-open"></i><span>Mutaba'ah</span>
                    </a>
                </li>

                <li class="{{ request()->is('santri/permissions*') ? 'active' : '' }}">
                    <a href="{{ Route::has('santri.permissions.index') ? route('santri.permissions.index') : '#' }}" class="nav-link">
                        <i class="fas fa-plane"></i><span>Izin</span>
                    </a>
                </li>

                <li class="{{ request()->is('santri/schedules*') ? 'active' : '' }}">
                    <a href="{{ Route::has('santri.schedules.index') ? route('santri.schedules.index') : '#' }}" class="nav-link">
                        <i class="fas fa-calendar-alt"></i><span>Jadwal</span>
                    </a>
                </li>

                <li class="{{ request()->is('santri/prayers*') ? 'active' : '' }}">
                    <a href="{{ Route::has('santri.prayers.index') ? route('santri.prayers.index') : '#' }}" class="nav-link">
                        <i class="fas fa-mosque"></i><span>Jadwal Adzan</span>
                    </a>
                </li>
            @endif

        </ul>
    </aside>
</div>
