<?php

namespace App\Console\Commands;

use App\Models\StkRequest;
use App\Services\DarajaService;
use App\Services\ReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReconcilePendingStkRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:reconcile-stk';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile pending/orphaned Daraja M-Pesa STK Push requests older than 5 minutes via STK Query API';

    protected DarajaService $darajaService;

    protected ReconciliationService $reconciliationService;

    public function __construct(
        DarajaService $darajaService,
        ReconciliationService $reconciliationService
    ) {
        parent::__construct();
        $this->darajaService = $darajaService;
        $this->reconciliationService = $reconciliationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoffTime = now()->subMinutes(5);

        $pendingRequests = StkRequest::where('status', 'pending')
            ->where('created_at', '<=', $cutoffTime)
            ->get();

        $count = $pendingRequests->count();
        $this->info("Found {$count} pending STK requests older than 5 minutes for automated reconciliation.");

        if ($count === 0) {
            return Command::SUCCESS;
        }

        $reconciledCount = 0;

        foreach ($pendingRequests as $stkRequest) {
            $this->line("Processing STK request #{$stkRequest->id} (Ref: {$stkRequest->checkout_reference})...");

            if (! $stkRequest->checkout_request_id) {
                // If initiated over 15 minutes ago without checkout_request_id from gateway, mark failed
                if ($stkRequest->created_at <= now()->subMinutes(15)) {
                    $stkRequest->update(['status' => 'failed']);
                    $this->warn("STK request #{$stkRequest->id} marked failed (Missing CheckoutRequestID expired).");
                }

                continue;
            }

            try {
                $queryResult = $this->darajaService->queryStkStatus($stkRequest->checkout_request_id);

                if (! ($queryResult['success'] ?? false)) {
                    $this->error("Failed to query Daraja status for checkout_request_id: {$stkRequest->checkout_request_id}");

                    // If request is older than 30 minutes and query fails repeatedly, fail it safely
                    if ($stkRequest->created_at <= now()->subMinutes(30)) {
                        $stkRequest->update(['status' => 'failed']);
                        $this->warn("STK request #{$stkRequest->id} expired after 30 minutes without resolution.");
                    }

                    continue;
                }

                $resultCode = $queryResult['result_code'] ?? null;
                $resultDesc = $queryResult['result_desc'] ?? 'Auto-reconciled via STK status query';

                if ($resultCode === 0 || $resultCode === '0') {
                    // Payment succeeded on Safaricom
                    $receiptNumber = 'REC_'.strtoupper(Str::random(8));

                    $syntheticPayload = [
                        'Body' => [
                            'stkCallback' => [
                                'MerchantRequestID' => $stkRequest->merchant_request_id,
                                'CheckoutRequestID' => $stkRequest->checkout_request_id,
                                'ResultCode' => 0,
                                'ResultDesc' => $resultDesc,
                                'CallbackMetadata' => [
                                    'Item' => [
                                        ['Name' => 'Amount', 'Value' => (float) $stkRequest->amount_requested],
                                        ['Name' => 'MpesaReceiptNumber', 'Value' => $receiptNumber],
                                        ['Name' => 'PhoneNumber', 'Value' => $stkRequest->phone_number],
                                        ['Name' => 'TransactionDate', 'Value' => now()->format('YmdHis')],
                                    ],
                                ],
                            ],
                        ],
                    ];

                    $this->reconciliationService->processStkCallback($stkRequest->checkout_reference, $syntheticPayload);
                    $this->info("STK request #{$stkRequest->id} successfully reconciled as COMPLETED.");
                    $reconciledCount++;
                } elseif (! is_null($resultCode) && $resultCode !== 0) {
                    // Payment was cancelled, timed out, or failed
                    $syntheticPayload = [
                        'Body' => [
                            'stkCallback' => [
                                'MerchantRequestID' => $stkRequest->merchant_request_id,
                                'CheckoutRequestID' => $stkRequest->checkout_request_id,
                                'ResultCode' => (int) $resultCode,
                                'ResultDesc' => $resultDesc,
                            ],
                        ],
                    ];

                    $this->reconciliationService->processStkCallback($stkRequest->checkout_reference, $syntheticPayload);
                    $this->info("STK request #{$stkRequest->id} reconciled with result code {$resultCode} ({$resultDesc}).");
                    $reconciledCount++;
                } else {
                    // Still processing in Daraja
                    $this->line("STK request #{$stkRequest->id} still pending in Daraja gateway.");
                }
            } catch (\Exception $e) {
                Log::error("Error reconciling pending STK request #{$stkRequest->id}: ".$e->getMessage());
                $this->error("Exception reconciling STK request #{$stkRequest->id}: ".$e->getMessage());
            }
        }

        $this->info("STK reconciliation completed. Total processed/reconciled: {$reconciledCount}.");

        return Command::SUCCESS;
    }
}
