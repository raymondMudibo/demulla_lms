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
        $response = $this->actingAs($admin)->post("/admin/loans/{$loan->id}/disburse");
        $response->assertRedirect();

        $loan->refresh();
        $this->assertEquals(1, $loan->disbursements()->count());
        $disbursement = $loan->disbursements()->first();
        
        // Primary transition verified: Loan is active and schedule is live immediately upon disburse
        $this->assertEquals('successful', $disbursement->status);
        $this->assertEquals('active', $loan->status);
        $this->assertNotNull($loan->disbursed_at);
        $this->assertEquals(3, $loan->installments()->count());
        $this->assertEquals(10000.00, (float) $disbursement->amount);
        $this->assertEquals('254712345678', $disbursement->phone_number);

        // 4. Safaricom B2C Webhook Callback Received (Fallback Reconciliation)
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

        // Audit check updated with exact Safaricom receipt
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

        // Loan state check
        $this->assertEquals('active', $loan->status);
        $this->assertFalse($loan->canBeDisbursed());

        // Installment schedule check: 3 monthly installments generated and live
        $this->assertEquals(3, $loan->installments()->count());
        $installments = $loan->installments()->orderBy('installment_number')->get();
        $this->assertEquals(1, $installments[0]->installment_number);
        $this->assertEquals('pending', $installments[0]->status);
        $this->assertEquals(13000.00, round($installments->sum('total_amount'), 2));

        // 6. Verification of one-way idempotency: cannot disburse again
        $secondDisburseResponse = $this->actingAs($admin)->post("/admin/loans/{$loan->id}/disburse");
        $secondDisburseResponse->assertRedirect();
        $this->assertEquals(1, $loan->disbursements()->count()); // Still 1 disbursement
    }
}
