<?php

namespace App\Http\Controllers\Backend\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Loan;
use Illuminate\Support\Facades\Auth;

class UserLoansController extends Controller
{
    //     public function index()
    // {
    //     $loans = Loan::where('user_id', Auth::id())
    //         ->orderBy('created_at', 'desc')
    //         ->paginate(10);

    //     return view('pages.user.loans.index', compact('loans'));
    // }

    ///code index 2
      public function index(Request $request)
    {
        $query = Loan::where('user_id', $this->userId())
            ->where('company_id', $this->companyId());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $loans = $query->latest()->paginate(10);

        return view('pages.user.loans.index', compact('loans'));
    }

    public function create()
    {
        return view('pages.user.loans.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'amount'       => 'required|numeric|min:1',
            'installments' => 'required|integer|min:1'
        ]);

        Loan::create([
            'user_id'      => Auth::id(),
            'amount'       => $request->amount,
            'balance'      => $request->amount,
            'installments' => $request->installments,
            'status'       => 'pending'
        ]);

        return redirect()->route('user.loans.index')
            ->with('success', 'Pengajuan kasbon berhasil diajukan dan menunggu persetujuan.');
    }

    public function show($id)
    {
        $loan = Loan::where('user_id', Auth::id())->findOrFail($id);

        return view('pages.user.loans.show', compact('loan'));
    }


    // edit
    public function edit($id)
    {
        $loan = Loan::where('user_id', Auth::id())->findOrFail($id);

        if ($loan->status != 'pending') {
            return redirect()->route('user.loans.index')
                ->with('error', 'Hanya pengajuan kasbon dengan status pending yang dapat diedit.');
        }

        return view('pages.user.loans.edit', compact('loan'));
    }

    // update
    public function update(Request $request, $id)
    {
        $loan = Loan::where('user_id', Auth::id())->findOrFail($id);
        if ($loan->status != 'pending') {
            return redirect()->route('user.loans.index')
                ->with('error', 'Hanya pengajuan kasbon dengan status pending yang dapat diperbarui.');
        }
        $request->validate([
            'amount'       => 'required|numeric|min:1',
            'installments' => 'required|integer|min:1'
        ]);
        $loan->update([
            'amount'       => $request->amount,
            'balance'      => $request->amount,
            'installments' => $request->installments
        ]);
        return redirect()->route('user.loans.index')
            ->with('success', 'Pengajuan kasbon berhasil diperbarui.');
    }

    // destroy
    public function destroy($id)
    {
        $loan = Loan::where('user_id', Auth::id())->findOrFail($id);

        if ($loan->status != 'pending') {
            return redirect()->route('user.loans.index')
                ->with('error', 'Hanya pengajuan kasbon dengan status pending yang dapat dibatalkan.');
        }

        $loan->delete();

        return redirect()->route('user.loans.index')
            ->with('success', 'Pengajuan kasbon berhasil dibatalkan.');
    }
}
