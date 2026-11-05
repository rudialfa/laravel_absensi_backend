@extends('layouts.app')

@section('title', 'Tiket #' . $ticket->id)

@section('main')
    @php
        $Ctrl = \App\Http\Controllers\Web\SuperAdmin\SuperAdminSupportTicketController::class;
        $isImage = fn($p) => $p && preg_match('/\.(jpe?g|png|webp|gif)$/i', $p);
        $tz = 'Asia/Jakarta';
        $userUrl = $ticket->user && Route::has('superadmin.users.show') ? route('superadmin.users.show', $ticket->user->id) : null;
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.support-tickets.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Tiket #{{ $ticket->id }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.support-tickets.index') }}">Tiket Bantuan</a></div>
                    <div class="breadcrumb-item">#{{ $ticket->id }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-8">
                        {{-- Pesan awal --}}
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ $ticket->subject }}</h4>
                                <div class="card-header-action">
                                    <span class="badge badge-{{ $priorityBadge[$ticket->priority] ?? 'light' }}">{{ $priorities[$ticket->priority] ?? $ticket->priority }}</span>
                                    <span class="badge badge-{{ $statusBadge[$ticket->status] ?? 'light' }}">{{ $statuses[$ticket->status] ?? $ticket->status }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="text-muted text-small mb-2">
                                    {{ $ticket->user->name ?? '-' }} ({{ $ticket->company->name ?? 'tanpa tenant' }})
                                    · {{ $ticket->created_at->timezone($tz)->format('d M Y H:i') }}
                                </p>
                                <div style="white-space: pre-line">{{ $ticket->message }}</div>
                            </div>
                        </div>

                        {{-- Percakapan --}}
                        <div class="card">
                            <div class="card-header"><h4>Percakapan ({{ $replies->where('is_internal_note', false)->count() }})</h4></div>
                            <div class="card-body">
                                @forelse ($replies as $reply)
                                    @php
                                        $fromCustomer = (int) $reply->user_id === (int) $ticket->user_id;
                                        $url = $Ctrl::fileUrl($reply->attachment);
                                    @endphp
                                    <div class="mb-3 p-3 rounded {{ $reply->is_internal_note ? 'bg-warning-light border border-warning' : ($fromCustomer ? 'bg-light' : 'border') }}"
                                        style="{{ $reply->is_internal_note ? 'background:#fff8e1' : '' }}{{ $fromCustomer ? '' : ';margin-left:40px' }}">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <strong>{{ $reply->user->name ?? '-' }}</strong>
                                                @if ($fromCustomer)
                                                    <span class="badge badge-info">Pengguna</span>
                                                @elseif ($reply->user && $reply->user->role === 'superadmin')
                                                    <span class="badge badge-primary">Tim Support</span>
                                                @endif
                                                @if ($reply->is_internal_note)<span class="badge badge-warning"><i class="fas fa-lock"></i> Catatan internal</span>@endif
                                            </div>
                                            <small class="text-muted">{{ $reply->created_at->timezone($tz)->format('d M Y H:i') }}</small>
                                        </div>
                                        <div class="mt-1" style="white-space: pre-line">{{ $reply->message }}</div>
                                        @if ($url)
                                            @if ($isImage($reply->attachment))
                                                <a href="{{ $url }}" target="_blank" class="d-inline-block mt-2">
                                                    <img src="{{ $url }}" alt="Lampiran" style="max-width:220px;max-height:220px;border-radius:8px;border:1px solid #eee">
                                                </a>
                                            @else
                                                <div class="mt-2"><a href="{{ $url }}" target="_blank"><i class="fas fa-paperclip"></i> {{ basename($reply->attachment) }}</a></div>
                                            @endif
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-muted">Belum ada balasan.</p>
                                @endforelse

                                <hr>
                                <form method="POST" action="{{ route('superadmin.support-tickets.reply', $ticket->id) }}" enctype="multipart/form-data">
                                    @csrf
                                    <div class="form-group">
                                        <label>Tulis balasan</label>
                                        <textarea name="message" rows="4" class="form-control @error('message') is-invalid @enderror" maxlength="5000" required>{{ old('message') }}</textarea>
                                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="form-row">
                                        <div class="col-md-6 form-group">
                                            <label class="text-small">Lampiran (gambar/PDF, maks. 4 MB)</label>
                                            <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf" class="form-control-file @error('attachment') is-invalid @enderror">
                                            @error('attachment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label class="text-small">Sekalian ubah status</label>
                                            <select name="set_status" class="form-control form-control-sm">
                                                <option value="">— otomatis —</option>
                                                @foreach ($statuses as $v => $l)
                                                    <option value="{{ $v }}">{{ $l }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" name="is_internal_note" value="1" class="custom-control-input" id="is_internal_note">
                                            <label for="is_internal_note" class="custom-control-label">Catatan internal (tidak terlihat pengguna, tidak ada notifikasi)</label>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary"><i class="fas fa-paper-plane"></i> Kirim</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header"><h4>Pengirim</h4></div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr><th>Nama</th><td class="text-right">@if ($userUrl)<a href="{{ $userUrl }}">{{ $ticket->user->name }}</a>@else {{ $ticket->user->name ?? '-' }} @endif</td></tr>
                                    <tr><th>Role</th><td class="text-right">{{ $ticket->user->role ?? '-' }}</td></tr>
                                    <tr><th>Email</th><td class="text-right">{{ $ticket->user->email ?? '-' }}</td></tr>
                                    <tr><th>HP</th><td class="text-right">{{ $ticket->user->phone ?? '-' }}</td></tr>
                                    <tr><th>Tenant</th><td class="text-right">@if ($ticket->company)<a href="{{ route('superadmin.tenants.show', $ticket->company->id) }}">{{ $ticket->company->name }}</a>@else - @endif</td></tr>
                                </table>
                                @if ($otherTickets->isNotEmpty())
                                    <hr>
                                    <div class="text-small text-muted mb-1">Tiket lain dari pengguna ini</div>
                                    @foreach ($otherTickets as $o)
                                        <div class="d-flex justify-content-between text-small">
                                            <a href="{{ route('superadmin.support-tickets.show', $o->id) }}">#{{ $o->id }} {{ \Illuminate\Support\Str::limit($o->subject, 25) }}</a>
                                            <span class="badge badge-{{ $statusBadge[$o->status] ?? 'light' }}">{{ $statuses[$o->status] ?? $o->status }}</span>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><h4>Status</h4></div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('superadmin.support-tickets.status', $ticket->id) }}" class="mb-2">
                                    @csrf
                                    @method('PATCH')
                                    <div class="input-group">
                                        <select name="status" class="form-control">
                                            @foreach ($statuses as $v => $l)
                                                <option value="{{ $v }}" @selected($ticket->status === $v)>{{ $l }}</option>
                                            @endforeach
                                        </select>
                                        <div class="input-group-append"><button class="btn btn-primary">Simpan</button></div>
                                    </div>
                                </form>
                                <div class="text-small text-muted">
                                    Dibuat {{ $ticket->created_at->timezone($tz)->format('d M Y H:i') }}
                                    @if ($ticket->resolved_at)
                                        @php $resolved = \Carbon\Carbon::parse($ticket->resolved_at); @endphp
                                        <br>Selesai {{ $resolved->timezone($tz)->format('d M Y H:i') }} ({{ $ticket->created_at->diffForHumans($resolved, true) }})
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><h4>Prioritas &amp; Kategori</h4></div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('superadmin.support-tickets.update', $ticket->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <div class="form-group">
                                        <select name="priority" class="form-control">
                                            @foreach ($priorities as $v => $l)
                                                <option value="{{ $v }}" @selected($ticket->priority === $v)>{{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <select name="category" class="form-control">
                                            @foreach ($categories as $v => $l)
                                                <option value="{{ $v }}" @selected($ticket->category === $v)>{{ $l }}</option>
                                            @endforeach
                                            @if (!array_key_exists($ticket->category, $categories))
                                                <option value="{{ $ticket->category }}" selected>{{ ucfirst($ticket->category) }}</option>
                                            @endif
                                        </select>
                                    </div>
                                    <button class="btn btn-primary btn-sm btn-block">Simpan</button>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><h4>Ditangani Oleh</h4></div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('superadmin.support-tickets.assign', $ticket->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <div class="input-group">
                                        <select name="assigned_to" class="form-control">
                                            <option value="">— belum ditugaskan —</option>
                                            @foreach ($staff as $s)
                                                <option value="{{ $s->id }}" @selected((int) $ticket->assigned_to === (int) $s->id)>{{ $s->name }}{{ (int) $s->id === (int) auth()->id() ? ' (saya)' : '' }}</option>
                                            @endforeach
                                        </select>
                                        <div class="input-group-append"><button class="btn btn-primary">Simpan</button></div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card border border-danger">
                            <div class="card-body">
                                <h6 class="text-danger mb-1">Hapus Tiket</h6>
                                <p class="small text-muted">Untuk tiket spam atau ganda. Semua balasan &amp; lampiran ikut terhapus. Tiket biasa cukup ditutup.</p>
                                <button type="button" class="btn btn-outline-danger btn-block" data-toggle="modal" data-target="#deleteTicketModal"><i class="fas fa-trash"></i> Hapus</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade" id="deleteTicketModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('superadmin.support-tickets.destroy', $ticket->id) }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">Hapus Tiket #{{ $ticket->id }}</h5>
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
        $(function () { $('#deleteTicketModal').appendTo('body'); });
    </script>
@endpush
