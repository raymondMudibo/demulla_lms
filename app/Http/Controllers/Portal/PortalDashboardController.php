<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanProduct;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $customerId = $request->user()->customer_id;

        if (! $customerId) {
            abort(403, 'User account is not linked to any customer profile.');
        }

        $loans = Loan::where('customer_id', $customerId)
            ->with('loanProduct')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalOutstanding = Loan::where('customer_id', $customerId)
            ->whereIn('status', ['active', 'disbursed'])
            ->sum('balance');

        $products = LoanProduct::orderBy('name')->get();

        return Inertia::render('Portal/Dashboard', [
            'loans' => $loans,
            'totalOutstanding' => (float) $totalOutstanding,
            'products' => $products,
        ]);
    }
}
