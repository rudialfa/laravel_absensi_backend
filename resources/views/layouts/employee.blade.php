<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Employee Portal') — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ══════════════════════════════════════════
           DESIGN TOKENS — satu tempat, semua pakai
           ══════════════════════════════════════════ */
        :root {
            /* Layout */
            --sidebar-w : 252px;
            --header-h  : 60px;

            /* Brand */
            --primary       : #1a56db;
            --primary-dark  : #1040b2;
            --primary-light : #ebf0ff;

            /* Alias c-* (dipakai view lama & baru) */
            --c-primary    : #1a56db;
            --c-primary-bg : #ebf0ff;
            --c-primary-dk : #1040b2;

            /* Status */
            --success      : #0e9f6e;
            --c-success    : #0e9f6e;
            --c-success-bg : #d1fae5;
            --warning      : #d97706;
            --c-warning    : #d97706;
            --c-warning-bg : #fef3c7;
            --danger       : #e02424;
            --c-danger     : #e02424;
            --c-danger-bg  : #fee2e2;
            --c-info       : #0369a1;
            --c-info-bg    : #e0f2fe;

            /* Neutral (gray-* dan c-* alias) */
            --gray-50  : #f9fafb;
            --gray-100 : #f3f4f6;
            --gray-200 : #e5e7eb;
            --gray-300 : #d1d5db;
            --gray-500 : #6b7280;
            --gray-700 : #374151;
            --gray-900 : #111827;
            --c-bg      : #f9fafb;
            --c-surface : #ffffff;
            --c-border  : #e5e7eb;
            --c-text    : #111827;
            --c-muted   : #6b7280;
            --c-hint    : #9ca3af;

            /* Radius & shadow */
            --radius    : 10px;
            --radius-sm : 6px;
            --radius-md : 10px;
            --radius-lg : 14px;
            --shadow    : 0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.06);
            --shadow-sm : 0 1px 3px rgba(0,0,0,.08);
            --shadow-md : 0 4px 12px rgba(0,0,0,.1);
        }

        /* ── Reset ──────────────────────────────── */
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;font-size:14px;background:var(--gray-50);color:var(--gray-900);min-height:100vh}

        /* ══════════════════════════════════════════
           SIDEBAR
           ══════════════════════════════════════════ */
        .sidebar{
            width:var(--sidebar-w);
            position:fixed;top:0;left:0;height:100vh;
            background:#fff;
            border-right:1px solid var(--gray-200);
            display:flex;flex-direction:column;
            z-index:100;
            transition:transform .3s;
        }
        /* Brand bar */
        .sidebar-brand{
            height:var(--header-h);
            flex-shrink:0;
            display:flex;align-items:center;gap:12px;
            padding:0 18px;
            border-bottom:1px solid var(--gray-200);
        }
        .sidebar-brand .logo{
            width:34px;height:34px;border-radius:8px;
            background:var(--primary);
            display:flex;align-items:center;justify-content:center;
            flex-shrink:0;
        }
        .sidebar-brand .logo i{color:#fff;font-size:15px}
        .sidebar-brand .brand-name{font-weight:700;font-size:15px;color:var(--gray-900);white-space:nowrap}

        /* Scrollable nav */
        .sidebar-nav{
            flex:1;
            overflow-y:auto;overflow-x:hidden;
            padding:8px 0;
            scrollbar-width:thin;
            scrollbar-color:var(--gray-200) transparent;
        }
        .sidebar-nav::-webkit-scrollbar{width:4px}
        .sidebar-nav::-webkit-scrollbar-track{background:transparent}
        .sidebar-nav::-webkit-scrollbar-thumb{background:var(--gray-200);border-radius:4px}

        /* Section label */
        .nav-section{
            padding:12px 18px 4px;
            font-size:10px;font-weight:700;
            letter-spacing:.08em;
            color:var(--gray-500);
            text-transform:uppercase;
            white-space:nowrap;
        }
        /* Nav link */
        .nav-item{
            display:flex;align-items:center;gap:10px;
            padding:9px 14px;margin:1px 8px;
            color:var(--gray-700);
            text-decoration:none;
            font-size:13.5px;font-weight:500;
            border-radius:7px;
            transition:all .15s;
            white-space:nowrap;overflow:hidden;
        }
        .nav-item i{width:18px;text-align:center;font-size:14px;color:var(--gray-500);flex-shrink:0}
        .nav-item:hover{background:var(--gray-100);color:var(--primary)}
        .nav-item:hover i{color:var(--primary)}
        .nav-item.active{background:var(--primary-light);color:var(--primary)}
        .nav-item.active i{color:var(--primary)}
        .nav-badge{
            margin-left:auto;flex-shrink:0;
            background:var(--danger);color:#fff;
            font-size:10px;font-weight:700;
            padding:1px 6px;border-radius:20px;min-width:18px;text-align:center;
        }

        /* Sidebar footer */
        .sidebar-footer{
            flex-shrink:0;
            padding:10px 8px;
            border-top:1px solid var(--gray-200);
        }
        .sidebar-user{
            display:flex;align-items:center;gap:10px;
            padding:8px 10px;border-radius:8px;
            background:var(--gray-50);margin-bottom:4px;
        }
        .user-avatar{
            width:34px;height:34px;border-radius:50%;
            background:var(--primary);
            display:flex;align-items:center;justify-content:center;
            color:#fff;font-size:13px;font-weight:700;flex-shrink:0;
        }
        .user-name{font-size:13px;font-weight:600;color:var(--gray-900);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .user-role{font-size:11px;color:var(--gray-500)}

        /* ══════════════════════════════════════════
           MAIN WRAPPER
           ══════════════════════════════════════════ */
        .main{
            margin-left:var(--sidebar-w);
            min-height:100vh;
            display:flex;flex-direction:column;
        }

        /* Topbar */
        .topbar{
            height:var(--header-h);
            background:#fff;border-bottom:1px solid var(--gray-200);
            display:flex;align-items:center;
            padding:0 24px;gap:16px;
            position:sticky;top:0;z-index:50;
        }
        .topbar-toggle{display:none;background:none;border:none;cursor:pointer;font-size:20px;color:var(--gray-700);padding:4px}
        .topbar-breadcrumb{
            flex:1;display:flex;align-items:center;gap:6px;
            font-size:13px;color:var(--gray-500);
        }
        .topbar-breadcrumb a{color:var(--gray-500);text-decoration:none}
        .topbar-breadcrumb a:hover{color:var(--primary)}
        .topbar-breadcrumb .sep{color:var(--gray-300)}
        .topbar-breadcrumb .current{color:var(--gray-900);font-weight:600}
        .topbar-actions{display:flex;align-items:center;gap:10px;margin-left:auto}
        .topbar-date{font-size:12px;color:var(--gray-500)}

        /* Page content */
        .page-content{flex:1;padding:24px}

        /* ══════════════════════════════════════════
           FLASH
           ══════════════════════════════════════════ */
        .flash{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:var(--radius);margin-bottom:20px;font-size:13.5px;font-weight:500}
        .flash-success{background:#d1fae5;color:#065f46;border-left:4px solid var(--success)}
        .flash-error  {background:#fee2e2;color:#991b1b;border-left:4px solid var(--danger)}
        .flash-warning{background:#fef3c7;color:#92400e;border-left:4px solid var(--warning)}

        /* ══════════════════════════════════════════
           CARDS
           ══════════════════════════════════════════ */
        .card{background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius);box-shadow:var(--shadow)}
        .card-header{padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;justify-content:space-between}
        .card-title{font-size:14px;font-weight:600}
        .card-body{padding:20px}

        .stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-bottom:24px}
        .stat-card{background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius);padding:18px}
        .stat-card .label{font-size:11.5px;color:var(--gray-500);font-weight:500;margin-bottom:6px}
        .stat-card .value{font-size:26px;font-weight:700;line-height:1}
        .stat-card .sub{font-size:11px;color:var(--gray-500);margin-top:4px}
        .stat-primary .value{color:var(--primary)}
        .stat-success .value{color:var(--success)}
        .stat-danger  .value{color:var(--danger)}
        .stat-warning .value{color:var(--warning)}

        /* ══════════════════════════════════════════
           BUTTONS
           ══════════════════════════════════════════ */
        .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:7px;font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;border:none;text-decoration:none;transition:all .15s}
        .btn-primary{background:var(--primary);color:#fff}
        .btn-primary:hover{background:var(--primary-dark)}
        .btn-success{background:var(--success);color:#fff}
        .btn-danger {background:var(--danger);color:#fff}
        .btn-outline{background:#fff;color:var(--gray-700);border:1px solid var(--gray-300)}
        .btn-outline:hover{background:var(--gray-100)}
        .btn-white{background:#fff;color:var(--primary);font-weight:700}
        .btn-white:hover{background:#f0f4ff}
        .btn-white-outline{background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.4)}
        .btn-white-outline:hover{background:rgba(255,255,255,.2)}
        .btn-sm{padding:5px 11px;font-size:12px}
        .btn-lg{padding:11px 22px;font-size:14px}

        /* ══════════════════════════════════════════
           BADGES
           ══════════════════════════════════════════ */
        .badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}
        .badge-success{background:#d1fae5;color:#065f46}
        .badge-danger {background:#fee2e2;color:#991b1b}
        .badge-warning{background:#fef3c7;color:#92400e}
        .badge-primary{background:var(--primary-light);color:var(--primary)}
        .badge-info   {background:#e0f2fe;color:#0369a1}
        .badge-gray   {background:var(--gray-100);color:var(--gray-700)}

        /* ══════════════════════════════════════════
           TABLE
           ══════════════════════════════════════════ */
        .table-wrap{overflow-x:auto}
        table{width:100%;border-collapse:collapse;font-size:13.5px}
        th{text-align:left;padding:10px 16px;font-size:11.5px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:.04em;background:var(--gray-50);border-bottom:1px solid var(--gray-200)}
        td{padding:13px 16px;border-bottom:1px solid var(--gray-100);vertical-align:middle}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:var(--gray-50)}

        /* ══════════════════════════════════════════
           FORMS
           ══════════════════════════════════════════ */
        .form-group{margin-bottom:16px}
        .form-label,.form-group label{display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:6px}
        label.lbl{display:block;font-size:12px;font-weight:600;color:var(--gray-500);margin-bottom:5px}
        input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=submit]),
        select,textarea{
            width:100%;padding:9px 12px;
            border:1px solid var(--gray-300);border-radius:7px;
            font-size:13.5px;font-family:inherit;outline:none;
            background:#fff;color:var(--gray-900);
            transition:border-color .15s;
        }
        input:focus,select:focus,textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,86,219,.1)}
        input[type=file]{padding:5px;width:100%}
        textarea{resize:vertical;min-height:80px}
        .form-hint {font-size:11.5px;color:var(--gray-500);margin-top:4px}
        .form-error{font-size:11.5px;color:var(--danger);margin-top:4px}
        .form-row {display:grid;grid-template-columns:1fr 1fr;gap:16px}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
        .form-full{grid-column:1/-1}

        /* ══════════════════════════════════════════
           MISC UTILITIES
           ══════════════════════════════════════════ */
        .progress{height:6px;border-radius:3px;background:var(--gray-200);overflow:hidden}
        .progress-fill{height:100%;border-radius:3px;background:var(--primary);transition:width .4s}
        .info-list .row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid var(--gray-100);font-size:13px}
        .info-list .row:last-child{border-bottom:none}
        .info-list .lbl{color:var(--gray-500);font-weight:500}
        .info-list .val{font-weight:600;text-align:right}
        .two-col  {display:grid;grid-template-columns:1fr 1fr;gap:20px}
        .three-col{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
        .page-title{font-size:20px;font-weight:700;margin-bottom:4px}
        .page-sub  {font-size:13px;color:var(--gray-500);margin-bottom:24px}
        .tabs{display:flex;gap:4px;border-bottom:1px solid var(--gray-200);margin-bottom:20px}
        .tab{padding:8px 16px;font-size:13px;font-weight:500;color:var(--gray-500);border-bottom:2px solid transparent;cursor:pointer;text-decoration:none;margin-bottom:-1px}
        .tab:hover{color:var(--primary)}
        .tab.active{color:var(--primary);border-bottom-color:var(--primary);font-weight:600}
        .pagination{display:flex;gap:4px;justify-content:center;margin-top:16px}
        .pagination a,.pagination span{padding:6px 11px;border-radius:6px;border:1px solid var(--gray-200);font-size:13px;text-decoration:none;color:var(--gray-700)}
        .pagination .active{background:var(--primary);color:#fff;border-color:var(--primary)}
        .pagination a:hover{background:var(--gray-100)}
        .empty{text-align:center;padding:48px 20px;color:var(--gray-500)}
        .empty i,.empty-icon{font-size:36px;margin-bottom:10px;opacity:.5;display:block}
        .empty p,.empty-text{font-size:14px}

        /* ══════════════════════════════════════════
           RESPONSIVE
           ══════════════════════════════════════════ */
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%)}
            .sidebar.open{transform:translateX(0);box-shadow:var(--shadow-md)}
            .main{margin-left:0}
            .topbar-toggle{display:block}
            .form-row,.form-grid,.two-col,.three-col{grid-template-columns:1fr}
            .stat-grid{grid-template-columns:1fr 1fr}
        }
    </style>
    @stack('styles')
</head>
<body>

{{-- ═══════════════ SIDEBAR ═══════════════ --}}
<aside class="sidebar" id="sidebar">

    <div class="sidebar-brand">
        <div class="logo"><i class="fas fa-building"></i></div>
        <span class="brand-name">{{ config('app.name','MyHR') }}</span>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Utama</div>
        <a href="{{ route('employee.dashboard') }}"
           class="nav-item {{ request()->routeIs('employee.dashboard') ? 'active' : '' }}">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="{{ route('employee.attendance.index') }}"
           class="nav-item {{ request()->routeIs('employee.attendance.*') ? 'active' : '' }}">
            <i class="fas fa-fingerprint"></i> Absensi
        </a>
        <a href="{{ route('employee.schedule.index') }}"
           class="nav-item {{ request()->routeIs('employee.schedule.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-alt"></i> Jadwal
        </a>
        <a href="{{ route('employee.holiday.index') }}"
           class="nav-item {{ request()->routeIs('employee.holiday.*') ? 'active' : '' }}">
            <i class="fas fa-umbrella-beach"></i> Hari Libur
        </a>

        <div class="nav-section">Pengajuan</div>
        <a href="{{ route('employee.permission.index') }}"
           class="nav-item {{ request()->routeIs('employee.permission.*') ? 'active' : '' }}">
            <i class="fas fa-hand-paper"></i> Izin
        </a>
        <a href="{{ route('employee.leave.index') }}"
           class="nav-item {{ request()->routeIs('employee.leave.*') ? 'active' : '' }}">
            <i class="fas fa-plane-departure"></i> Cuti
        </a>
        <a href="{{ route('employee.overtime.index') }}"
           class="nav-item {{ request()->routeIs('employee.overtime.*') ? 'active' : '' }}">
            <i class="fas fa-clock"></i> Lembur
        </a>

        <div class="nav-section">Laporan</div>
        <a href="{{ route('employee.daily-report.index') }}"
           class="nav-item {{ request()->routeIs('employee.daily-report.*') ? 'active' : '' }}">
            <i class="fas fa-clipboard-list"></i> Laporan Harian
        </a>
        <a href="{{ route('employee.monthly-report.index') }}"
           class="nav-item {{ request()->routeIs('employee.monthly-report.*') ? 'active' : '' }}">
            <i class="fas fa-chart-bar"></i> Laporan Bulanan
        </a>

        <div class="nav-section">Keuangan</div>
        <a href="{{ route('employee.payroll.index') }}"
           class="nav-item {{ request()->routeIs('employee.payroll.*') ? 'active' : '' }}">
            <i class="fas fa-money-check-alt"></i> Payroll
        </a>
        <a href="{{ route('employee.loan.index') }}"
           class="nav-item {{ request()->routeIs('employee.loan.*') ? 'active' : '' }}">
            <i class="fas fa-hand-holding-usd"></i> Pinjaman
        </a>

        <div class="nav-section">Lainnya</div>
        <a href="{{ route('employee.performance.index') }}"
           class="nav-item {{ request()->routeIs('employee.performance.*') ? 'active' : '' }}">
            <i class="fas fa-star"></i> Performa
        </a>
        <a href="{{ route('employee.notes.index') }}"
           class="nav-item {{ request()->routeIs('employee.notes.*') ? 'active' : '' }}">
            <i class="fas fa-bell"></i> Catatan
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">{{ strtoupper(substr(Auth::user()->name ?? 'E', 0, 1)) }}</div>
            <div style="flex:1;min-width:0">
                <div class="user-name">{{ Auth::user()->name ?? 'Employee' }}</div>
                <div class="user-role">{{ Auth::user()->position ?? 'Karyawan' }}</div>
            </div>
        </div>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none">@csrf</form>
        <a href="#" class="nav-item" style="color:var(--danger)"
           onclick="event.preventDefault();document.getElementById('logout-form').submit()">
            <i class="fas fa-sign-out-alt" style="color:var(--danger)"></i> Logout
        </a>
    </div>
</aside>

{{-- ═══════════════ MAIN ═══════════════ --}}
<div class="main">

    <header class="topbar">
        <button class="topbar-toggle"
                onclick="document.getElementById('sidebar').classList.toggle('open')">
            <i class="fas fa-bars"></i>
        </button>

        <nav class="topbar-breadcrumb">
            <a href="{{ route('employee.dashboard') }}">
                <i class="fas fa-home" style="font-size:13px"></i>
            </a>
            @hasSection('breadcrumb')
                <span class="sep">/</span>
                @yield('breadcrumb')
            @endif
        </nav>

        <div class="topbar-actions">
            <span class="topbar-date">{{ now()->isoFormat('ddd, D MMM Y') }}</span>
            <div class="user-avatar" style="cursor:default">
                {{ strtoupper(substr(Auth::user()->name ?? 'E', 0, 1)) }}
            </div>
        </div>
    </header>

    <main class="page-content">
        @if(session('success'))
            <div class="flash flash-success">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="flash flash-error">
                <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            </div>
        @endif
        @if(session('warning'))
            <div class="flash flash-warning">
                <i class="fas fa-exclamation-triangle"></i> {{ session('warning') }}
            </div>
        @endif

        @yield('content')
    </main>
</div>

<script>
document.addEventListener('click', function(e){
    const sb = document.getElementById('sidebar');
    const toggle = document.querySelector('.topbar-toggle');
    if(window.innerWidth <= 768 && sb.classList.contains('open')
       && !sb.contains(e.target) && toggle && !toggle.contains(e.target)){
        sb.classList.remove('open');
    }
});
</script>
@stack('scripts')
</body>
</html>