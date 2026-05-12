{{-- resources/views/pages/employee/schedule/invitations.blade.php --}}
@extends('layouts.employee')

@section('title', 'Undangan Jadwal')

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

    .inv-page { padding: 24px 0; }

    /* ── Header ── */
    .page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 28px;
    }
    .page-title   { font-size: 1.5rem; font-weight: 700; color: var(--gray-900); margin: 0 0 4px; }
    .page-subtitle{ font-size: .875rem; color: var(--gray-500); margin: 0; }
    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #fff;
        color: var(--gray-700);
        font-size: .875rem;
        font-weight: 600;
        padding: 9px 16px;
        border-radius: 8px;
        border: 1px solid var(--gray-300);
        text-decoration: none;
        transition: background .15s;
    }
    .btn-back:hover { background: var(--gray-100); color: var(--gray-900); }

    /* ── Filter ── */
    .filter-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .filter-chip {
        padding: 7px 16px;
        border-radius: 999px;
        font-size: .8rem;
        font-weight: 600;
        border: 1.5px solid var(--gray-200);
        background: #fff;
        color: var(--gray-500);
        text-decoration: none;
        transition: all .15s;
        cursor: pointer;
    }
    .filter-chip:hover          { border-color: var(--primary); color: var(--primary); }
    .filter-chip.active         { background: var(--primary); border-color: var(--primary); color: #fff; }
    .filter-chip.chip-pending   {}
    .filter-chip.chip-accepted  {}
    .filter-chip.chip-declined  {}

    /* ── List ── */
    .inv-list { display: flex; flex-direction: column; gap: 14px; }

    /* ── Invitation Card ── */
    .inv-card {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        padding: 20px 22px;
        box-shadow: var(--shadow-sm);
        display: flex;
        align-items: flex-start;
        gap: 18px;
        transition: box-shadow .2s;
    }
    .inv-card:hover { box-shadow: var(--shadow-md); }

    /* left accent bar */
    .inv-card.status-pending  { border-left: 4px solid var(--warning); }
    .inv-card.status-accepted { border-left: 4px solid var(--success); }
    .inv-card.status-declined { border-left: 4px solid var(--danger); }

    .inv-icon {
        width: 44px; height: 44px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .inv-icon.pending  { background: var(--warning-light); color: var(--warning); }
    .inv-icon.accepted { background: var(--success-light); color: var(--success); }
    .inv-icon.declined { background: var(--danger-light);  color: var(--danger);  }

    .inv-body { flex: 1; min-width: 0; }
    .inv-name { font-size: 1rem; font-weight: 700; color: var(--gray-900); margin: 0 0 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .inv-meta { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 6px; }
    .meta-item {
        display: flex; align-items: center; gap: 5px;
        font-size: .8rem; color: var(--gray-500);
    }
    .inv-note {
        font-size: .8rem;
        color: var(--gray-500);
        background: var(--gray-50);
        border-radius: 6px;
        padding: 6px 10px;
        margin-top: 8px;
        border-left: 3px solid var(--gray-300);
        font-style: italic;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: .72rem;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 999px;
        white-space: nowrap;
    }
    .badge-pending  { background: var(--warning-light); color: var(--warning); }
    .badge-accepted { background: var(--success-light); color: var(--success); }
    .badge-declined { background: var(--danger-light);  color: var(--danger);  }

    .inv-actions {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 10px;
        flex-shrink: 0;
    }

    /* ── Respond Buttons ── */
    .btn-accept, .btn-decline {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 7px 14px;
        border-radius: 7px;
        font-size: .8rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: filter .15s;
        text-decoration: none;
    }
    .btn-accept  { background: var(--success-light); color: var(--success); }
    .btn-decline { background: var(--danger-light);  color: var(--danger);  }
    .btn-accept:hover  { filter: brightness(.93); }
    .btn-decline:hover { filter: brightness(.93); }

    /* ── Modal ── */
    .modal-overlay {
        display: none;
        position: fixed; inset: 0;
        background: rgba(0,0,0,.45);
        z-index: 1050;
        align-items: center;
        justify-content: center;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
        background: #fff;
        border-radius: 16px;
        padding: 28px;
        width: 100%;
        max-width: 440px;
        box-shadow: 0 20px 60px rgba(0,0,0,.18);
        animation: modalIn .18s ease;
    }
    @keyframes modalIn {
        from { transform: translateY(16px) scale(.97); opacity: 0; }
        to   { transform: none; opacity: 1; }
    }
    .modal-title { font-size: 1.1rem; font-weight: 700; color: var(--gray-900); margin: 0 0 6px; }
    .modal-sub   { font-size: .875rem; color: var(--gray-500); margin: 0 0 20px; }
    .modal-form-group { margin-bottom: 16px; }
    .modal-label { display: block; font-size: .8rem; font-weight: 600; color: var(--gray-700); margin-bottom: 6px; }
    .modal-textarea {
        width: 100%; padding: 10px 12px;
        border: 1px solid var(--gray-300);
        border-radius: 8px; font-size: .875rem;
        resize: vertical; min-height: 80px;
        transition: border-color .15s;
    }
    .modal-textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
    .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
    .btn-modal-cancel {
        padding: 9px 18px; background: #fff;
        border: 1px solid var(--gray-300); border-radius: 8px;
        font-size: .875rem; font-weight: 600; color: var(--gray-700);
        cursor: pointer; transition: background .15s;
    }
    .btn-modal-cancel:hover { background: var(--gray-100); }
    .btn-modal-submit {
        padding: 9px 20px; border: none; border-radius: 8px;
        font-size: .875rem; font-weight: 600; color: #fff;
        cursor: pointer; transition: filter .15s;
    }
    .btn-modal-submit.accept  { background: var(--success); }
    .btn-modal-submit.decline { background: var(--danger);  }
    .btn-modal-submit:hover   { filter: brightness(.9); }

    /* ── Empty State ── */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
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

    .pagination-wrap { margin-top: 24px; display: flex; justify-content: center; }

    @media (max-width: 576px) {
        .inv-card { flex-wrap: wrap; }
        .inv-actions { flex-direction: row; align-items: center; width: 100%; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid inv-page">

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
            <h1 class="page-title">Undangan Jadwal</h1>
            <p class="page-subtitle">Terima atau tolak undangan jadwal yang dikirimkan kepada Anda</p>
        </div>
        <a href="{{ route('employee.schedule.index') }}" class="btn-back">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Kembali ke Jadwal
        </a>
    </div>

    {{-- ── Filter Chips ── --}}
    <div class="filter-bar">
        <a href="{{ route('employee.schedule.invitations') }}"
           class="filter-chip {{ !request('status') ? 'active' : '' }}">
            Semua
        </a>
        <a href="{{ route('employee.schedule.invitations', ['status' => 'pending']) }}"
           class="filter-chip chip-pending {{ request('status') === 'pending' ? 'active' : '' }}">
            ⏳ Menunggu
        </a>
        <a href="{{ route('employee.schedule.invitations', ['status' => 'accepted']) }}"
           class="filter-chip chip-accepted {{ request('status') === 'accepted' ? 'active' : '' }}">
            ✓ Diterima
        </a>
        <a href="{{ route('employee.schedule.invitations', ['status' => 'declined']) }}"
           class="filter-chip chip-declined {{ request('status') === 'declined' ? 'active' : '' }}">
            ✕ Ditolak
        </a>
    </div>

    {{-- ── Invitation List ── --}}
    <div class="inv-list">
        @forelse($items as $item)
            @php
                $status    = $item['status'] ?? 'pending';
                $startDate = isset($item['schedule']['start_date'])
                    ? \Carbon\Carbon::parse($item['schedule']['start_date'])->translatedFormat('d M Y') : '-';
                $endDate   = isset($item['schedule']['end_date'])
                    ? \Carbon\Carbon::parse($item['schedule']['end_date'])->translatedFormat('d M Y')   : '-';
                $invitedAt = isset($item['created_at'])
                    ? \Carbon\Carbon::parse($item['created_at'])->diffForHumans() : '-';
            @endphp

            <div class="inv-card status-{{ $status }}">
                {{-- Icon --}}
                <div class="inv-icon {{ $status }}">
                    @if($status === 'accepted')
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    @elseif($status === 'declined')
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    @else
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    @endif
                </div>

                {{-- Body --}}
                <div class="inv-body">
                    <p class="inv-name">{{ $item['schedule']['name'] ?? 'Undangan Jadwal' }}</p>

                    <div class="inv-meta">
                        <span class="meta-item">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            {{ $startDate }} — {{ $endDate }}
                        </span>
                        @if(!empty($item['schedule']['type']))
                        <span class="meta-item">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
                            </svg>
                            {{ ucfirst($item['schedule']['type']) }}
                        </span>
                        @endif
                        <span class="meta-item">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                            </svg>
                            {{ $invitedAt }}
                        </span>
                        @if(!empty($item['invited_by']))
                        <span class="meta-item">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                            Dari: {{ $item['invited_by'] }}
                        </span>
                        @endif
                    </div>

                    @if(!empty($item['note']))
                        <div class="inv-note">{{ $item['note'] }}</div>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="inv-actions">
                    @php
                        $badgeClass = match($status) {
                            'accepted' => 'badge-accepted',
                            'declined' => 'badge-declined',
                            default    => 'badge-pending',
                        };
                        $badgeLabel = match($status) {
                            'accepted' => 'Diterima',
                            'declined' => 'Ditolak',
                            default    => 'Menunggu',
                        };
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>

                    @if($status === 'pending')
                        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                            <button
                                class="btn-accept"
                                onclick="openRespondModal({{ $item['id'] ?? 0 }}, '{{ addslashes($item['schedule']['name'] ?? 'Jadwal') }}', 'accepted')"
                            >
                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                Terima
                            </button>
                            <button
                                class="btn-decline"
                                onclick="openRespondModal({{ $item['id'] ?? 0 }}, '{{ addslashes($item['schedule']['name'] ?? 'Jadwal') }}', 'declined')"
                            >
                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                                Tolak
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="empty-state">
                <div class="empty-icon">
                    <svg width="28" height="28" fill="none" stroke="#9ca3af" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <p class="empty-title">Tidak ada undangan</p>
                <p class="empty-desc">
                    @if(request('status'))
                        Tidak ada undangan dengan status <strong>{{ request('status') }}</strong>.
                    @else
                        Anda belum menerima undangan jadwal apapun.
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    @if(!empty($items))
        <div class="pagination-wrap">
            {{-- jika API mengembalikan Laravel paginator --}}
        </div>
    @endif
</div>

{{-- ── Respond Modal ── --}}
<div class="modal-overlay" id="respondModal">
    <div class="modal-box">
        <p class="modal-title" id="modalTitle">Konfirmasi</p>
        <p class="modal-sub"  id="modalSub"></p>

        <form id="respondForm" method="POST">
            @csrf
            @method('POST')
            <input type="hidden" name="status" id="modalStatus">

            <div class="modal-form-group">
                <label class="modal-label" for="modalNote">Catatan <span style="color:var(--gray-400);font-weight:400;">(opsional)</span></label>
                <textarea
                    name="note"
                    id="modalNote"
                    class="modal-textarea"
                    placeholder="Tulis catatan jika ada..."
                    maxlength="500"
                ></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" onclick="closeRespondModal()">Batal</button>
                <button type="submit" class="btn-modal-submit" id="modalSubmitBtn">Konfirmasi</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const baseRespondUrl = "{{ rtrim(route('employee.schedule.index'), '/') }}";

    function openRespondModal(id, name, action) {
        const isAccept = action === 'accepted';

        document.getElementById('modalTitle').textContent = isAccept ? 'Terima Undangan' : 'Tolak Undangan';
        document.getElementById('modalSub').textContent   = isAccept
            ? `Anda akan menerima undangan jadwal "${name}".`
            : `Anda akan menolak undangan jadwal "${name}".`;
        document.getElementById('modalStatus').value      = action;
        document.getElementById('modalNote').value        = '';

        const btn = document.getElementById('modalSubmitBtn');
        btn.textContent = isAccept ? 'Terima' : 'Tolak';
        btn.className   = 'btn-modal-submit ' + (isAccept ? 'accept' : 'decline');

        // Sesuaikan action form ke route respond
        document.getElementById('respondForm').action = `{{ url('employee/schedule') }}/${id}/respond`;

        document.getElementById('respondModal').classList.add('open');
    }

    function closeRespondModal() {
        document.getElementById('respondModal').classList.remove('open');
    }

    // Close on overlay click
    document.getElementById('respondModal').addEventListener('click', function(e) {
        if (e.target === this) closeRespondModal();
    });

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeRespondModal();
    });
</script>
@endpush
