@extends('layouts.app')

@section('title', 'Pengumuman: ' . $b->title)

@section('main')
    @php
        $tz = 'Asia/Jakarta';
        $total = (int) ($stats->total ?? 0);
        $readPct = $total ? round(($stats->read_count / $total) * 100) : null;
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.notifications.index') }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ $b->title }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.notifications.index') }}">Kirim
                            Notifikasi</a></div>
                    <div class="breadcrumb-item">#{{ $b->id }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @if ($b->status === 'sending')
                    <div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Sedang dikirim…
                        {{ number_format($b->recipients_count) }} penerima diproses sejauh ini. <a
                            href="{{ url()->current() }}" class="alert-link">Muat ulang</a></div>
                @elseif ($b->status === 'scheduled')
                    <div class="alert alert-info"><i class="far fa-clock"></i> Dijadwalkan
                        {{ $b->scheduled_at->timezone($tz)->translatedFormat('l, d M Y H:i') }} WIB.</div>
                @elseif ($b->status === 'failed')
                    <div class="alert alert-danger"><i class="fas fa-times-circle"></i> Pengiriman gagal:
                        {{ $b->error ?: 'lihat log server' }}</div>
                @endif
                @if ($b->retracted_at)
                    <div class="alert alert-dark"><i class="fas fa-undo"></i> Ditarik dari kotak masuk pengguna pada
                        {{ $b->retracted_at->timezone($tz)->format('d M Y H:i') }}.</div>
                @endif

                <div class="row">
                    @foreach ([['Penerima', number_format($b->recipients_count ?: $estimate['users'] ?? 0) . ($b->recipients_count ? '' : ' (perkiraan)'), 'primary', 'fa-users'], ['Push Terkirim', number_format($b->sent_count), 'success', 'fa-paper-plane'], ['Gagal / Tanpa Token', number_format($b->failed_count) . ' / ' . number_format($b->no_token_count), $b->failed_count ? 'danger' : 'secondary', 'fa-exclamation'], ['Dibaca', $readPct !== null ? $readPct . '% (' . number_format((int) $stats->read_count) . ')' : '-', 'info', 'fa-envelope-open']] as [$label, $value, $color, $icon])
                        <div class="col-6 col-lg-3">
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i>
                                </div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4>{{ $label }}</h4>
                                    </div>
                                    <div class="card-body" style="font-size:17px">{{ $value }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>Isi Pengumuman</h4>
                                <div class="card-header-action"><span
                                        class="badge badge-{{ $statusBadge[$b->status] ?? 'light' }}">{{ $statuses[$b->status] ?? $b->status }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5>{{ $b->title }}</h5>
                                <div style="white-space:pre-line">{{ $b->body }}</div>
                                @if ($b->url)
                                    <div class="mt-2"><a href="{{ $b->url }}" target="_blank" rel="noopener"><i
                                                class="fas fa-link"></i> {{ $b->url }}</a></div>
                                @endif
                            </div>
                        </div>

                        @if ($recent->isNotEmpty())
                            <div class="card">
                                <div class="card-header">
                                    <h4>Penerima Terakhir</h4>
                                    <div class="card-header-action">
                                        <a href="{{ route('superadmin.notifications.index', ['tab' => 'logs', 'type' => 'broadcast.announcement', 'search' => $b->title]) }}"
                                            class="btn btn-sm btn-light">Semua di Log</a>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Penerima</th>
                                                <th>Tenant</th>
                                                <th>Push</th>
                                                <th>Dibaca</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($recent as $n)
                                                <tr>
                                                    <td class="text-small">{{ $n->user->name ?? '#' . $n->user_id }} <span
                                                            class="text-muted">{{ $n->user->role ?? '' }}</span></td>
                                                    <td class="text-small">{{ $n->company->name ?? '-' }}</td>
                                                    <td>
                                                        @if ($n->push_status)
                                                            <span
                                                                class="badge badge-{{ $pushBadge[$n->push_status] ?? 'light' }}">{{ $pushStatus[$n->push_status] ?? $n->push_status }}</span>
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td class="text-small">
                                                        {{ $n->read_at ? $n->read_at->timezone($tz)->format('d M H:i') : '-' }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Detail</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Penerima</th>
                                        <td class="text-right text-small">{{ $targetText }}</td>
                                    </tr>
                                    <tr>
                                        <th>Kategori</th>
                                        <td class="text-right">{{ $categories[$b->category] ?? $b->category }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat</th>
                                        <td class="text-right text-small">
                                            {{ $b->creator->name ?? '-' }}<br>{{ optional($b->created_at)->timezone($tz)->format('d M Y H:i') }}
                                        </td>
                                    </tr>
                                    @if ($b->started_at)
                                        <tr>
                                            <th>Mulai kirim</th>
                                            <td class="text-right text-small">
                                                {{ $b->started_at->timezone($tz)->format('d M Y H:i:s') }}</td>
                                        </tr>
                                    @endif
                                    @if ($b->finished_at)
                                        <tr>
                                            <th>Selesai</th>
                                            <td class="text-right text-small">
                                                {{ $b->finished_at->timezone($tz)->format('d M Y H:i:s') }}</td>
                                        </tr>
                                    @endif
                                </table>
                                @if ($estimate)
                                    <div class="alert alert-light border mt-3 mb-0 text-small">
                                        Perkiraan saat ini: <b>{{ number_format($estimate['users']) }}</b> pengguna di
                                        {{ $estimate['companies'] }} tenant, {{ number_format($estimate['with_token']) }}
                                        punya token HP.
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Aksi</h4>
                            </div>
                            <div class="card-body">
                                @if ($b->isEditable())
                                    <form action="{{ route('superadmin.notifications.send-now', $b->id) }}" method="POST"
                                        class="mb-2"
                                        onsubmit="return confirm('Kirim sekarang ke {{ number_format($estimate['users'] ?? 0) }} pengguna?')">
                                        @csrf
                                        <button class="btn btn-success btn-block" @disabled(!($estimate['users'] ?? 0))><i
                                                class="fas fa-paper-plane"></i> Kirim Sekarang</button>
                                    </form>
                                    <a href="{{ route('superadmin.notifications.edit', $b->id) }}"
                                        class="btn btn-outline-info btn-block mb-2"><i class="fas fa-pen"></i> Edit</a>
                                    <form action="{{ route('superadmin.notifications.cancel', $b->id) }}" method="POST"
                                        class="mb-2" onsubmit="return confirm('Batalkan pengumuman ini?')">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-outline-warning btn-block"><i class="fas fa-ban"></i>
                                            Batalkan</button>
                                    </form>
                                @endif
                                <a href="{{ route('superadmin.notifications.create', ['copy' => $b->id]) }}"
                                    class="btn btn-outline-secondary btn-block mb-2"><i class="fas fa-copy"></i> Kirim Ulang
                                    / Duplikat</a>
                                @if (in_array($b->status, ['sent', 'failed'], true) && !$b->retracted_at && $total)
                                    <button type="button" class="btn btn-outline-danger btn-block mb-2" data-toggle="modal"
                                        data-target="#retractModal"><i class="fas fa-undo"></i> Tarik dari Kotak
                                        Masuk</button>
                                @endif
                                @if ($b->status !== 'sending')
                                    <form action="{{ route('superadmin.notifications.destroy', $b->id) }}" method="POST"
                                        onsubmit="return confirm('Hapus pengumuman ini dari daftar?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-light btn-block text-danger"><i class="fas fa-trash"></i>
                                            Hapus</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        @if ($byCompany->isNotEmpty())
                            <div class="card">
                                <div class="card-header">
                                    <h4>Per Tenant</h4>
                                </div>
                                <div class="card-body">
                                    @foreach ($byCompany as $c)
                                        <div class="d-flex justify-content-between text-small mb-1">
                                            <span>{{ $companyNames[$c->company_id] ?? '-' }}</span>
                                            <span class="text-muted">{{ (int) $c->read_count }}/{{ $c->total }}
                                                dibaca</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade" id="retractModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('superadmin.notifications.retract', $b->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">Tarik dari Kotak Masuk</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">Pengumuman dihapus dari kotak masuk {{ number_format($total) }}
                            pengguna. Pop-up yang sudah muncul di HP <b>tidak bisa</b> ditarik — kirim pengumuman ralat bila
                            perlu.</p>
                        <div class="form-group mb-0">
                            <label>Alasan <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="2" maxlength="500" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Tarik</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function() {
            $('#retractModal').appendTo('body');
        });
        @if ($b->status === 'sending' || ($b->status === 'scheduled' && $b->scheduled_at && $b->scheduled_at->lte(now())))
            setTimeout(() => location.reload(), 5000);
        @endif
    </script>
@endpush
