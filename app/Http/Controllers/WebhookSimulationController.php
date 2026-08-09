<?php

namespace App\Http\Controllers;

use App\Models\Disbursement;
use App\Models\StkRequest;
use App\Services\DisbursementService;
use App\Services\ReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookSimulationController extends Controller
{
    protected DisbursementService $disbursementService;

    protected ReconciliationService $reconciliationService;

    public function __construct(
        DisbursementService $disbursementService,
        ReconciliationService $reconciliationService
    ) {
        $this->disbursementService = $disbursementService;
        $this->reconciliationService = $reconciliationService;
    }

    public function simulateB2cCallback(Request $request, string $reference): RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:success,failed',
        ]);

        $disbursement = Disbursement::where('reference', $reference)->firstOrFail();
        $status = $request->input('status');

        if ($status === 'success') {
            $payload = [
                'Result' => [
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'OriginatorConversationID' => $disbursement->originator_conversation_id ?: ('SIM_ORIG_'.uniqid()),
                    'ConversationID' => $disbursement->conversation_id ?: ('SIM_CONV_'.uniqid()),
                    'TransactionID' => 'QW'.strtoupper(Str::random(8)),
                    'ResultParameters' => [
                        'ResultParameter' => [
                            [
                                'Key' => 'TransactionAmount',
                                'Value' => $disbursement->amount,
                            ],
                            [
                                'Key' => 'ReceiptNo',
                                'Value' => 'QW'.strtoupper(Str::random(8)),
                            ],
                        ],
                    ],
                ],
            ];
        } else {
            $payload = [
                'Result' => [
                    'ResultCode' => 2001,
                    'ResultDesc' => 'The initiator credential is invalid or has expired.',
                    'OriginatorConversationID' => $disbursement->originator_conversation_id ?: ('SIM_ORIG_'.uniqid()),
                    'ConversationID' => $disbursement->conversation_id ?: ('SIM_CONV_'.uniqid()),
                ],
            ];
        }

        $this->disbursementService->processB2cCallback($reference, $payload);

        $msg = $status === 'success'
            ? 'B2C Disbursement callback simulation completed successfully. Loan is now active.'
            : 'B2C Disbursement callback simulation completed with failure. Payout is marked failed.';

        return redirect()->back()->with($status === 'success' ? 'success' : 'error', $msg);
    }

    public function simulateStkCallback(Request $request, string $checkout_reference): RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:success,failed,cancelled,mismatch',
        ]);

        $stkRequest = StkRequest::where('checkout_reference', $checkout_reference)->firstOrFail();
        $status = $request->input('status');

        if ($status === 'success' || $status === 'mismatch') {
            $amountPaid = $status === 'mismatch'
                ? ((float) $stkRequest->amount_requested + 150.00)
                : (float) $stkRequest->amount_requested;

            $payload = [
                'Body' => [
                    'stkCallback' => [
                        'MerchantRequestID' => $stkRequest->merchant_request_id ?: ('SIM_MERCH_'.uniqid()),
                        'CheckoutRequestID' => $stkRequest->checkout_request_id ?: ('SIM_CO_'.uniqid()),
                        'ResultCode' => 0,
                        'ResultDesc' => 'The service request is processed successfully.',
                        'CallbackMetadata' => [
                            'Item' => [
                                [
                                    'Name' => 'Amount',
                                    'Value' => $amountPaid,
                                ],
                                [
                                    'Name' => 'MpesaReceiptNumber',
                                    'Value' => 'NL'.strtoupper(Str::random(8)),
                                ],
                                [
                                    'Name' => 'PhoneNumber',
                                    'Value' => '254712345678',
                                ],
                                [
                                    'Name' => 'TransactionDate',
                                    'Value' => now()->format('YmdHis'),
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        } else {
            $payload = [
                'Body' => [
                    'stkCallback' => [
                        'MerchantRequestID' => $stkRequest->merchant_request_id ?: ('SIM_MERCH_'.uniqid()),
                        'CheckoutRequestID' => $stkRequest->checkout_request_id ?: ('SIM_CO_'.uniqid()),
                        'ResultCode' => $status === 'cancelled' ? 1032 : 1003,
                        'ResultDesc' => $status === 'cancelled' ? 'Request cancelled by user.' : 'Insufficient balance.',
                    ],
                ],
            ];
        }

        $this->reconciliationService->processStkCallback($checkout_reference, $payload);

        $msg = 'STK Push callback simulation completed. Repayment processed.';

        return redirect()->back()->with('success', $msg);
    }
}
