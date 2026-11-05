@extends('layouts.app')

@section('title', 'Edit Invoice')

@section('main')
    @php
        $canEditDue = $invoice->isPending() && !$invoice->vaPayment;
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.invoices.show', $invoice->id) }}" class="btn btn-icon"><i
                            class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Edit Invoice</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.invoices.index') }}">Invoice</a></div>
                    <div class="breadcrumb-item"><a
                            href="{{ route('superadmin.invoices.show', $invoice->id) }}">{{ $invoice->invoice_number }}</a>
                    </div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="alert alert-light border">
                    <i class="fas fa-info-circle"></i>
                    Nominal, paket, dan tenant invoice tidak bisa diubah karena sudah menjadi catatan tagihan.
                    Jika salah, batalkan invoice ini lalu buat yang baru.
                </div>

                <form method="POST" action="{{ route('superadmin.invoices.update', $invoice->id) }}">
                    @csrf
                    @method('PUT')
                    <div class="card">
                        <div class="card-header">
                            <h4>{{ $invoice->invoice_number }} · {{ $invoice->company->name ?? '-' }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Jatuh tempo</label>
                                <input type="datetime-local" name="due_at"
                                    value="{{ old('due_at', optional($invoice->due_at)->format('Y-m-d\TH:i')) }}"
                                    class="form-control @error('due_at') is-invalid @enderror" @disabled(!$canEditDue)>
                                @if (!$canEditDue)
                                    <small class="text-muted">
                                        {{ $invoice->vaPayment ? 'Tidak bisa diubah karena VA sudah dibuat (masa berlaku VA di Midtrans tetap).' : 'Hanya untuk invoice yang masih menunggu bayar.' }}
                                    </small>
                                @endif
                                @error('due_at')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group mb-0">
                                <label>Catatan</label>
                                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="6" maxlength="1000">{{ old('notes', $invoice->notes) }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('superadmin.invoices.show', $invoice->id) }}"
                                class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary ml-1"><i class="fas fa-save"></i> Simpan</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection
