{{-- Form pengumuman. Variabel: $b, $companies, $categories, $companyTypes, $roles, $targets, $userEmails, $fcmConfigured --}}
@php
    $target = old('target', $b->target ?? 'all');
    $selTypes = old('company_types', $b->company_types ?? []);
    $selCompanies = array_map('strval', old('company_ids', $b->company_ids ?? []));
    $selRoles = old('roles', $b->roles ?? []);
    $schedVal = old(
        'scheduled_at',
        $b->scheduled_at && $b->status === 'scheduled'
            ? $b->scheduled_at->timezone('Asia/Jakarta')->format('Y-m-d\TH:i')
            : now('Asia/Jakarta')->addHour()->startOfHour()->format('Y-m-d\TH:i'),
    );
@endphp

<div class="row">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header">
                <h4>Isi Notifikasi</h4>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Judul <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="nTitle" value="{{ old('title', $b->title) }}"
                        maxlength="150" required class="form-control @error('title') is-invalid @enderror"
                        placeholder="Contoh: Pemeliharaan sistem Sabtu malam">
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted"><span id="titleCount">0</span>/150 — judul pendek lebih mudah terbaca di
                        HP (± 40 karakter).</small>
                </div>
                <div class="form-group">
                    <label>Isi <span class="text-danger">*</span></label>
                    <textarea name="body" id="nBody" rows="5" maxlength="1000" required
                        class="form-control @error('body') is-invalid @enderror" style="height:auto">{{ old('body', $b->body) }}</textarea>
                    @error('body')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted"><span id="bodyCount">0</span>/1000 — di pop-up HP hanya ± 2 baris pertama
                        yang terlihat; selengkapnya di kotak masuk aplikasi.</small>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-5">
                        <label>Kategori (ikon)</label>
                        <select name="category" class="form-control">
                            @foreach ($categories as $v => $l)
                                <option value="{{ $v }}" @selected(old('category', $b->category ?? 'system') === $v)>{{ $l }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-7">
                        <label>Link (opsional)</label>
                        <input type="url" name="url" value="{{ old('url', $b->url) }}" maxlength="255"
                            class="form-control @error('url') is-invalid @enderror" placeholder="https://...">
                        @error('url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Pratinjau tampilan di HP --}}
                <label class="text-small text-muted">Pratinjau di HP</label>
                <div class="border rounded p-3 bg-light" style="max-width:420px">
                    <div class="d-flex">
                        <div class="mr-2"><i class="fas fa-bell text-primary"></i></div>
                        <div style="min-width:0">
                            <div class="font-weight-bold text-truncate" id="pvTitle">Judul notifikasi</div>
                            <div class="text-small text-muted" id="pvBody"
                                style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
                                Isi notifikasi</div>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <div class="input-group" style="max-width:520px">
                        <input type="email" id="testEmail" class="form-control form-control-sm"
                            placeholder="Email penerima tes (kosong = akun Anda)">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnTest"
                                @disabled(!$fcmConfigured)><i class="fas fa-vial"></i> Kirim Tes ke 1 HP</button>
                        </div>
                    </div>
                    <div class="text-small mt-1" id="testResult"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header">
                <h4>Penerima</h4>
            </div>
            <div class="card-body">
                <div class="form-group">
                    @foreach ($targets as $v => $l)
                        <div class="custom-control custom-radio">
                            <input type="radio" name="target" value="{{ $v }}"
                                id="target_{{ $v }}" class="custom-control-input js-target"
                                @checked($target === $v)>
                            <label class="custom-control-label"
                                for="target_{{ $v }}">{{ $l }}</label>
                        </div>
                    @endforeach
                </div>

                <div class="form-group js-box" data-for="types">
                    <label>Jenis tenant</label>
                    @foreach ($companyTypes as $v => $l)
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="company_types[]" value="{{ $v }}"
                                id="ct_{{ $v }}" class="custom-control-input js-est"
                                @checked(in_array($v, $selTypes, true))>
                            <label class="custom-control-label"
                                for="ct_{{ $v }}">{{ $l }}</label>
                        </div>
                    @endforeach
                    @error('company_types')
                        <div class="text-danger text-small">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group js-box" data-for="companies">
                    <label>Tenant</label>
                    <select name="company_ids[]" class="form-control js-est" multiple size="8">
                        @foreach ($companies as $c)
                            <option value="{{ $c->id }}" @selected(in_array((string) $c->id, $selCompanies, true))>{{ $c->name }}
                                ({{ $companyTypes[$c->type] ?? $c->type }})
                                {{ $c->is_active ? '' : ' — suspend' }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Tahan Ctrl / Cmd untuk memilih lebih dari satu.</small>
                    @error('company_ids')
                        <div class="text-danger text-small">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group js-box" data-for="users">
                    <label>Email pengguna</label>
                    <textarea name="user_emails" rows="4" class="form-control js-est @error('user_emails') is-invalid @enderror"
                        style="height:auto" placeholder="satu email per baris">{{ old('user_emails', $userEmails) }}</textarea>
                    @error('user_emails')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group js-roles">
                    <label>Role (kosong = semua role)</label>
                    <div class="row">
                        @foreach ($roles as $v => $l)
                            <div class="col-6">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" name="roles[]" value="{{ $v }}"
                                        id="role_{{ $v }}" class="custom-control-input js-est"
                                        @checked(in_array($v, $selRoles, true))>
                                    <label class="custom-control-label text-small"
                                        for="role_{{ $v }}">{{ $l }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="form-group">
                    <input type="hidden" name="only_active_tenants" value="0">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" name="only_active_tenants" value="1" id="only_active"
                            class="custom-control-input js-est" @checked(old('only_active_tenants', $b->only_active_tenants ?? true))>
                        <label class="custom-control-label" for="only_active">Lewati tenant yang disuspend</label>
                    </div>
                </div>

                <div class="alert alert-light border mb-0" id="estimateBox">
                    <i class="fas fa-users"></i> Perkiraan penerima: <b id="estUsers">…</b> pengguna
                    di <b id="estCompanies">…</b> tenant · <b id="estTokens">…</b> punya token HP
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4>Kapan Dikirim</h4>
            </div>
            <div class="card-body">
                @php $mode = old('mode', $b->exists && $b->status === 'scheduled' && $b->scheduled_at && $b->scheduled_at->isFuture() ? 'schedule' : 'now'); @endphp
                <div class="custom-control custom-radio">
                    <input type="radio" name="mode" value="now" id="mode_now"
                        class="custom-control-input js-mode" @checked($mode === 'now')>
                    <label class="custom-control-label" for="mode_now">Kirim sekarang</label>
                </div>
                <div class="custom-control custom-radio">
                    <input type="radio" name="mode" value="schedule" id="mode_schedule"
                        class="custom-control-input js-mode" @checked($mode === 'schedule')>
                    <label class="custom-control-label" for="mode_schedule">Jadwalkan</label>
                </div>
                <div class="form-group mt-2 mb-2 js-sched">
                    <input type="datetime-local" name="scheduled_at" value="{{ $schedVal }}"
                        class="form-control @error('scheduled_at') is-invalid @enderror">
                    @error('scheduled_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">WIB. Butuh scheduler aktif.</small>
                </div>
                <div class="custom-control custom-radio">
                    <input type="radio" name="mode" value="draft" id="mode_draft"
                        class="custom-control-input js-mode" @checked($mode === 'draft')>
                    <label class="custom-control-label" for="mode_draft">Simpan sebagai draft</label>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary btn-block" id="btnSubmit"><i class="fas fa-paper-plane"></i>
                    <span>Kirim</span></button>
                <a href="{{ route('superadmin.notifications.index') }}" class="btn btn-light btn-block">Batal</a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        $(function() {
            const $form = $('#btnSubmit').closest('form');

            function syncPreview() {
                const t = $('#nTitle').val(),
                    b = $('#nBody').val();
                $('#pvTitle').text(t || 'Judul notifikasi');
                $('#pvBody').text(b || 'Isi notifikasi');
                $('#titleCount').text(t.length);
                $('#bodyCount').text(b.length);
            }

            function syncTarget() {
                const t = $('.js-target:checked').val();
                $('.js-box').each(function() {
                    $(this).toggle($(this).data('for') === t);
                });
                $('.js-roles').toggle(t !== 'users');
            }

            function syncMode() {
                const m = $('.js-mode:checked').val();
                $('.js-sched').toggle(m === 'schedule');
                $('#btnSubmit span').text({
                    now: 'Kirim Sekarang',
                    schedule: 'Jadwalkan',
                    draft: 'Simpan Draft'
                } [m]);
            }

            let timer;

            function estimate() {
                clearTimeout(timer);
                timer = setTimeout(function() {
                    const data = $form.serializeArray().filter(f => f.name !== '_method' && f.name !==
                        '_token');
                    $.get('{{ route('superadmin.notifications.estimate') }}', $.param(data)).done(function(
                        r) {
                        $('#estUsers').text(r.users.toLocaleString('id-ID'));
                        $('#estCompanies').text(r.companies.toLocaleString('id-ID'));
                        $('#estTokens').text(r.with_token.toLocaleString('id-ID'));
                        $('#estimateBox').toggleClass('alert-warning', r.users === 0).toggleClass(
                            'alert-light', r.users > 0);
                    });
                }, 300);
            }

            $('#nTitle, #nBody').on('input', syncPreview);
            $('.js-target').on('change', function() {
                syncTarget();
                estimate();
            });
            $('.js-est').on('change input', estimate);
            $('.js-mode').on('change', syncMode);

            $form.on('submit', function() {
                const m = $('.js-mode:checked').val();
                if (m === 'now') {
                    return confirm('Kirim pengumuman ini sekarang ke ' + $('#estUsers').text() +
                        ' pengguna? Notifikasi yang sudah muncul di HP tidak bisa ditarik.');
                }
                return true;
            });

            $('#btnTest').on('click', function() {
                const $btn = $(this).prop('disabled', true);
                $('#testResult').removeClass('text-danger text-success').text('Mengirim…');
                $.post('{{ route('superadmin.notifications.test') }}', {
                        _token: '{{ csrf_token() }}',
                        title: $('#nTitle').val(),
                        body: $('#nBody').val(),
                        category: $('[name=category]').val(),
                        test_email: $('#testEmail').val()
                    }).done(r => $('#testResult').addClass('text-success').text(r.message))
                    .fail(x => $('#testResult').addClass('text-danger').text(x.responseJSON?.message ||
                        'Gagal mengirim tes.'))
                    .always(() => $btn.prop('disabled', false));
            });

            syncPreview();
            syncTarget();
            syncMode();
            estimate();
        });
    </script>
@endpush
