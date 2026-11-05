<?php

namespace App\Http\Controllers\Web\SuperAdmin\Boarding;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\SuperAdmin\School\SuperAdminSchoolClassController;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Dormitory;
use App\Models\DormitoryRoom;
use App\Models\RoomAssignment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Asrama & Kamar (dormitories, dormitory_rooms, room_assignments) untuk pesantren & SD pondok.
 *
 * Dikelola Admin Sekolah/Ustadz. Superadmin: pantau keterisian kamar, temukan masalah
 * (kamar penuh/lebih, santri di asrama beda jenis kelamin, santri asrama belum dapat kamar,
 * penempatan dobel), export, dan koreksi:
 *  - keluarkan santri dari kamar (checkout),
 *  - hapus kamar / asrama yang sudah kosong.
 * Semua wajib alasan & tercatat di Audit Log.
 */
class SuperAdminDormitoryController extends Controller
{
    public const TABS = [
        'dormitories' => 'Asrama',
        'rooms'       => 'Kamar',
        'occupants'   => 'Penghuni',
        'unassigned'  => 'Belum Dapat Kamar',
    ];

    public const GENDERS = ['L' => 'Putra', 'P' => 'Putri'];

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'dormitories';

        $common = [
            'tab'          => $tab,
            'tabs'         => self::TABS,
            'schools'      => Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->orderBy('name')->get(['id', 'name']),
            'companyNames' => Company::pluck('name', 'id'),
            'genders'      => self::GENDERS,
            'dormOptions'  => $request->company_id
                ? Dormitory::where('company_id', $request->company_id)->orderBy('name')->get(['id', 'name', 'gender'])
                : collect(),
            'summary'      => $this->summary($request),
        ];

        $items = match ($tab) {
            'rooms' => $this->roomQuery($request)
                ->with('dormitory:id,name,gender,company_id')
                ->withCount('activeAssignments')
                ->addSelect(['gender_mismatch' => RoomAssignment::selectRaw('count(*)')
                    ->join('students', 'students.id', '=', 'room_assignments.student_id')
                    ->whereColumn('room_assignments.room_id', 'dormitory_rooms.id')
                    ->whereNull('room_assignments.moved_out_at')
                    ->whereRaw('students.gender <> (select d.gender from dormitories d where d.id = dormitory_rooms.dormitory_id)')])
                ->orderBy('dormitory_id')->orderBy('name')
                ->paginate(30),

            'occupants' => $this->occupantQuery($request)
                ->with([
                    'student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis', 'gender', 'class_id', 'is_boarding', 'is_active', 'company_id', 'deleted_at')->with('classRoom:id,name'),
                    'room:id,name,dormitory_id,capacity',
                    'room.dormitory:id,name,gender,company_id',
                    'assignedBy:id,name',
                ])
                ->addSelect(['active_count' => RoomAssignment::from('room_assignments as ra2')->selectRaw('count(*)')
                    ->whereColumn('ra2.student_id', 'room_assignments.student_id')->whereNull('ra2.moved_out_at')])
                ->orderByDesc('moved_in_at')
                ->paginate(30),

            'unassigned' => $this->unassignedQuery($request)
                ->with('classRoom:id,name')
                ->orderBy('company_id')->orderBy('name')
                ->paginate(30),

