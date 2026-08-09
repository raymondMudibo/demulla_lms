<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\Loan;
use App\Models\LoanProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoanService
{
    public function createLoan(array $data): Loan
    {
        return DB::transaction(function () use ($data) {
            $product = LoanProduct::findOrFail($data['loan_product_id']);
            $principal = (float) $data['principal_amount'];

            $calculations = $this->calculateLoanAmounts($product, $principal);

            $loanAccountNumber = 'LN-'.strtoupper(Str::random(8));
            while (Loan::where('loan_account_number', $loanAccountNumber)->exists()) {
                $loanAccountNumber = 'LN-'.strtoupper(Str::random(8));
            }

            return Loan::create([
                'loan_account_number' => $loanAccountNumber,
                'customer_id' => $data['customer_id'],
                'loan_product_id' => $product->id,
                'principal_amount' => $principal,
                'interest_amount' => $calculations['interest_amount'],
                'total_amount' => $calculations['total_amount'],
                'balance' => $calculations['total_amount'],
                'status' => 'pending',
            ]);
        });
    }

    public function approveLoan(Loan $loan): Loan
    {
        return DB::transaction(function () use ($loan) {
            $loan->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            return $loan;
        });
    }

    public function generateInstallmentSchedule(Loan $loan, Carbon $startDate): void
    {
        DB::transaction(function () use ($loan, $startDate) {
            // Avoid generating duplicate schedules
            if ($loan->installments()->exists()) {
                return;
            }

            $product = $loan->loanProduct;
            $principal = (float) $loan->principal_amount;
            $termLength = $product->term_length;
            $rate = (float) $product->interest_rate / 100;
            $interestType = $product->interest_type;

            if ($interestType === 'flat') {
                $totalInterest = $principal * $rate * $termLength;
                $installmentTotal = ($principal + $totalInterest) / $termLength;
                $installmentPrincipal = $principal / $termLength;
                $installmentInterest = $totalInterest / $termLength;

                for ($i = 1; $i <= $termLength; $i++) {
                    $dueDate = $product->term_unit === 'weeks'
                        ? $startDate->copy()->addWeeks($i)
                        : $startDate->copy()->addMonths($i);

                    // Handle rounding on the last installment
                    if ($i === $termLength) {
                        $prevPrincipalSum = Installment::where('loan_id', $loan->id)->sum('principal_amount');
                        $actualPrincipal = $principal - $prevPrincipalSum;

                        $prevInterestSum = Installment::where('loan_id', $loan->id)->sum('interest_amount');
                        $actualInterest = $totalInterest - $prevInterestSum;

                        $actualTotal = $actualPrincipal + $actualInterest;
                    } else {
                        $actualPrincipal = round($installmentPrincipal, 2);
                        $actualInterest = round($installmentInterest, 2);
                        $actualTotal = round($installmentTotal, 2);
                    }

                    Installment::create([
                        'loan_id' => $loan->id,
                        'installment_number' => $i,
                        'due_date' => $dueDate->toDateString(),
                        'principal_amount' => $actualPrincipal,
                        'interest_amount' => $actualInterest,
                        'total_amount' => $actualTotal,
                        'amount_paid' => 0.00,
                        'status' => 'pending',
                    ]);
                }
            } else {
                // Reducing Balance (Amortized PMT)
                $n = $termLength;
                $p = $principal;
                $r = $rate;

                if ($r > 0) {
                    $pmt = $p * ($r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);
                } else {
                    $pmt = $p / $n;
                }

                $remainingBalance = $p;

                for ($i = 1; $i <= $n; $i++) {
                    $dueDate = $product->term_unit === 'weeks'
                        ? $startDate->copy()->addWeeks($i)
                        : $startDate->copy()->addMonths($i);

                    $interestForPeriod = $remainingBalance * $r;

                    if ($i === $n) {
                        $principalForPeriod = $remainingBalance;
                        $totalForPeriod = $principalForPeriod + $interestForPeriod;
                    } else {
                        $principalForPeriod = $pmt - $interestForPeriod;
                        $totalForPeriod = $pmt;
                    }

                    Installment::create([
                        'loan_id' => $loan->id,
                        'installment_number' => $i,
                        'due_date' => $dueDate->toDateString(),
                        'principal_amount' => round($principalForPeriod, 2),
                        'interest_amount' => round($interestForPeriod, 2),
                        'total_amount' => round($totalForPeriod, 2),
                        'amount_paid' => 0.00,
                        'status' => 'pending',
                    ]);

                    $remainingBalance -= $principalForPeriod;
                }
            }
        });
    }

    public function calculateLoanAmounts(LoanProduct $product, float $principal): array
    {
        $termLength = $product->term_length;
        $rate = (float) $product->interest_rate / 100;

        if ($product->interest_type === 'flat') {
            $interestAmount = $principal * $rate * $termLength;
            $totalAmount = $principal + $interestAmount;
        } else {
            // Pre-calculate reducing balance schedule to obtain exact aggregated interest sum
            $n = $termLength;
            $p = $principal;
            $r = $rate;

            if ($r > 0) {
                $pmt = $p * ($r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);
            } else {
                $pmt = $p / $n;
            }

            $remainingBalance = $p;
            $interestAmount = 0.00;

            for ($i = 1; $i <= $n; $i++) {
                $interestForPeriod = $remainingBalance * $r;
                $principalForPeriod = ($i === $n) ? $remainingBalance : ($pmt - $interestForPeriod);

                $interestAmount += $interestForPeriod;
                $remainingBalance -= $principalForPeriod;
            }

            $totalAmount = $principal + $interestAmount;
        }

        return [
            'interest_amount' => round($interestAmount, 2),
            'total_amount' => round($totalAmount, 2),
        ];
    }
}
