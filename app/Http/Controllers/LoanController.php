<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    protected LoanService $loanService;

    public function __construct(LoanService $loanService)
    {
        $this->loanService = $loanService;
    }

    public function index(): Response
    {
        $loans = Loan::with(['customer', 'loanProduct'])
            ->orderBy('created_at', 'desc')
            ->get();

        $customers = Customer::orderBy('name')->get();
        $products = LoanProduct::orderBy('name')->get();

        return Inertia::render('Loans/Index', [
            'loans' => $loans,
            'customers' => $customers,
            'products' => $products,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'loan_product_id' => 'required|exists:loan_products,id',
            'principal_amount' => 'required|numeric|min:1',
        ]);

        $this->loanService->createLoan($validated);

        return redirect()->route('loans.index')->with('success', 'Loan application submitted.');
    }

    public function show(Loan $loan): Response
    {
        $loan->load([
            'customer',
            'loanProduct',
            'installments' => function ($q) {
                $q->orderBy('installment_number');
            },
            'disbursements' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
            'stkRequests' => function ($q) {
                $q->with('payment')->orderBy('created_at', 'desc');
            },
            'payments' => function ($q) {
                $q->with('installments')->orderBy('created_at', 'desc');
            },
        ]);

        return Inertia::render('Loans/Show', [
            'loan' => $loan,
        ]);
    }

    public function approve(Loan $loan): RedirectResponse
    {
        if ($loan->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending loans can be approved.');
        }

        $this->loanService->approveLoan($loan);

        return redirect()->route('loans.show', $loan->id)->with('success', 'Loan approved successfully.');
    }
}
