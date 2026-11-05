<?php

namespace App\Http\Controllers\Web\SuperAdmin\School;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillPayment;
use App\Models\BillType;
use App\Models\ClassRoom;
use App\Models\Company;
use App\Models\Student;
use App\Models\StudentBill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monitoring Keuangan Sekolah (SPP, uang buku, asrama, dll) semua sekolah/pesantren.
 * Tabel: bill_types, student_bills, bill_payments.
 *
 * Ini uang milik sekolah (bukan langganan aplikasi). Superadmin hanya memantau, rekap piutang,
 * export, dan koreksi data salah:
 *  - hapus tagihan yang BELUM ada pembayarannya,
 *  - hapus pembayaran yang tercatat dobel/salah (status tagihan dihitung ulang),
 *  - hapus jenis tagihan yang belum pernah dipakai.
 * Semua wajib alasan & tercatat di Audit Log.
 */
class SuperAdminSchoolFinanceController extends Controller
{
    public const TABS = [
        'recap'    => 'Rekap per Sekolah',
        'bills'    => 'Tagihan Murid',
        'payments' => 'Pembayaran',
        'types'    => 'Jenis Tagihan',
    ];

    public const BILL_STATUS = ['belum_lunas' => 'Belum Lunas', 'sebagian' => 'Dibayar Sebagian', 'lunas' => 'Lunas'];
    public const BILL_BADGE = ['belum_lunas' => 'danger', 'sebagian' => 'warning', 'lunas' => 'success'];
    public const PERIODS = ['bulanan' => 'Bulanan', 'sekali' => 'Sekali bayar'];
    public const METHODS = ['cash' => 'Tunai', 'transfer' => 'Transfer'];

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'recap';

        $common = [
            'tab'          => $tab,
            'tabs'         => self::TABS,
            'schools'      => $this->schools(),
            'companyNames' => Company::pluck('name', 'id'),
            'classOptions' => $request->company_id
                ? ClassRoom::where('company_id', $request->company_id)->orderByDesc('is_active')->orderBy('grade_level')->orderBy('name')->get(['id', 'name', 'academic_year'])
                : collect(),
            'typeOptions'  => $request->company_id
                ? BillType::where('company_id', $request->company_id)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'billStatus'   => self::BILL_STATUS,
            'billBadge'    => self::BILL_BADGE,
            'periods'      => self::PERIODS,
            'methods'      => self::METHODS,
            'months'       => $this->monthNames(),
        ];

