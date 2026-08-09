<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\InstallmentPayment;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\StkRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReconciliationService
{
    protected DarajaService $darajaService;

    public function __construct(DarajaService $darajaService)
    {
        $this->darajaService = $darajaService;
    }

    public function initiateStkPush(Loan $loan, float $amount, string $phoneNumber): array
    {
        return DB::transaction(function () use ($loan, $amount, $phoneNumber) {
            $checkoutReference = (string) Str::uuid();

            $stkRequest = StkRequest::create([
                'loan_id' => $loan->id,
                'checkout_reference' => $checkoutReference,
                'amount_requested' => $amount,
                'phone_number' => $phoneNumber,
                'status' => 'pending',
            ]);

            // Call Safaricom STK Push API
            $response = $this->darajaService->initiateStkPush($checkoutReference, $amount, $phoneNumber);

            if ($response['success']) {
                $stkRequest->update([
                    'checkout_request_id' => $response['checkout_request_id'] ?? null,
                    'merchant_request_id' => $response['merchant_request_id'] ?? null,
                ]);

                return [
                    'success' => true,
                    'stk_request' => $stkRequest,
                    'is_simulation' => $response['is_simulation'] ?? false,
                    'message' => 'STK push repayment request initiated successfully.',
                ];
            } else {
                $stkRequest->update([
                    'status' => 'failed',
                ]);

                return [
                    'success' => false,
                    'message' => $response['message'] ?? 'Failed to initiate STK push',
                ];
            }
        });
    }

    public function processStkCallback(string $checkoutReference, array $payload): void
    {
        DB::transaction(function () use ($checkoutReference, $payload) {
            $stkRequest = StkRequest::where('checkout_reference', $checkoutReference)
                ->lockForUpdate()
                ->firstOrFail();

            if ($stkRequest->status !== 'pending') {
                Log::warning("STK callback already processed for reference: {$checkoutReference}");

                return;
            }

            $stkRequest->update([
                'raw_callback_payload' => $payload,
            ]);

            $stkCallback = $payload['Body']['stkCallback'] ?? $payload;
            $resultCode = $stkCallback['ResultCode'] ?? 1;
            $resultDesc = $stkCallback['ResultDesc'] ?? 'Transaction failed';

            if ($resultCode === 0) {
                // Successful Payment
                $metaItems = $stkCallback['CallbackMetadata']['Item'] ?? [];

                $amountPaid = 0.00;
                $mpesaReceiptNumber = null;
                $payerPhone = $stkRequest->phone_number;

                foreach ($metaItems as $item) {
                    $name = $item['Name'] ?? '';
                    $value = $item['Value'] ?? null;

                    if ($name === 'Amount') {
                        $amountPaid = (float) $value;
                    } elseif ($name === 'MpesaReceiptNumber') {
                        $mpesaReceiptNumber = $value;
                    } elseif ($name === 'PhoneNumber') {
                        $payerPhone = (string) $value;
                    }
                }

                // Check for amount mismatch
                $isMismatched = abs($amountPaid - (float) $stkRequest->amount_requested) > 0.01;
                $stkRequest->update([
                    'status' => $isMismatched ? 'mismatched' : 'completed',
                ]);

                // Create the Payment Record
                $payment = Payment::create([
                    'loan_id' => $stkRequest->loan_id,
                    'stk_request_id' => $stkRequest->id,
                    'mpesa_receipt_number' => $mpesaReceiptNumber ?: ('MOCK_'.strtoupper(Str::random(10))),
                    'amount_paid' => $amountPaid,
                    'payer_phone_number' => $payerPhone,
                    'paid_at' => now(),
                ]);

                // Perform Ledger Allocation
                $this->allocateLedger($payment);

            } else {
                // Failed Payment (e.g. cancelled by user)
                $stkRequest->update([
                    'status' => $resultCode === 1032 ? 'cancelled' : 'failed',
                ]);
            }
        });
    }

    protected function allocateLedger(Payment $payment): void
    {
        $loan = $payment->loan;
        $amountRemaining = (float) $payment->amount_paid;

        // Fetch unpaid installments ordered by due_date ASC
        $installments = Installment::where('loan_id', $loan->id)
            ->where('status', '!=', 'paid')
            ->orderBy('due_date', 'asc')
            ->lockForUpdate()
            ->get();

        foreach ($installments as $installment) {
            if ($amountRemaining <= 0.005) {
                break;
            }

            $totalAmount = (float) $installment->total_amount;
            $amountPaidSoFar = (float) $installment->amount_paid;
            $installmentBalance = $totalAmount - $amountPaidSoFar;

            if ($installmentBalance <= 0) {
                continue;
            }

            $allocated = min($amountRemaining, $installmentBalance);
            $newPaidAmount = $amountPaidSoFar + $allocated;

            $status = abs($newPaidAmount - $totalAmount) < 0.005 ? 'paid' : 'partially_paid';

            $installment->update([
                'amount_paid' => $newPaidAmount,
                'status' => $status,
            ]);

            InstallmentPayment::create([
                'payment_id' => $payment->id,
                'installment_id' => $installment->id,
                'amount_applied' => $allocated,
            ]);

            $amountRemaining -= $allocated;
        }

        // Update the Loan's outstanding balance
        $currentBalance = (float) $loan->balance;
        $newLoanBalance = max(0.00, $currentBalance - (float) $payment->amount_paid);

        $loanData = [
            'balance' => $newLoanBalance,
        ];

        // If the balance is zero, close the loan
        if ($newLoanBalance <= 0.005) {
            $loanData['status'] = 'closed';
        }

        $loan->update($loanData);
    }
}
