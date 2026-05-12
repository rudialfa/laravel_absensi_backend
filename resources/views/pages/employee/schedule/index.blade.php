{{-- resources/views/pages/employee/schedule/index.blade.php --}}
@extends('layouts.employee')

@section('title', 'Jadwal Saya')

@push('styles')
<style>
    :root {
        --primary: #2563eb;
        --primary-light: #eff6ff;
        --primary-dark: #1d4ed8;
        --success: #16a34a;
        --success-light: #f0fdf4;
        --warning: #d97706;
        --warning-light: #fffbeb;
        --danger: #dc2626;
        --danger-light: #fef2f2;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-500: #6b7280;
        --gray-700: #374151;
        --gray-900: #111827;
        --radius: 12px;
        --shadow-sm: 0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06);
        --shadow-md: 0 4px 16px rgba(0,0,0,.10);
    }

    .schedule-page { padding: 24px 0; }

    /* ── Header ── */
    .page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 28px;
    }
    .page-title { font-size: 1.5rem; font-weight: 700; color: var(--gray-900); margin: 0 0 4px; }
    .page-subtitle { font-size: .875rem; color: var(--gray-500); margin: 0; }
    .btn-invitation {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--primary);
        color: #fff;
        font-size: .875rem;
        font-weight: 600;
        padding: 10px 18px;
        border-radius: 8px;
        text-decoration: none;
        transition: background .15s;
        white-space: nowrap;
    }
    .btn-invitation:hover { background: var(--primary-dark); color: #fff; }

    /* ── Filter Card ── */
    .filter-card {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        padding: 18px 20px;
        margin-bottom: 20px;
        box-shadow: var(--shadow-sm);
    }
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        align-items: end;
    }
    .filter-group { display: flex; flex-direction: column; gap: 5px; }
    .filter-label { font-size: .75rem; font-weight: 600; color: var(--gray-700); text-transform: uppercase; letter-spacing: .04em; }
    .filter-control {
        padding: 8px 12px;
        border: 1px solid var(--gray-300);
        border-radius: 8px;
        font-size: .875rem;
        color: var(--gray-900);
        background: var(--gray-50);
        transition: border-color .15s, box-shadow .15s;
        width: 100%;
    }
    .filter-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
    .btn-filter {
        padding: 9px 20px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: .875rem;
        font-weight: 600;
        cursor: pointer;
        transition: background .15s;
        width: 100%;
    }
    .btn-filter:hover { background: var(--primary-dark); }
    .btn-reset {
        padding: 9px 16px;
        background: #fff;
        color: var(--gray-700);
        border: 1px solid var(--gray-300);
        border-radius: 8px;
        font-size: .875rem;
        font-weight: 500;
        cursor: pointer;
        transition: background .15s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
    }
    .btn-reset:hover { background: var(--gray-100); color: var(--gray-900); }

    /* ── Schedule Grid ── */
    .schedule-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 16px;
    }

    /* ── Schedule Card ── */
    .schedule-card {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        padding: 20px;
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
        gap: 14px;
        transition: box-shadow .2s, transform .2s;
    }
    .schedule-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }

    .card-header-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
    .schedule-name { font-size: 1rem; font-weight: 700; color: var(--gray-900); margin: 0 0 4px; line-height: 1.3; }
    .schedule-type { font-size: .75rem; color: var(--gray-500); margin: 0; }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: .72rem;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 999px;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .badge-active   { background: var(--success-light); color: var(--success); }
    .badge-inactive { background: var(--gray-100);      color: var(--gray-500); }
    .badge-pending  { background: var(--warning-light); color: var(--warning); }
    .badge-draft    { background: var(--gray-100);      color: var(--gray-500); }

    .card-meta { display: flex; flex-wrap: wrap; gap: 10px; }
    .meta-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: .8rem;
        color: var(--gray-500);
    }
    .meta-item svg { flex-shrink: 0; }

    .card-footer-row { display: flex; align-items: center; justify-content: space-between; margin-top: auto; }
    .btn-detail {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: .8rem;
        font-weight: 600;
        color: var(--primary);
        text-decoration: none;
        padding: 6px 14px;
        border: 1px solid var(--primary);
        border-radius: 7px;
        transition: background .15s, color .15s;
    }
    .btn-detail:hover { background: var(--primary); color: #fff; }

    /* ── Empty State ── */
    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 56px 20px;
        color: var(--gray-500);
    }
    .empty-icon {
        width: 64px; height: 64px;
        background: var(--gray-100);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 16px;
    }
    .empty-title { font-size: 1rem; font-weight: 600; color: var(--gray-700); margin: 0 0 6px; }
    .empty-desc  { font-size: .875rem; margin: 0; }

    /* ── Pagination placeholder ── */
    .pagination-wrap { margin-top: 24px; display: flex; justify-content: center; }

    @media (max-width: 576px) {
        .schedule-grid { grid-template-columns: 1fr; }
        .filter-grid   { grid-template-columns: 1fr 1fr; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid schedule-page">

    {{-- ── Alerts ── --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ── Page Header ── --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Jadwal Saya</h1>
            <p class="page-subtitle">Kelola dan pantau jadwal kerja Anda</p>
        </div>
        <a href="{{ route('employee.schedule.invitations') }}" class="btn-invitation">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            Undangan Jadwal
        </a>
    </div>

    {{-- ── Filter ── --}}
    <div class="filter-card">
        <form method="GET" action="{{ route('employee.schedule.index') }}" id="filterForm">
            <div class="filter-grid">
                <div class="filter-group">
                    <label class="filter-label">Lingkup</label>
                    <select name="scope" class="filter-control">
                        <option value="">Semua</option>
                        <option value="today"     {{ request('scope')=='today'     ? 'selected':'' }}>Hari Ini</option>
                        <option value="this_week" {{ request('scope')=='this_week' ? 'selected':'' }}>Minggu Ini</option>
                        <option value="this_month"{{ request('scope')=='this_month'? 'selected':'' }}>Bulan Ini</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select name="status" class="filter-control">
                        <option value="">Semua Status</option>
                        <option value="active"   {{ request('status')=='active'   ? 'selected':'' }}>Aktif</option>
                        <option value="inactive" {{ request('status')=='inactive' ? 'selected':'' }}>Nonaktif</option>
                        <option value="draft"    {{ request('status')=='draft'    ? 'selected':'' }}>Draft</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Tipe</label>
                    <select name="type" class="filter-control">
                        <option value="">Semua Tipe</option>
                        <option value="shift"  {{ request('type')=='shift'  ? 'selected':'' }}>Shift</option>
                        <option value="flexi"  {{ request('type')=='flexi'  ? 'selected':'' }}>Fleksibel</option>
                        <option value="wfh"    {{ request('type')=='wfh'    ? 'selected':'' }}>WFH</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Tanggal Mulai</label>
                    <input type="date" name="start_date" class="filter-control" value="{{ request('start_date') }}">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Tanggal Akhir</label>
                    <input type="date" name="end_date" class="filter-control" value="{{ request('end_date') }}">
                </div>
                <div class="filter-group">
                    <label class="filter-label">&nbsp;</label>
                    <button type="submit" class="btn-filter">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>
                </div>
                <div class="filter-group">
                    <label class="filter-label">&nbsp;</label>
                    <a href="{{ route('employee.schedule.index') }}" class="btn-reset">Reset</a>
                </div>
            </div>
        </form>
    </div>

    {{-- ── Schedule Cards ── --}}
    <div class="schedule-grid">
        @forelse($schedules as $schedule)
            @php
                $status = $schedule['status'] ?? 'inactive';
                $badgeClass = match($status) {
                    'active'   => 'badge-active',
                    'pending'  => 'badge-pending',
                    'draft'    => 'badge-draft',
                    default    => 'badge-inactive',
                };
                $badgeLabel = match($status) {
                    'active'   => 'Aktif',
                    'pending'  => 'Pending',
                    'draft'    => 'Draft',
                    default    => 'Nonaktif',
                };
                $startDate = isset($schedule['start_date']) ? \Carbon\Carbon::parse($schedule['start_date'])->translatedFormat('d M Y') : '-';
                $endDate   = isset($schedule['end_date'])   ? \Carbon\Carbon::parse($schedule['end_date'])->translatedFormat('d M Y')   : '-';
            @endphp

            <div class="schedule-card">
                <div class="card-header-row">
                    <div>
                        <p class="schedule-name">{{ $schedule['name'] ?? 'Jadwal' }}</p>
                        <p class="schedule-type">{{ ucfirst($schedule['type'] ?? '-') }}</p>
                    </div>
                    <span class="badge {{ $badgeClass }}">
                        <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span>
                        {{ $badgeLabel }}
                    </span>
                </div>

                <div class="card-meta">
                    <span class="meta-item">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        {{ $startDate }} — {{ $endDate }}
                    </span>
                    @if(!empty($schedule['work_hours']))
                    <span class="meta-item">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                        {{ $schedule['work_hours'] }} jam/hari
                    </span>
                    @endif
                    @if(!empty($schedule['location']))
                    <span class="meta-item">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>
                        </svg>
                        {{ $schedule['location'] }}
                    </span>
                    @endif
                </div>

                <div class="card-footer-row">
                    <span style="font-size:.78rem;color:var(--gray-500);">
                        ID #{{ $schedule['id'] ?? '-' }}
                    </span>
                    <a href="{{ route('employee.schedule.show', $schedule['id'] ?? 0) }}" class="btn-detail">
                        Detail
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
        @empty
            <div class="empty-state">
                <div class="empty-icon">
                    <svg width="28" height="28" fill="none" stroke="#9ca3af" stroke-width="1.5" viewBox="0 0 24 24">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </div>
                <p class="empty-title">Belum ada jadwal</p>
                <p class="empty-desc">Tidak ada jadwal yang sesuai filter saat ini.</p>
            </div>
        @endforelse
    </div>

    {{-- ── Pagination ── --}}
    @if(!empty($schedules) && method_exists(collect($schedules), 'links'))
        <div class="pagination-wrap">
            {{ $schedules->appends(request()->query())->links() }}
        </div>
    @endif

</div>
@endsection
