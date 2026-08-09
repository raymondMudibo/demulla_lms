<?php

namespace App\Services;

use App\Models\Disbursement;
use App\Models\Loan;
use Illuminate\Support\Carbon;
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
            if (! $loan->canBeDisbursed()) {
                return ['success' => false, 'message' => 'Loan is not in a status that can be disbursed.'];
            }

            $reference = (string) Str::uuid();
            $phoneNumber = $loan->customer->phone_number;
            $amount = (float) $loan->principal_amount;

            $disbursement = Disbursement::create([
                'loan_id' => $loan->id,
                'reference' => $reference,
                'amount' => $amount,
                'phone_number' => $phoneNumber,
                'status' => 'initiated',
            ]);

            // Call Safaricom B2C API
            $response = $this->darajaService->initiateB2cDisbursement($reference, $amount, $phoneNumber);

            if ($response['success']) {
                $receiptNumber = $response['transaction_id'] ?? ('B2C_'.strtoupper(Str::random(8)));

                // Primary transition: directly disburse and activate loan
                $disbursement->update([
                    'status' => 'successful',
                    'mpesa_receipt_number' => $receiptNumber,
                    'conversation_id' => $response['conversation_id'] ?? null,
                    'originator_conversation_id' => $response['originator_conversation_id'] ?? null,
                    'raw_callback_payload' => $response,
                    'disbursed_at' => now(),
                ]);

                $loan->update([
                    'status' => 'active',
                    'disbursed_at' => now(),
                ]);

                // Make the installment schedule live immediately
                $this->loanService->generateInstallmentSchedule($loan, Carbon::today());

                $isSim = $response['is_simulation'] ?? false;
                $msg = $isSim
                    ? 'Loan payout disbursed successfully (Sandbox mode). Loan is now active with live repayment schedule.'
                    : 'Loan payout disbursed successfully via M-Pesa B2C. Loan is now active with live repayment schedule.';

                return [
                    'success' => true,
                    'disbursement' => $disbursement,
                    'is_simulation' => $isSim,
                    'message' => $msg,
                ];
            } else {
                $disbursement->update([
                    'status' => 'failed',
                    'failure_reason' => $response['message'] ?? 'Initial B2C call failed',
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
        DB::transaction(function () use ($reference, $payload) {
            $disbursement = Disbursement::where('reference', $reference)
                ->lockForUpdate()
                ->firstOrFail();

            // Parse result from Safaricom B2C payload
            $result = $payload['Result'] ?? $payload;
            $resultCode = $result['ResultCode'] ?? 1;
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

            // If loan was already disbursed/active primarily, update metadata as fallback
            if ($disbursement->status === 'successful') {
                $disbursement->update([
                    'raw_callback_payload' => $payload,
                    'mpesa_receipt_number' => $transactionId ?: $disbursement->mpesa_receipt_number,
                    'conversation_id' => $conversationId ?: $disbursement->conversation_id,
                    'originator_conversation_id' => $originatorConversationId ?: $disbursement->originator_conversation_id,
                ]);

                return;
            }

            // Fallback activation if previously still in 'initiated' state
            if ($resultCode === 0) {
                $disbursement->update([
                    'status' => 'successful',
                    'mpesa_receipt_number' => $transactionId ?: ('B2C_'.strtoupper(Str::random(8))),
                    'raw_callback_payload' => $payload,
                    'disbursed_at' => now(),
                    'conversation_id' => $conversationId ?: $disbursement->conversation_id,
                    'originator_conversation_id' => $originatorConversationId ?: $disbursement->originator_conversation_id,
                ]);

                $loan = $disbursement->loan;
                if ($loan->status !== 'active') {
                    $loan->update([
                        'status' => 'active',
                        'disbursed_at' => now(),
                    ]);

                    $this->loanService->generateInstallmentSchedule($loan, Carbon::today());
                }
            } else {
                $disbursement->update([
                    'status' => 'failed',
                    'failure_reason' => $resultDesc,
                    'raw_callback_payload' => $payload,
                    'conversation_id' => $conversationId ?: $disbursement->conversation_id,
                    'originator_conversation_id' => $originatorConversationId ?: $disbursement->originator_conversation_id,
                ]);
            }
        });
    }
}