        return match ($tab) {
            'bills'    => $this->billsTab($request, $common),
            'payments' => $this->paymentsTab($request, $common),
            'types'    => $this->typesTab($request, $common),
            default    => $this->recapTab($request, $common),
        };
    }

    private function recapTab(Request $request, array $common)
    {
        $schoolIds = $request->company_id ? [(int) $request->company_id] : $common['schools']->pluck('id')->all();
        $today = today()->toDateString();

        $billRows = $this->billQuery($request, withStatus: false)
            ->toBase()
            ->selectRaw("student_bills.company_id as k,
                count(*) as bills,
                coalesce(sum(student_bills.amount), 0) as billed,
                coalesce(sum(paid.total), 0) as paid,
                sum(student_bills.status <> 'lunas' and student_bills.due_date < ?) as overdue,
                coalesce(sum(case when student_bills.status <> 'lunas' and student_bills.due_date < ? then student_bills.amount - coalesce(paid.total, 0) end), 0) as overdue_amount,
                count(distinct case when student_bills.status <> 'lunas' then student_bills.student_id end) as students_owing", [$today, $today])
            ->groupBy('student_bills.company_id')
            ->get()->keyBy('k');

        // Uang masuk 30 hari terakhir per sekolah
        $income30 = BillPayment::query()
            ->join('student_bills', 'student_bills.id', '=', 'bill_payments.student_bill_id')
            ->whereIn('student_bills.company_id', $schoolIds)
            ->where('bill_payments.paid_at', '>=', today()->subDays(29)->toDateString())
            ->toBase()
            ->selectRaw('student_bills.company_id as k, sum(bill_payments.amount) as total, max(bill_payments.paid_at) as last_paid')
            ->groupBy('student_bills.company_id')
            ->get()->keyBy('k');

        $recap = $common['schools']->whereIn('id', $schoolIds)->map(function ($s) use ($billRows, $income30) {
            $r = $billRows[$s->id] ?? null;
            $billed = (float) ($r->billed ?? 0);
            $paid = (float) ($r->paid ?? 0);

            return [
                'id'             => $s->id,
                'name'           => $s->name,
                'type'           => $s->type,
                'bills'          => (int) ($r->bills ?? 0),
                'billed'         => $billed,
                'paid'           => $paid,
                'outstanding'    => max(0, $billed - $paid),
                'rate'           => $billed > 0 ? round($paid / $billed * 100, 1) : null,
                'overdue'        => (int) ($r->overdue ?? 0),
                'overdue_amount' => (float) ($r->overdue_amount ?? 0),
                'students_owing' => (int) ($r->students_owing ?? 0),
                'income30'       => (float) ($income30[$s->id]->total ?? 0),
                'last_paid'      => $income30[$s->id]->last_paid ?? null,
            ];
        })->sortByDesc('outstanding')->values();

        return view('pages.superadmin.school.finance.index', $common + [
            'recap'  => $recap,
            'totals' => [
                'billed'      => $recap->sum('billed'),
                'paid'        => $recap->sum('paid'),
                'outstanding' => $recap->sum('outstanding'),
                'overdue'     => $recap->sum('overdue_amount'),
                'income30'    => $recap->sum('income30'),
            ],
        ]);
    }

    private function billsTab(Request $request, array $common)
    {
        $query = $this->billQuery($request);

        $items = (clone $query)
            ->select('student_bills.*')
            ->selectRaw('coalesce(paid.total, 0) as paid_total, coalesce(paid.cnt, 0) as payments_count')
            ->with([
                'student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis', 'class_id', 'deleted_at')->with('classRoom:id,name'),
                'billType:id,name,periode',
            ])
            ->orderByRaw("student_bills.status = 'lunas'")
            ->orderBy('student_bills.due_date')
            ->paginate(30)->withQueryString();

        $summary = $this->billQuery($request, withStatus: false)->toBase()
            ->selectRaw("count(*) as total,
                coalesce(sum(student_bills.amount), 0) as billed,
                coalesce(sum(paid.total), 0) as paid,
                sum(student_bills.status = 'lunas') as lunas,
                sum(student_bills.status <> 'lunas' and student_bills.due_date < ?) as overdue", [today()->toDateString()])
            ->first();

        return view('pages.superadmin.school.finance.index', $common + ['items' => $items, 'summary' => $summary]);
    }

    private function paymentsTab(Request $request, array $common)
    {
        $query = $this->paymentQuery($request);

        $items = (clone $query)
            ->with([
                'studentBill' => fn($q) => $q->with([
                    'student' => fn($s) => $s->withTrashed()->select('id', 'name', 'nis', 'class_id', 'deleted_at')->with('classRoom:id,name'),
                    'billType:id,name',
                ]),
                'recordedBy:id,name',
            ])
            ->orderByDesc('bill_payments.paid_at')->orderByDesc('bill_payments.id')
            ->paginate(30)->withQueryString();

        // select() mengganti kolom bill_payments.* supaya agregat tidak bentrok dengan ONLY_FULL_GROUP_BY
        $summary = (clone $query)->toBase()->select(DB::raw("
                count(*) as total,
                coalesce(sum(bill_payments.amount), 0) as amount,
                coalesce(sum(case when bill_payments.method = 'cash' then bill_payments.amount end), 0) as cash,
                coalesce(sum(case when bill_payments.method = 'transfer' then bill_payments.amount end), 0) as transfer
            "))->first();

        return view('pages.superadmin.school.finance.index', $common + ['items' => $items, 'summary' => $summary]);
    }

    private function typesTab(Request $request, array $common)
    {
        $items = BillType::query()
            ->whereIn('company_id', $this->schoolIdsQuery())
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->periode, fn($q, $p) => $q->where('periode', $p))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', '%' . addcslashes($s, '%_\\') . '%'))
            ->withCount('bills')
            ->withSum('bills as billed_total', 'amount')
            ->addSelect(['last_period' => StudentBill::selectRaw("max(concat(period_year, '-', lpad(period_month, 2, '0')))")
                ->whereColumn('student_bills.bill_type_id', 'bill_types.id')])
            ->orderBy('company_id')->orderByDesc('is_active')->orderBy('name')
            ->paginate(30)->withQueryString();

        return view('pages.superadmin.school.finance.index', $common + ['items' => $items]);
    }

    // =========================================================
    // SHOW TAGIHAN
    // =========================================================
    public function show($id)
    {
        $bill = StudentBill::with([
            'company:id,name',
            'student' => fn($q) => $q->withTrashed()->with(['classRoom:id,name,academic_year', 'guardians:id,name,phone']),
            'billType',
            'createdBy:id,name',
            'payments' => fn($q) => $q->with('recordedBy:id,name')->orderBy('paid_at')->orderBy('id'),
        ])->findOrFail($id);

        // Tagihan lain murid yang sama
        $others = StudentBill::where('student_id', $bill->student_id)->where('id', '!=', $bill->id)
            ->with('payments:id,student_bill_id,amount')
            ->orderByDesc('due_date')->limit(12)->get();

        return view('pages.superadmin.school.finance.show', [
            'bill'       => $bill,
            'others'     => $others,
            'billStatus' => self::BILL_STATUS,
            'billBadge'  => self::BILL_BADGE,
            'periods'    => self::PERIODS,
            'methods'    => self::METHODS,
            'months'     => $this->monthNames(),
            // Status tersimpan vs hasil hitung ulang (deteksi data tidak sinkron)
            'computedStatus' => $this->computeStatus((float) $bill->amount, (float) $bill->payments->sum('amount')),
        ]);
    }

    // =========================================================
    // DESTROY (?tab=bills|payments|types)
    // =========================================================
    public function destroy(Request $request, string $tab, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);
        $reason = $data['reason'];
        $money = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

        if ($tab === 'payments') {
            $payment = BillPayment::with(['studentBill.student' => fn($q) => $q->withTrashed()->select('id', 'name')])->findOrFail($id);
            $bill = $payment->studentBill;
            $snapshot = $payment->getAttributes();

            DB::transaction(function () use ($payment, $bill) {
                $payment->delete();
                $bill?->refreshStatus();
            });

            AuditLog::record(
                'delete_school_payment',
                null,
                "Pembayaran {$payment->receipt_number} ({$money($payment->amount)}) tagihan \"{$bill?->title}\" murid {$bill?->student?->name} (tenant #{$bill?->company_id}) dihapus. Alasan: {$reason}",
                $snapshot + ['reason' => $reason, 'bill_status_after' => $bill?->fresh()?->status]
            );

            return redirect()->route('superadmin.school.finance.show', $bill->id)
                ->with('success', "Pembayaran {$payment->receipt_number} dihapus. Status tagihan dihitung ulang.");
        }

        if ($tab === 'types') {
            $type = BillType::withCount('bills')->findOrFail($id);
            if ($type->bills_count > 0) {
                return back()->with('error', "Jenis tagihan {$type->name} sudah dipakai di {$type->bills_count} tagihan. Menghapusnya akan ikut menghapus semua tagihan & pembayarannya.");
            }

            $snapshot = $type->getAttributes();
            $type->delete();

            AuditLog::record('delete_bill_type', null, "Jenis tagihan {$type->name} (tenant #{$type->company_id}) dihapus. Alasan: {$reason}", $snapshot + ['reason' => $reason]);

            return redirect()->route('superadmin.school.finance.index', ['tab' => 'types', 'company_id' => $type->company_id])
                ->with('success', "Jenis tagihan {$type->name} dihapus.");
        }

        abort_unless($tab === 'bills', 404);

        $bill = StudentBill::withCount('payments')->with(['student' => fn($q) => $q->withTrashed()->select('id', 'name')])->findOrFail($id);
        if ($bill->payments_count > 0) {
            return back()->with('error', "Tagihan ini sudah punya {$bill->payments_count} pembayaran. Hapus pembayarannya dulu satu per satu (dengan alasan) bila memang salah catat.");
        }

        $snapshot = $bill->getAttributes();
        $bill->delete();

        AuditLog::record(
            'delete_student_bill',
            null,
            "Tagihan \"{$bill->title}\" ({$money($bill->amount)}) murid {$bill->student?->name} (tenant #{$bill->company_id}) dihapus. Alasan: {$reason}",
            $snapshot + ['reason' => $reason]
        );

        return redirect()->route('superadmin.school.finance.index', ['tab' => 'bills', 'company_id' => $bill->company_id])
            ->with('success', "Tagihan \"{$bill->title}\" dihapus.");
    }

    /** Hitung ulang status tagihan dari total pembayaran (perbaiki status yang tidak sinkron) */
    public function resync(Request $request, $id)
    {
        $bill = StudentBill::findOrFail($id);
        $before = $bill->status;

        $bill->refreshStatus();

        if ($before !== $bill->status) {
            AuditLog::record('resync_student_bill', null, "Status tagihan #{$bill->id} disinkronkan: {$before} → {$bill->status}", ['bill_id' => $bill->id, 'before' => $before, 'after' => $bill->status]);
        }

        return back()->with('success', $before === $bill->status ? 'Status tagihan sudah sesuai.' : "Status tagihan diperbaiki: " . (self::BILL_STATUS[$before] ?? $before) . ' → ' . (self::BILL_STATUS[$bill->status] ?? $bill->status) . '.');
    }

    // =========================================================
    // EXPORT CSV
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'recap';
        $companyNames = Company::pluck('name', 'id');
        $months = $this->monthNames();

        AuditLog::record('export_school_finance_' . $tab, null, 'Export ' . self::TABS[$tab] . ' (keuangan sekolah) ke CSV', ['filters' => $request->query()]);

        if ($tab === 'recap') {
            $recap = $this->recapTab($request, ['schools' => $this->schools()] + ['tab' => 'recap', 'tabs' => self::TABS])->getData()['recap'];

            return $this->csv(
                'rekap-keuangan-sekolah',
                ['Sekolah', 'Jumlah tagihan', 'Total tagihan', 'Terbayar', 'Piutang', '% terbayar', 'Tagihan lewat jatuh tempo', 'Nominal lewat jatuh tempo', 'Murid menunggak', 'Masuk 30 hari', 'Bayar terakhir'],
                function ($out) use ($recap) {
                    foreach ($recap as $r) {
                        fputcsv($out, [$r['name'], $r['bills'], $r['billed'], $r['paid'], $r['outstanding'], $r['rate'], $r['overdue'], $r['overdue_amount'], $r['students_owing'], $r['income30'], $r['last_paid']], ';');
                    }
                }
            );
        }

        if ($tab === 'payments') {
            return $this->csv(
                'pembayaran-sekolah',
                ['Sekolah', 'No. kwitansi', 'Tanggal bayar', 'Murid', 'NIS', 'Kelas', 'Tagihan', 'Jenis', 'Nominal', 'Metode', 'Dicatat oleh', 'Catatan'],
                function ($out) use ($request, $companyNames) {
                    $this->paymentQuery($request)
                        ->with(['studentBill' => fn($q) => $q->with(['student' => fn($s) => $s->withTrashed()->with('classRoom:id,name'), 'billType:id,name']), 'recordedBy:id,name'])
                        ->orderBy('bill_payments.id')
                        ->chunk(500, function ($rows) use ($out, $companyNames) {
                            foreach ($rows as $p) {
                                $b = $p->studentBill;
                                fputcsv($out, [
                                    $companyNames[$b->company_id ?? 0] ?? '-',
                                    $p->receipt_number,
                                    optional($p->paid_at)->format('Y-m-d'),
                                    $b->student->name ?? '',
                                    $b->student->nis ?? '',
                                    $b->student->classRoom->name ?? '',
                                    $b->title ?? '',
                                    $b->billType->name ?? '',
                                    (float) $p->amount,
                                    self::METHODS[$p->method] ?? $p->method,
                                    $p->recordedBy->name ?? '',
                                    $p->notes,
                                ], ';');
                            }
                        });
                }
            );
        }

        if ($tab === 'types') {
            return $this->csv(
                'jenis-tagihan-sekolah',
                ['Sekolah', 'Nama', 'Periode', 'Nominal default', 'Status', 'Jumlah tagihan'],
                function ($out) use ($request, $companyNames) {
                    BillType::whereIn('company_id', $this->schoolIdsQuery())
                        ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
                        ->withCount('bills')->orderBy('company_id')->orderBy('name')
                        ->each(function ($t) use ($out, $companyNames) {
                            fputcsv($out, [$companyNames[$t->company_id] ?? '-', $t->name, self::PERIODS[$t->periode] ?? $t->periode, (float) $t->default_amount, $t->is_active ? 'Aktif' : 'Nonaktif', $t->bills_count], ';');
                        });
                }
            );
        }

        return $this->csv(
            'tagihan-murid',
            ['Sekolah', 'Murid', 'NIS', 'Kelas', 'Tagihan', 'Jenis', 'Periode', 'Jatuh tempo', 'Nominal', 'Terbayar', 'Sisa', 'Status'],
            function ($out) use ($request, $companyNames, $months) {
                $this->billQuery($request)
                    ->select('student_bills.*')->selectRaw('coalesce(paid.total, 0) as paid_total')
                    ->with(['student' => fn($q) => $q->withTrashed()->with('classRoom:id,name'), 'billType:id,name'])
                    ->orderBy('student_bills.id')
                    ->chunk(500, function ($rows) use ($out, $companyNames, $months) {
                        foreach ($rows as $b) {
                            fputcsv($out, [
                                $companyNames[$b->company_id] ?? '-',
                                $b->student->name ?? '',
                                $b->student->nis ?? '',
                                $b->student->classRoom->name ?? '',
                                $b->title,
                                $b->billType->name ?? '',
                                $b->period_month ? ($months[$b->period_month] ?? $b->period_month) . ' ' . $b->period_year : '',
                                optional($b->due_date)->format('Y-m-d'),
                                (float) $b->amount,
                                (float) $b->paid_total,
                                max(0, (float) $b->amount - (float) $b->paid_total),
                                self::BILL_STATUS[$b->status] ?? $b->status,
                            ], ';');
                        }
                    });
            }
        );
    }

    // =========================================================
    // QUERY
    // =========================================================
    private function schoolIdsQuery()
    {
        return Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->select('id');
    }

    /** Tagihan + total bayar (subquery "paid") */
    private function billQuery(Request $request, bool $withStatus = true)
    {
        $paid = DB::table('bill_payments')->selectRaw('student_bill_id, sum(amount) as total, count(*) as cnt')->groupBy('student_bill_id');
        $like = $request->search ? '%' . addcslashes($request->search, '%_\\') . '%' : null;

        return StudentBill::query()
            ->leftJoinSub($paid, 'paid', 'paid.student_bill_id', '=', 'student_bills.id')
            ->whereIn('student_bills.company_id', $this->schoolIdsQuery())
            ->when($request->company_id, fn($q, $id) => $q->where('student_bills.company_id', $id))
            ->when($request->bill_type_id, fn($q, $id) => $q->where('student_bills.bill_type_id', $id))
            ->when($request->class_id, fn($q, $id) => $q->whereIn('student_bills.student_id', Student::withTrashed()->where('class_id', $id)->select('id')))
            ->when($request->period, function ($q, $ym) {
                [$y, $m] = array_pad(explode('-', $ym), 2, null);
                $q->where('student_bills.period_year', (int) $y)->where('student_bills.period_month', (int) $m);
            })
            ->when($like, fn($q) => $q->where(fn($w) => $w->where('student_bills.title', 'like', $like)
                ->orWhereIn('student_bills.student_id', Student::withTrashed()->where(fn($s) => $s->where('name', 'like', $like)->orWhere('nis', 'like', $like))->select('id'))))
            ->when($withStatus && $request->status === 'overdue', fn($q) => $q->where('student_bills.status', '<>', 'lunas')->whereDate('student_bills.due_date', '<', today()))
            ->when($withStatus && $request->status === 'unpaid', fn($q) => $q->where('student_bills.status', '<>', 'lunas'))
            ->when($withStatus && in_array($request->status, array_keys(self::BILL_STATUS), true), fn($q) => $q->where('student_bills.status', $request->status))
            // Status tersimpan tidak sesuai dengan total pembayaran
            ->when($withStatus && $request->status === 'mismatch', fn($q) => $q->whereRaw("student_bills.status <> (case
                    when coalesce(paid.total, 0) <= 0 then 'belum_lunas'
                    when coalesce(paid.total, 0) >= student_bills.amount then 'lunas'
                    else 'sebagian' end)"));
    }

    private function paymentQuery(Request $request)
    {
        $like = $request->search ? '%' . addcslashes($request->search, '%_\\') . '%' : null;

        return BillPayment::query()
            ->select('bill_payments.*')
            ->join('student_bills', 'student_bills.id', '=', 'bill_payments.student_bill_id')
            ->whereIn('student_bills.company_id', $this->schoolIdsQuery())
            ->when($request->company_id, fn($q, $id) => $q->where('student_bills.company_id', $id))
            ->when($request->bill_type_id, fn($q, $id) => $q->where('student_bills.bill_type_id', $id))
            ->when($request->class_id, fn($q, $id) => $q->whereIn('student_bills.student_id', Student::withTrashed()->where('class_id', $id)->select('id')))
            ->when($request->method, fn($q, $m) => $q->where('bill_payments.method', $m))
            ->when($request->date_from, fn($q, $d) => $q->whereDate('bill_payments.paid_at', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('bill_payments.paid_at', '<=', $d))
            ->when($like, fn($q) => $q->where(fn($w) => $w->where('bill_payments.receipt_number', 'like', $like)
                ->orWhereIn('student_bills.student_id', Student::withTrashed()->where(fn($s) => $s->where('name', 'like', $like)->orWhere('nis', 'like', $like))->select('id'))));
    }

    // =========================================================
    // HELPER
    // =========================================================
    private function schools()
    {
        return Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->orderBy('name')->get(['id', 'name', 'type', 'is_active']);
    }

    private function computeStatus(float $amount, float $paid): string
    {
        return $paid <= 0 ? 'belum_lunas' : ($paid >= $amount ? 'lunas' : 'sebagian');
    }

    private function monthNames(): array
    {
        return [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    }

    private function csv(string $name, array $header, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $writer) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');
            $writer($out);
            fclose($out);
        }, $name . '-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
