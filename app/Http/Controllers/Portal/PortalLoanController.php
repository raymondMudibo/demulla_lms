<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\LoanService;
use App\Services\ReconciliationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalLoanController extends Controller
{
    use AuthorizesRequests;

    protected LoanService $loanService;

    protected ReconciliationService $reconciliationService;

    public function __construct(
        LoanService $loanService,
        ReconciliationService $reconciliationService
    ) {
        $this->loanService = $loanService;
        $this->reconciliationService = $reconciliationService;
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Loan::class);

        $validated = $request->validate([
            'loan_product_id' => 'required|exists:loan_products,id',
            'principal_amount' => 'required|numeric|min:1',
        ]);

        $validated['customer_id'] = $request->user()->customer_id;

        $loan = $this->loanService->createLoan($validated);

        return redirect()->route('portal.dashboard')->with('success', 'Loan application submitted successfully.');
    }

    public function show(Loan $loan): Response
    {
        $this->authorize('view', $loan);

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

        return Inertia::render('Portal/Loans/Show', [
            'loan' => $loan,
        ]);
    }

    public function repay(Request $request, Loan $loan): RedirectResponse
    {
        $this->authorize('repay', $loan);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'phone_number' => ['required', 'string', 'regex:/^(254|0|\+254)?(7|1)\d{8}$/'],
        ]);

        // Normalize phone number to 254...
        $phone = preg_replace('/\D/', '', $validated['phone_number']);
        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        } elseif (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }
        if (! str_starts_with($phone, '254') && strlen($phone) === 9) {
            $phone = '254'.$phone;
        }

        $result = $this->reconciliationService->initiateStkPush($loan, (float) $validated['amount'], $phone);

        if ($result['success']) {
            $msg = $result['is_simulation']
                ? 'STK Push request simulated. Use the Webhook Simulator on this page to trigger success/cancel callbacks.'
                : 'M-Pesa STK Push sent successfully. Please enter your PIN on your mobile screen to complete payment.';

            return redirect()->back()->with('success', $msg);
        }

        return redirect()->back()->with('error', $result['message'] ?? 'Failed to initiate M-Pesa STK Push payment.');
    }
}