            default => $this->dormQuery($request)
                ->with('pengasuh:id,name,phone,company_id,is_active')
                ->withCount(['rooms', 'rooms as active_rooms_count' => fn($q) => $q->where('is_active', true)])
                ->withSum(['rooms as capacity_total' => fn($q) => $q->where('is_active', true)], 'capacity')
                ->addSelect(['occupants' => RoomAssignment::selectRaw('count(*)')
                    ->join('dormitory_rooms as r', 'r.id', '=', 'room_assignments.room_id')
                    ->whereColumn('r.dormitory_id', 'dormitories.id')
                    ->whereNull('room_assignments.moved_out_at')])
                ->orderBy('company_id')->orderByDesc('is_active')->orderBy('name')
                ->paginate(30),
        };

        return view('pages.superadmin.boarding.dormitories.index', $common + ['items' => $items->withQueryString()]);
    }

    // =========================================================
    // DETAIL ASRAMA
    // =========================================================
    public function show($id)
    {
        $dorm = Dormitory::with(['company:id,name', 'pengasuh:id,name,phone,email,company_id,is_active'])->findOrFail($id);

        $rooms = DormitoryRoom::where('dormitory_id', $dorm->id)
            ->with(['activeAssignments' => fn($q) => $q->with([
                'student' => fn($s) => $s->withTrashed()->select('id', 'name', 'nis', 'gender', 'class_id', 'is_boarding', 'is_active', 'deleted_at')->with('classRoom:id,name'),
            ])->orderBy('moved_in_at')])
            ->orderBy('name')
            ->get();

        $history = RoomAssignment::whereIn('room_id', $rooms->pluck('id'))
            ->whereNotNull('moved_out_at')
            ->with(['student' => fn($q) => $q->withTrashed()->select('id', 'name'), 'room:id,name'])
            ->latest('moved_out_at')->limit(15)->get();

        return view('pages.superadmin.boarding.dormitories.show', [
            'dorm'    => $dorm,
            'rooms'   => $rooms,
            'history' => $history,
            'genders' => self::GENDERS,
        ]);
    }

    // =========================================================
    // AKSI
    // =========================================================

    /** Keluarkan santri dari kamar (isi moved_out_at), tidak menghapus riwayat */
    public function checkout(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        $assignment = RoomAssignment::with(['student' => fn($q) => $q->withTrashed()->select('id', 'name', 'company_id'), 'room.dormitory:id,name'])->findOrFail($id);

        if ($assignment->moved_out_at) {
            return back()->with('error', 'Penempatan ini sudah selesai sebelumnya.');
        }

        $assignment->forceFill(['moved_out_at' => now()->toDateString()])->save();

        AuditLog::record(
            'checkout_room_assignment',
            null,
            "Santri {$assignment->student?->name} dikeluarkan dari kamar {$assignment->room?->name} ({$assignment->room?->dormitory?->name}), tenant #{$assignment->student?->company_id}. Alasan: {$data['reason']}",
            ['assignment_id' => $assignment->id, 'reason' => $data['reason']]
        );

        return back()->with('success', "{$assignment->student?->name} dikeluarkan dari kamar {$assignment->room?->name}. Riwayat penempatan tetap tersimpan.");
    }

    public function destroy(Request $request, string $tab, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);
        $reason = $data['reason'];

        if ($tab === 'rooms') {
            $room = DormitoryRoom::with('dormitory:id,name,company_id')->withCount(['activeAssignments', 'assignments'])->findOrFail($id);
            if ($room->active_assignments_count > 0) {
                return back()->with('error', "Kamar {$room->name} masih dihuni {$room->active_assignments_count} santri. Keluarkan/pindahkan dulu.");
            }

            $snapshot = $room->getAttributes() + ['history_count' => $room->assignments_count];
            DB::transaction(fn() => $room->delete()); // riwayat penempatan kamar ini ikut terhapus (cascade)

            AuditLog::record('delete_dormitory_room', null, "Kamar {$room->name} ({$room->dormitory?->name}, tenant #{$room->dormitory?->company_id}) dihapus. Alasan: {$reason}", $snapshot + ['reason' => $reason]);

            return redirect()->route('superadmin.boarding.dormitories.show', $room->dormitory_id)->with('success', "Kamar {$room->name} dihapus.");
        }

        abort_unless($tab === 'dormitories', 404);

        $dorm = Dormitory::withCount('rooms')->findOrFail($id);
        $occupants = RoomAssignment::whereNull('moved_out_at')->whereIn('room_id', DormitoryRoom::where('dormitory_id', $dorm->id)->select('id'))->count();
        if ($occupants > 0) {
            return back()->with('error', "Asrama {$dorm->name} masih dihuni {$occupants} santri. Keluarkan/pindahkan dulu.");
        }

        $snapshot = $dorm->getAttributes() + ['rooms' => DormitoryRoom::where('dormitory_id', $dorm->id)->get(['id', 'name', 'capacity'])->toArray()];
        DB::transaction(fn() => $dorm->delete()); // kamar & riwayat penempatannya ikut terhapus (cascade)

        AuditLog::record('delete_dormitory', null, "Asrama {$dorm->name} ({$dorm->rooms_count} kamar, tenant #{$dorm->company_id}) dihapus. Alasan: {$reason}", $snapshot + ['reason' => $reason]);

        return redirect()->route('superadmin.boarding.dormitories.index', ['company_id' => $dorm->company_id])->with('success', "Asrama {$dorm->name} dihapus.");
    }

    // =========================================================
    // EXPORT CSV
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'dormitories';
        $companyNames = Company::pluck('name', 'id');

        AuditLog::record('export_boarding_' . $tab, null, 'Export ' . self::TABS[$tab] . ' ke CSV', ['filters' => $request->query()]);

        return response()->streamDownload(function () use ($tab, $request, $companyNames) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            if ($tab === 'occupants') {
                fputcsv($out, ['Sekolah', 'Asrama', 'Kamar', 'Santri', 'NIS', 'L/P', 'Kelas', 'Masuk', 'Ditempatkan oleh'], ';');
                $this->occupantQuery($request)->with(['student' => fn($q) => $q->withTrashed()->with('classRoom:id,name'), 'room.dormitory', 'assignedBy:id,name'])
                    ->orderBy('room_assignments.id')
                    ->chunk(500, function ($rows) use ($out, $companyNames) {
                        foreach ($rows as $a) {
                            fputcsv($out, [
                                $companyNames[$a->room->dormitory->company_id ?? 0] ?? '-',
                                $a->room->dormitory->name ?? '',
                                $a->room->name ?? '',
                                $a->student->name ?? '',
                                $a->student->nis ?? '',
                                $a->student->gender ?? '',
                                $a->student->classRoom->name ?? '',
                                optional($a->moved_in_at)->format('Y-m-d'),
                                $a->assignedBy->name ?? '',
                            ], ';');
                        }
                    });
            } elseif ($tab === 'unassigned') {
                fputcsv($out, ['Sekolah', 'Santri', 'NIS', 'L/P', 'Kelas'], ';');
                $this->unassignedQuery($request)->with('classRoom:id,name')->orderBy('company_id')->orderBy('name')
                    ->each(fn($s) => fputcsv($out, [$companyNames[$s->company_id] ?? '-', $s->name, $s->nis, $s->gender, $s->classRoom->name ?? ''], ';'));
            } elseif ($tab === 'rooms') {
                fputcsv($out, ['Sekolah', 'Asrama', 'Kamar', 'Kapasitas', 'Penghuni', 'Sisa', 'Status'], ';');
                $this->roomQuery($request)->with('dormitory')->withCount('activeAssignments')->orderBy('dormitory_id')->orderBy('name')
                    ->each(fn($r) => fputcsv($out, [
                        $companyNames[$r->dormitory->company_id ?? 0] ?? '-',
                        $r->dormitory->name ?? '',
                        $r->name,
                        $r->capacity,
                        $r->active_assignments_count,
                        $r->capacity - $r->active_assignments_count,
                        $r->is_active ? 'Aktif' : 'Nonaktif',
                    ], ';'));
            } else {
                fputcsv($out, ['Sekolah', 'Asrama', 'Putra/Putri', 'Pengasuh', 'Kamar', 'Kapasitas', 'Penghuni', 'Status', 'Alamat'], ';');
                $this->dormQuery($request)->with('pengasuh:id,name')->withCount('rooms')->withSum('rooms as capacity_total', 'capacity')
                    ->orderBy('company_id')->orderBy('name')
                    ->each(function ($d) use ($out, $companyNames) {
                        $occ = RoomAssignment::whereNull('moved_out_at')->whereIn('room_id', DormitoryRoom::where('dormitory_id', $d->id)->select('id'))->count();
                        fputcsv($out, [
                            $companyNames[$d->company_id] ?? '-',
                            $d->name,
                            self::GENDERS[$d->gender] ?? $d->gender,
                            $d->pengasuh->name ?? '',
                            $d->rooms_count,
                            (int) $d->capacity_total,
                            $occ,
                            $d->is_active ? 'Aktif' : 'Nonaktif',
                            $d->address
                        ], ';');
                    });
            }

            fclose($out);
        }, 'asrama-' . $tab . '-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // QUERY
    // =========================================================
    private function schoolIds()
    {
        return Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->select('id');
    }

    private function dormQuery(Request $request)
    {
        return Dormitory::query()
            ->whereIn('company_id', $this->schoolIds())
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->gender, fn($q, $g) => $q->where('gender', $g))
            ->when($request->status === 'active', fn($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn($q) => $q->where('is_active', false))
            ->when($request->status === 'no_pengasuh', fn($q) => $q->whereNull('pengasuh_id'))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', '%' . addcslashes($s, '%_\\') . '%'));
    }

    private function roomQuery(Request $request)
    {
        $active = fn($q) => $q->from('room_assignments')->whereColumn('room_assignments.room_id', 'dormitory_rooms.id')->whereNull('moved_out_at')->selectRaw('count(*)');

        return DormitoryRoom::query()
            ->whereIn('dormitory_id', Dormitory::whereIn('company_id', $this->schoolIds())
                ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
                ->when($request->gender, fn($q, $g) => $q->where('gender', $g))
                ->select('id'))
            ->when($request->dormitory_id, fn($q, $id) => $q->where('dormitory_id', $id))
            ->when($request->status === 'full', fn($q) => $q->whereRaw('(' . DB::table('room_assignments')->whereColumn('room_assignments.room_id', 'dormitory_rooms.id')->whereNull('moved_out_at')->selectRaw('count(*)')->toSql() . ') >= capacity'))
            ->when($request->status === 'over', fn($q) => $q->whereRaw('(' . DB::table('room_assignments')->whereColumn('room_assignments.room_id', 'dormitory_rooms.id')->whereNull('moved_out_at')->selectRaw('count(*)')->toSql() . ') > capacity'))
            ->when($request->status === 'empty', fn($q) => $q->whereNotExists(fn($e) => $e->from('room_assignments')->whereColumn('room_assignments.room_id', 'dormitory_rooms.id')->whereNull('moved_out_at')))
            ->when($request->status === 'inactive', fn($q) => $q->where('is_active', false))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', '%' . addcslashes($s, '%_\\') . '%'));
    }

    private function occupantQuery(Request $request)
    {
        $roomIds = DormitoryRoom::whereIn('dormitory_id', Dormitory::whereIn('company_id', $this->schoolIds())
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->gender, fn($q, $g) => $q->where('gender', $g))
            ->when($request->dormitory_id, fn($q, $id) => $q->where('id', $id))
            ->select('id'))->select('id');

        return RoomAssignment::query()
            ->select('room_assignments.*')
            ->whereNull('room_assignments.moved_out_at')
            ->whereIn('room_assignments.room_id', $roomIds)
            ->when($request->status === 'gender_mismatch', fn($q) => $q
                ->join('students as s', 's.id', '=', 'room_assignments.student_id')
                ->join('dormitory_rooms as r', 'r.id', '=', 'room_assignments.room_id')
                ->join('dormitories as d', 'd.id', '=', 'r.dormitory_id')
                ->whereColumn('s.gender', '<>', 'd.gender'))
            ->when($request->status === 'not_boarding', fn($q) => $q->whereIn(
                'room_assignments.student_id',
                Student::withTrashed()->where(fn($w) => $w->where('is_boarding', false)->orWhere('is_active', false)->orWhereNotNull('deleted_at'))->select('id')
            ))
            ->when($request->status === 'duplicate', fn($q) => $q->whereIn(
                'room_assignments.student_id',
                RoomAssignment::whereNull('moved_out_at')->groupBy('student_id')->havingRaw('count(*) > 1')->select('student_id')
            ))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                $q->whereIn('room_assignments.student_id', Student::withTrashed()->where(fn($w) => $w->where('name', 'like', $like)->orWhere('nis', 'like', $like))->select('id'));
            });
    }

    /** Santri asrama (is_boarding) yang aktif tapi belum punya kamar */
    private function unassignedQuery(Request $request)
    {
        return Student::query()
            ->whereIn('company_id', $this->schoolIds())
            ->where('is_boarding', true)->where('is_active', true)
            ->whereDoesntHave('roomAssignments', fn($q) => $q->whereNull('moved_out_at'))
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->gender, fn($q, $g) => $q->where('gender', $g))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                $q->where(fn($w) => $w->where('name', 'like', $like)->orWhere('nis', 'like', $like));
            });
    }

    /** Angka ringkasan (mengikuti filter sekolah) */
    private function summary(Request $request): array
    {
        $plain = new Request($request->only('company_id'));

        $capacity = (int) $this->roomQuery($plain)->where('is_active', true)->sum('capacity');
        $occupied = (int) $this->occupantQuery($plain)->count();

        return [
            'dormitories'     => $this->dormQuery($plain)->count(),
            'rooms'           => $this->roomQuery($plain)->count(),
            'capacity'        => $capacity,
            'occupied'        => $occupied,
            'rate'            => $capacity ? round($occupied / $capacity * 100) : null,
            'unassigned'      => $this->unassignedQuery($plain)->count(),
            'over'            => $this->roomQuery(new Request($request->only('company_id') + ['status' => 'over']))->count(),
            'gender_mismatch' => $this->occupantQuery(new Request($request->only('company_id') + ['status' => 'gender_mismatch']))->count(),
            'duplicate'       => $this->occupantQuery(new Request($request->only('company_id') + ['status' => 'duplicate']))->count(),
            'not_boarding'    => $this->occupantQuery(new Request($request->only('company_id') + ['status' => 'not_boarding']))->count(),
        ];
    }
}
