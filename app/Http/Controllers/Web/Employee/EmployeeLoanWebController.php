<?php

namespace App\Http\Controllers\Web\Employee;

use App\Http\Controllers\Controller;
use App\Services\EmployeeApiService;
use Illuminate\Http\Request;

class EmployeeLoanWebController extends Controller
{
protected EmployeeApiService $api;
    public function __construct() { $this->api = new EmployeeApiService(); }
 
    public function index(Request $request)
    {
        $res  = $this->api->get('/company/employee/loans', $request->only('status'));
        $data = $res->successful() ? $res->json('data', []) : [];
        return view('pages.employee.loan.index', compact('data'));
    }
 
    public function active()
    {
        $res  = $this->api->get('/company/employee/loans/active');
        $loan = $res->successful() ? $res->json('data') : null;
        return view('pages.employee.loan.active', compact('loan'));
    }
 
    public function create()
    {
        // Cek apakah ada pinjaman aktif
        $res        = $this->api->get('/company/employee/loans/active');
        $activeLoan = $res->successful() ? $res->json('data') : null;
        return view('pages.employee.loan.create', compact('activeLoan'));
    }
 
    public function store(Request $request)
    {
        $request->validate([
            'amount'                => 'required|numeric|min:10000',
            'installments'          => 'required|integer|min:1|max:60',
            'purpose_category'      => 'required|in:education,health,emergency,renovation,business,other',
            'purpose_note'          => 'nullable|string|max:500',
            'payment_type'          => 'required|in:salary_deduction,scheduled_date,lump_sum',
            'payment_date_of_month' => 'nullable|integer|min:1|max:28',
        ]);
 
        $res = $this->api->post('/company/employee/loans', $request->only(
            'amount', 'installments', 'purpose_category', 'purpose_note',
            'payment_type', 'payment_date_of_month'
        ));
 
        if ($res->successful()) {
            EmployeeApiService::flashSuccess('Pengajuan pinjaman berhasil dikirim.');
            return redirect()->route('pages.employee.loan.index');
        }
 
        EmployeeApiService::flashError($res);
        return back()->withInput();
    }
 
    public function show(int $id)
    {
        $res  = $this->api->get("/company/employee/loans/{$id}");
        $loan = $res->successful() ? $res->json('data') : null;
        abort_if(!$loan, 404);
        return view('pages.employee.loan.show', compact('loan'));
    }
 
    public function cancel(Request $request, int $id)
    {
        $res = $this->api->put("/company/employee/loans/{$id}/cancel", [
            'reason' => $request->get('reason'),
        ]);
 
        $res->successful()
            ? EmployeeApiService::flashSuccess('Pengajuan pinjaman berhasil dibatalkan.')
            : EmployeeApiService::flashError($res);
 
        return redirect()->route('pages.pages.employee.loan.index');
    }
 
    public function paymentHistory(int $id)
    {
        $res  = $this->api->get("/company/employee/loans/{$id}/payments");
        $data = $res->successful() ? $res->json('data') : null;
        abort_if(!$data, 404);
        return view('pages.employee.loan.payments', compact('data', 'id'));
    }
}
