{{--
    Form staff superadmin — dipakai create & edit.
    Variabel: $staff (edit saja), $isSelf (edit saja)
--}}
@php
    $s = $staff ?? null;
    $self = $isSelf ?? false;
@endphp

<div class="card-body">
    <div class="form-row">
        <div class="form-group col-md-6">
            <label>Nama <span class="text-danger">*</span></label>
            <input type="text" name="name" value="{{ old('name', $s->name ?? '') }}"
                class="form-control @error('name') is-invalid @enderror" required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group col-md-6">
            <label>Email Login <span class="text-danger">*</span></label>
            <input type="email" name="email" value="{{ old('email', $s->email ?? '') }}"
                class="form-control @error('email') is-invalid @enderror" autocomplete="off" required>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6">
            <label>No. HP</label>
            <input type="text" name="phone" value="{{ old('phone', $s->phone ?? '') }}"
                class="form-control @error('phone') is-invalid @enderror">
            @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group col-md-6 d-flex align-items-end">
            @if ($self)
                <p class="text-muted small mb-2"><i class="fas fa-info-circle"></i> Ini akun Anda sendiri, selalu aktif.
                </p>
            @else
                <label class="custom-switch pl-0 mb-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="custom-switch-input"
                        @checked(old('is_active', $s->is_active ?? true))>
                    <span class="custom-switch-indicator"></span>
                    <span class="custom-switch-description">Akun aktif (bisa login ke panel)</span>
                </label>
            @endif
        </div>
    </div>

    <hr>
    <h6 class="mb-3">{{ $s ? 'Ganti Password' : 'Password' }}</h6>
    @if ($s)
        <p class="text-muted small">Kosongkan jika tidak ingin mengganti password.</p>
    @endif
    <div class="form-row">
        <div class="form-group col-md-6">
            <label>Password {{ $s ? 'Baru' : '' }} @unless ($s)
                    <span class="text-danger">*</span>
                @endunless
            </label>
            <input type="password" name="password" minlength="8" autocomplete="new-password"
                class="form-control @error('password') is-invalid @enderror" @required(!$s)>
            <small class="text-muted">Minimal 8 karakter.</small>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group col-md-6">
            <label>Ulangi Password @unless ($s)
                    <span class="text-danger">*</span>
                @endunless
            </label>
            <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password"
                class="form-control" @required(!$s)>
        </div>
    </div>
</div>
