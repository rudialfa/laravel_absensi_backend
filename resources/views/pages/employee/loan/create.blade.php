@extends('layouts.employee')
@section('title','Ajukan Pinjaman')
@section('breadcrumb')
    <a href="{{ route('employee.loan.index') }}">Pinjaman</a>
    <span class="sep">/</span><span class="current">Ajukan</span>
@endsection
@section('content')
<div class="page-title">Ajukan Pinjaman</div>
<div class="page-sub">Pengajuan akan diproses oleh HR setelah disubmit.</div>

@if($activeLoan)
    <div class="flash flash-warning">
        ⚠ Kamu masih memiliki pinjaman <strong>{{ $activeLoan['status'] === 'pending' ? 'yang menunggu persetujuan' : 'aktif' }}</strong>
        (Rp {{ number_format($activeLoan['balance'],0,',','.') }} tersisa).
        Selesaikan pinjaman sebelumnya terlebih dahulu.
    </div>
@else
    <div class="card" style="max-width:600px">
        <form method="POST" action="{{ route('employee.loan.store') }}" id="loan-form">
            @csrf
            <div class="form-grid">
                <div class="form-group form-full">
                    <label class="lbl">Jumlah Pinjaman (Rp) <span style="color:var(--c-danger)">*</span></label>
                    <input type="number" name="amount" value="{{ old('amount') }}" min="10000" step="1000" placeholder="Contoh: 2000000" required oninput="calcInstallment()">
                    @error('amount')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="lbl">Cicilan (bulan) <span style="color:var(--c-danger)">*</span></label>
                    <select name="installments" required onchange="calcInstallment()">
                        @foreach([3,6,9,12,18,24,36,48,60] as $i)
                            <option value="{{ $i }}" {{ old('installments')==$i?'selected':'' }}>{{ $i }} bulan</option>
                        @endforeach
                    </select>
                    @error('installments')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="lbl">Estimasi Cicilan/Bulan</label>
                    <input type="text" id="est-installment" readonly placeholder="—" style="background:var(--c-bg);color:var(--c-primary);font-weight:600">
                </div>
                <div class="form-group form-full">
                    <label class="lbl">Kategori Tujuan <span style="color:var(--c-danger)">*</span></label>
                    <select name="purpose_category" required>
                        <option value="">-- Pilih Kategori --</option>
                        @foreach(['education'=>'Pendidikan','health'=>'Kesehatan','emergency'=>'Darurat','renovation'=>'Renovasi','business'=>'Usaha','other'=>'Lainnya'] as $v=>$l)
                            <option value="{{ $v }}" {{ old('purpose_category')===$v?'selected':'' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                    @error('purpose_category')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group form-full">
                    <label class="lbl">Keterangan (opsional)</label>
                    <textarea name="purpose_note" rows="2" placeholder="Jelaskan kebutuhan lebih detail..." maxlength="500">{{ old('purpose_note') }}</textarea>
                </div>
                <div class="form-group">
                    <label class="lbl">Jenis Pembayaran <span style="color:var(--c-danger)">*</span></label>
                    <select name="payment_type" required onchange="togglePayDate(this.value)">
                        <option value="salary_deduction" {{ old('payment_type')==='salary_deduction'?'selected':'' }}>Potong Gaji</option>
                        <option value="scheduled_date"   {{ old('payment_type')==='scheduled_date'  ?'selected':'' }}>Tanggal Tetap</option>
                        <option value="lump_sum"         {{ old('payment_type')==='lump_sum'        ?'selected':'' }}>Sekaligus</option>
                    </select>
                    @error('payment_type')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group" id="pay-date-box" style="display:none">
                    <label class="lbl">Tanggal Bayar per Bulan</label>
                    <input type="number" name="payment_date_of_month" min="1" max="28" value="{{ old('payment_date_of_month') }}" placeholder="1–28">
                    <div class="form-hint">Tanggal setiap bulan untuk pembayaran cicilan.</div>
                    @error('payment_date_of_month')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:8px">
                <button type="submit" class="btn btn-primary">Ajukan Pinjaman</button>
                <a href="{{ route('employee.loan.index') }}" class="btn">Batal</a>
            </div>
        </form>
    </div>
@endif
@endsection

@push('scripts')
<script>
function calcInstallment() {
    const amt  = parseFloat(document.querySelector('[name=amount]').value) || 0;
    const inst = parseInt(document.querySelector('[name=installments]').value) || 1;
    const est  = amt > 0 ? Math.ceil(amt / inst) : 0;
    document.getElementById('est-installment').value = est > 0 ? 'Rp ' + est.toLocaleString('id-ID') : '—';
}
function togglePayDate(v) {
    document.getElementById('pay-date-box').style.display = v === 'scheduled_date' ? 'block' : 'none';
}
togglePayDate('{{ old('payment_type','salary_deduction') }}');
calcInstallment();
</script>
@endpush