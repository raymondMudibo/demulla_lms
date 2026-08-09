<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\MpesaCallbackLog;
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
        $payload = $request->all();
        $stkCallback = $payload['Body']['stkCallback'] ?? $payload;

        // Persist callback directly to the database for auditing
        $callbackLog = MpesaCallbackLog::create([
            'type' => 'stk_callback',
            'reference' => $checkout_reference,
            'merchant_request_id' => $stkCallback['MerchantRequestID'] ?? null,
            'checkout_request_id' => $stkCallback['CheckoutRequestID'] ?? null,
            'result_code' => isset($stkCallback['ResultCode']) ? (int) $stkCallback['ResultCode'] : null,
            'result_desc' => $stkCallback['ResultDesc'] ?? null,
            'payload' => $payload,
            'processing_status' => 'received',
            'ip_address' => $request->ip(),
        ]);

        try {
            $this->reconciliationService->processStkCallback($checkout_reference, $payload);
            $callbackLog->update(['processing_status' => 'processed']);
        } catch (\Exception $e) {
            Log::error("Failed to process STK callback for {$checkout_reference}: ".$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            $callbackLog->update([
                'processing_status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        // Always return 200 OK to Safaricom webhook gateway to acknowledge receipt
        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Success',
        ], 200);
    }

    public function callback(Request $request, string $checkout_reference): JsonResponse
    {
        return $this->handleStkCallback($request, $checkout_reference);
    }
}
