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
                $disbursement->update([
                    'conversation_id' => $response['conversation_id'] ?? null,
                    'originator_conversation_id' => $response['originator_conversation_id'] ?? null,
                ]);

                // If running in simulation mode, we can auto-process callback in local sandbox for quick feedback if needed
                // But we still allow explicit manual webhook triggering via simulator
                return [
                    'success' => true,
                    'disbursement' => $disbursement,
                    'is_simulation' => $response['is_simulation'] ?? false,
                    'message' => 'Disbursement payout request initiated successfully.',
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

            if ($disbursement->status !== 'initiated') {
                Log::warning("Disbursement callback already processed for reference: {$reference}");

                return;
            }

            // Parse result from Safaricom B2C payload
            $result = $payload['Result'] ?? $payload;
            $resultCode = $result['ResultCode'] ?? 1;
            $resultDesc = $result['ResultDesc'] ?? 'Unknown error';
            $conversationId = $result['ConversationID'] ?? null;
            $originatorConversationId = $result['OriginatorConversationID'] ?? null;

            if ($resultCode === 0) {
                // Successful disbursement
                $transactionId = $result['TransactionID'] ?? null;

                // Fallback to searching ResultParameters for Transaction ID
                if (! $transactionId && isset($result['ResultParameters']['ResultParameter'])) {
                    foreach ($result['ResultParameters']['ResultParameter'] as $param) {
                        if (($param['Key'] ?? '') === 'ReceiptNo' || ($param['Name'] ?? '') === 'TransactionID') {
                            $transactionId = $param['Value'] ?? $transactionId;
                        }
                    }
                }

                $disbursement->update([
                    'status' => 'successful',
                    'mpesa_receipt_number' => $transactionId,
                    'disbursed_at' => now(),
                    'conversation_id' => $conversationId ?: $disbursement->conversation_id,
                    'originator_conversation_id' => $originatorConversationId ?: $disbursement->originator_conversation_id,
                ]);

                $loan = $disbursement->loan;
                $loan->update([
                    'status' => 'active',
                    'disbursed_at' => now(),
                ]);

                // Generate installment schedule starting from today (disbursement date)
                $this->loanService->generateInstallmentSchedule($loan, Carbon::today());

            } else {
                // Failed disbursement
                $disbursement->update([
                    'status' => 'failed',
                    'failure_reason' => $resultDesc,
                    'conversation_id' => $conversationId ?: $disbursement->conversation_id,
                    'originator_conversation_id' => $originatorConversationId ?: $disbursement->originator_conversation_id,
                ]);
            }
        });
    }
}
