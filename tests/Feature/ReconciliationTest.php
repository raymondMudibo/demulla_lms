<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\StkRequest;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $stkRequest = StkRequest::create([
            'loan_id' => $loan->id,
            'checkout_reference' => (string) Str::uuid(),
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
        $finalStk = StkRequest::create([
            'loan_id' => $loan->id,
            'checkout_reference' => (string) Str::uuid(),
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

    public function test_amount_mismatch_marks_stk_request_mismatched_and_allocates_ledger(): void
    {
        $customer = Customer::create([
            'name' => 'Charlie Day',
            'phone_number' => '254733445566',
            'id_number' => '33445566',
            'email' => 'charlie@example.com',
        ]);

        $product = LoanProduct::create([
            'name' => 'Flash Loan 1-Month',
            'interest_rate' => 10.00,
            'interest_type' => 'flat',
            'term_length' => 1,
            'term_unit' => 'months',
        ]);

        $loan = Loan::create([
            'loan_account_number' => 'LN-CHARLIE-01',
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'principal_amount' => 5000.00,
            'interest_amount' => 500.00,
            'total_amount' => 5500.00,
            'balance' => 5500.00,
            'status' => 'active',
            'disbursed_at' => now(),
        ]);

        app(LoanService::class)->generateInstallmentSchedule($loan, Carbon::today());

        // Create STK request for KES 5,500
        $stkRequest = StkRequest::create([
            'loan_id' => $loan->id,
            'checkout_reference' => (string) Str::uuid(),
            'amount_requested' => 5500.00,
            'phone_number' => '254733445566',
            'status' => 'pending',
        ]);

        // Customer pays KES 3,000 instead (Amount mismatch)
        $mismatchedCallbackPayload = [
            'Body' => [
                'stkCallback' => [
                    'ResultCode' => 0,
                    'ResultDesc' => 'Success',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 3000.00],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'MISMATCH123'],
                            ['Name' => 'PhoneNumber', 'Value' => '254733445566'],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson("/api/daraja/stk-callback/{$stkRequest->checkout_reference}", $mismatchedCallbackPayload);
        $response->assertStatus(200);

        $stkRequest->refresh();
        $loan->refresh();

        // Auditing status marked 'mismatched'
        $this->assertEquals('mismatched', $stkRequest->status);

        // Payment record created
        $this->assertEquals(1, $loan->payments()->count());
        $payment = $loan->payments()->first();
        $this->assertEquals(3000.00, (float) $payment->amount_paid);

        // Cascading ledger applied
        $this->assertEquals(2500.00, (float) $loan->balance); // 5500 - 3000 = 2500 remaining
        $installment = $loan->installments()->first();
        $this->assertEquals('partially_paid', $installment->status);
        $this->assertEquals(3000.00, (float) $installment->amount_paid);
    }

    public function test_reconcile_pending_stk_requests_command_auto_resolves_orphaned_requests(): void
    {
        $customer = Customer::create([
            'name' => 'David Kim',
            'phone_number' => '254744556677',
            'id_number' => '44556677',
            'email' => 'david@example.com',
        ]);

        $product = LoanProduct::create([
            'name' => 'Mini Loan 1-Month',
            'interest_rate' => 10.00,
            'interest_type' => 'flat',
            'term_length' => 1,
            'term_unit' => 'months',
        ]);

        $loan = Loan::create([
            'loan_account_number' => 'LN-DAVID-01',
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'principal_amount' => 2000.00,
            'interest_amount' => 200.00,
            'total_amount' => 2200.00,
            'balance' => 2200.00,
            'status' => 'active',
            'disbursed_at' => now(),
        ]);

        app(LoanService::class)->generateInstallmentSchedule($loan, Carbon::today());

        // Create pending request created 10 minutes ago
        $pendingStk = StkRequest::create([
            'loan_id' => $loan->id,
            'checkout_reference' => (string) Str::uuid(),
            'checkout_request_id' => 'ws_CO_TEST_QUERY_123',
            'merchant_request_id' => 'merch_test_123',
            'amount_requested' => 2200.00,
            'phone_number' => '254744556677',
            'status' => 'pending',
        ]);
        DB::table('stk_requests')
            ->where('id', $pendingStk->id)
            ->update(['created_at' => now()->subMinutes(10)]);

        // Run artisan command
        $this->artisan('loans:reconcile-stk')
            ->expectsOutputToContain('Found 1 pending STK requests older than 5 minutes')
            ->assertExitCode(0);

        $pendingStk->refresh();
        $loan->refresh();

        // In sandbox/simulation mode, queryStkStatus returns success -> reconciles to completed and closes loan
        $this->assertEquals('completed', $pendingStk->status);
        $this->assertEquals(0.00, (float) $loan->balance);
        $this->assertEquals('closed', $loan->status);
    }

    public function test_stk_callback_fast_acknowledgment_returns_200_ok(): void
    {
        $customer = Customer::create([
            'name' => 'Eve Stone',
            'phone_number' => '254755667788',
            'id_number' => '55667788',
            'email' => 'eve@example.com',
        ]);

        $product = LoanProduct::create([
            'name' => 'Fast Loan 1-Month',
            'interest_rate' => 10.00,
            'interest_type' => 'flat',
            'term_length' => 1,
            'term_unit' => 'months',
        ]);

        $loan = Loan::create([
            'loan_account_number' => 'LN-EVE-01',
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'principal_amount' => 1000.00,
            'interest_amount' => 100.00,
            'total_amount' => 1100.00,
            'balance' => 1100.00,
            'status' => 'active',
            'disbursed_at' => now(),
        ]);

        $stkRequest = StkRequest::create([
            'loan_id' => $loan->id,
            'checkout_reference' => (string) Str::uuid(),
            'amount_requested' => 1100.00,
            'phone_number' => '254755667788',
            'status' => 'pending',
        ]);

        $callbackPayload = [
            'Body' => [
                'stkCallback' => [
                    'ResultCode' => 0,
                    'ResultDesc' => 'Success',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 1100.00],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'EVE123456'],
                            ['Name' => 'PhoneNumber', 'Value' => '254755667788'],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson("/api/daraja/stk-callback/{$stkRequest->checkout_reference}", $callbackPayload);
        $response->assertStatus(200);
        $response->assertJson([
            'ResultCode' => 0,
            'ResultDesc' => 'Success',
        ]);

        $this->assertDatabaseHas('mpesa_callback_logs', [
            'type' => 'stk_callback',
            'reference' => $stkRequest->checkout_reference,
            'processing_status' => 'processed',
        ]);
    }
}
