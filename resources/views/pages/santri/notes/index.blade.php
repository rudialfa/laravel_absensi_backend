{{-- resources/views/pages/santri/notes/index.blade.php --}}
@extends('layouts.Santri')
@section('title','Catatan dari Ustadz')

@push('styles')
@include('pages.santri._shared_css')
<style>
.note-card {
    background:#fff; border:1px solid var(--gray-200); border-radius:var(--radius);
    padding:18px 20px; margin-bottom:12px; box-shadow:var(--shadow);
    display:flex; align-items:flex-start; gap:16px;
    transition:box-shadow .2s;
}
.note-card:hover { box-shadow:var(--shadow-md); }
.note-card.unread { border-left:4px solid var(--p); }
.note-card.read   { border-left:4px solid var(--gray-300); }
.note-icon { width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.note-icon-warn   { background:var(--gold-lt);color:var(--gold); }
.note-icon-info   { background:var(--blue-lt);color:var(--blue); }
.note-icon-praise { background:var(--p-light);color:var(--p); }
</style>
@endpush

@section('content')
<div class="container-fluid s-page">
    <div class="s-header">
        <div>
            <h1 class="s-title">📝 Catatan dari Ustadz</h1>
            <p class="s-sub">Pesan dan catatan yang diberikan oleh pengajar</p>
        </div>
    </div>

    {{-- Summary chips --}}
    @if(!empty($summary))
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;">
        <div style="background:#fff;border:1.5px solid var(--gray-200);border-radius:999px;padding:6px 16px;font-size:.8rem;font-weight:600;color:var(--gray-700);">
            Total: {{ $summary['total'] ?? 0 }}
        </div>
        <div style="background:var(--p-light);border:1.5px solid #b2d8c3;border-radius:999px;padding:6px 16px;font-size:.8rem;font-weight:700;color:var(--p);">
            Belum Dibaca: {{ $summary['unread'] ?? 0 }}
        </div>
    </div>
    @endif

    {{-- Search / Filter --}}
    <div class="s-filter">
        <form method="GET" class="s-filter-grid">
            <div>
                <label class="s-label">Tipe</label>
                <select name="type" class="s-control">
                    <option value="">Semua Tipe</option>
                    <option value="warning"  {{ request('type')=='warning' ?'selected':'' }}>Peringatan</option>
                    <option value="info"     {{ request('type')=='info'    ?'selected':'' }}>Informasi</option>
                    <option value="praise"   {{ request('type')=='praise'  ?'selected':'' }}>Pujian</option>
                </select>
            </div>
            <div>
                <label class="s-label">Status Baca</label>
                <select name="is_read" class="s-control">
                    <option value="">Semua</option>
                    <option value="0" {{ request('is_read')==='0'?'selected':'' }}>Belum Dibaca</option>
                    <option value="1" {{ request('is_read')==='1'?'selected':'' }}>Sudah Dibaca</option>
                </select>
            </div>
            <div>
                <label class="s-label">Cari</label>
                <input type="text" name="search" class="s-control" placeholder="Kata kunci…" value="{{ request('search') }}">
            </div>
            <div style="display:flex;gap:8px;align-items:flex-end;">
                <button type="submit" class="s-btn s-btn-primary" style="flex:1;">Filter</button>
                <a href="{{ route('santri.notes.index') }}" class="s-btn s-btn-outline" style="flex:1;">Reset</a>
            </div>
        </form>
    </div>

    {{-- Notes list --}}
    @forelse($notes as $note)
    @php
        $isRead = $note['is_read'] ?? false;
        $type   = $note['type'] ?? 'info';
        $iconClass = match($type){ 'warning'=>'note-icon-warn','praise'=>'note-icon-praise',default=>'note-icon-info' };
        $emoji     = match($type){ 'warning'=>'⚠️','praise'=>'🌟',default=>'ℹ️' };
    @endphp
    <div class="note-card {{ $isRead ? 'read' : 'unread' }}">
        <div class="note-icon {{ $iconClass }}">{{ $emoji }}</div>
        <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <p style="font-size:.9rem;font-weight:{{ $isRead?'500':'700' }};color:var(--gray-900);margin:0 0 4px;">
                    {{ $note['title'] ?? 'Catatan' }}
                    @if(!$isRead)<span style="display:inline-block;width:7px;height:7px;background:var(--p);border-radius:50%;margin-left:6px;vertical-align:middle;"></span>@endif
                </p>
                <span style="font-size:.75rem;color:var(--gray-400);white-space:nowrap;">
                    {{ isset($note['created_at']) ? \Carbon\Carbon::parse($note['created_at'])->diffForHumans() : '-' }}
                </span>
            </div>
            <p style="font-size:.83rem;color:var(--gray-600);margin:0 0 10px;">{{ \Str::limit($note['content'] ?? '', 120) }}</p>
            <div style="display:flex;gap:8px;align-items:center;">
                @if(!empty($note['from']))<span style="font-size:.75rem;color:var(--gray-500);">Dari: <strong>{{ $note['from'] }}</strong></span>@endif
                <a href="{{ route('santri.notes.show', $note['id']) }}" class="s-btn s-btn-outline s-btn-sm" style="margin-left:auto;">Lihat</a>
                @if(!$isRead)
                <form method="POST" action="{{ route('santri.notes.read', $note['id']) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="s-btn s-btn-primary s-btn-sm">Tandai Dibaca</button>
                </form>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="s-card"><div class="s-empty">
        <div class="s-empty-icon"><svg width="24" height="24" fill="none" stroke="#adb5bd" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
        <p class="s-empty-title">Tidak ada catatan</p>
        <p class="s-empty-desc">Belum ada catatan yang sesuai filter.</p>
    </div></div>
    @endforelse
</div>
@endsection
