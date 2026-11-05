@extends('layouts.app')

@section('title', 'Jadwal Sholat')

@section('main')
    @php
        $tz = 'Asia/Jakarta';
        $today = now($tz)->toDateString();
        $days = $month->daysInMonth;
        $daysThis = $thisMonth->daysInMonth;
        $daysNext = $nextMonth->daysInMonth;
        $filled = $rows->count();
        $manual = $rows->where('source', 'manual')->count();
        $cov = fn($n, $total) => $n >= $total ? 'success' : ($n > 0 ? 'warning' : 'danger');
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Jadwal Sholat</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Sistem</div>
                    <div class="breadcrumb-item">Jadwal Sholat</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <p class="section-lead">
                    Jadwal sholat per kota (metode KEMENAG, sumber API Aladhan) yang dipakai aplikasi santri &amp; ustadz.
                    Kota diambil dari data tenant; tenant tanpa kota memakai <b>{{ $fallbackCity }}</b>.
                </p>

                @if ($noCityTenants->isNotEmpty())
                    <div class="alert alert-warning">
                        <i class="fas fa-map-marker-alt"></i> <b>{{ $noCityTenants->count() }} tenant belum mengisi
                            kota</b>, jadwal sholatnya memakai {{ $fallbackCity }} (bisa selisih beberapa menit):
                        @foreach ($noCityTenants as $t)
                            <a href="{{ route('superadmin.tenants.edit', $t->id) }}"
                                class="alert-link">{{ $t->name }}</a>
                            @if (!$loop->last)
                                ,
                            @endif
                        @endforeach
                    </div>
                @endif

                <div class="row">
                    {{-- ======== DAFTAR KOTA ======== --}}
                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Kota</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Kota</th>
                                            <th class="text-center" title="{{ $thisMonth->translatedFormat('F') }}">
                                                {{ $thisMonth->translatedFormat('M') }}</th>
                                            <th class="text-center" title="{{ $nextMonth->translatedFormat('F') }}">
                                                {{ $nextMonth->translatedFormat('M') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($cities as $c)
                                            <tr class="{{ $c['city'] === $selectedCity ? 'table-active' : '' }}">
                                                <td>
                                                    <a href="{{ route('superadmin.prayers.index', ['city' => $c['city'], 'month' => $month->format('Y-m')]) }}"
                                                        class="font-weight-bold">{{ $c['city'] }}</a>
                                                    <div class="text-small text-muted">
                                                        {{ $c['tenants'] ? $c['tenants'] . ' tenant' : 'tidak dipakai tenant' }}
                                                        @if ($c['last_date'])
                                                            · s/d
                                                            {{ \Carbon\Carbon::parse($c['last_date'])->format('d M Y') }}
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="text-center"><span
                                                        class="badge badge-{{ $cov($c['this_month'], $daysThis) }}">{{ $c['this_month'] }}/{{ $daysThis }}</span>
                                                </td>
                                                <td class="text-center"><span
                                                        class="badge badge-{{ $c['tenants'] ? $cov($c['next_month'], $daysNext) : 'light' }}">{{ $c['next_month'] }}/{{ $daysNext }}</span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center text-muted py-3">Belum ada kota.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer text-small text-muted">
                                Bulan yang belum lengkap diambil otomatis dari API saat aplikasi dibuka (lebih lambat).
                                Siapkan lebih dulu dengan tombol <b>Ambil dari API</b> atau command <code>php artisan
                                    prayers:sync</code>.
                            </div>
                        </div>

                        {{-- Ambil dari API --}}
                        <div class="card">
                            <div class="card-header">
                                <h4>Ambil dari API</h4>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('superadmin.prayers.fetch') }}"
                                    onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> Mengambil…';">
                                    @csrf
                                    <div class="form-group">
                                        <label>Kota</label>
                                        <input type="text" name="city" value="{{ old('city', $selectedCity) }}"
                                            class="form-control" list="cityList" required maxlength="100">
                                        <datalist id="cityList">
                                            @foreach ($cities as $c)
                                                <option value="{{ $c['city'] }}">
                                            @endforeach
                                        </datalist>
                                        <small class="text-muted">Nama kota/kabupaten dalam bahasa Indonesia, contoh:
                                            Magelang, Yogyakarta.</small>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-7">
                                            <label>Mulai bulan</label>
                                            <input type="month" name="month"
                                                value="{{ old('month', $month->format('Y-m')) }}" class="form-control"
                                                required>
                                        </div>
                                        <div class="form-group col-5">
                                            <label>Jumlah</label>
                                            <select name="months" class="form-control">
                                                @foreach ([1, 2, 3, 6, 12] as $n)
                                                    <option value="{{ $n }}" @selected((int) old('months', 1) === $n)>
                                                        {{ $n }} bulan</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="custom-control custom-checkbox mb-3">
                                        <input type="checkbox" name="overwrite" value="1" id="overwrite"
                                            class="custom-control-input">
                                        <label for="overwrite" class="custom-control-label text-small">Timpa juga hari yang
                                            sudah dikoreksi manual</label>
                                    </div>
                                    <button class="btn btn-primary btn-block"><i class="fas fa-cloud-download-alt"></i>
                                        Ambil Jadwal</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- ======== KALENDER 1 KOTA × 1 BULAN ======== --}}
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ $selectedCity }} — {{ $month->translatedFormat('F Y') }}</h4>
                                <div class="card-header-action d-flex align-items-center">
                                    <a href="{{ route('superadmin.prayers.index', ['city' => $selectedCity, 'month' => $month->copy()->subMonthNoOverflow()->format('Y-m')]) }}"
                                        class="btn btn-sm btn-light mr-1"><i class="fas fa-chevron-left"></i></a>
                                    <form method="GET" action="{{ route('superadmin.prayers.index') }}" class="mr-1">
                                        <input type="hidden" name="city" value="{{ $selectedCity }}">
                                        <input type="month" name="month" value="{{ $month->format('Y-m') }}"
                                            class="form-control form-control-sm" onchange="this.form.submit()">
                                    </form>
                                    <a href="{{ route('superadmin.prayers.index', ['city' => $selectedCity, 'month' => $month->copy()->addMonthNoOverflow()->format('Y-m')]) }}"
                                        class="btn btn-sm btn-light mr-2"><i class="fas fa-chevron-right"></i></a>
                                    <a href="{{ route('superadmin.prayers.export', ['city' => $selectedCity, 'month' => $month->format('Y-m')]) }}"
                                        class="btn btn-sm btn-outline-success" title="Unduh CSV"><i
                                            class="fas fa-file-excel"></i></a>
                                </div>
                            </div>
                            <div class="card-body pb-0">
                                <div class="d-flex flex-wrap align-items-center mb-2">
                                    <span
                                        class="badge badge-{{ $cov($filled, $days) }} mr-2">{{ $filled }}/{{ $days }}
                                        hari tersedia</span>
                                    @if ($manual)
                                        <span class="badge badge-info mr-2">{{ $manual }} dikoreksi manual</span>
                                    @endif
                                    @if ($cityTenants->isNotEmpty())
                                        <span class="text-small text-muted">Dipakai:
                                            {{ $cityTenants->pluck('name')->implode(', ') }}</span>
                                    @endif
                                </div>
                                @if ($filled < $days)
                                    <form method="POST" action="{{ route('superadmin.prayers.fetch') }}"
                                        class="mb-3">
                                        @csrf
                                        <input type="hidden" name="city" value="{{ $selectedCity }}">
                                        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                                        <input type="hidden" name="months" value="1">
                                        <button class="btn btn-sm btn-outline-primary"><i
                                                class="fas fa-cloud-download-alt"></i> Lengkapi bulan ini dari API</button>
                                    </form>
                                @endif
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0 text-center">
                                        <thead>
                                            <tr>
                                                <th class="text-left">Tanggal</th>
                                                @foreach ($times as $f => $label)
                                                    <th>{{ $label }}</th>
                                                @endforeach
                                                <th>Sumber</th>
                                                <th class="text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @for ($d = 1; $d <= $days; $d++)
                                                @php
                                                    $date = $month->copy()->day($d);
                                                    $key = $date->toDateString();
                                                    $p = $rows[$key] ?? null;
                                                    $isToday = $key === $today;
                                                    $payload = ['city' => $selectedCity, 'date' => $key];
                                                    foreach ($times as $f => $l) {
                                                        $payload[$f] = $p ? $p->hm($f) : '';
                                                    }
                                                @endphp
                                                <tr
                                                    class="{{ $isToday ? 'table-primary' : '' }} {{ $date->isFriday() ? 'font-weight-bold' : '' }}">
                                                    <td class="text-left text-nowrap">
                                                        {{ $date->format('d') }} <span
                                                            class="text-small text-muted">{{ $date->translatedFormat('D') }}</span>
                                                        @if ($isToday)
                                                            <span class="badge badge-primary">hari ini</span>
                                                        @endif
                                                    </td>
                                                    @if ($p)
                                                        @foreach ($times as $f => $l)
                                                            <td>{{ $p->hm($f) ?? '-' }}</td>
                                                        @endforeach
                                                        <td><span
                                                                class="badge badge-{{ $p->source === 'manual' ? 'info' : 'light' }}">{{ $p->source === 'manual' ? 'manual' : 'API' }}</span>
                                                        </td>
                                                    @else
                                                        <td colspan="{{ count($times) + 1 }}"
                                                            class="text-muted text-small">belum ada</td>
                                                    @endif
                                                    <td class="text-right text-nowrap">
                                                        <button type="button" class="btn btn-sm btn-light js-edit"
                                                            data-payload='@json($payload)'
                                                            title="{{ $p ? 'Koreksi' : 'Isi manual' }}"><i
                                                                class="fas fa-{{ $p ? 'pen' : 'plus' }}"></i></button>
                                                        @if ($p)
                                                            <form
                                                                action="{{ route('superadmin.prayers.destroy', $p->id) }}"
                                                                method="POST" class="d-inline"
                                                                onsubmit="return confirm('Hapus jadwal {{ $date->format('d M Y') }}?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button class="btn btn-sm btn-light text-danger"
                                                                    title="Hapus"><i class="fas fa-trash"></i></button>
                                                            </form>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endfor
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @if ($filled)
                                <div class="card-footer text-right">
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal"
                                        data-target="#deleteMonthModal"><i class="fas fa-trash"></i> Hapus jadwal bulan
                                        ini</button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- Modal isi / koreksi 1 hari --}}
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('superadmin.prayers.save') }}">
                @csrf
                <input type="hidden" name="city" id="eCity">
                <input type="hidden" name="date" id="eDate">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Jadwal <span id="eTitle"></span></h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-row">
                            @foreach ($times as $f => $label)
                                <div class="form-group col-4">
                                    <label>{{ $label }}</label>
                                    <input type="time" name="{{ $f }}" id="e_{{ $f }}"
                                        class="form-control" required>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-small text-muted mb-0">Disimpan sebagai <b>manual</b> dan tidak ditimpa saat
                            mengambil ulang dari API (kecuali dipilih).</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button class="btn btn-primary">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal hapus 1 bulan --}}
    <div class="modal fade" id="deleteMonthModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('superadmin.prayers.destroy-month') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="city" value="{{ $selectedCity }}">
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">Hapus {{ $selectedCity }}
                            {{ $month->translatedFormat('F Y') }}</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">{{ $filled }} hari akan dihapus, termasuk koreksi manual.
                            Aplikasi akan mengambil ulang dari API saat dibuka.</p>
                        <div class="form-group mb-0">
                            <label>Alasan <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="2" maxlength="500" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button class="btn btn-danger">Hapus</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function() {
            $('#editModal, #deleteMonthModal').appendTo('body');

            $('.js-edit').on('click', function() {
                const p = $(this).data('payload');
                $('#eCity').val(p.city);
                $('#eDate').val(p.date);
                $('#eTitle').text(p.city + ' · ' + new Date(p.date + 'T00:00:00').toLocaleDateString(
                    'id-ID', {
                        weekday: 'long',
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric'
                    }));
                @foreach (array_keys($times) as $f)
                    $('#e_{{ $f }}').val(p.{{ $f }} || '');
                @endforeach
                $('#editModal').modal('show');
            });
        });
    </script>
@endpush
