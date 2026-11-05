@extends('layouts.app')

@section('title', 'Job #' . $job->id)

@section('main')
    @php $tz = 'Asia/Jakarta'; @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.jobs.index', ['tab' => $kind]) }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>{{ $kind === 'failed' ? 'Job Gagal' : 'Job Menunggu' }} #{{ $job->id }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.jobs.index', ['tab' => $kind]) }}">Antrian
                            Job</a></div>
                    <div class="breadcrumb-item">#{{ $job->id }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-8">
                        @if ($kind === 'failed')
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="text-danger">Error</h4>
                                </div>
                                <div class="card-body">
                                    <p class="font-weight-bold text-danger mb-2" style="word-break:break-word">
                                        {{ $job->error }}</p>
                                    <pre class="bg-dark text-light p-3 rounded text-small mb-0"
                                        style="white-space:pre-wrap;word-break:break-word;max-height:480px;overflow:auto">{{ $job->exception }}</pre>
                                </div>
                            </div>
                        @endif

                        <div class="card">
                            <div class="card-header">
                                <h4>Payload</h4>
                                <div class="card-header-action">
                                    <button type="button" class="btn btn-sm btn-light" id="copyPayload"><i
                                            class="far fa-copy"></i> Salin</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <pre class="bg-light p-3 rounded text-small mb-0"
                                    style="white-space:pre-wrap;word-break:break-word;max-height:480px;overflow:auto" id="payload">{{ $payloadJson }}</pre>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Detail</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th>Job</th>
                                        <td class="text-right text-small" style="word-break:break-all">{{ $job->name }}
                                        </td>
                                    </tr>
                                    @if (!empty($job->uuid))
                                        <tr>
                                            <th>UUID</th>
                                            <td class="text-right text-small" style="word-break:break-all">
                                                {{ $job->uuid }}</td>
                                        </tr>
                                    @endif
                                    @if (isset($job->connection))
                                        <tr>
                                            <th>Koneksi</th>
                                            <td class="text-right text-small">{{ $job->connection }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <th>Queue</th>
                                        <td class="text-right text-small">{{ $job->queue }}</td>
                                    </tr>
                                    @if ($job->max_tries)
                                        <tr>
                                            <th>Maks. percobaan</th>
                                            <td class="text-right text-small">{{ $job->max_tries }}</td>
                                        </tr>
                                    @endif
                                    @if ($job->timeout)
                                        <tr>
                                            <th>Timeout</th>
                                            <td class="text-right text-small">{{ $job->timeout }} dtk</td>
                                        </tr>
                                    @endif
                                    @if ($kind === 'pending')
                                        <tr>
                                            <th>Percobaan</th>
                                            <td class="text-right text-small">{{ $job->attempts }}</td>
                                        </tr>
                                        <tr>
                                            <th>Dibuat</th>
                                            <td class="text-right text-small">
                                                {{ $job->created->timezone($tz)->format('d M Y H:i:s') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Siap dijalankan</th>
                                            <td class="text-right text-small">
                                                {{ $job->available->timezone($tz)->format('d M Y H:i:s') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Diproses</th>
                                            <td class="text-right text-small">
                                                {{ $job->reserved ? $job->reserved->timezone($tz)->format('d M Y H:i:s') : '-' }}
                                            </td>
                                        </tr>
                                    @else
                                        <tr>
                                            <th>Gagal</th>
                                            <td class="text-right text-small">
                                                {{ $job->failed->timezone($tz)->format('d M Y H:i:s') }}<br><span
                                                    class="text-muted">{{ $job->failed->diffForHumans() }}</span></td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Aksi</h4>
                            </div>
                            <div class="card-body">
                                @if ($kind === 'failed')
                                    <form method="POST" action="{{ route('superadmin.jobs.retry', $job->id) }}"
                                        class="mb-2">
                                        @csrf
                                        <button class="btn btn-primary btn-block"><i class="fas fa-redo"></i> Retry</button>
                                    </form>
                                @endif
                                @if ($kind === 'failed' || !$job->reserved)
                                    <form method="POST" action="{{ route('superadmin.jobs.destroy', [$kind, $job->id]) }}"
                                        onsubmit="return confirm('Hapus job ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-light btn-block text-danger"><i class="fas fa-trash"></i>
                                            Hapus</button>
                                    </form>
                                @else
                                    <div class="text-small text-muted">Job sedang diproses worker.</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        $('#copyPayload').on('click', function() {
            const btn = $(this);
            navigator.clipboard.writeText(document.getElementById('payload').innerText).then(function() {
                btn.html('<i class="fas fa-check"></i> Tersalin');
                setTimeout(() => btn.html('<i class="far fa-copy"></i> Salin'), 1500);
            });
        });
    </script>
@endpush
