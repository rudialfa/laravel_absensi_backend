{{--
    Modal hapus tenant (dipakai index & show).
    Tombol pemicu cukup diberi: data-toggle="modal" data-target="#deleteModal"
    data-action="URL destroy" data-name="Nama tenant" data-users="jumlah user"
--}}
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" id="deleteForm" action="#">
            @csrf
            @method('DELETE')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle"></i> Hapus Tenant
                        Permanen</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        Tenant <strong data-slot="name"></strong> beserta <strong data-slot="users"></strong> pengguna,
                        absensi, payroll, data sekolah, langganan, dan invoice akan <strong>terhapus semua</strong>.
                        Tindakan ini tidak bisa dibatalkan.
                    </p>
                    <p class="text-muted small">Kalau hanya ingin menghentikan akses sementara, gunakan <b>Suspend</b>.
                    </p>
                    <label>Ketik ulang nama tenant untuk konfirmasi:</label>
                    <input type="text" name="confirm_name" id="confirmName" class="form-control" autocomplete="off"
                        required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn" disabled>Hapus Permanen</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        $(function() {
            let expected = '';

            // Stisla: modal di dalam .main-wrapper bisa tertutup backdrop, pindahkan ke <body>
            $('#deleteModal').appendTo('body');

            $('#deleteModal').on('show.bs.modal', function(e) {
                const btn = $(e.relatedTarget);
                expected = String(btn.data('name'));
                $('#deleteForm').attr('action', btn.data('action'));
                $(this).find('[data-slot="name"]').text(expected);
                $(this).find('[data-slot="users"]').text(btn.data('users'));
                $('#confirmName').val('');
                $('#confirmDeleteBtn').prop('disabled', true);
            });

            $('#deleteModal').on('shown.bs.modal', () => $('#confirmName').trigger('focus'));

            $('#confirmName').on('input', function() {
                $('#confirmDeleteBtn').prop('disabled', this.value.trim() !== expected);
            });
        });
    </script>
@endpush
