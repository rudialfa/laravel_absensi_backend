{{-- Form artikel bantuan. Variabel: $article, $categories --}}
<div class="row">
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header">
                <h4>Isi Artikel</h4>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Judul / Pertanyaan <span class="text-danger">*</span></label>
                    <input type="text" name="title" value="{{ old('title', $article->title) }}" maxlength="255"
                        required class="form-control @error('title') is-invalid @enderror"
                        placeholder="Contoh: Bagaimana cara mengajukan cuti?">
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group mb-0">
                    <label>Isi Jawaban <span class="text-danger">*</span></label>
                    <textarea name="content" id="articleContent" rows="14" required
                        class="form-control @error('content') is-invalid @enderror" style="height:auto">{{ old('content', $article->content) }}</textarea>
                    @error('content')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="form-text text-muted">
                        Tulis teks biasa (Enter = baris baru), atau HTML sederhana: &lt;b&gt;, &lt;i&gt;,
                        &lt;ul&gt;&lt;li&gt;, &lt;ol&gt;, &lt;a href&gt;, &lt;h4&gt;.
                        Script dan style akan dibuang otomatis saat ditampilkan.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header">
                <h4>Pengaturan</h4>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Kategori <span class="text-danger">*</span></label>
                    <input type="text" name="category"
                        value="{{ old('category', $article->category ?? request('category')) }}" maxlength="100"
                        required list="categoryList" class="form-control @error('category') is-invalid @enderror"
                        placeholder="Pilih atau ketik kategori baru">
                    <datalist id="categoryList">
                        @foreach ($categories as $c)
                            <option value="{{ $c }}">
                        @endforeach
                    </datalist>
                    @error('category')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="form-text text-muted">Pakai nama kategori yang sama persis supaya artikel terkumpul
                        dalam satu grup.</small>
                </div>

                <div class="form-group">
                    <label>Urutan Tampil</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $article->sort_order ?? 0) }}"
                        min="0" max="65535" class="form-control @error('sort_order') is-invalid @enderror">
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="form-text text-muted">Angka kecil tampil lebih atas dalam kategorinya.</small>
                </div>

                <div class="form-group mb-0">
                    <input type="hidden" name="is_published" value="0">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" name="is_published" value="1" class="custom-control-input"
                            id="is_published" @checked(old('is_published', $article->is_published ?? true))>
                        <label for="is_published" class="custom-control-label">Tayangkan ke pengguna</label>
                    </div>
                    <small class="form-text text-muted">Matikan untuk menyimpan sebagai draft.</small>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary btn-block" name="after" value="index"><i class="fas fa-save"></i>
                    Simpan</button>
                <button class="btn btn-outline-primary btn-block" name="after" value="preview"><i
                        class="fas fa-eye"></i> Simpan &amp; Pratinjau</button>
                <a href="{{ route('superadmin.help-articles.index') }}" class="btn btn-light btn-block">Batal</a>
            </div>
        </div>
    </div>
</div>
