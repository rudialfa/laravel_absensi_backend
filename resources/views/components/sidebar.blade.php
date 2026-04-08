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

            @if(Auth::user()->role == 'admin')
                <li class="{{ request()->is('admin/dashboard') ? 'active' : '' }}">
                    <a href="{{ route('admin.dashboard') }}" class="nav-link">
                        <i class="fas fa-fire"></i><span>Dashboard Superadmin</span>
                    </a>
                </li>
            @elseif(Auth::user()->role == 'company')
                <li class="{{ request()->is('company/dashboard') ? 'active' : '' }}">
                    <a href="{{ route('company.dashboard') }}" class="nav-link">
                        <i class="fas fa-building"></i><span>Dashboard Company</span>
                    </a>
                </li>
            @else
                <li class="{{ request()->is('user/dashboard') ? 'active' : '' }}">
                    <a href="{{ route('user.dashboard') }}" class="nav-link">
                        <i class="fas fa-user"></i><span>Dashboard User</span>
                    </a>
                </li>
            @endif


            {{-- ============================= --}}
            {{-- SUPERADMIN MENU --}}
            {{-- ============================= --}}
            @if(Auth::user()->role == 'admin')
                <li class="menu-header">Superadmin Management</li>

                <li class="{{ request()->is('admin/users*') ? 'active' : '' }}">
                    <a href="{{ route('admin.users.index') }}" class="nav-link">
                        <i class="fas fa-users"></i><span>Users</span>
                    </a>
                </li>

                <li class="{{ request()->is('admin/companies*') ? 'active' : '' }}">
                    <a href="{{ route('admin.companies.index') }}" class="nav-link">
                        <i class="fas fa-building"></i><span>Companies</span>
                    </a>
                </li>

                <li class="{{ request()->is('admin/attendances*') ? 'active' : '' }}">
                    <a href="{{ route('admin.attendances.index') }}" class="nav-link">
                        <i class="fas fa-clock"></i><span>Attendances</span>
                    </a>
                </li>

                <li class="{{ request()->is('admin/permissions*') ? 'active' : '' }}">
                    <a href="{{ route('admin.permissions.index') }}" class="nav-link">
                        <i class="fas fa-user-check"></i><span>Permissions</span>
                    </a>
                </li>

                <li class="{{ request()->is('admin/payrools*') ? 'active' : '' }}">
                    <a href="{{ route('admin.payrools.index') }}" class="nav-link">
                        <i class="fas fa-file-invoice-dollar"></i><span>Payrolls</span>
                    </a>
                </li>

                <li class="{{ request()->is('admin/loans*') ? 'active' : '' }}">
                    <a href="{{ route('admin.loans.index') }}" class="nav-link">
                        <i class="fas fa-hand-holding-usd"></i><span>Loans (Kasbon)</span>
                    </a>
                </li>

                <li class="{{ request()->is('admin/shifts*') ? 'active' : '' }}">
                    <a href="{{ route('admin.shifts.index') }}" class="nav-link">
                        <i class="fas fa-calendar-alt"></i><span>Shift Management</span>
                    </a>
                </li>

                <li class="{{ request()->is('admin/schedules*') ? 'active' : '' }}">
                    <a href="{{ route('admin.schedules.index') }}" class="nav-link">
                        <i class="fas fa-tasks"></i><span>Schedules</span>
                    </a>
                </li>

                <li class="{{ request()->is('admin/prayers*') ? 'active' : '' }}">
                    <a href="{{ route('admin.prayers.index') }}" class="nav-link">
                        <i class="fas fa-mosque"></i><span>Prayers (Adzan)</span>
                    </a>
                </li>

                <li class="{{ request()->is('admin/reports*') ? 'active' : '' }}">
                    <a href="{{ route('admin.reports.index') }}" class="nav-link">
                        <i class="fas fa-chart-line"></i><span>Reports & Analytics</span>
                    </a>
                </li>
            @endif


            {{-- ============================= --}}
            {{-- COMPANY MENU --}}
            {{-- ============================= --}}
            @if(Auth::user()->role == 'company')
                <li class="menu-header">Company Management</li>

                <li class="{{ request()->is('company/attendances*') ? 'active' : '' }}">
                    <a href="{{ route('company.attendances.index') }}" class="nav-link">
                        <i class="fas fa-clock"></i><span>Attendances</span>
                    </a>
                </li>

                <li class="{{ request()->is('company/employees*') ? 'active' : '' }}">
                    <a href="{{ route('employees.index') }}" class="nav-link">
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
                        <i class="fas fa-calendar-alt"></i><span>Shift Schedule</span>
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
            {{-- USER MENU --}}
            {{-- ============================= --}}
            @if(Auth::user()->role == 'user')
                <li class="menu-header">My Menu</li>

                <li class="{{ request()->is('user/attendances*') ? 'active' : '' }}">
                    <a href="{{ route('user.attendances.index') }}" class="nav-link">
                        <i class="fas fa-clock"></i><span>Absensi Saya</span>
                    </a>
                </li>

                 <li class="{{ request()->is('user/notes*') ? 'active' : '' }}">
                    <a href="{{ route('user.notes.index') }}" class="nav-link">
                        <i class="fas fa-sticky-note"></i><span>Catatan Saya</span>
                    </a>
                </li>


                <li class="{{ request()->is('user/permissions*') ? 'active' : '' }}">
                    <a href="{{ route('user.permissions.index') }}" class="nav-link">
                        <i class="fas fa-plane"></i><span>Izin & Dinas</span>
                    </a>
                </li>

                <li class="{{ request()->is('user/schedules*') ? 'active' : '' }}">
                    <a href="{{ route('user.schedules.index') }}" class="nav-link">
                        <i class="fas fa-calendar-alt"></i><span>Jadwal & Reminder</span>
                    </a>
                </li>

                <li class="{{ request()->is('user/payrolls*') ? 'active' : '' }}">
                    <a href="{{ route('user.payrolls.index') }}" class="nav-link">
                        <i class="fas fa-file-invoice-dollar"></i><span>Slip Gaji</span>
                    </a>
                </li>

                <li class="{{ request()->is('user/loans*') ? 'active' : '' }}">
                    <a href="{{ route('user.loans.index') }}" class="nav-link">
                        <i class="fas fa-hand-holding-usd"></i><span>Kasbon Saya</span>
                    </a>
                </li>

                <li class="{{ request()->is('user/chat*') ? 'active' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="fas fa-comments"></i><span>Chat Admin</span>
                    </a>
                </li>

                <li class="{{ request()->is('user/prayers*') ? 'active' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="fas fa-mosque"></i><span>Jadwal Adzan</span>
                    </a>
                </li>
            @endif

        </ul>
    </aside>
</div>
