@extends('layouts.app')

@section('title', 'Tambah Hari Libur')

@section('main')
    @php
        $selected = collect(old('company_ids', $presetIds))->map(fn($v) => (string) $v)->all();
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.monitoring.holidays.index') }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Tambah Hari Libur</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.monitoring.holidays.index') }}">Hari
                            Libur</a></div>
                    <div class="breadcrumb-item">Tambah</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <form method="POST" action="{{ route('superadmin.monitoring.holidays.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-12 col-lg-7">
                            <div class="card">
                                <div class="card-header">
                                    <h4>Data Libur</h4>
                                </div>
                                <div class="card-body">
                                    @include('pages.superadmin.monitoring.holidays._form')
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-5">
                            <div class="card">
                                <div class="card-header">
                                    <h4>Berlaku untuk Perusahaan</h4>
                                    <div class="card-header-action">
                                        <button type="button" class="btn btn-sm btn-light" id="check-all">Pilih
                                            semua</button>
                                        <button type="button" class="btn btn-sm btn-light"
                                            id="uncheck-all">Kosongkan</button>
                                    </div>
                                </div>
                                <div class="card-body" style="max-height:420px;overflow:auto">
                                    @error('company_ids')
                                        <div class="text-danger small mb-2">{{ $message }}</div>
                                    @enderror
                                    @forelse ($companies as $c)
                                        <div class="custom-control custom-checkbox mb-1">
                                            <input type="checkbox" name="company_ids[]" value="{{ $c->id }}"
                                                class="custom-control-input js-company" id="c{{ $c->id }}"
                                                @checked(in_array((string) $c->id, $selected, true))>
                                            <label class="custom-control-label" for="c{{ $c->id }}">
                                                {{ $c->name }}
                                                @unless ($c->is_active)
                                                    <span class="badge badge-secondary">suspend</span>
                                                @endunless
                                            </label>
                                        </div>
                                    @empty
                                        <p class="text-muted mb-0">Belum ada tenant perusahaan.</p>
                                    @endforelse
                                </div>
                                <div class="card-footer bg-whitesmoke small text-muted">
                                    <span id="count-selected">0</span> perusahaan dipilih. Perusahaan yang tanggalnya
                                    bentrok dengan libur lain akan dilewati.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-footer text-right">
                            <a href="{{ route('superadmin.monitoring.holidays.index') }}"
                                class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary ml-1"><i class="fas fa-save"></i> Simpan</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const boxes = () => document.querySelectorAll('.js-company');
            const update = () => document.getElementById('count-selected').textContent = [...boxes()].filter(b => b
                .checked).length;
            document.getElementById('check-all').addEventListener('click', () => {
                boxes().forEach(b => b.checked = true);
                update();
            });
            document.getElementById('uncheck-all').addEventListener('click', () => {
                boxes().forEach(b => b.checked = false);
                update();
            });
            boxes().forEach(b => b.addEventListener('change', update));
            update();
        })();
    </script>
@endpush
