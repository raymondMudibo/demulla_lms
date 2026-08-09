<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Http\RedirectResponse;
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

        return Inertia::render('Admin/Loans/Index', [
            'loans' => $loans,
        ]);
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

        return Inertia::render('Admin/Loans/Show', [
            'loan' => $loan,
        ]);
    }

    public function approve(Loan $loan): RedirectResponse
    {
        if ($loan->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending loans can be approved.');
        }

        $this->loanService->approveLoan($loan);

        return redirect()->route('admin.loans.show', $loan->id)->with('success', 'Loan approved successfully.');
    }

    public function reject(Loan $loan): RedirectResponse
    {
        if ($loan->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending loans can be rejected.');
        }

        $loan->update(['status' => 'rejected']);

        return redirect()->route('admin.loans.show', $loan->id)->with('success', 'Loan application rejected.');
    }
}
