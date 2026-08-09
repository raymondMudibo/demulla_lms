<?php

namespace App\Services;

use App\Models\Disbursement;
use App\Models\Loan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DisbursementService
{
    protected DarajaService $darajaService;

    protected LoanService $loanService;

    public function __construct(DarajaService $darajaService, LoanService $loanService)
    {
        $this->darajaService = $darajaService;
        $this->loanService = $loanService;
    }

    public function initiateDisbursement(Loan $loan): array
    {
        return DB::transaction(function () use ($loan) {
            $lockedLoan = Loan::where('id', $loan->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedLoan->canBeDisbursed()) {
                return ['success' => false, 'message' => 'Loan is not in a status that can be disbursed.'];
            }

            // Check if there is already an active initiated disbursement awaiting callback
            if ($lockedLoan->disbursements()->where('status', 'initiated')->exists()) {
                return ['success' => false, 'message' => 'A disbursement request is already in progress for this loan.'];
            }

            $reference = (string) Str::uuid();
            $phoneNumber = $lockedLoan->customer->phone_number;
            $amount = (float) $lockedLoan->principal_amount;

            $disbursement = Disbursement::create([
                'loan_id' => $lockedLoan->id,
                'reference' => $reference,
                'amount' => $amount,
                'phone_number' => $phoneNumber,
                'status' => 'initiated',
            ]);

            // Call Safaricom B2C API
            $response = $this->darajaService->initiateB2cDisbursement($reference, $amount, $phoneNumber);

            if ($response['success']) {
                $disbursement->update([
                    'conversation_id' => $response['conversation_id'] ?? null,
                    'originator_conversation_id' => $response['originator_conversation_id'] ?? null,
                    'raw_callback_payload' => $response,
                ]);

                // Maintain loan in 'approved' state until webhook callback confirms success
                $isSim = $response['is_simulation'] ?? false;
                $msg = $isSim
                    ? 'B2C Payout request initiated (Sandbox mode). Awaiting M-Pesa result callback to activate loan.'
                    : 'B2C Payout request dispatched to M-Pesa. Awaiting webhook result callback confirmation.';

                return [
                    'success' => true,
                    'disbursement' => $disbursement,
                    'is_simulation' => $isSim,
                    'message' => $msg,
                ];
            } else {
                $disbursement->update([
                    'status' => 'failed',
                    'failure_reason' => $response['message'] ?? 'Initial B2C gateway call failed',
                    'raw_callback_payload' => $response,
                ]);

                return [
                    'success' => false,
                    'message' => $response['message'] ?? 'Failed to initiate B2C payout',
                ];
            }
        });
    }

    public function processB2cCallback(string $reference, array $payload): void
    {
        Cache::lock("b2c_lock_{$reference}", 10)->block(5, function () use ($reference, $payload) {
            DB::transaction(function () use ($reference, $payload) {
                $disbursement = Disbursement::where('reference', $reference)
                    ->lockForUpdate()
                    ->first();

                if (! $disbursement) {
                    Log::warning("B2C disbursement record not found for reference: {$reference}");

                    return;
                }

                // Check idempotency (prevent double disbursement processing)
                if ($disbursement->status !== 'initiated') {
                    Log::warning("B2C callback already processed for reference: {$reference}, status: {$disbursement->status}");

                    return;
                }

                $loan = Loan::where('id', $disbursement->loan_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Parse result from Safaricom B2C payload
                $result = $payload['Result'] ?? $payload;
                $resultCode = isset($result['ResultCode']) ? (int) $result['ResultCode'] : 1;
                $resultDesc = $result['ResultDesc'] ?? 'Unknown error';
                $conversationId = $result['ConversationID'] ?? null;
                $originatorConversationId = $result['OriginatorConversationID'] ?? null;

                // Extract transaction ID if present
                $transactionId = $result['TransactionID'] ?? null;
                if (! $transactionId && isset($result['ResultParameters']['ResultParameter'])) {
                    foreach ($result['ResultParameters']['ResultParameter'] as $param) {
                        if (($param['Key'] ?? '') === 'ReceiptNo' || ($param['Name'] ?? '') === 'TransactionID') {
                            $transactionId = $param['Value'] ?? $transactionId;
                        }
                    }
                }

                if ($resultCode === 0) {
                    // Successful B2C payout
                    $receiptNumber = $transactionId ?: ('B2C_'.strtoupper(Str::random(8)));

                    $disbursement->update([
                        'status' => 'successful',
                        'mpesa_receipt_number' => $receiptNumber,
                        'raw_callback_payload' => $payload,
                        'disbursed_at' => now(),
                        'conversation_id' => $conversationId ?: $disbursement->conversation_id,
                        'originator_conversation_id' => $originatorConversationId ?: $disbursement->originator_conversation_id,
                    ]);

                    // Transition loan to active and generate live installment schedule
                    if ($loan->status !== 'active') {
                        $loan->update([
                            'status' => 'active',
                            'disbursed_at' => now(),
                        ]);

                        $this->loanService->generateInstallmentSchedule($loan, Carbon::today());
                    }
                } else {
                    // Failed B2C payout: record failure and leave loan in 'approved' status for admin retry
                    $disbursement->update([
                        'status' => 'failed',
                        'failure_reason' => $resultDesc,
                        'raw_callback_payload' => $payload,
                        'conversation_id' => $conversationId ?: $disbursement->conversation_id,
                        'originator_conversation_id' => $originatorConversationId ?: $disbursement->originator_conversation_id,
                    ]);

                    Log::warning("B2C Disbursement failed for Reference {$reference}: {$resultDesc}");
                }
            });
        });
    }
}
