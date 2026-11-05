@extends('layouts.app')

@section('title', 'Edit Hari Libur')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.monitoring.holidays.index', ['year' => $holiday->start_date?->year]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Edit Hari Libur</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.monitoring.holidays.index') }}">Hari
                            Libur</a></div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <form method="POST" action="{{ route('superadmin.monitoring.holidays.update', $holiday->id) }}">
                            @csrf
                            @method('PUT')
                            <div class="card">
                                <div class="card-header">
                                    <h4>{{ $company->name ?? '-' }}</h4>
                                </div>
                                <div class="card-body">
                                    @include('pages.superadmin.monitoring.holidays._form')

                                    @if ($siblings->isNotEmpty())
                                        <div class="custom-control custom-checkbox mt-2">
                                            <input type="hidden" name="apply_siblings" value="0">
                                            <input type="checkbox" name="apply_siblings" value="1"
                                                class="custom-control-input" id="apply_siblings"
                                                @checked(old('apply_siblings'))>
                                            <label class="custom-control-label" for="apply_siblings">
                                                Terapkan juga ke {{ $siblings->count() }} perusahaan lain yang punya libur
                                                sama
                                            </label>
                                        </div>
                                    @endif
                                </div>
                                <div class="card-footer text-right">
                                    <a href="{{ route('superadmin.monitoring.holidays.index', ['year' => $holiday->start_date?->year]) }}"
                                        class="btn btn-secondary">Batal</a>
                                    <button type="submit" class="btn btn-primary ml-1"><i class="fas fa-save"></i> Simpan
                                        Perubahan</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Informasi</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Periode</th>
                                        <td class="text-right">{{ $holiday->period_label }}</td>
                                    </tr>
                                    <tr>
                                        <th>Pengingat H-1</th>
                                        <td class="text-right">
                                            {{ $holiday->reminder_sent_at ? $holiday->reminder_sent_at->format('d M Y H:i') : 'belum' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat</th>
                                        <td class="text-right">{{ optional($holiday->created_at)->format('d M Y') }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        @if ($siblings->isNotEmpty())
                            <div class="card">
                                <div class="card-header">
                                    <h4>Libur Sama di Perusahaan Lain</h4>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm table-striped mb-0">
                                        @foreach ($siblings as $s)
                                            <tr>
                                                <td class="pl-4"><a
                                                        href="{{ route('superadmin.monitoring.holidays.edit', $s->id) }}">{{ $companyNames[$s->company_id] ?? '#' . $s->company_id }}</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                            </div>
                        @endif

                        <div class="card border border-danger">
                            <div class="card-body">
                                <h6 class="text-danger mb-2">Hapus Libur</h6>
                                <form action="{{ route('superadmin.monitoring.holidays.destroy', $holiday->id) }}"
                                    method="POST" onsubmit="return confirm('Hapus libur ini?')">
                                    @csrf
                                    @method('DELETE')
                                    @if ($siblings->isNotEmpty())
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" name="apply_siblings" value="1"
                                                class="custom-control-input" id="del_siblings">
                                            <label class="custom-control-label small" for="del_siblings">Hapus juga dari
                                                {{ $siblings->count() }} perusahaan lain</label>
                                        </div>
                                    @endif
                                    <div class="custom-control custom-checkbox mb-3">
                                        <input type="hidden" name="notify" value="0">
                                        <input type="checkbox" name="notify" value="1" class="custom-control-input"
                                            id="del_notify" checked>
                                        <label class="custom-control-label small" for="del_notify">Kabari karyawan bahwa
                                            libur dibatalkan</label>
                                    </div>
                                    <button class="btn btn-outline-danger btn-block"><i class="fas fa-trash"></i>
                                        Hapus</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
