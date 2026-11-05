{{--
    Modal konfirmasi serbaguna untuk menu Monitoring Sekolah.
    Tombol pemicu:
        data-toggle="modal" data-target="#schoolActionModal"
        data-action="URL aksi"            (wajib)
        data-label="Hapus Murid Budi"     (judul modal)
        data-method="DELETE|PATCH"        (opsional, default DELETE)
        data-button="Hapus"               (opsional, teks tombol)
        data-note="Keterangan tambahan"   (opsional)
--}}
<div class="modal fade" id="schoolActionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" id="schoolActionForm" action="#">
            @csrf
            <input type="hidden" name="_method" value="DELETE" id="schoolActionMethod">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" data-slot="label"></h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted" data-slot="note"></p>
                    <p class="small text-muted mb-3">
                        Data ini dikelola Admin Sekolah. Ubah hanya data yang jelas salah atau ganda. Data lama
                        tersimpan di Audit Log.
                    </p>
                    <div class="form-group mb-0">
                        <label>Alasan <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="2" maxlength="500" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger" data-slot="button">Hapus</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        $(function() {
            $('#schoolActionModal').appendTo('body').on('show.bs.modal', function(e) {
                const btn = $(e.relatedTarget);
                const method = (btn.data('method') || 'DELETE').toString().toUpperCase();
                const isDelete = method === 'DELETE';

                $('#schoolActionForm').attr('action', btn.data('action'));
                $('#schoolActionMethod').val(method);
                $(this).find('[data-slot="label"]').text(btn.data('label') || 'Konfirmasi')
                    .toggleClass('text-danger', isDelete);
                $(this).find('[data-slot="note"]').text(btn.data('note') || '').toggle(!!btn.data('note'));
                $(this).find('[data-slot="button"]').text(btn.data('button') || (isDelete ? 'Hapus' :
                        'Simpan'))
                    .toggleClass('btn-danger', isDelete).toggleClass('btn-warning', !isDelete);
                $(this).find('textarea').val('');
            });
        });
    </script>
@endpush
