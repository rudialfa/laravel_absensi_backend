@extends('layouts.app')

@section('title', $staff->name)

@section('main')
    @php
        $initials = collect(explode(' ', trim($staff->name)))
            ->filter()
            ->take(2)
            ->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');
        $auditUrl = \Illuminate\Support\Facades\Route::has('superadmin.audit-logs')
            ? route('superadmin.audit-logs', ['user_id' => $staff->id])
            : null;
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.staff.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Detail Staff</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.staff.edit', $staff->id) }}" class="btn btn-primary"><i
                            class="fas fa-edit"></i> Edit</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.staff.index') }}">Staff</a></div>
                    <div class="breadcrumb-item">{{ $staff->name }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-body text-center pt-4">
                                <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3"
                                    style="width:88px;height:88px;font-size:30px;font-weight:700">{{ $initials ?: '?' }}
                                </div>
                                <h5 class="mb-1">
                                    {{ $staff->name }}
                                    @if ($isSelf)
                                        <span class="badge badge-primary align-middle">Anda</span>
                                    @endif
                                </h5>
                                <div class="text-muted mb-2">{{ $staff->email }}</div>
                                <span class="badge badge-{{ $staff->is_active ? 'success' : 'secondary' }}">
                                    {{ $staff->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                            <div class="card-body border-top">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>No. HP</th>
                                        <td class="text-right">{{ $staff->phone ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat</th>
                                        <td class="text-right">{{ optional($staff->created_at)->format('d M Y') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Total aksi</th>
                                        <td class="text-right">{{ number_format($totalLogs) }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="card-footer bg-whitesmoke">
                                <a href="{{ route('superadmin.staff.edit', $staff->id) }}"
                                    class="btn btn-primary btn-block">
                                    <i class="fas fa-edit"></i> Edit / Ganti Password
                                </a>
                                @unless ($isSelf)
                                    <form action="{{ route('superadmin.staff.toggle-active', $staff->id) }}" method="POST"
                                        onsubmit="return confirm('{{ $staff->is_active ? 'Nonaktifkan staff ini? Ia langsung keluar dari panel.' : 'Aktifkan kembali staff ini?' }}')">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-block {{ $staff->is_active ? 'btn-warning' : 'btn-success' }}"
                                            @disabled($staff->is_active && $isLastActive)>
                                            <i class="fas {{ $staff->is_active ? 'fa-user-slash' : 'fa-user-check' }}"></i>
                                            {{ $staff->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                        data-target="#deleteModal" @disabled($staff->is_active && $isLastActive)>
                                        <i class="fas fa-trash"></i> Hapus Staff
                                    </button>
                                @endunless
                            </div>
                        </div>

                        @if ($actionCounts->isNotEmpty())
                            <div class="card">
                                <div class="card-header">
                                    <h4>Aksi Terbanyak</h4>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm table-striped mb-0">
                                        @foreach ($actionCounts as $action => $total)
                                            <tr>
                                                <td class="pl-4"><code>{{ $action }}</code></td>
                                                <td class="text-right pr-4">{{ $total }}</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>Aktivitas Terakhir</h4>
                                @if ($auditUrl)
                                    <div class="card-header-action">
                                        <a href="{{ $auditUrl }}" class="btn btn-sm btn-primary">Semua di Audit Log</a>
                                    </div>
                                @endif
                            </div>
                            <div class="card-body">
                                @if ($logs->isEmpty())
                                    <p class="text-muted mb-0">Belum ada aktivitas yang tercatat.</p>
                                @else
                                    <div class="activities">
                                        @foreach ($logs as $log)
                                            <div class="activity">
                                                <div class="activity-icon bg-primary text-white shadow-primary">
                                                    <i class="fas fa-history"></i>
                                                </div>
                                                <div class="activity-detail">
                                                    <div class="mb-2">
                                                        <span
                                                            class="text-job">{{ $log->created_at->diffForHumans() }}</span>
                                                        <span class="bullet"></span>
                                                        <code class="text-small">{{ $log->action }}</code>
                                                    </div>
                                                    <p class="mb-0">{{ $log->description ?: '-' }}</p>
                                                    <small class="text-muted">{{ $log->created_at->format('d M Y H:i') }} ·
                                                        IP {{ $log->ip_address ?: '-' }}</small>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    @unless ($isSelf)
        <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form action="{{ route('superadmin.staff.destroy', $staff->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle"></i> Hapus Staff</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">Riwayat aktivitasnya di Audit Log tetap disimpan.</p>
                            <p>Ketik email <strong>{{ $staff->email }}</strong> untuk konfirmasi:</p>
                            <input type="text" name="confirm_email" id="confirmEmail" class="form-control" autocomplete="off"
                                required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger" id="confirmDeleteBtn" disabled>Hapus</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @push('scripts')
            <script>
                $(function() {
                    $('#deleteModal').appendTo('body');
                    const expected = @json($staff->email);
                    $('#confirmEmail').on('input', function() {
                        $('#confirmDeleteBtn').prop('disabled', this.value.trim() !== expected);
                    });
                });
            </script>
        @endpush
    @endunless
@endsection
