<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\MpesaCallbackLog;
use App\Services\DisbursementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DisbursementController extends Controller
{
    protected DisbursementService $disbursementService;

    public function __construct(DisbursementService $disbursementService)
    {
        $this->disbursementService = $disbursementService;
    }

    public function disburse(Loan $loan): RedirectResponse
    {
        $result = $this->disbursementService->initiateDisbursement($loan);

        if ($result['success']) {
            $msg = $result['is_simulation']
                ? 'B2C Payout initiated (SIMULATION MODE). Use the simulator below to complete callback.'
                : 'B2C Payout request sent to M-Pesa. Awaiting callback confirmation.';

            return redirect()->back()->with('success', $msg);
        }

        return redirect()->back()->with('error', $result['message'] ?? 'Failed to initiate disbursement.');
    }

    public function handleB2cCallback(Request $request, string $reference): JsonResponse
    {
        $payload = $request->all();
        $result = $payload['Result'] ?? $payload;

        // Extract transaction ID if present
        $transactionId = $result['TransactionID'] ?? null;
        if (! $transactionId && isset($result['ResultParameters']['ResultParameter'])) {
            foreach ($result['ResultParameters']['ResultParameter'] as $param) {
                if (($param['Key'] ?? '') === 'ReceiptNo' || ($param['Name'] ?? '') === 'TransactionID') {
                    $transactionId = $param['Value'] ?? $transactionId;
                }
            }
        }

        // Persist callback directly to the database
        $callbackLog = MpesaCallbackLog::create([
            'type' => 'b2c_callback',
            'reference' => $reference,
            'conversation_id' => $result['ConversationID'] ?? null,
            'originator_conversation_id' => $result['OriginatorConversationID'] ?? null,
            'transaction_id' => $transactionId,
            'result_code' => isset($result['ResultCode']) ? (int) $result['ResultCode'] : null,
            'result_desc' => $result['ResultDesc'] ?? null,
            'payload' => $payload,
            'processing_status' => 'received',
            'ip_address' => $request->ip(),
        ]);

        try {
            $this->disbursementService->processB2cCallback($reference, $payload);
            $callbackLog->update(['processing_status' => 'processed']);

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
            ]);
        } catch (\Exception $e) {
            $callbackLog->update([
                'processing_status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => $e->getMessage(),
            ], 500);
        }
    }
}
