@extends('layouts.app')

@section('title', 'Detail ' . $tabs[$tab])

@section('main')
    @php
        $fileUrl = $file ? (str_starts_with($file, 'http') ? $file : asset($file)) : null;
        $isImage = $file && preg_match('/\.(jpe?g|png|webp|gif)$/i', $file);
        $para = fn($v) => nl2br(e($v ?: '-'));
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.monitoring.reports.index', ['tab' => $tab, 'company_id' => $item->company_id]) }}"
                        class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ $tabs[$tab] }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.monitoring.reports.index', ['tab' => $tab]) }}">{{ $tabs[$tab] }}</a>
                    </div>
                    <div class="breadcrumb-item">#{{ $item->id }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-8">
                        <div class="card">
                            {{-- ── LAPORAN HARIAN ── --}}
                            @if ($tab === 'daily')
                                <div class="card-header">
                                    <h4>{{ optional($item->date)->translatedFormat('l, d F Y') }}</h4>
                                    <div class="card-header-action">
                                        @if ($item->achievement === null)
                                            <span class="badge badge-light">Pencapaian belum diisi</span>
                                        @else
                                            <span
                                                class="badge badge-{{ $item->is_achieved ? 'success' : 'danger' }}">{{ $item->is_achieved ? 'Tercapai' : 'Tidak tercapai' }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h6 class="small text-muted">Target (pagi)</h6>
                                    <div class="bg-light rounded p-3 mb-3">{!! $para($item->target) !!}</div>
                                    <h6 class="small text-muted">Pencapaian (sore)</h6>
                                    <div class="bg-light rounded p-3 mb-3">{!! $para($item->achievement) !!}</div>
                                    @if ($item->reason_not_achieved)
                                        <h6 class="small text-muted">Alasan tidak tercapai</h6>
                                        <div class="bg-light rounded p-3 mb-3">{!! $para($item->reason_not_achieved) !!}</div>
                                    @endif
                                    <small class="text-muted">Dikirim {{ optional($item->created_at)->format('d M Y H:i') }}
                                        @if ($item->updated_at && $item->updated_at->ne($item->created_at))
                                            · diperbarui {{ $item->updated_at->format('d M Y H:i') }}
                                        @endif
                                    </small>
                                </div>

                                {{-- ── LAPORAN BULANAN ── --}}
                            @elseif ($tab === 'monthly')
                                <div class="card-header">
                                    <h4>{{ $item->period_label }}</h4>
                                    <div class="card-header-action">
                                        <span
                                            class="badge badge-{{ $monthlyBadge[$item->status] ?? 'light' }}">{{ $statusLabels[$item->status] ?? $item->status }}</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    @foreach (['target' => 'Target', 'achievement' => 'Pencapaian', 'problem' => 'Masalah', 'solution' => 'Solusi'] as $field => $label)
                                        <h6 class="small text-muted">{{ $label }}</h6>
                                        <div class="bg-light rounded p-3 mb-3">{!! $para($item->{$field}) !!}</div>
                                    @endforeach
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <th style="width:150px">Dikirim</th>
                                            <td>{{ $item->submitted_at ? $item->submitted_at->format('d M Y H:i') : '-' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Direview</th>
                                            <td>{{ $item->approver->name ?? '-' }}{{ $item->approved_at ? ' · ' . $item->approved_at->format('d M Y H:i') : '' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Skor HR</th>
                                            <td>{{ $item->score ? number_format($item->score, 1) : '-' }}</td>
                                        </tr>
                                    </table>
                                </div>

                                {{-- ── CATATAN HR ── --}}
                            @else
                                <div class="card-header">
                                    <h4>{{ $item->title }}</h4>
                                    <div class="card-header-action">
                                        <span
                                            class="badge badge-{{ $noteBadge[$item->type] ?? 'light' }}">{{ $noteTypes[$item->type] ?? $item->type }}</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="bg-light rounded p-3 mb-3">{!! $para($item->note) !!}</div>
                                    @if ($item->reason)
                                        <h6 class="small text-muted">Alasan</h6>
                                        <div class="bg-light rounded p-3 mb-3">{!! $para($item->reason) !!}</div>
                                    @endif
                                    @if ($item->target_achievement)
                                        <h6 class="small text-muted">Target yang diharapkan</h6>
                                        <div class="bg-light rounded p-3 mb-3">{!! $para($item->target_achievement) !!}</div>
                                    @endif
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <th style="width:150px">Dari</th>
                                            <td>{{ $item->creator->name ?? '-' }} ·
                                                {{ optional($item->created_at)->format('d M Y H:i') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Dibaca karyawan</th>
                                            <td>{{ $item->is_read ? 'Ya, ' . optional($item->read_at)->format('d M Y H:i') : 'Belum' }}
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            @endif
                        </div>

                        @if ($fileUrl)
                            <div class="card">
                                <div class="card-header">
                                    <h4>Lampiran</h4>
                                </div>
                                <div class="card-body">
                                    @if ($isImage)
                                        <a href="{{ $fileUrl }}" target="_blank"><img src="{{ $fileUrl }}"
                                                alt="Lampiran" class="img-fluid rounded border"
                                                style="max-height:420px"></a>
                                    @else
                                        <a href="{{ $fileUrl }}" target="_blank" class="btn btn-light"><i
                                                class="fas fa-paperclip"></i> Buka lampiran</a>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Karyawan</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Nama</th>
                                        <td class="text-right">{{ $item->user->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Jabatan</th>
                                        <td class="text-right">{{ $item->user->position ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Departemen</th>
                                        <td class="text-right">{{ $item->user->department ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Perusahaan</th>
                                        <td class="text-right">
                                            @if ($company)
                                                <a
                                                    href="{{ route('superadmin.tenants.show', $company->id) }}">{{ $company->name }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        @if ($score)
                            <div class="card">
                                <div class="card-header">
                                    <h4>Skor Kinerja Bulan Ini</h4>
                                </div>
                                <div class="card-body text-center">
                                    <div class="h2 text-primary mb-0">{{ number_format((float) $score->final_score, 1) }}
                                    </div>
                                    <div class="text-muted">{{ $score->targets_achieved }} dari
                                        {{ $score->total_targets }} target harian tercapai
                                        ({{ number_format((float) $score->achievement_rate, 1) }}%)</div>
                                </div>
                            </div>
                        @endif

                        <div class="card border border-danger">
                            <div class="card-body">
                                <h6 class="text-danger mb-1">Hapus</h6>
                                <p class="small text-muted">Untuk data ganda atau salah. Lampiran ikut terhapus.</p>
                                <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal"
                                    data-target="#deleteModal"><i class="fas fa-trash"></i> Hapus</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('superadmin.monitoring.reports.destroy', [$tab, $item->id]) }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">Hapus {{ $tabs[$tab] }}</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-0">
                            <label>Alasan <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="2" maxlength="500" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function() {
            $('#deleteModal').appendTo('body');
        });
    </script>
@endpush
