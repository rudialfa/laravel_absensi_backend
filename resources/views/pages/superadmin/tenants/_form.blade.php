{{--
    Form tenant — dipakai create & edit.
    Variabel: $types, $adminRoleMap, $timezones, $dayLabels, $workDays, $tenant (edit saja)
--}}
@php
    $t = $tenant ?? null;
    $oldWorkDays = array_map('intval', (array) old('work_days', $workDays));
    $roleLabels = ['hr' => 'HR', 'admin' => 'Admin Sekolah', 'ustadz' => 'Ustadz'];
@endphp

{{-- ── IDENTITAS ─────────────────────────────────────────── --}}
<div class="card">
    <div class="card-header">
        <h4>Identitas Lembaga</h4>
    </div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group col-md-6">
                <label>Nama Lembaga <span class="text-danger">*</span></label>
                <input type="text" name="name" value="{{ old('name', $t->name ?? '') }}"
                    class="form-control @error('name') is-invalid @enderror" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group col-md-6">
                <label>Email Lembaga <span class="text-danger">*</span></label>
                <input type="email" name="email" value="{{ old('email', $t->email ?? '') }}"
                    class="form-control @error('email') is-invalid @enderror" required>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Jenis Lembaga <span class="text-danger">*</span></label>
                <select name="type" id="tenant-type" class="form-control @error('type') is-invalid @enderror"
                    required>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $t->type ?? 'company') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                @if ($t)
                    <small class="text-warning d-block mt-1">
                        <i class="fas fa-exclamation-triangle"></i>
                        Mengubah jenis membuat menu di aplikasi berubah total. Ubah hanya jika salah input.
                    </small>
                @endif
            </div>

            <div class="form-group col-md-4" id="boarding-wrap">
                <label>&nbsp;</label>
                <div class="custom-control custom-checkbox mt-2">
                    <input type="hidden" name="is_boarding" value="0">
                    <input type="checkbox" name="is_boarding" value="1" class="custom-control-input"
                        id="is_boarding" @checked(old('is_boarding', $t->is_boarding ?? false))>
                    <label class="custom-control-label" for="is_boarding">Sekolah berasrama (pondok)</label>
                </div>
                <small class="text-muted">Mengaktifkan fitur asrama, mutaba'ah, dan tahfidz.</small>
            </div>

            <div class="form-group col-md-4">
                <label>Logo</label>
                @if ($t && $t->image_url)
                    <div class="d-flex align-items-center mb-2">
                        <img src="{{ str_starts_with($t->image_url, 'http') ? $t->image_url : asset($t->image_url) }}"
                            alt="Logo" style="height:48px; width:48px; object-fit:contain"
                            class="border rounded mr-2">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="remove_logo" value="1" class="custom-control-input"
                                id="remove_logo">
                            <label class="custom-control-label" for="remove_logo">Hapus logo</label>
                        </div>
                    </div>
                @endif
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp"
                    class="form-control @error('logo') is-invalid @enderror">
                <small class="text-muted">JPG/PNG/WEBP, maks 2 MB</small>
                @error('logo')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>

