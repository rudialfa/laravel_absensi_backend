@extends('layouts.app')

@section('title', 'Edit Companies')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('backend/asset/library/bootstrap-daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/asset/library/bootstrap-colorpicker/dist/css/bootstrap-colorpicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/asset/library/select2/dist/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/asset/library/selectric/public/selectric.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/asset/library/bootstrap-timepicker/css/bootstrap-timepicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/asset/library/bootstrap-tagsinput/dist/bootstrap-tagsinput.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Edit Users</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Forms</a></div>
                    <div class="breadcrumb-item">Companies</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Companies</h2>



                <div class="card">
                    <form action="{{ route('admin.companies.update', $company) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="card-header">
                            <h4>Input Text</h4>
                        </div>
                        <div class="card-body">
                             <div class="form-group">
    <label>Nama Perusahaan</label>
    <input type="text" name="name" value="{{ old('name', $company->name ?? '') }}" class="form-control" required>
</div>

<div class="form-group">
    <label>Email</label>
    <input type="email" name="email" value="{{ old('email', $company->email ?? '') }}" class="form-control">
</div>

<div class="form-group">
    <label>Alamat</label>
    <textarea name="address" class="form-control">{{ old('address', $company->address ?? '') }}</textarea>
</div>

<div class="form-group">
    <label>Latitude</label>
    <input type="text" name="latitude" value="{{ old('latitude', $company->latitude ?? '') }}" class="form-control">
</div>

<div class="form-group">
    <label>Longitude</label>
    <input type="text" name="longitude" value="{{ old('longitude', $company->longitude ?? '') }}" class="form-control">
</div>

<div class="form-group">
    <label>Radius (KM)</label>
    <input type="number" step="0.01" name="radius_km" value="{{ old('radius_km', $company->radius_km ?? 0.5) }}" class="form-control">
</div>

<div class="form-group">
    <label>Jam Masuk</label>
    <input type="time" name="time_in" value="{{ old('time_in', $company->time_in ?? '08:00') }}" class="form-control" required>
</div>

<div class="form-group">
    <label>Jam Pulang</label>
    <input type="time" name="time_out" value="{{ old('time_out', $company->time_out ?? '17:00') }}" class="form-control" required>
</div>

<div class="form-group">
    <label>Zona Waktu</label>
    <input type="text" name="timezone" value="{{ old('timezone', $company->timezone ?? 'Asia/Jakarta') }}" class="form-control">
</div>

<div class="form-group">
    <label>Tipe</label>
   <select name="type" class="form-control">
    <option value="company" {{ old('type', $company->type ?? '') == 'company' ? 'selected' : '' }}>Company</option>
    <option value="school" {{ old('type', $company->type ?? '') == 'school' ? 'selected' : '' }}>School</option>
    <option value="pesantren" {{ old('type', $company->type ?? '') == 'pesantren' ? 'selected' : '' }}>Pesantren</option>
    <option value="hospital" {{ old('type', $company->type ?? '') == 'hospital' ? 'selected' : '' }}>Hospital / Clinic</option>
    <option value="government" {{ old('type', $company->type ?? '') == 'government' ? 'selected' : '' }}>Government Office</option>
    <option value="factory" {{ old('type', $company->type ?? '') == 'factory' ? 'selected' : '' }}>Factory / Industry</option>
    <option value="retail" {{ old('type', $company->type ?? '') == 'retail' ? 'selected' : '' }}>Retail / Store</option>
    <option value="restaurant" {{ old('type', $company->type ?? '') == 'restaurant' ? 'selected' : '' }}>Restaurant / Hotel</option>
    <option value="training" {{ old('type', $company->type ?? '') == 'training' ? 'selected' : '' }}>Course / Training Center</option>
    <option value="organization" {{ old('type', $company->type ?? '') == 'organization' ? 'selected' : '' }}>Organization / Foundation</option>
    <option value="transport" {{ old('type', $company->type ?? '') == 'transport' ? 'selected' : '' }}>Transport / Logistics</option>
    <option value="remote" {{ old('type', $company->type ?? '') == 'remote' ? 'selected' : '' }}>Remote / Hybrid Team</option>
    <option value="sports" {{ old('type', $company->type ?? '') == 'sports' ? 'selected' : '' }}>Sports / Gym</option>
</select>

</div>

<div class="form-group">
    <label>URL Gambar / Logo</label>
    <input type="url" name="image_url" value="{{ old('image_url', $company->image_url ?? '') }}" class="form-control">
</div>
                        </div>
                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Submit</button>
                        </div>
                    </form>
                </div>

            </div>
        </section>
    </div>
@endsection

@push('scripts')
@endpush
