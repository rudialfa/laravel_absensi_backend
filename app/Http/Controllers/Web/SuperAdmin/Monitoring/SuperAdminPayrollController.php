<?php

namespace App\Http\Controllers\Web\SuperAdmin\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Payrool;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monitoring Payroll (tabel payrools) & Pinjaman/Kasbon (loans + loan_payments) semua perusahaan.
 * Data keuangan karyawan dikelola HR. Superadmin hanya memantau, export, dan menghapus draft/pengajuan yang salah.
 */
class SuperAdminPayrollController extends Controller
{
    public const TABS = ['payrolls' => 'Payroll', 'loans' => 'Pinjaman / Kasbon'];

    public const PAYROLL_STATUS = ['draft' => 'Draft', 'approved' => 'Disetujui', 'paid' => 'Dibayar'];
    public const PAYROLL_BADGE  = ['draft' => 'secondary', 'approved' => 'info', 'paid' => 'success'];

    public const LOAN_STATUS = [
        'pending'  => 'Menunggu',
        'active'   => 'Berjalan',
        'approved' => 'Disetujui',
        'paid'     => 'Lunas',
        'rejected' => 'Ditolak',
        'canceled' => 'Dibatalkan',
    ];
    public const LOAN_BADGE = [
        'pending'  => 'warning',
        'active'   => 'primary',
        'approved' => 'primary',
        'paid'     => 'success',
        'rejected' => 'danger',
        'canceled' => 'secondary',
    ];

    public const LOAN_PURPOSE = [
        'education'  => 'Pendidikan',
        'health'     => 'Kesehatan',
        'emergency'  => 'Darurat',
        'renovation' => 'Renovasi',
        'business'   => 'Usaha',
        'other'      => 'Lainnya',
    ];

    public const PAYMENT_TYPE = [
        'salary_deduction' => 'Potong gaji',
        'scheduled_date'   => 'Bayar tiap tanggal',
        'lump_sum'         => 'Sekali lunas',
    ];

    // =========================================================
    // INDEX (?tab=payrolls|loans)
    // =========================================================
    public function index(Request $request)
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'payrolls';

        $common = [
            'tab'          => $tab,
            'tabs'         => self::TABS,
            'companies'    => Company::where('type', 'company')->orderBy('name')->get(['id', 'name']),
            'companyNames' => Company::pluck('name', 'id'),
        ];

