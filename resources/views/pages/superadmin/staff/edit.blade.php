@extends('layouts.app')

@section('title', 'Edit Staff')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.staff.show', $staff->id) }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Edit Staff</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.staff.index') }}">Staff</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.staff.show', $staff->id) }}">{{ $staff->name }}</a></div>
                    <div class="breadcrumb-item">Edit</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')


                <div class="card">
                    <form method="POST" action="{{ route('superadmin.staff.update', $staff->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="card-header">
                            <h4>Data Staff</h4>
                        </div>

                        @include('pages.superadmin.staff._form')

                        <div class="card-footer text-right">
                            <a href="{{ route('superadmin.staff.show', $staff->id) }}" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary ml-1"><i class="fas fa-save"></i> Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection
