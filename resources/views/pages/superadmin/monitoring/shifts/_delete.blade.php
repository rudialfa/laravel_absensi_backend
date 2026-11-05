{{--
    Modal hapus serbaguna untuk menu Shift & Jadwal.
    Tombol pemicu: data-toggle="modal" data-target="#shiftDeleteModal"
                   data-action="URL destroy" data-label="Nama data"
--}}
<div class="modal fade" id="shiftDeleteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" id="shiftDeleteForm" action="#">
            @csrf
            @method('DELETE')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Hapus <span data-slot="label"></span></h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Pengaturan shift adalah wewenang HR. Hapus hanya data yang jelas salah atau ganda.
                        Data lama tersimpan di Audit Log.
                    </p>
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

@push('scripts')
    <script>
        $(function() {
            $('#shiftDeleteModal').appendTo('body').on('show.bs.modal', function(e) {
                const btn = $(e.relatedTarget);
                $('#shiftDeleteForm').attr('action', btn.data('action'));
                $(this).find('[data-slot="label"]').text(btn.data('label'));
                $(this).find('textarea').val('');
            });
        });
    </script>
@endpush
