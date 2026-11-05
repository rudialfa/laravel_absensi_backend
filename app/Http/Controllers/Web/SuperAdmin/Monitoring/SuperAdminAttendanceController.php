<?php

namespace App\Http\Controllers\Web\SuperAdmin\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Services\AttendancePhotoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monitoring absensi karyawan semua tenant perusahaan.
 * Superadmin hanya MELIHAT & menghapus data yang salah — input/koreksi absen tetap wewenang HR.
 */
class SuperAdminAttendanceController extends Controller
{
    public const STATUSES = [
        'on_time'    => 'Tepat waktu',
        'late'       => 'Terlambat',
        'overtime'   => 'Lembur',
        'permission' => 'Izin',
        'absent'     => 'Tidak hadir',
        'guest'      => 'Tamu',
    ];

    public const STATUS_BADGE = [
        'on_time'    => 'success',
        'late'       => 'warning',
        'overtime'   => 'info',
        'permission' => 'primary',
        'absent'     => 'danger',
        'guest'      => 'secondary',
    ];

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $attendances = $this->filteredQuery($request, $from, $to)
            ->with(['company:id,name', 'user:id,name,position,department', 'shift:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('time_in')
            ->paginate(25)
            ->withQueryString();

        $summary = $this->filteredQuery($request, $from, $to)
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(status = 'on_time') as on_time")
            ->selectRaw("sum(status = 'late') as late")
            ->selectRaw('sum(time_in is not null and time_out is null) as no_checkout')
            ->selectRaw('sum(is_outside_radius_in = 1 or is_outside_radius_out = 1) as outside')
            ->selectRaw('sum(marked_by is not null) as manual')
            ->first();

        // Rekap per tenant untuk 1 hari (dipakai kalau rentang = 1 hari & tidak filter tenant)
        $tenantRecap = collect();
        if ($from->isSameDay($to) && !$request->filled('company_id')) {
            $tenantRecap = $this->tenantRecap($from);
        }

        return view('pages.superadmin.monitoring.attendances.index', [
            'attendances'   => $attendances,
            'summary'       => $summary,
            'tenantRecap'   => $tenantRecap,
            'from'          => $from,
            'to'            => $to,
            'companies'     => Company::where('type', 'company')->orderBy('name')->get(['id', 'name']),
            'employees'     => $request->filled('company_id')
                ? User::where('company_id', $request->company_id)->whereIn('role', ['employee', 'hr'])->orderBy('name')->get(['id', 'name'])
                : collect(),
            'statuses'      => self::STATUSES,
            'badge'         => self::STATUS_BADGE,
        ]);
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show($id)
    {
        $attendance = Attendance::with([
            'company:id,name,latitude,longitude,radius_km,time_in,time_out,timezone',
            'user:id,name,email,position,department,image_url',
            'shift',
            'markedBy:id,name',
            'permission',
        ])->findOrFail($id);

        // Riwayat 14 hari terakhir karyawan yang sama
        $recent = Attendance::where('user_id', $attendance->user_id)
            ->where('id', '!=', $attendance->id)
            ->orderByDesc('date')
            ->limit(14)
            ->get(['id', 'date', 'time_in', 'time_out', 'status', 'late_minutes']);

        return view('pages.superadmin.monitoring.attendances.show', [
            'attendance' => $attendance,
            'recent'     => $recent,
            'statuses'   => self::STATUSES,
            'badge'      => self::STATUS_BADGE,
        ]);
    }

    // =========================================================
    // DESTROY — hapus data absen yang salah/duplikat
    // =========================================================
    public function destroy(Request $request, $id, AttendancePhotoService $photos)
    {
        $attendance = Attendance::with(['user:id,name', 'company:id,name'])->findOrFail($id);

        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        $snapshot = $attendance->only([
            'id',
            'company_id',
            'user_id',
            'date',
            'time_in',
            'time_out',
            'status',
            'late_minutes',
            'latlon_in',
            'latlon_out',
            'photo_in',
            'photo_out',
        ]);
        $snapshot['date'] = optional($attendance->date)->toDateString();

        DB::transaction(fn() => $attendance->delete());

        // Foto ikut dihapus dari server
        $photos->delete($attendance->photo_in);
        $photos->delete($attendance->photo_out);

        AuditLog::record(
            'delete_attendance',
            null,
            "Absen {$attendance->user?->name} ({$attendance->company?->name}) tanggal {$snapshot['date']} dihapus. Alasan: {$data['reason']}",
            array_merge($snapshot, ['reason' => $data['reason']])
        );

        return redirect()
            ->route('superadmin.monitoring.attendances.index', ['company_id' => $attendance->company_id, 'from' => $snapshot['date'], 'to' => $snapshot['date']])
            ->with('success', 'Data absen dihapus.');
    }

    // =========================================================
    // EXPORT CSV
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);

        $query = $this->filteredQuery($request, $from, $to)
            ->with(['company:id,name', 'user:id,name,department', 'shift:id,name', 'markedBy:id,name'])
            ->orderBy('company_id')->orderBy('date')->orderBy('user_id');

        $fileName = 'absensi-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv';

        AuditLog::record('export_attendances', null, "Export absensi {$from->toDateString()} s/d {$to->toDateString()}", ['filters' => $request->query()]);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Tanggal',
                'Tenant',
                'Karyawan',
                'Departemen',
                'Shift',
                'Jadwal Masuk',
                'Jadwal Pulang',
                'Masuk',
                'Pulang',
                'Status',
                'Telat (menit)',
                'Pulang cepat (menit)',
                'Lembur (menit)',
                'Jarak masuk (m)',
                'Di luar radius',
                'Diinput HR',
                'Catatan'
            ], ';');

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $a) {
                    fputcsv($out, [
                        optional($a->date)->format('Y-m-d'),
                        $a->company->name ?? '-',
                        $a->user->name ?? '-',
                        $a->user->department ?? '',
                        $a->shift->name ?? '',
                        $a->scheduled_in ? substr($a->scheduled_in, 0, 5) : '',
                        $a->scheduled_out ? substr($a->scheduled_out, 0, 5) : '',
                        $a->time_in ? substr($a->time_in, 0, 5) : '',
                        $a->time_out ? substr($a->time_out, 0, 5) : '',
                        self::STATUSES[$a->status] ?? $a->status,
                        (int) $a->late_minutes,
                        (int) $a->early_leave_minutes,
                        (int) $a->overtime_minutes,
                        $a->distance_in_m,
                        ($a->is_outside_radius_in || $a->is_outside_radius_out) ? 'Ya' : '',
                        $a->markedBy->name ?? '',
                        $a->note,
                    ], ';');
                }
            });

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // HELPER
    // =========================================================

    /** @return array{0: Carbon, 1: Carbon} default = hari ini */
    private function dateRange(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : today();
        $to   = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : $from->copy()->endOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        // Batasi maksimal 1 tahun supaya query tetap ringan
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subYear()->startOfDay();
        }

        return [$from, $to];
    }

    private function filteredQuery(Request $request, Carbon $from, Carbon $to)
    {
        $query = Attendance::query()->whereBetween('date', [$from->toDateString(), $to->toDateString()]);

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        match ($request->input('flag')) {
            'outside'     => $query->where(fn($q) => $q->where('is_outside_radius_in', true)->orWhere('is_outside_radius_out', true)),
            'no_checkout' => $query->whereNotNull('time_in')->whereNull('time_out'),
            'manual'      => $query->whereNotNull('marked_by'),
            'permission'  => $query->whereNotNull('permission_id'),
            default       => null,
        };

        if ($request->filled('search')) {
            $like = '%' . addcslashes(trim($request->search), '%_\\') . '%';
            $query->whereHas('user', fn($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like));
        }

        return $query;
    }

    /** Rekap 1 hari per tenant perusahaan: karyawan, masuk, telat, belum absen */
    private function tenantRecap(Carbon $date)
    {
        $employees = User::where('role', 'employee')
            ->whereNotNull('company_id')
            ->where('is_active', true)
            ->select('company_id', DB::raw('count(*) as total'))
            ->groupBy('company_id')
            ->pluck('total', 'company_id');

        $att = Attendance::whereDate('date', $date->toDateString())
            ->select('company_id')
            ->selectRaw('sum(time_in is not null) as checkin')
            ->selectRaw("sum(status = 'late') as late")
            ->selectRaw('sum(time_out is not null) as checkout')
            ->groupBy('company_id')
            ->get()
            ->keyBy('company_id');

        return Company::where('type', 'company')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(function ($c) use ($employees, $att) {
                $total   = (int) ($employees[$c->id] ?? 0);
                $row     = $att[$c->id] ?? null;
                $checkin = (int) ($row->checkin ?? 0);

                return (object) [
                    'id'        => $c->id,
                    'name'      => $c->name,
                    'is_active' => $c->is_active,
                    'employees' => $total,
                    'checkin'   => $checkin,
                    'late'      => (int) ($row->late ?? 0),
                    'checkout'  => (int) ($row->checkout ?? 0),
                    'absent'    => max(0, $total - $checkin),
                    'rate'      => $total > 0 ? round($checkin / $total * 100) : null,
                ];
            });
    }
}
