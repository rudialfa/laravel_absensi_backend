@extends('layouts.app')

@section('title', 'Detail Tiket Bantuan')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Detail Tiket Bantuan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.support-tickets.index') }}">Tiket Bantuan</a>
                    </div>
                    <div class="breadcrumb-item">#{{ $ticket->id }}</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ $ticket->subject }}</h4>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-1">
                                    Dari: <strong>{{ $ticket->user->name ?? '-' }}</strong>
                                    ({{ $ticket->company->name ?? '-' }})
                                    &middot; {{ $ticket->created_at->format('d/m/Y H:i') }}
                                </p>
                                <p>{{ $ticket->message }}</p>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header">
                                <h4>Percakapan</h4>
                            </div>
                            <div class="card-body">
                                @forelse($replies as $reply)
                                    <div class="media mb-3 pb-3 border-bottom">
                                        <div class="media-body">
                                            <strong>{{ $reply->user->name ?? '-' }}</strong>
                                            @if ($reply->is_internal_note)
                                                <span class="badge badge-secondary">Catatan Internal</span>
                                            @endif
                                            <small
                                                class="text-muted float-right">{{ $reply->created_at->format('d/m/Y H:i') }}</small>
                                            <p class="mb-0">{{ $reply->message }}</p>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted">Belum ada balasan.</p>
                                @endforelse

                                <form method="POST" action="{{ route('superadmin.support-tickets.reply', $ticket->id) }}">
                                    @csrf
                                    <div class="form-group">
                                        <label>Balasan</label>
                                        <textarea name="message" rows="4" class="form-control" required></textarea>
                                    </div>
                                    <div class="form-group">
                                        <div class="custom-checkbox custom-control">
                                            <input type="checkbox" name="is_internal_note" value="1"
                                                class="custom-control-input" id="is_internal_note">
                                            <label for="is_internal_note" class="custom-control-label">Catatan internal
                                                (tidak terlihat tenant)</label>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary">Kirim Balasan</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Info Tiket</h4>
                            </div>
                            <div class="card-body">
                                <p><strong>Kategori:</strong> {{ ucfirst($ticket->category) }}</p>
                                <p><strong>Prioritas:</strong> {{ ucfirst($ticket->priority) }}</p>
                                <p><strong>Status saat ini:</strong> {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                </p>
                                <p><strong>Ditugaskan ke:</strong> {{ $ticket->assignedTo->name ?? 'Belum ditugaskan' }}
                                </p>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header">
                                <h4>Ubah Status</h4>
                            </div>
                            <div class="card-body">
                                <form method="POST"
                                    action="{{ route('superadmin.support-tickets.status', $ticket->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <div class="form-group">
                                        <select name="status" class="form-control">
                                            @foreach (['open', 'in_progress', 'resolved', 'closed'] as $s)
                                                <option value="{{ $s }}" @selected($ticket->status === $s)>
                                                    {{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button class="btn btn-primary btn-sm w-100">Update Status</button>
                                </form>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header">
                                <h4>Assign Staff</h4>
                            </div>
                            <div class="card-body">
                                <form method="POST"
                                    action="{{ route('superadmin.support-tickets.assign', $ticket->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <div class="form-group">
                                        <select name="assigned_to" class="form-control" required>
                                            <option value="">Pilih staff</option>
                                            @foreach ($staff as $s)
                                                <option value="{{ $s->id }}" @selected($ticket->assigned_to === $s->id)>
                                                    {{ $s->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button class="btn btn-primary btn-sm w-100">Assign</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
