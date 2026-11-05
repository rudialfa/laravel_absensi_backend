<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SubscriptionInvoice;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VaPayment;
use Illuminate\Http\Request;

class SuperAdminGlobalSearchController extends Controller
{
    /** Maksimal hasil per kategori */
    private const LIMIT = 15;

    /** Minimal panjang kata kunci */
    private const MIN_LENGTH = 2;

    /**
     * Pencarian cepat lintas semua data tenant:
     * tenant, pengguna, invoice, VA, dan tiket bantuan.
     */
    public function index(Request $request)
    {
        $keyword = trim((string) $request->input('q'));
        $only    = $request->input('only'); // filter kategori (opsional)

        $results = [
            'companies' => collect(),
            'users'     => collect(),
            'invoices'  => collect(),
            'vas'       => collect(),
            'tickets'   => collect(),
        ];

        $tooShort = $keyword !== '' && mb_strlen($keyword) < self::MIN_LENGTH;

        if ($keyword !== '' && !$tooShort) {
            // Escape karakter wildcard LIKE supaya "%" atau "_" dicari apa adanya
            $like = '%' . addcslashes($keyword, '%_\\') . '%';

            $want = fn(string $key) => !$only || $only === $key;

            if ($want('companies')) {
                $results['companies'] = Company::withCount('users')
                    ->where(function ($q) use ($like, $keyword) {
                        $q->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('city', 'like', $like)
                            ->orWhere('address', 'like', $like);

                        if (ctype_digit($keyword)) {
                            $q->orWhere('id', (int) $keyword);
                        }
                    })
                    ->orderBy('name')
                    ->limit(self::LIMIT)
                    ->get();
            }

            if ($want('users')) {
                $results['users'] = User::with('company:id,name')
                    ->where(function ($q) use ($like) {
                        $q->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    })
                    ->orderBy('name')
                    ->limit(self::LIMIT)
                    ->get();
            }

            if ($want('invoices')) {
                // FIX: orWhereHas dibungkus where() supaya kondisi OR tidak "bocor"
                $results['invoices'] = SubscriptionInvoice::with(['company:id,name', 'plan:id,name'])
                    ->where(function ($q) use ($like) {
                        $q->where('invoice_number', 'like', $like)
                            ->orWhereHas('company', fn($c) => $c->where('name', 'like', $like));
                    })
                    ->latest('issued_at')
                    ->limit(self::LIMIT)
                    ->get();
            }

            if ($want('vas')) {
                $results['vas'] = VaPayment::with(['company:id,name', 'invoice:id,invoice_number'])
                    ->where(function ($q) use ($like) {
                        $q->where('va_number', 'like', $like)
                            ->orWhere('va_name', 'like', $like)
                            ->orWhere('bank_reference_no', 'like', $like);
                    })
                    ->latest()
                    ->limit(self::LIMIT)
                    ->get();
            }

            if ($want('tickets')) {
                $results['tickets'] = SupportTicket::with(['company:id,name', 'user:id,name'])
                    ->where(function ($q) use ($like, $keyword) {
                        $q->where('subject', 'like', $like)
                            ->orWhere('message', 'like', $like)
                            ->orWhereHas('company', fn($c) => $c->where('name', 'like', $like))
                            ->orWhereHas('user', fn($u) => $u->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like));

                        // "#12" atau "12" = cari tiket nomor 12
                        $number = ltrim($keyword, '#');
                        if (ctype_digit($number)) {
                            $q->orWhere('id', (int) $number);
                        }
                    })
                    ->latest()
                    ->limit(self::LIMIT)
                    ->get();
            }
        }

        $totalFound = collect($results)->sum(fn($items) => $items->count());

        return view('pages.superadmin.global-search.index', [
            'keyword'    => $keyword,
            'only'       => $only,
            'tooShort'   => $tooShort,
            'minLength'  => self::MIN_LENGTH,
            'limit'      => self::LIMIT,
            'totalFound' => $totalFound,
            'companies'  => $results['companies'],
            'users'      => $results['users'],
            'invoices'   => $results['invoices'],
            'vas'        => $results['vas'],
            'tickets'    => $results['tickets'],
        ]);
    }
}
