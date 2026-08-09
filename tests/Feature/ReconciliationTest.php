<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\LoanService;
use App\Services\ReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_waterfall_allocation_and_idempotency(): void
    {
        $customer = Customer::create([
            'name' => 'Alice Kip',
            'phone_number' => '254711223344',
            'id_number' => '22334455',
            'email' => 'alice@example.com',
        ]);

        $product = LoanProduct::create([
            'name' => 'Biashara 2-Month',
            'interest_rate' => 10.00,
            'interest_type' => 'flat',
            'term_length' => 2,
            'term_unit' => 'months',
        ]);

        // Principal: 10000, Interest: 2000, Total: 12000, Installments: 2 x 6000 (5000 principal + 1000 interest)
        $loan = Loan::create([
            'loan_account_number' => 'LN-ALICE-01',
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'principal_amount' => 10000.00,
            'interest_amount' => 2000.00,
            'total_amount' => 12000.00,
            'balance' => 12000.00,
            'status' => 'active',
            'disbursed_at' => now(),
        ]);

        app(LoanService::class)->generateInstallmentSchedule($loan, Carbon::today());
        $this->assertEquals(2, $loan->installments()->count());

        // 1. Create STK Push Request for KES 8,000 (which covers Installment 1 [6000] in full + partial Installment 2 [2000])
        $stkRequest = \App\Models\StkRequest::create([
            'loan_id' => $loan->id,
            'checkout_reference' => (string) \Illuminate\Support\Str::uuid(),
            'amount_requested' => 8000.00,
            'phone_number' => '254711223344',
            'status' => 'pending',
        ]);

        // 2. Callback arrives with Internal UUID Token (No reliance on Safaricom's internal IDs as primary key)
        $callbackPayload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'MERCH_MOCK_123',
                    'CheckoutRequestID' => 'ws_CO_MOCK_456',
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 8000.00],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'QW11223344'],
                            ['Name' => 'PhoneNumber', 'Value' => '254711223344'],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson("/api/daraja/stk-callback/{$stkRequest->checkout_reference}", $callbackPayload);
        $response->assertStatus(200);

        // 3. Verify Waterfall Ledger Allocations
        $loan->refresh();
        $this->assertEquals(4000.00, (float) $loan->balance); // 12000 - 8000 = 4000 remaining
        $this->assertEquals(1, $loan->payments()->count());

        $installments = $loan->installments()->orderBy('installment_number')->get();
        // Installment 1: fully paid (6000 / 6000)
        $this->assertEquals('paid', $installments[0]->status);
        $this->assertEquals(6000.00, (float) $installments[0]->amount_paid);

        // Installment 2: partially paid (2000 / 6000)
        $this->assertEquals('partially_paid', $installments[1]->status);
        $this->assertEquals(2000.00, (float) $installments[1]->amount_paid);

        // 4. Test Idempotency / Duplicate Webhook Callback
        // If Safaricom or network retries and delivers the identical callback a 2nd time:
        $duplicateResponse = $this->postJson("/api/daraja/stk-callback/{$stkRequest->checkout_reference}", $callbackPayload);
        $duplicateResponse->assertStatus(200);

        $loan->refresh();
        // Ledger balance MUST stay 4000.00 and payment count MUST remain 1 (no double credit)
        $this->assertEquals(4000.00, (float) $loan->balance);
        $this->assertEquals(1, $loan->payments()->count());

        // 5. Final Repayment to Close the Loan: Pay remaining 4000
        $finalStk = \App\Models\StkRequest::create([
            'loan_id' => $loan->id,
            'checkout_reference' => (string) \Illuminate\Support\Str::uuid(),
            'amount_requested' => 4000.00,
            'phone_number' => '254711223344',
            'status' => 'pending',
        ]);

        $finalPayload = [
            'Body' => [
                'stkCallback' => [
                    'ResultCode' => 0,
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 4000.00],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'QW99880011'],
                            ['Name' => 'PhoneNumber', 'Value' => '254711223344'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson("/api/daraja/stk-callback/{$finalStk->checkout_reference}", $finalPayload);
        $loan->refresh();

        $this->assertEquals(0.00, (float) $loan->balance);
        $this->assertEquals('closed', $loan->status); // Loan lifecycle completed
    }
}
