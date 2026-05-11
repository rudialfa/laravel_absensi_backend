{{-- resources/views/employee/holiday/index.blade.php --}}
@extends('layouts.employee')
@section('title','Hari Libur')
@section('page-title','Hari Libur')
@section('content')
<div class="card">
    <div class="card-header"><span class="card-title">Daftar Hari Libur</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Tanggal</th><th>Nama Libur</th><th>Tipe</th><th></th></tr></thead>
            <tbody>
                @forelse($data as $h)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($h['date'])->isoFormat('D MMM Y') }}</td>
                    <td style="font-weight:500">{{ $h['name'] ?? '—' }}</td>
                    <td>
                        <span class="badge {{ ($h['type']??'national')==='national' ? 'badge-danger' : 'badge-primary' }}">
                            {{ ($h['type']??'national')==='national' ? 'Nasional' : 'Perusahaan' }}
                        </span>
                    </td>
                    <td><a href="{{ route('employee.holiday.show', $h['id']) }}" class="btn btn-outline btn-sm">Detail</a></td>
                </tr>
                @empty
                <tr><td colspan="4"><div class="empty"><i class="fas fa-umbrella-beach"></i><p>Tidak ada hari libur</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection