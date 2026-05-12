{{-- resources/views/pages/santri/permission/index.blade.php --}}
@extends('layouts.Santri')
@section('title','Perizinan')

@push('styles')
@include('pages.santri._shared_css')
@endpush

@section('content')
<div class="container-fluid s-page">
    @if(session('success'))<div class="s-alert s-alert-success"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="s-alert s-alert-error"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>{{ session('error') }}</div>@endif

    <div class="s-header">
        <div>
            <h1 class="s-title">📋 Perizinan</h1>
            <p class="s-sub">Riwayat pengajuan izin Anda</p>
        </div>
        <a href="{{ route('santri.permission.create') }}" class="s-btn s-btn-primary s-btn-sm">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Ajukan Izin
        </a>
    </div>

    <div class="s-card">
        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Tanggal Izin</th>
                        <th>Alasan</th>
                        <th>Status</th>
                        <th>Diajukan</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data as $p)
                    @php
                        $st  = $p['status'] ?? 'pending';
                        $bdg = match($st){
                            'approved' => ['s-badge-green','✅ Disetujui'],
                            'rejected' => ['s-badge-red',  '❌ Ditolak'],
                            'cancelled'=> ['s-badge-gray', '🚫 Dibatalkan'],
                            default    => ['s-badge-gold', '⏳ Menunggu'],
                        };
                    @endphp
                    <tr>
                        <td style="white-space:nowrap;font-weight:600;">
                            {{ isset($p['date_permission']) ? \Carbon\Carbon::parse($p['date_permission'])->translatedFormat('d M Y') : '-' }}
                        </td>
                        <td style="max-width:240px;font-size:.85rem;color:var(--gray-700);">{{ \Str::limit($p['reason'] ?? '-', 60) }}</td>
                        <td><span class="s-badge {{ $bdg[0] }}">{{ $bdg[1] }}</span></td>
                        <td style="font-size:.8rem;color:var(--gray-500);">
                            {{ isset($p['created_at']) ? \Carbon\Carbon::parse($p['created_at'])->diffForHumans() : '-' }}
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <a href="{{ route('santri.permission.show', $p['id']) }}" class="s-btn s-btn-outline s-btn-sm">Detail</a>
                                @if($st === 'pending')
                                <form method="POST" action="{{ route('santri.permission.cancel', $p['id']) }}">
                                    @csrf
                                    <button type="submit" class="s-btn s-btn-danger s-btn-sm" onclick="return confirm('Batalkan izin ini?')">Batalkan</button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5"><div class="s-empty">
                        <div class="s-empty-icon"><svg width="24" height="24" fill="none" stroke="#adb5bd" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg></div>
                        <p class="s-empty-title">Belum ada pengajuan izin</p>
                    </div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
