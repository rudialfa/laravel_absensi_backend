@extends('layouts.app')

@section('title', 'Analytics')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Analytics</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item">Analytics</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Analytics &amp; Revenue</h2>
                <p class="section-lead">
                    Ringkasan performa langganan &amp; pertumbuhan tenant.
                </p>

                <div class="row mt-4">
                    <div class="col-md-3 col-6">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-primary"><i class="fas fa-money-bill-wave"></i></div>
                            <div class="card-wrap">
                                <div class="card-header">
                                    <h4>Revenue Bulan Ini</h4>
                                </div>
                                <div class="card-body">
                                    Rp{{ number_format($revenueThisMonth, 0, ',', '.') }}
                                    <br>
                                    <small class="{{ $revenueGrowthPercent >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $revenueGrowthPercent >= 0 ? '▲' : '▼' }} {{ abs($revenueGrowthPercent) }}% vs
                                        bulan lalu
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-success"><i class="fas fa-building"></i></div>
                            <div class="card-wrap">
                                <div class="card-header">
                                    <h4>Tenant Aktif</h4>
                                </div>
                                <div class="card-body">
                                    {{ $activeTenants }}
                                    <br><small class="text-muted">{{ $suspendedTenants }} disuspend</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-warning"><i class="fas fa-file-invoice"></i></div>
                            <div class="card-wrap">
                                <div class="card-header">
                                    <h4>Invoice Pending</h4>
                                </div>
                                <div class="card-body">
                                    {{ $pendingInvoices }}
                                    <br><small class="text-danger">{{ $overdueInvoices }} overdue</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-info"><i class="fas fa-layer-group"></i></div>
                            <div class="card-wrap">
                                <div class="card-header">
                                    <h4>Tenant per Tipe</h4>
                                </div>
                                <div class="card-body">
                                    @foreach ($tenantsByType as $type => $total)
                                        {{ ucfirst($type) }}: <strong>{{ $total }}</strong><br>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-1">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Subscription per Status</h4>
                            </div>
                            <div class="card-body">
                                <canvas id="chartStatus" height="220"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Revenue per Paket (Lunas)</h4>
                            </div>
                            <div class="card-body">
                                <canvas id="chartRevenuePlan" height="220"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-1">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Tren Tenant Baru (6 Bulan Terakhir)</h4>
                            </div>
                            <div class="card-body">
                                <canvas id="chartNewTenants" height="120"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <script>
        new Chart(document.getElementById('chartStatus'), {
            type: 'doughnut',
            data: {
                labels: {!! json_encode(array_map('ucfirst', array_keys($subscriptionsByStatus->toArray()))) !!},
                datasets: [{
                    data: {!! json_encode(array_values($subscriptionsByStatus->toArray())) !!}
                }]
            }
        });

        new Chart(document.getElementById('chartRevenuePlan'), {
            type: 'bar',
            data: {
                labels: {!! json_encode($revenueByPlan->pluck('plan_name')) !!},
                datasets: [{
                    label: 'Revenue (Rp)',
                    data: {!! json_encode($revenueByPlan->pluck('total')) !!}
                }]
            }
        });

        new Chart(document.getElementById('chartNewTenants'), {
            type: 'line',
            data: {
                labels: {!! json_encode(array_keys($newTenantsTrend->toArray())) !!},
                datasets: [{
                    label: 'Tenant Baru',
                    data: {!! json_encode(array_values($newTenantsTrend->toArray())) !!},
                    tension: 0.3
                }]
            }
        });
    </script>
@endpush
