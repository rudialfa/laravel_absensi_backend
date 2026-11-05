@extends('layouts.app')

@section('title', 'Kirim Notifikasi')

@section('main')
    @php
        $tz = 'Asia/Jakarta';
        $hasFilter = collect(request()->except(['tab', 'page']))
            ->filter(fn($v) => $v !== null && $v !== '')
            ->isNotEmpty();
        $userUrl = fn($id) => Route::has('superadmin.users.show') ? route('superadmin.users.show', $id) : null;
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Kirim Notifikasi</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.notifications.create') }}" class="btn btn-primary"><i
                            class="fas fa-bullhorn"></i> Buat Pengumuman</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Konten &amp; Dukungan</div>
                    <div class="breadcrumb-item">Kirim Notifikasi</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @unless ($fcmConfigured)
                    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <b>FCM belum dikonfigurasi</b>
                        (file service account tidak ditemukan). Notifikasi tetap masuk kotak masuk aplikasi, tapi tidak muncul
                        sebagai pop-up di HP.</div>
                @endunless
                @if ($schedulerLate)
                    <div class="alert alert-warning"><i class="fas fa-clock"></i> Ada pengumuman terjadwal yang belum
                        terkirim lewat dari jadwalnya. Pastikan scheduler berjalan (<code>php artisan schedule:work</code>
                        di lokal / cron <code>schedule:run</code> di server).</div>
                @endif

                <ul class="nav nav-tabs mb-3">
                    @foreach ($tabs as $key => $label)
                        <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                                href="{{ route('superadmin.notifications.index', ['tab' => $key]) }}">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>

                {{-- =================== PENGUMUMAN =================== --}}
                @if ($tab === 'broadcasts')
                    <div class="card">
                        <div class="card-body pb-0">
                            <form method="GET" action="{{ route('superadmin.notifications.index') }}">
                                <div class="form-row">
                                    <div class="col-md-3 mb-3">
                                        <select name="status" class="form-control">
                                            <option value="">Semua Status</option>
                                            @foreach ($statuses as $v => $l)
                                                <option value="{{ $v }}" @selected(request('status') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md mb-3"><input type="text" name="search"
                                            value="{{ request('search') }}" class="form-control"
                                            placeholder="Cari judul / isi"></div>
                                    <div class="col-auto mb-3"><button class="btn btn-primary"><i
                                                class="fas fa-search"></i></button></div>
                                </div>
                            </form>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Pengumuman</th>
                                            <th>Status</th>
                                            <th>Waktu Kirim</th>
                                            <th class="text-center">Penerima</th>
                                            <th class="text-center">Push Terkirim</th>
                                            <th style="width:150px">Dibaca</th>
                                            <th class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $b)
                                            @php
                                                $r = $reads[$b->id] ?? null;
                                                $readPct =
                                                    $r && $r->total ? round(($r->read_count / $r->total) * 100) : null;
                                            @endphp
                                            <tr>
                                                <td>
                                                    <a href="{{ route('superadmin.notifications.show', $b->id) }}"
                                                        class="font-weight-bold">{{ $b->title }}</a>
                                                    <div class="text-small text-muted">
                                                        {{ \Illuminate\Support\Str::limit($b->body, 70) }}</div>
                                                    <div class="text-small text-muted">
                                                        {{ $categories[$b->category] ?? $b->category }} · oleh
                                                        {{ $b->creator->name ?? '-' }}</div>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge badge-{{ $statusBadge[$b->status] ?? 'light' }}">{{ $statuses[$b->status] ?? $b->status }}</span>
                                                    @if ($b->retracted_at)
                                                        <div><span class="badge badge-dark">ditarik</span></div>
                                                    @endif
                                                </td>
                                                <td class="text-small text-nowrap">
                                                    @if ($b->finished_at)
                                                        {{ $b->finished_at->timezone($tz)->format('d M Y H:i') }}
                                                    @elseif ($b->scheduled_at)
                                                        <i class="far fa-clock"></i>
                                                        {{ $b->scheduled_at->timezone($tz)->format('d M Y H:i') }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    {{ $b->recipients_count ? number_format($b->recipients_count) : '-' }}
                                                </td>
                                                <td class="text-center">
                                                    @if ($b->recipients_count)
                                                        {{ number_format($b->sent_count) }}
                                                        @if ($b->failed_count)
                                                            <div class="text-small text-danger">{{ $b->failed_count }}
                                                                gagal</div>
                                                        @endif
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($readPct !== null)
                                                        <div class="d-flex align-items-center">
                                                            <div class="progress flex-fill mr-2" style="height:8px">
                                                                <div class="progress-bar bg-success"
                                                                    style="width:{{ $readPct }}%"></div>
                                                            </div>
                                                            <span class="text-small">{{ $readPct }}%</span>
                                                        </div>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-right text-nowrap">
                                                    <a href="{{ route('superadmin.notifications.show', $b->id) }}"
                                                        class="btn btn-sm btn-outline-primary"><i
                                                            class="fas fa-eye"></i></a>
                                                    @if ($b->isEditable())
                                                        <a href="{{ route('superadmin.notifications.edit', $b->id) }}"
                                                            class="btn btn-sm btn-outline-info"><i
                                                                class="fas fa-pen"></i></a>
                                                    @endif
                                                    <a href="{{ route('superadmin.notifications.create', ['copy' => $b->id]) }}"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        title="Kirim ulang / duplikat"><i class="fas fa-copy"></i></a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Belum ada pengumuman.
                                                    Klik "Buat Pengumuman".</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if ($items->hasPages())
                            <div class="card-footer text-right">{{ $items->links() }}</div>
                        @endif
                    </div>

                    {{-- =================== LOG NOTIFIKASI =================== --}}
                @else
                    @php
                        $h = $health;
                        $pushTotal = (int) $h->sent + (int) $h->failed + (int) $h->no_token;
                        $tokenPct = $tokenStats['users']
                            ? round(($tokenStats['with_token'] / $tokenStats['users']) * 100)
                            : 0;
                    @endphp
                    <div class="row">
                        @foreach ([['Notifikasi 24 Jam', number_format((int) $h->total), 'primary', 'fa-bell'], ['Push Berhasil', $pushTotal ? round(($h->sent / $pushTotal) * 100) . '%' : '-', $pushTotal && $h->failed / max(1, $pushTotal) > 0.1 ? 'danger' : 'success', 'fa-paper-plane'], ['Dibaca (24 jam)', $h->total ? round(($h->read_count / $h->total) * 100) . '%' : '-', 'info', 'fa-envelope-open'], ['Pengguna Punya Token HP', $tokenPct . '% (' . number_format($tokenStats['with_token']) . ')', $tokenPct < 50 ? 'warning' : 'success', 'fa-mobile-alt']] as [$label, $value, $color, $icon])
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

                    @if ($types->isNotEmpty())
                        <div class="card">
                            <div class="card-body py-3">
                                <span class="text-small text-muted mr-2">Jenis notifikasi 30 hari:</span>
                                @foreach ($types as $grp => $n)
                                    <a href="{{ route('superadmin.notifications.index', ['tab' => 'logs', 'type' => $grp . '.']) }}"
                                        class="badge badge-light mr-1">{{ $grp }}: {{ number_format($n) }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="card">
                        <div class="card-body pb-0">
                            <form method="GET" action="{{ route('superadmin.notifications.index') }}">
                                <input type="hidden" name="tab" value="logs">
                                <div class="form-row">
                                    <div class="col-md-2 mb-3">
                                        <select name="company_id" class="form-control">
                                            <option value="">Semua Tenant</option>
                                            @foreach ($companies as $c)
                                                <option value="{{ $c->id }}" @selected((string) request('company_id') === (string) $c->id)>
                                                    {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <select name="category" class="form-control">
                                            <option value="">Semua Kategori</option>
                                            @foreach (['chat', 'approval', 'reminder', 'attendance', 'schedule', 'holiday', 'report', 'payroll', 'school', 'subscription', 'system'] as $c)
                                                <option value="{{ $c }}" @selected(request('category') === $c)>
                                                    {{ $c }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <select name="push_status" class="form-control">
                                            <option value="">Semua Push</option>
                                            @foreach ($pushStatus as $v => $l)
                                                <option value="{{ $v }}" @selected(request('push_status') === $v)>
                                                    {{ $l }}</option>
                                            @endforeach
                                            <option value="none" @selected(request('push_status') === 'none')>Belum diproses</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1 mb-3">
                                        <select name="read" class="form-control">
                                            <option value="">Baca</option>
                                            <option value="1" @selected(request('read') === '1')>Dibaca</option>
                                            <option value="0" @selected(request('read') === '0')>Belum</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3"><input type="date" name="date"
                                            value="{{ request('date') }}" class="form-control"></div>
                                    <div class="col-md mb-3"><input type="text" name="search"
                                            value="{{ request('search') }}" class="form-control"
                                            placeholder="Judul / isi / penerima"></div>
                                    <div class="col-auto mb-3">
                                        @if (request('type'))
                                            <input type="hidden" name="type" value="{{ request('type') }}">
                                        @endif
                                        <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                    </div>
                                </div>
                                @if ($hasFilter)
                                    <div class="mb-3"><a
                                            href="{{ route('superadmin.notifications.index', ['tab' => 'logs']) }}"
                                            class="text-small"><i class="fas fa-times"></i> Hapus filter</a>
                                        @if (request('type'))
                                            <span class="text-small text-muted ml-2">jenis: {{ request('type') }}*</span>
                                        @endif
                                    </div>
                                @endif
                            </form>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Waktu</th>
                                            <th>Penerima</th>
                                            <th>Notifikasi</th>
                                            <th>Jenis</th>
                                            <th>Push</th>
                                            <th>Dibaca</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $n)
                                            <tr>
                                                <td class="text-small text-nowrap">
                                                    {{ \Carbon\Carbon::parse($n->created_at)->timezone($tz)->format('d M H:i') }}
                                                </td>
                                                <td class="text-small">
                                                    @if ($n->user && $userUrl($n->user->id))
                                                        <a href="{{ $userUrl($n->user->id) }}">{{ $n->user->name }}</a>
                                                    @else
                                                        {{ $n->user->name ?? '#' . $n->user_id }}
                                                    @endif
                                                    <div class="text-muted">{{ $n->user->role ?? '' }} ·
                                                        {{ $n->company->name ?? '-' }}</div>
                                                </td>
                                                <td>
                                                    <div class="font-weight-bold text-small">{{ $n->title }}</div>
                                                    <div class="text-small text-muted">
                                                        {{ \Illuminate\Support\Str::limit($n->body, 80) }}</div>
                                                </td>
                                                <td class="text-small"><code>{{ $n->type }}</code>
                                                    <div class="text-muted">{{ $n->category }}</div>
                                                </td>
                                                <td>
                                                    @if ($n->push_status)
                                                        <span
                                                            class="badge badge-{{ $pushBadge[$n->push_status] ?? 'light' }}">{{ $pushStatus[$n->push_status] ?? $n->push_status }}</span>
                                                    @else
                                                        <span class="text-muted text-small">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-small">
                                                    {{ $n->read_at ? $n->read_at->timezone($tz)->format('d M H:i') : '-' }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">Tidak ada
                                                    notifikasi.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if ($items->hasPages())
                            <div class="card-footer text-right">{{ $items->links() }}</div>
                        @endif
                    </div>
                    <p class="text-small text-muted">Notifikasi lebih dari 90 hari dihapus otomatis setiap hari jam 02:00.
                        Token HP yang sudah tidak berlaku dikosongkan otomatis saat push gagal.</p>
                @endif
            </div>
        </section>
    </div>
@endsection