        if ($tab === 'loans') {
            $query = $this->loanQuery($request);

            return view('pages.superadmin.monitoring.payrolls.index', $common + [
                'items'   => (clone $query)->with('user:id,name,department')->withCount('payments')
                    ->orderByRaw("status = 'pending' DESC")->latest()->paginate(20)->withQueryString(),
                'summary' => (clone $query)->selectRaw("
                        count(*) as total,
                        sum(status = 'pending') as pending,
                        sum(status in ('active','approved')) as running,
                        coalesce(sum(case when status in ('active','approved') then balance end), 0) as outstanding,
                        coalesce(sum(case when status in ('active','approved','paid') then amount end), 0) as disbursed
                    ")->first(),
                'statuses' => self::LOAN_STATUS,
                'badge'    => self::LOAN_BADGE,
                'purposes' => self::LOAN_PURPOSE,
            ]);
        }

        $query = $this->payrollQuery($request);

        return view('pages.superadmin.monitoring.payrolls.index', $common + [
            'items'   => (clone $query)->with('user:id,name,department')->withCount('components')
                ->orderByDesc('period_start')->orderBy('company_id')->paginate(20)->withQueryString(),
            'summary' => (clone $query)->selectRaw("
                    count(*) as total,
                    sum(status = 'draft') as draft,
                    sum(status = 'approved') as approved,
                    sum(status = 'paid') as paid,
                    coalesce(sum(net_pay), 0) as net_total,
                    coalesce(sum(case when status = 'paid' then net_pay end), 0) as net_paid
                ")->first(),
            'statuses' => self::PAYROLL_STATUS,
            'badge'    => self::PAYROLL_BADGE,
            'months'   => $this->monthOptions(),
        ]);
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show(string $tab, $id)
    {
        if ($tab === 'loans') {
            $loan = Loan::with([
                'user:id,name,email,department,position,salary',
                'approvedBy:id,name',
                'payments' => fn($q) => $q->with('recordedBy:id,name')->orderBy('payment_date'),
            ])->findOrFail($id);

            return view('pages.superadmin.monitoring.payrolls.show-loan', [
                'loan'        => $loan,
                'company'     => Company::find($loan->company_id),
                'statuses'    => self::LOAN_STATUS,
                'badge'       => self::LOAN_BADGE,
                'purposes'    => self::LOAN_PURPOSE,
                'paymentType' => self::PAYMENT_TYPE,
                'canDelete'   => $this->loanCanDelete($loan),
            ]);
        }

        abort_unless($tab === 'payrolls', 404);

        $payroll = Payrool::with(['user:id,name,email,department,position,salary', 'components'])->findOrFail($id);

        // Cicilan pinjaman yang dipotong lewat payroll ini
        $loanPayments = LoanPayment::with('loan:id,amount,purpose_category')->where('payroll_id', $payroll->id)->get();

        return view('pages.superadmin.monitoring.payrolls.show-payroll', [
            'payroll'      => $payroll,
            'company'      => Company::find($payroll->company_id),
            'loanPayments' => $loanPayments,
            'statuses'     => self::PAYROLL_STATUS,
            'badge'        => self::PAYROLL_BADGE,
            'history'      => Payrool::where('user_id', $payroll->user_id)->where('id', '!=', $payroll->id)
                ->orderByDesc('period_start')->limit(6)->get(['id', 'period_start', 'period_end', 'net_pay', 'status']),
        ]);
    }

    // =========================================================
    // DESTROY — hanya payroll draft & pinjaman yang belum berjalan
    // =========================================================
    public function destroy(Request $request, string $tab, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        if ($tab === 'loans') {
            $loan = Loan::with('user:id,name')->findOrFail($id);

            if (!$this->loanCanDelete($loan)) {
                return back()->with('error', 'Pinjaman yang sudah berjalan, lunas, atau punya riwayat cicilan tidak boleh dihapus.');
            }

            $snapshot = $loan->getAttributes();
            $loan->delete();

            AuditLog::record('delete_loan', null, "Pinjaman {$loan->user?->name} (Rp " . number_format((float) $loan->amount, 0, ',', '.') . ") dihapus. Alasan: {$data['reason']}", $snapshot + ['reason' => $data['reason']]);

            return redirect()->route('superadmin.monitoring.payrolls.index', ['tab' => 'loans'])->with('success', 'Pinjaman dihapus.');
        }

        abort_unless($tab === 'payrolls', 404);

        $payroll = Payrool::with(['user:id,name', 'components'])->findOrFail($id);

        if ($payroll->status !== 'draft') {
            return back()->with('error', 'Hanya payroll berstatus Draft yang boleh dihapus. Payroll yang sudah disetujui/dibayar adalah catatan keuangan.');
        }

        if (LoanPayment::where('payroll_id', $payroll->id)->exists()) {
            return back()->with('error', 'Payroll ini sudah dipakai untuk mencatat cicilan pinjaman.');
        }

        $snapshot = $payroll->getAttributes() + ['components' => $payroll->components->map->getAttributes()->all()];

        DB::transaction(fn() => $payroll->delete()); // komponen ikut terhapus (cascade)

        AuditLog::record('delete_payroll', null, "Payroll draft {$payroll->user?->name} periode {$payroll->period_formatted} dihapus. Alasan: {$data['reason']}", $snapshot + ['reason' => $data['reason']]);

        return redirect()->route('superadmin.monitoring.payrolls.index', ['tab' => 'payrolls'])->with('success', 'Payroll draft dihapus.');
    }

    // =========================================================
    // EXPORT CSV
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        $tab = $request->tab === 'loans' ? 'loans' : 'payrolls';
        $companyNames = Company::pluck('name', 'id');

        AuditLog::record('export_' . $tab, null, 'Export ' . self::TABS[$tab] . ' ke CSV', ['filters' => $request->query()]);

        return response()->streamDownload(function () use ($tab, $request, $companyNames) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            if ($tab === 'loans') {
                fputcsv($out, ['Perusahaan', 'Karyawan', 'Tujuan', 'Jumlah', 'Sisa', 'Cicilan/bulan', 'Tenor', 'Cara bayar', 'Status', 'Disetujui', 'Diajukan'], ';');
                $this->loanQuery($request)->with(['user:id,name', 'approvedBy:id,name'])->orderBy('company_id')->chunk(500, function ($rows) use ($out, $companyNames) {
                    foreach ($rows as $l) {
                        fputcsv($out, [
                            $companyNames[$l->company_id] ?? '-',
                            $l->user->name ?? '-',
                            self::LOAN_PURPOSE[$l->purpose_category] ?? $l->purpose_category,
                            (float) $l->amount,
                            (float) $l->balance,
                            (float) $l->monthly_installment,
                            (int) $l->installments,
                            self::PAYMENT_TYPE[$l->payment_type] ?? $l->payment_type,
                            self::LOAN_STATUS[$l->status] ?? $l->status,
                            $l->approvedBy->name ?? '',
                            optional($l->created_at)->format('Y-m-d'),
                        ], ';');
                    }
                });
            } else {
                fputcsv($out, ['Perusahaan', 'Karyawan', 'Departemen', 'Periode mulai', 'Periode selesai', 'Gaji pokok', 'Tunjangan', 'Lembur', 'Bonus', 'Potongan', 'Gaji bersih', 'Status'], ';');
                $this->payrollQuery($request)->with('user:id,name,department')->orderBy('company_id')->orderBy('period_start')->chunk(500, function ($rows) use ($out, $companyNames) {
                    foreach ($rows as $p) {
                        fputcsv($out, [
                            $companyNames[$p->company_id] ?? '-',
                            $p->user->name ?? '-',
                            $p->user->department ?? '',
                            $p->period_start,
                            $p->period_end,
                            (float) $p->base_salary,
                            (float) $p->allowance,
                            (float) $p->overtime_pay,
                            (float) $p->bonus,
                            (float) $p->deductions,
                            (float) $p->net_pay,
                            self::PAYROLL_STATUS[$p->status] ?? $p->status,
                        ], ';');
                    }
                });
            }

            fclose($out);
        }, $tab . '-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // HELPER
    // =========================================================
    private function payrollQuery(Request $request)
    {
        return Payrool::query()
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->month, function ($q, $ym) {
                // Periode yang beririsan dengan bulan terpilih (format Y-m)
                $start = Carbon::createFromFormat('Y-m', $ym)->startOfMonth()->toDateString();
                $end   = Carbon::createFromFormat('Y-m', $ym)->endOfMonth()->toDateString();
                $q->whereDate('period_start', '<=', $end)->whereDate('period_end', '>=', $start);
            })
            ->when($request->search, fn($q, $s) => $q->whereHas('user', fn($u) => $u->where('name', 'like', '%' . addcslashes($s, '%_\\') . '%')));
    }

    private function loanQuery(Request $request)
    {
        return Loan::query()
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->status, fn($q, $s) => $s === 'running' ? $q->whereIn('status', ['active', 'approved']) : $q->where('status', $s))
            ->when($request->purpose, fn($q, $p) => $q->where('purpose_category', $p))
            ->when($request->search, fn($q, $s) => $q->whereHas('user', fn($u) => $u->where('name', 'like', '%' . addcslashes($s, '%_\\') . '%')));
    }

    private function loanCanDelete(Loan $loan): bool
    {
        return in_array($loan->status, ['pending', 'rejected', 'canceled'], true) && !$loan->payments()->exists();
    }

    /** 12 bulan terakhir untuk filter */
    private function monthOptions(): array
    {
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $m = now()->startOfMonth()->subMonthsNoOverflow($i);
            $months[$m->format('Y-m')] = $m->translatedFormat('F Y');
        }

        return $months;
    }
}
