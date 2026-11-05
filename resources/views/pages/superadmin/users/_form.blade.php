{{--
    Form pengguna — dipakai create & edit.
    Variabel: $companies, $rolesByType, $roleLabels, $typeLabels, $presetCompanyId, $user (edit saja)
--}}
@php
    $u = $user ?? null;
    $selectedCompany = old('company_id', $u->company_id ?? $presetCompanyId);
    $selectedRole = old('role', $u->role ?? '');
    $isActive = old('is_active', $u->is_active ?? true);
    $photo =
        $u && $u->image_url ? (str_starts_with($u->image_url, 'http') ? $u->image_url : asset($u->image_url)) : null;
    // Avatar kosong (SVG inline, tidak butuh file gambar)
    $placeholder =
        'data:image/svg+xml;utf8,' .
        rawurlencode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96"><rect width="96" height="96" fill="#e9ecef"/><circle cx="48" cy="38" r="16" fill="#adb5bd"/><path d="M18 86c4-16 16-24 30-24s26 8 30 24" fill="#adb5bd"/></svg>',
        );
@endphp

<div class="row">
    <div class="col-12 col-lg-8">
        {{-- ── TENANT & PERAN ─────────────────────────────── --}}
        <div class="card">
            <div class="card-header">
                <h4>Tenant &amp; Peran</h4>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-7">
                        <label>Tenant / Lembaga <span class="text-danger">*</span></label>
                        <select name="company_id" id="company_id"
                            class="form-control @error('company_id') is-invalid @enderror" required>
                            <option value="">-- Pilih tenant --</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" data-type="{{ $company->type }}"
                                    @selected((string) $selectedCompany === (string) $company->id)>
                                    {{ $company->name }} ({{ $typeLabels[$company->type] ?? ucfirst($company->type) }})
                                </option>
                            @endforeach
                        </select>
                        @error('company_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if ($u)
                            <small class="text-muted">Memindahkan tenant akan mengeluarkan pengguna dari
                                aplikasi.</small>
                        @endif
                    </div>
                    <div class="form-group col-md-5">
                        <label>Peran <span class="text-danger">*</span></label>
                        <select name="role" id="role" class="form-control @error('role') is-invalid @enderror"
                            required>
                            <option value="">-- Pilih tenant dulu --</option>
                        </select>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ── DATA DIRI ──────────────────────────────────── --}}
        <div class="card">
            <div class="card-header">
                <h4>Data Pengguna</h4>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Nama <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $u->name ?? '') }}"
                            class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Email Login <span class="text-danger">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $u->email ?? '') }}"
                            class="form-control @error('email') is-invalid @enderror" autocomplete="off" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>No. HP</label>
                        <input type="text" name="phone" value="{{ old('phone', $u->phone ?? '') }}"
                            class="form-control @error('phone') is-invalid @enderror" placeholder="08xxxxxxxxxx">
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Jabatan</label>
                        <input type="text" name="position" value="{{ old('position', $u->position ?? '') }}"
                            class="form-control @error('position') is-invalid @enderror"
                            placeholder="Contoh: Staff Gudang">
                        @error('position')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Hanya untuk perusahaan --}}
                <div class="form-row" id="company-only">
                    <div class="form-group col-md-6">
                        <label>Departemen</label>
                        <input type="text" name="department" value="{{ old('department', $u->department ?? '') }}"
                            class="form-control @error('department') is-invalid @enderror"
                            placeholder="Contoh: Produksi">
                        @error('department')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Gaji Pokok (Rp)</label>
                        <input type="number" step="1" min="0" name="salary"
                            value="{{ old('salary', isset($u->salary) ? (int) $u->salary : '') }}"
                            class="form-control @error('salary') is-invalid @enderror" placeholder="0">
                        <small class="text-muted">Dipakai sebagai gaji pokok saat generate payroll.</small>
                        @error('salary')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ── PASSWORD ───────────────────────────────────── --}}
        <div class="card">
            <div class="card-header">
                <h4>{{ $u ? 'Ganti Password' : 'Password' }}</h4>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    @if ($u)
                        Kosongkan jika tidak ingin mengganti password. Jika diganti, pengguna harus login ulang di
                        aplikasi.
                    @else
                        Kosongkan untuk membuat password acak otomatis. Password akan ditampilkan sekali setelah
                        disimpan.
                    @endif
                </p>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Password {{ $u ? 'Baru' : '' }}</label>
                        <input type="password" name="password" minlength="6" autocomplete="new-password"
                            class="form-control @error('password') is-invalid @enderror">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label>Ulangi Password</label>
                        <input type="password" name="password_confirmation" minlength="6" autocomplete="new-password"
                            class="form-control">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        {{-- ── FOTO & STATUS ──────────────────────────────── --}}
        <div class="card">
            <div class="card-header">
                <h4>Foto &amp; Status</h4>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <img id="photo-preview" src="{{ $photo ?? $placeholder }}" alt="Foto"
                        class="rounded-circle border" style="width:96px;height:96px;object-fit:cover">
                </div>
                <div class="form-group">
                    <input type="file" name="image" id="image" accept="image/png,image/jpeg,image/webp"
                        class="form-control @error('image') is-invalid @enderror">
                    <small class="text-muted">JPG/PNG/WEBP, maks 2 MB</small>
                    @error('image')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                @if ($photo)
                    <div class="custom-control custom-checkbox mb-3">
                        <input type="checkbox" name="remove_image" value="1" class="custom-control-input"
                            id="remove_image">
                        <label class="custom-control-label" for="remove_image">Hapus foto</label>
                    </div>
                @endif

                <div class="form-group mb-0">
                    <label class="custom-switch pl-0">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="custom-switch-input"
                            @checked($isActive)>
                        <span class="custom-switch-indicator"></span>
                        <span class="custom-switch-description">Akun aktif (bisa login)</span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function() {
            const rolesByType = @json($rolesByType);
            const roleLabels = @json($roleLabels);
            const selectedRole = @json($selectedRole);

            const companySelect = document.getElementById('company_id');
            const roleSelect = document.getElementById('role');
            const companyOnly = document.getElementById('company-only');

            function syncRoles() {
                const option = companySelect.options[companySelect.selectedIndex];
                const type = option ? option.dataset.type : null;
                const roles = (type && rolesByType[type]) ? rolesByType[type] : [];
                const current = roleSelect.value || selectedRole;

                roleSelect.innerHTML = '';
                if (!roles.length) {
                    roleSelect.add(new Option(type ? 'Jenis lembaga ini belum punya peran' : '-- Pilih tenant dulu --',
                        ''));
                } else {
                    roleSelect.add(new Option('-- Pilih peran --', ''));
                    roles.forEach(r => roleSelect.add(new Option(roleLabels[r] || r, r, false, r === current)));
                }

                companyOnly.style.display = type === 'company' ? '' : 'none';
            }

            companySelect.addEventListener('change', syncRoles);
            syncRoles();

            // Pratinjau foto
            document.getElementById('image').addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    document.getElementById('photo-preview').src = URL.createObjectURL(this.files[0]);
                }
            });
        })();
    </script>
@endpush
