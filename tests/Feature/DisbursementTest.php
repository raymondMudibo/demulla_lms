<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisbursementTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_b2c_disbursement_lifecycle_and_installment_generation(): void
    {
        // 1. Setup Admin, Customer, and Loan Product
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $customer = Customer::create([
            'name' => 'Jane Doe',
            'phone_number' => '254712345678',
            'id_number' => '12345678',
            'email' => 'jane@example.com',
        ]);

        $product = LoanProduct::create([
            'name' => 'Personal Loan 3-Month',
            'interest_rate' => 10.00,
            'interest_type' => 'flat',
            'term_length' => 3,
            'term_unit' => 'months',
        ]);

        $loan = Loan::create([
            'loan_account_number' => 'LN-TEST0001',
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'principal_amount' => 10000.00,
            'interest_amount' => 3000.00,
            'total_amount' => 13000.00,
            'balance' => 13000.00,
            'status' => 'pending',
        ]);

        // Verify initial state: not approved, not disbursed, no installments
        $this->assertEquals('pending', $loan->status);
        $this->assertNull($loan->approved_at);
        $this->assertNull($loan->disbursed_at);
        $this->assertEquals(0, $loan->installments()->count());

        // 2. Admin approves loan
        $response = $this->actingAs($admin)->post("/admin/loans/{$loan->id}/approve");
        $response->assertRedirect();

        $loan->refresh();
        $this->assertEquals('approved', $loan->status);
        $this->assertNotNull($loan->approved_at);
        $this->assertNull($loan->disbursed_at);
        $this->assertEquals(0, $loan->installments()->count());

        // 3. Admin initiates B2C Disbursement
        // State mutation prevented on initial HTTP response: disbursement is 'initiated', loan remains 'approved'
        $response = $this->actingAs($admin)->post("/admin/loans/{$loan->id}/disburse");
        $response->assertRedirect();

        $loan->refresh();
        $this->assertEquals(1, $loan->disbursements()->count());
        $disbursement = $loan->disbursements()->first();

        $this->assertEquals('initiated', $disbursement->status);
        $this->assertEquals('approved', $loan->status);
        $this->assertNull($loan->disbursed_at);
        $this->assertEquals(0, $loan->installments()->count()); // Installments not active yet
        $this->assertEquals(10000.00, (float) $disbursement->amount);
        $this->assertEquals('254712345678', $disbursement->phone_number);

        // 4. Safaricom B2C Webhook Callback Received (Success)
        $callbackPayload = [
            'Result' => [
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
                'TransactionID' => 'QW99887766',
                'ConversationID' => 'AG_123456',
                'OriginatorConversationID' => 'ORIG_123456',
            ],
        ];

        $callbackResponse = $this->postJson("/api/daraja/b2c-callback/{$disbursement->reference}", $callbackPayload);
        $callbackResponse->assertStatus(200);
        $callbackResponse->assertJson(['ResultCode' => 0]);

        // 5. Verify Post-Callback Reconciled State
        $loan->refresh();
        $disbursement->refresh();

        $this->assertEquals('successful', $disbursement->status);
        $this->assertEquals('QW99887766', $disbursement->mpesa_receipt_number);
        $this->assertNotNull($disbursement->disbursed_at);
        $this->assertNotNull($disbursement->raw_callback_payload);

        // Verify MpesaCallbackLog persisted to DB
        $this->assertDatabaseHas('mpesa_callback_logs', [
            'type' => 'b2c_callback',
            'reference' => $disbursement->reference,
            'transaction_id' => 'QW99887766',
            'result_code' => 0,
            'processing_status' => 'processed',
        ]);

        // Loan state check: now active
        $this->assertEquals('active', $loan->status);
        $this->assertNotNull($loan->disbursed_at);
        $this->assertFalse($loan->canBeDisbursed());

        // Installment schedule check: 3 monthly installments generated and live
        $this->assertEquals(3, $loan->installments()->count());
        $installments = $loan->installments()->orderBy('installment_number')->get();
        $this->assertEquals(1, $installments[0]->installment_number);
        $this->assertEquals('pending', $installments[0]->status);
        $this->assertEquals(13000.00, round($installments->sum('total_amount'), 2));

        // 6. Test Idempotency on duplicate webhook delivery
        $duplicateCallbackResponse = $this->postJson("/api/daraja/b2c-callback/{$disbursement->reference}", $callbackPayload);
        $duplicateCallbackResponse->assertStatus(200);

        $loan->refresh();
        $this->assertEquals(3, $loan->installments()->count());
        $this->assertEquals(1, $loan->disbursements()->count());

        // 7. Cannot disburse again once loan is active
        $secondDisburseResponse = $this->actingAs($admin)->post("/admin/loans/{$loan->id}/disburse");
        $secondDisburseResponse->assertRedirect();
        $this->assertEquals(1, $loan->disbursements()->count());
    }

    public function test_failed_b2c_disbursement_keeps_loan_approved_and_allows_retry(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $customer = Customer::create([
            'name' => 'Bob Miller',
            'phone_number' => '254722334455',
            'id_number' => '87654321',
            'email' => 'bob@example.com',
        ]);

        $product = LoanProduct::create([
            'name' => 'Quick Cash 1-Month',
            'interest_rate' => 5.00,
            'interest_type' => 'flat',
            'term_length' => 1,
            'term_unit' => 'months',
        ]);

        $loan = Loan::create([
            'loan_account_number' => 'LN-BOB0001',
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'principal_amount' => 5000.00,
            'interest_amount' => 250.00,
            'total_amount' => 5250.00,
            'balance' => 5250.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // 1. Initiate 1st disbursement attempt
        $this->actingAs($admin)->post("/admin/loans/{$loan->id}/disburse");
        $loan->refresh();
        $firstDisbursement = $loan->disbursements()->first();
        $this->assertEquals('initiated', $firstDisbursement->status);

        // 2. Callback returns failure (e.g. insufficient funds in B2C utility account)
        $failPayload = [
            'Result' => [
                'ResultCode' => 2001,
                'ResultDesc' => 'Insufficient funds in corporate utility wallet.',
            ],
        ];

        $this->postJson("/api/daraja/b2c-callback/{$firstDisbursement->reference}", $failPayload);

        $firstDisbursement->refresh();
        $loan->refresh();

        $this->assertEquals('failed', $firstDisbursement->status);
        $this->assertEquals('Insufficient funds in corporate utility wallet.', $firstDisbursement->failure_reason);
        // Loan stays 'approved' so admin can re-try
        $this->assertEquals('approved', $loan->status);
        $this->assertTrue($loan->canBeDisbursed());
        $this->assertEquals(0, $loan->installments()->count());

        // 3. Admin Retries Disbursement
        $this->actingAs($admin)->post("/admin/loans/{$loan->id}/disburse");
        $loan->refresh();
        $this->assertEquals(2, $loan->disbursements()->count());

        $retryDisbursement = $loan->disbursements()->orderBy('id', 'desc')->first();
        $this->assertEquals('initiated', $retryDisbursement->status);

        // 4. Successful callback on retry
        $successPayload = [
            'Result' => [
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
                'TransactionID' => 'NL99112233',
            ],
        ];

        $this->postJson("/api/daraja/b2c-callback/{$retryDisbursement->reference}", $successPayload);

        $retryDisbursement->refresh();
        $loan->refresh();

        $this->assertEquals('successful', $retryDisbursement->status);
        $this->assertEquals('NL99112233', $retryDisbursement->mpesa_receipt_number);
        $this->assertEquals('active', $loan->status);
        $this->assertEquals(1, $loan->installments()->count());
    }
}