{{-- ── LOKASI ────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-header">
        <h4>Lokasi &amp; Radius Absen</h4>
    </div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group col-md-8">
                <label>Alamat <span class="text-danger">*</span></label>
                <input type="text" name="address" value="{{ old('address', $t->address ?? '') }}"
                    class="form-control @error('address') is-invalid @enderror" required>
                @error('address')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group col-md-4">
                <label>Kota</label>
                <input type="text" name="city" value="{{ old('city', $t->city ?? '') }}"
                    class="form-control @error('city') is-invalid @enderror" placeholder="Contoh: Yogyakarta">
                <small class="text-muted">Dipakai untuk jadwal sholat.</small>
                @error('city')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div id="tenant-map" class="rounded border mb-2" style="height:300px"></div>
        <p class="text-muted small mb-3">
            <i class="fas fa-mouse-pointer"></i> Klik peta atau geser penanda untuk menentukan titik lokasi.
            Lingkaran biru = radius absen.
        </p>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Latitude <span class="text-danger">*</span></label>
                <input type="text" name="latitude" id="latitude" value="{{ old('latitude', $t->latitude ?? '') }}"
                    class="form-control @error('latitude') is-invalid @enderror" placeholder="-7.7956" required>
                @error('latitude')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group col-md-4">
                <label>Longitude <span class="text-danger">*</span></label>
                <input type="text" name="longitude" id="longitude"
                    value="{{ old('longitude', $t->longitude ?? '') }}"
                    class="form-control @error('longitude') is-invalid @enderror" placeholder="110.3695" required>
                @error('longitude')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group col-md-4">
                <label>Radius (km) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0.01" max="100" name="radius_km" id="radius_km"
                    value="{{ old('radius_km', $t->radius_km ?? '0.1') }}"
                    class="form-control @error('radius_km') is-invalid @enderror" required>
                <small class="text-muted">0.1 = 100 meter</small>
                @error('radius_km')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>

{{-- ── JAM & HARI KERJA ─────────────────────────────────── --}}
<div class="card">
    <div class="card-header">
        <h4>Jam &amp; Hari Kerja Default</h4>
    </div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Jam Masuk <span class="text-danger">*</span></label>
                <input type="time" name="time_in"
                    value="{{ old('time_in', isset($t->time_in) ? substr($t->time_in, 0, 5) : '08:00') }}"
                    class="form-control @error('time_in') is-invalid @enderror" required>
                @error('time_in')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group col-md-4">
                <label>Jam Pulang <span class="text-danger">*</span></label>
                <input type="time" name="time_out"
                    value="{{ old('time_out', isset($t->time_out) ? substr($t->time_out, 0, 5) : '16:00') }}"
                    class="form-control @error('time_out') is-invalid @enderror" required>
                @error('time_out')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group col-md-4">
                <label>Zona Waktu <span class="text-danger">*</span></label>
                <select name="timezone" class="form-control @error('timezone') is-invalid @enderror" required>
                    @foreach ($timezones as $value => $label)
                        <option value="{{ $value }}" @selected(old('timezone', $t->timezone ?? 'Asia/Jakarta') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('timezone')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="form-group mb-0">
            <label>Hari Kerja <span class="text-danger">*</span></label>
            <div class="selectgroup selectgroup-pills">
                @foreach ($dayLabels as $num => $label)
                    <label class="selectgroup-item">
                        <input type="checkbox" name="work_days[]" value="{{ $num }}"
                            class="selectgroup-input" @checked(in_array($num, $oldWorkDays, true))>
                        <span class="selectgroup-button">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @error('work_days')
                <div class="text-danger small">{{ $message }}</div>
            @enderror
            <small class="text-muted d-block">HR/admin lembaga tetap bisa mengubah jam & hari kerja dari
                aplikasi.</small>
        </div>
    </div>
</div>

{{-- ── AKUN ADMIN (hanya saat tambah) ───────────────────── --}}
@unless ($t)
    <div class="card">
        <div class="card-header">
            <h4>Akun Admin Pertama</h4>
        </div>
        <div class="card-body">
            <p class="text-muted">
                Akun ini dipakai pengelola lembaga untuk login di aplikasi, dengan role
                <strong
                    id="admin-role-label">{{ $roleLabels[$adminRoleMap[old('type', 'company')] ?? 'hr'] ?? 'HR' }}</strong>.
            </p>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Nama <span class="text-danger">*</span></label>
                    <input type="text" name="admin_name" value="{{ old('admin_name') }}"
                        class="form-control @error('admin_name') is-invalid @enderror" required>
                    @error('admin_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group col-md-4">
                    <label>Email Login <span class="text-danger">*</span></label>
                    <input type="email" name="admin_email" value="{{ old('admin_email') }}"
                        class="form-control @error('admin_email') is-invalid @enderror" autocomplete="off" required>
                    @error('admin_email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group col-md-4">
                    <label>No. HP</label>
                    <input type="text" name="admin_phone" value="{{ old('admin_phone') }}"
                        class="form-control @error('admin_phone') is-invalid @enderror">
                    @error('admin_phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Password <span class="text-danger">*</span></label>
                    <input type="password" name="admin_password" minlength="6"
                        class="form-control @error('admin_password') is-invalid @enderror" autocomplete="new-password"
                        required>
                    @error('admin_password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group col-md-4">
                    <label>Ulangi Password <span class="text-danger">*</span></label>
                    <input type="password" name="admin_password_confirmation" minlength="6" class="form-control"
                        autocomplete="new-password" required>
                </div>
                <div class="form-group col-md-4 d-flex align-items-end">
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="hidden" name="start_trial" value="0">
                        <input type="checkbox" name="start_trial" value="1" class="custom-control-input"
                            id="start_trial" @checked(old('start_trial', true))>
                        <label class="custom-control-label" for="start_trial">Langsung mulai trial gratis</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endunless

@push('style')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (function() {
            // ── Tampilkan opsi asrama hanya untuk sekolah & ganti label role admin ──
            const typeSelect = document.getElementById('tenant-type');
            const boardingWrap = document.getElementById('boarding-wrap');
            const roleLabel = document.getElementById('admin-role-label');
            const roleMap = @json($adminRoleMap);
            const roleNames = @json($roleLabels);

            function syncType() {
                boardingWrap.style.display = typeSelect.value === 'school' ? '' : 'none';
                if (roleLabel) {
                    const role = roleMap[typeSelect.value] || 'hr';
                    roleLabel.textContent = roleNames[role] || role;
                }
            }
            typeSelect.addEventListener('change', syncType);
            syncType();

            // ── Peta pemilih lokasi ──
            if (typeof L === 'undefined') return;

            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const radiusInput = document.getElementById('radius_km');

            const startLat = parseFloat(latInput.value) || -7.7956;
            const startLng = parseFloat(lngInput.value) || 110.3695;
            const hasPoint = latInput.value !== '' && lngInput.value !== '';

            const map = L.map('tenant-map').setView([startLat, startLng], hasPoint ? 16 : 5);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(map);

            const marker = L.marker([startLat, startLng], {
                draggable: true
            }).addTo(map);
            const circle = L.circle([startLat, startLng], {
                radius: (parseFloat(radiusInput.value) || 0.1) * 1000,
                color: '#6777ef',
                fillOpacity: 0.12,
            }).addTo(map);

            function setPoint(lat, lng, updateInputs = true) {
                marker.setLatLng([lat, lng]);
                circle.setLatLng([lat, lng]);
                if (updateInputs) {
                    latInput.value = lat.toFixed(7);
                    lngInput.value = lng.toFixed(7);
                }
            }

            map.on('click', e => setPoint(e.latlng.lat, e.latlng.lng));
            marker.on('dragend', () => {
                const p = marker.getLatLng();
                setPoint(p.lat, p.lng);
            });

            [latInput, lngInput].forEach(input => input.addEventListener('change', () => {
                const lat = parseFloat(latInput.value),
                    lng = parseFloat(lngInput.value);
                if (!isNaN(lat) && !isNaN(lng)) {
                    setPoint(lat, lng, false);
                    map.setView([lat, lng], 16);
                }
            }));

            radiusInput.addEventListener('input', () => {
                circle.setRadius((parseFloat(radiusInput.value) || 0) * 1000);
            });

            // Peta di dalam card kadang belum punya ukuran saat dimuat
            setTimeout(() => map.invalidateSize(), 300);
        })();
    </script>
@endpush
