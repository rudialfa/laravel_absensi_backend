@extends('layouts.employee')
@section('title','Catatan')
@section('breadcrumb')<span class="current">Catatan</span>@endsection
@section('content')
<div class="page-title">Catatan dari HR</div>
<div class="page-sub">Pesan, peringatan, dan apresiasi dari manajemen.</div>

<div class="metrics">
    <div class="metric"><div class="metric-label">Total Catatan</div><div class="metric-val primary">{{ $summary['total_notes'] ?? 0 }}</div></div>
    <div class="metric"><div class="metric-label">Belum Dibaca</div><div class="metric-val warning">{{ $summary['total_unread'] ?? 0 }}</div></div>
    <div class="metric"><div class="metric-label">Peringatan</div><div class="metric-val danger">{{ $summary['total_warning'] ?? 0 }}</div></div>
    <div class="metric"><div class="metric-label">Pujian</div><div class="metric-val success">{{ $summary['total_praise'] ?? 0 }}</div></div>
    <div class="metric"><div class="metric-label">Performa</div><div class="metric-val info">{{ $summary['total_performance'] ?? 0 }}</div></div>
</div>

<div class="card">
    <div class="card-header" style="margin-bottom:12px">
        <span class="card-title">Semua Catatan</span>
        <form method="GET" style="display:flex;gap:8px">
            <select name="type" class="btn" style="padding:6px 10px;width:auto">
                <option value="">Semua Tipe</option>
                @foreach(['warning','praise','performance','absence','general'] as $t)
                    <option value="{{ $t }}" {{ request('type')===$t?'selected':'' }}>{{ ucfirst($t) }}</option>
                @endforeach
            </select>
            <select name="is_read" class="btn" style="padding:6px 10px;width:auto">
                <option value="">Semua</option>
                <option value="0" {{ request('is_read')==='0'?'selected':'' }}>Belum Dibaca</option>
                <option value="1" {{ request('is_read')==='1'?'selected':'' }}>Sudah Dibaca</option>
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        </form>
    </div>

    @forelse($notes['data'] ?? $notes as $n)
        <a href="{{ route('employee.notes.show', $n['id']) }}"
           style="display:block;padding:14px;border-radius:var(--radius-md);border:1px solid var(--c-border);margin-bottom:10px;text-decoration:none;background:{{ !$n['is_read'] ? 'var(--c-primary-bg)' : 'var(--c-surface)' }};transition:background .15s"
           onmouseenter="this.style.background='var(--c-bg)'" onmouseleave="this.style.background='{{ !$n['is_read'] ? 'var(--c-primary-bg)' : 'var(--c-surface)' }}'">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
                <div style="flex:1">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                        @if(!$n['is_read'])<div style="width:8px;height:8px;border-radius:50%;background:var(--c-primary);flex-shrink:0"></div>@endif
                        <span style="font-size:13px;font-weight:{{ !$n['is_read']?'600':'500' }};color:var(--c-text)">{{ $n['title'] }}</span>
                        @if($n['type']==='warning') <span class="badge badge-danger">Peringatan</span>
                        @elseif($n['type']==='praise') <span class="badge badge-success">Pujian</span>
                        @elseif($n['type']==='performance') <span class="badge badge-info">Performa</span>
                        @elseif($n['type']==='absence') <span class="badge badge-warning">Absensi</span>
                        @else <span class="badge badge-gray">Umum</span>
                        @endif
                    </div>
                    <div style="font-size:12px;color:var(--c-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:500px">{{ $n['note'] }}</div>
                </div>
                <div style="font-size:11px;color:var(--c-hint);white-space:nowrap">
                    {{ \Carbon\Carbon::parse($n['created_at'])->isoFormat('D MMM Y') }}
                </div>
            </div>
        </a>
    @empty
        <div class="empty"><div class="empty-icon">📭</div><div class="empty-text">Tidak ada catatan.</div></div>
    @endforelse

    @if(isset($notes['last_page']) && $notes['last_page'] > 1)
        <div class="pagination">
            @for($p=1;$p<=$notes['last_page'];$p++)
                <a href="{{ request()->fullUrlWithQuery(['page'=>$p]) }}" class="{{ $p==($notes['current_page']??1)?'active':'' }}">{{ $p }}</a>
            @endfor
        </div>
    @endif
</div>
@endsection