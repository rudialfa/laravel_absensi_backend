{{-- Modal hapus. Variabel: $action (URL destroy), $label --}}
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ $action }}" method="POST">
            @csrf
            @method('DELETE')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Hapus {{ $label }}</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Data lama tersimpan di Audit Log.</p>
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
            $('#deleteModal').appendTo('body');
        });
    </script>
@endpush
