{{-- resources/views/pages/santri/schedule/index.blade.php --}}
@extends('layouts.santri')
@section('title','Jadwal Santri')

@push('styles')
@include('pages.santri._shared_css')
@endpush

@section('content')
<div class="container-fluid s-page">
    @if(session('success'))<div class="s-alert s-alert-success"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="s-alert s-alert-error"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>{{ session('error') }}</div>@endif

    <div class="s-header">
        <div>
            <h1 class="s-title">🗓️ Jadwal Saya</h1>
            <p class="s-sub">Jadwal kegiatan & pengajaran di pesantren</p>
        </div>
        <div style="display:flex;gap:10px;">
            <a href="{{ route('santri.schedule.today') }}" class="s-btn s-btn-gold s-btn-sm">Hari Ini</a>
            <a href="{{ route('santri.schedule.invitations') }}" class="s-btn s-btn-primary s-btn-sm">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Undangan
            </a>
        </div>
    </div>

    {{-- Filter --}}
    <div class="s-filter">
        <form method="GET" class="s-filter-grid">
            <div>
                <label class="s-label">Lingkup</label>
                <select name="scope" class="s-control">
                    <option value="">Semua</option>
                    <option value="today"      {{ request('scope')=='today'?'selected':'' }}>Hari Ini</option>
                    <option value="this_week"  {{ request('scope')=='this_week'?'selected':'' }}>Minggu Ini</option>
                    <option value="this_month" {{ request('scope')=='this_month'?'selected':'' }}>Bulan Ini</option>
                </select>
            </div>
            <div>
                <label class="s-label">Status</label>
                <select name="status" class="s-control">
                    <option value="">Semua Status</option>
                    <option value="active"   {{ request('status')=='active'?'selected':'' }}>Aktif</option>
                    <option value="inactive" {{ request('status')=='inactive'?'selected':'' }}>Nonaktif</option>
                    <option value="draft"    {{ request('status')=='draft'?'selected':'' }}>Draft</option>
                </select>
            </div>
            <div>
                <label class="s-label">Tipe</label>
                <select name="type" class="s-control">
                    <option value="">Semua Tipe</option>
                    <option value="ngaji"   {{ request('type')=='ngaji'?'selected':'' }}>Ngaji</option>
                    <option value="kajian"  {{ request('type')=='kajian'?'selected':'' }}>Kajian</option>
                    <option value="kegiatan"{{ request('type')=='kegiatan'?'selected':'' }}>Kegiatan</option>
                </select>
            </div>
            <div>
                <label class="s-label">Tanggal Mulai</label>
                <input type="date" name="start_date" class="s-control" value="{{ request('start_date') }}">
            </div>
            <div>
                <label class="s-label">Tanggal Akhir</label>
                <input type="date" name="end_date" class="s-control" value="{{ request('end_date') }}">
            </div>
            <div style="display:flex;gap:8px;align-items:flex-end;">
                <button type="submit" class="s-btn s-btn-primary" style="flex:1;">Filter</button>
                <a href="{{ route('santri.schedule.index') }}" class="s-btn s-btn-outline" style="flex:1;">Reset</a>
            </div>
        </form>
    </div>

    {{-- Cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;">
        @forelse($schedules as $sch)
        @php
            $st  = $sch['status'] ?? 'inactive';
            $bdg = match($st){ 'active'=>['s-badge-green','Aktif'],'draft'=>['s-badge-gray','Draft'],default=>['s-badge-gold','Pending'] };
            $s   = isset($sch['start_date']) ? \Carbon\Carbon::parse($sch['start_date'])->translatedFormat('d M Y') : '-';
            $e   = isset($sch['end_date'])   ? \Carbon\Carbon::parse($sch['end_date'])->translatedFormat('d M Y')   : '-';
        @endphp
        <div class="s-card" style="display:flex;flex-direction:column;gap:12px;transition:box-shadow .2s;" onmouseenter="this.style.boxShadow='var(--shadow-md)'" onmouseleave="this.style.boxShadow=''">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <p style="font-size:.95rem;font-weight:700;color:var(--gray-900);margin:0 0 3px;">{{ $sch['name'] ?? 'Jadwal' }}</p>
                    <p style="font-size:.78rem;color:var(--gray-500);margin:0;">{{ ucfirst($sch['type'] ?? '-') }}</p>
                </div>
                <span class="s-badge {{ $bdg[0] }}">{{ $bdg[1] }}</span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <span style="font-size:.8rem;color:var(--gray-500);display:flex;align-items:center;gap:5px;">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    {{ $s }} — {{ $e }}
                </span>
                @if(!empty($sch['ustadz']))<span style="font-size:.8rem;color:var(--gray-500);">👤 {{ $sch['ustadz'] }}</span>@endif
            </div>
            <div style="margin-top:auto;">
                <a href="{{ route('pages.santri.schedule.show', $sch['id']) }}" class="s-btn s-btn-outline s-btn-sm">Detail →</a>
            </div>
        </div>
        @empty
        <div class="s-card" style="grid-column:1/-1;"><div class="s-empty">
            <div class="s-empty-icon">🗓️</div>
            <p class="s-empty-title">Belum ada jadwal</p>
            <p class="s-empty-desc">Tidak ada jadwal yang sesuai filter.</p>
        </div></div>
        @endforelse
    </div>
</div>
@endsection
