<?php

namespace App\Http\Controllers;

use App\Models\Loan;
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
        Log::info("M-Pesa B2C callback received for reference {$reference}: ".json_encode($request->all()));

        try {
            $this->disbursementService->processB2cCallback($reference, $request->all());

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
            ]);
        } catch (\Exception $e) {
            Log::error('Error processing B2C callback: '.$e->getMessage());

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => $e->getMessage(),
            ], 500);
        }
    }
}
