{{-- resources/views/pages/santri/schedule/invitations.blade.php --}}
@extends('layouts.santri')
@section('title','Undangan Jadwal')

@push('styles')
@include('pages.santri._shared_css')
<style>
.inv-card { background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius);padding:18px 20px;margin-bottom:12px;box-shadow:var(--shadow);display:flex;align-items:flex-start;gap:16px;transition:box-shadow .2s; }
.inv-card:hover { box-shadow:var(--shadow-md); }
.inv-card.pending  { border-left:4px solid var(--gold); }
.inv-card.accepted { border-left:4px solid var(--p); }
.inv-card.declined { border-left:4px solid var(--red); }
</style>
@endpush

@section('content')
<div class="container-fluid s-page">
    @if(session('success'))<div class="s-alert s-alert-success"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="s-alert s-alert-error"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>{{ session('error') }}</div>@endif

    <div class="s-header">
        <div>
            <h1 class="s-title">📨 Undangan Jadwal</h1>
            <p class="s-sub">Terima atau tolak undangan jadwal dari ustadz</p>
        </div>
        <a href="{{ route('pages.santri.schedule.index') }}" class="s-btn s-btn-outline s-btn-sm">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Kembali
        </a>
    </div>

    {{-- Filter chips --}}
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
        @foreach([''=> 'Semua','pending'=>'⏳ Menunggu','accepted'=>'✅ Diterima','declined'=>'❌ Ditolak'] as $val=>$lbl)
        <a href="{{ route('pages.santri.schedule.invitations', $val?['status'=>$val]:[]) }}"
           style="padding:7px 16px;border-radius:999px;font-size:.8rem;font-weight:600;text-decoration:none;border:1.5px solid {{ request('status')===$val && ($val||!request('status')) ? 'var(--p)':'var(--gray-200)' }};background:{{ request('status')===$val && ($val||!request('status'))? 'var(--p)':'#fff' }};color:{{ request('status')===$val && ($val||!request('status'))? '#fff':'var(--gray-600)' }};">
            {{ $lbl }}
        </a>
        @endforeach
    </div>

    @forelse($items as $item)
    @php
        $st = $item['status'] ?? 'pending';
        $bdg = match($st){ 'accepted'=>['s-badge-green','✅ Diterima'],'declined'=>['s-badge-red','❌ Ditolak'],default=>['s-badge-gold','⏳ Menunggu'] };
        $s   = isset($item['schedule']['start_date']) ? \Carbon\Carbon::parse($item['schedule']['start_date'])->translatedFormat('d M Y') : '-';
        $e   = isset($item['schedule']['end_date'])   ? \Carbon\Carbon::parse($item['schedule']['end_date'])->translatedFormat('d M Y')   : '-';
    @endphp
    <div class="inv-card {{ $st }}">
        <div style="width:42px;height:42px;border-radius:10px;background:{{ $st==='accepted'?'var(--p-light)':($st==='declined'?'var(--red-lt)':'var(--gold-lt)') }};display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.2rem;">
            {{ $st==='accepted'?'✅':($st==='declined'?'❌':'📨') }}
        </div>
        <div style="flex:1;min-width:0;">
            <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                <p style="font-size:.95rem;font-weight:700;color:var(--gray-900);margin:0;">{{ $item['schedule']['name'] ?? 'Undangan Jadwal' }}</p>
                <span class="s-badge {{ $bdg[0] }}">{{ $bdg[1] }}</span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:8px;">
                <span style="font-size:.8rem;color:var(--gray-500);">📅 {{ $s }} — {{ $e }}</span>
                @if(!empty($item['schedule']['type']))<span style="font-size:.8rem;color:var(--gray-500);">• {{ ucfirst($item['schedule']['type']) }}</span>@endif
                <span style="font-size:.8rem;color:var(--gray-400);">{{ isset($item['created_at'])?  \Carbon\Carbon::parse($item['created_at'])->diffForHumans():'-' }}</span>
            </div>
            @if(!empty($item['note']))<div style="background:var(--gray-50);border-left:3px solid var(--gray-300);padding:6px 10px;border-radius:0 6px 6px 0;font-size:.82rem;color:var(--gray-600);font-style:italic;margin-bottom:8px;">{{ $item['note'] }}</div>@endif
            @if($st === 'pending')
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button onclick="openModal({{ $item['id'] }},'{{ addslashes($item['schedule']['name'] ?? '') }}','accepted')" style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;background:var(--p-light);color:var(--p);border:none;border-radius:7px;font-size:.8rem;font-weight:700;cursor:pointer;">✅ Terima</button>
                <button onclick="openModal({{ $item['id'] }},'{{ addslashes($item['schedule']['name'] ?? '') }}','declined')" style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;background:var(--red-lt);color:var(--red);border:none;border-radius:7px;font-size:.8rem;font-weight:700;cursor:pointer;">❌ Tolak</button>
            </div>
            @endif
        </div>
    </div>
    @empty
    <div class="s-card"><div class="s-empty">
        <div class="s-empty-icon">📨</div>
        <p class="s-empty-title">Tidak ada undangan</p>
        <p class="s-empty-desc">Belum ada undangan jadwal yang masuk.</p>
    </div></div>
    @endforelse
</div>

{{-- Modal --}}
<div id="respondModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:28px;width:100%;max-width:420px;box-shadow:var(--shadow-md);margin:16px;">
        <p id="mTitle" style="font-size:1.1rem;font-weight:700;color:var(--gray-900);margin:0 0 5px;"></p>
        <p id="mSub"   style="font-size:.85rem;color:var(--gray-500);margin:0 0 18px;"></p>
        <form id="mForm" method="POST">
            @csrf
            <input type="hidden" name="status" id="mStatus">
            <div style="margin-bottom:16px;">
                <label class="s-label">Catatan (opsional)</label>
                <textarea name="note" id="mNote" class="s-control" rows="3" placeholder="Tulis catatan jika ada…" maxlength="500"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="closeModal()" style="padding:9px 18px;background:#fff;border:1.5px solid var(--gray-300);border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer;">Batal</button>
                <button type="submit" id="mSubmit" style="padding:9px 20px;border:none;border-radius:8px;font-size:.875rem;font-weight:700;color:#fff;cursor:pointer;"></button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openModal(id, name, action) {
    const accept = action === 'accepted';
    document.getElementById('mTitle').textContent  = accept ? 'Terima Undangan' : 'Tolak Undangan';
    document.getElementById('mSub').textContent    = `${accept?'Anda akan menerima':'Anda akan menolak'} undangan "${name}".`;
    document.getElementById('mStatus').value       = action;
    document.getElementById('mNote').value         = '';
    document.getElementById('mSubmit').textContent = accept ? 'Terima' : 'Tolak';
    document.getElementById('mSubmit').style.background = accept ? 'var(--p)' : 'var(--red)';
    document.getElementById('mForm').action = `{{ url('santri/schedule') }}/${id}/respond`;
    document.getElementById('respondModal').style.display = 'flex';
}
function closeModal() { document.getElementById('respondModal').style.display = 'none'; }
document.getElementById('respondModal').addEventListener('click', e => { if(e.target===e.currentTarget) closeModal(); });
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });
</script>
@endpush
