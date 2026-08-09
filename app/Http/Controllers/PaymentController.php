<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected ReconciliationService $reconciliationService;

    public function __construct(ReconciliationService $reconciliationService)
    {
        $this->reconciliationService = $reconciliationService;
    }

    public function stkPush(Request $request, Loan $loan): RedirectResponse
    {
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
                ? 'STK Push simulated successfully. Use the simulator panel to trigger callback.'
                : 'STK Push request sent to M-Pesa. Awaiting payment callback.';

            return redirect()->back()->with('success', $msg);
        }

        return redirect()->back()->with('error', $result['message'] ?? 'Failed to initiate STK push.');
    }

    public function handleStkCallback(Request $request, string $checkout_reference): JsonResponse
    {
        Log::info("M-Pesa STK callback received for reference {$checkout_reference}: ".json_encode($request->all()));

        try {
            $this->reconciliationService->processStkCallback($checkout_reference, $request->all());

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
            ]);
        } catch (\Exception $e) {
            Log::error('Error processing STK callback: '.$e->getMessage());

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => $e->getMessage(),
            ], 500);
        }
    }
}
