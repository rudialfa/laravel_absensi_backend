@extends('layouts.app')

@section('title', 'Dashboard Superadmin')

@push('style')
<link rel="stylesheet" href="{{ asset('backend/asset/library/jqvmap/dist/jqvmap.min.css') }}">
<link rel="stylesheet" href="{{ asset('backend/asset/library/summernote/dist/summernote-bs4.min.css') }}">
@endpush

@section('main')
<div class="main-content">
  <section class="section">
    <div class="section-header">
      <h1>Dashboard Superadmin</h1>
    </div>

    {{-- Statistik Utama --}}
    <div class="row">

      <div class="col-lg-2 col-md-6 col-sm-6 col-12">
        <div class="card card-statistic-1">
          <div class="card-icon bg-info">
            <i class="fas fa-building"></i>
          </div>
          <div class="card-wrap">
            <div class="card-header"><h4>Total Perusahaan</h4></div>
            <div class="card-body">{{ $totalCompanies }}</div>
          </div>
        </div>
      </div>

      <div class="col-lg-2 col-md-6 col-sm-6 col-12">
        <div class="card card-statistic-1">
          <div class="card-icon bg-primary">
            <i class="fas fa-users"></i>
          </div>
          <div class="card-wrap">
            <div class="card-header"><h4>Total Karyawan</h4></div>
            <div class="card-body">{{ $totalEmployees }}</div>
          </div>
        </div>
      </div>

      <div class="col-lg-2 col-md-6 col-sm-6 col-12">
        <div class="card card-statistic-1">
          <div class="card-icon bg-success">
            <i class="fas fa-user-check"></i>
          </div>
          <div class="card-wrap">
            <div class="card-header"><h4>Hadir Hari Ini</h4></div>
            <div class="card-body">{{ $todayPresent }}</div>
          </div>
        </div>
      </div>

      <div class="col-lg-2 col-md-6 col-sm-6 col-12">
        <div class="card card-statistic-1">
          <div class="card-icon bg-warning">
            <i class="fas fa-clock"></i>
          </div>
          <div class="card-wrap">
            <div class="card-header"><h4>Terlambat</h4></div>
            <div class="card-body">{{ $todayLate }}</div>
          </div>
        </div>
      </div>

      <div class="col-lg-2 col-md-6 col-sm-6 col-12">
        <div class="card card-statistic-1">
          <div class="card-icon bg-danger">
            <i class="fas fa-user-times"></i>
          </div>
          <div class="card-wrap">
            <div class="card-header"><h4>Izin / Cuti</h4></div>
            <div class="card-body">{{ $todayPermission }}</div>
          </div>
        </div>
      </div>

      <div class="col-lg-2 col-md-6 col-sm-6 col-12">
        <div class="card card-statistic-1">
          <div class="card-icon bg-secondary">
            <i class="fas fa-map-marker-alt"></i>
          </div>
          <div class="card-wrap">
            <div class="card-header"><h4>Luar Area</h4></div>
            <div class="card-body">{{ $outsideAttendance }}</div>
          </div>
        </div>
      </div>

    </div>

    {{-- Grafik Kehadiran --}}
    <div class="row">
      <div class="col-lg-8">
        <div class="card">
          <div class="card-header">
            <h4>Statistik Kehadiran Hari Ini</h4>
          </div>

          <div class="card-body text-center p-3" style="height: 450px;">
            <canvas id="attendanceDoughnut" style="max-height: 300px;"></canvas>

            <div class="statistic-details mt-2">
              <div class="statistic-details-item">
                <div class="detail-value">{{ $todayPresent }}</div>
                <div class="detail-name">Hadir</div>
              </div>
              <div class="statistic-details-item">
                <div class="detail-value">{{ $todayLate }}</div>
                <div class="detail-name">Terlambat</div>
              </div>
              <div class="statistic-details-item">
                <div class="detail-value">{{ $todayPermission }}</div>
                <div class="detail-name">Izin</div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- Daftar Kehadiran --}}
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header"><h4>Kehadiran Hari Ini</h4></div>
          <div class="card-body p-3">
            <ul class="list-unstyled list-unstyled-border">

              @forelse($todayAttendanceList as $item)
                <li class="media">
                  <img class="rounded-circle mr-3" width="45"
                       src="{{ $item->user->image_url ?? asset('backend/asset/img/avatar/avatar-1.png') }}">
                  <div class="media-body">
                    <div class="float-right text-{{ $item->status === 'late' ? 'danger' : 'success' }}">
                        {{ $item->time_in ?? '-' }}
                    </div>
                    <div class="media-title">{{ $item->user->name }}</div>
                    <span class="text-small text-muted">{{ ucfirst($item->status) }}</span>
                  </div>
                </li>
              @empty
              <p class="text-center">Belum ada data</p>
              @endforelse

            </ul>
          </div>
        </div>
      </div>
    </div>

    {{-- Permission Table --}}
    <div class="row">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header"><h4>Permintaan Izin / Cuti</h4></div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped mb-0">
                <thead>
                  <tr><th>Nama</th><th>Tanggal</th><th>Alasan</th><th>Status</th></tr>
                </thead>
                <tbody>
                  @foreach($permissionList as $p)
                  <tr>
                    <td>{{ $p->user->name }}</td>
                    <td>{{ $p->date_permission }}</td>
                    <td>{{ $p->reason }}</td>
                    <td>
                      <span class="badge badge-{{ $p->is_approved ? 'success' : 'warning' }}">
                        {{ $p->is_approved ? 'Disetujui' : 'Menunggu' }}
                      </span>
                    </td>
                  </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      {{-- Quick Actions --}}
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header"><h4>Aksi Cepat</h4></div>
          <div class="card-body">
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary mb-2">
              <i class="fas fa-user-plus"></i> Tambah User
            </a>
            <a href="{{ route('admin.shifts.index') }}" class="btn btn-info mb-2">
              <i class="fas fa-calendar-plus"></i> Kelola Shift
            </a>
            <a href="{{ route('admin.reports.index') }}" class="btn btn-success mb-2">
              <i class="fas fa-file-export"></i> Export Laporan
            </a>
            <a href="{{ route('admin.attendances.index') }}" class="btn btn-warning mb-2">
              <i class="fas fa-map-marker-alt"></i> Cek Lokasi Absen
            </a>
          </div>
        </div>
      </div>

    </div>

  </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('backend/asset/library/chart.js/dist/Chart.min.js') }}"></script>
<script>
new Chart(document.getElementById('attendanceDoughnut'), {
  type: 'doughnut',
  data: {
    labels: ['Hadir', 'Terlambat', 'Izin'],
    datasets: [{
      data: [{{ $todayPresent }}, {{ $todayLate }}, {{ $todayPermission }}],
      backgroundColor: ['#47c363', '#ffa426', '#fc544b']
    }]
  },
  options: {
    cutout: '70%',
    responsive: true,
    plugins: { legend: { position: 'bottom' } }
  }
});
</script>
@endpush
