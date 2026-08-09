<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Loan $loan): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isCustomer() && $user->customer_id === $loan->customer_id;
    }

    /**
     * Determine whether the user can apply for a loan.
     */
    public function create(User $user): bool
    {
        return $user->isCustomer() && ! is_null($user->customer_id);
    }

    /**
     * Determine whether the user can trigger STK push repayment.
     */
    public function repay(User $user, Loan $loan): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isCustomer() && $user->customer_id === $loan->customer_id;
    }
}
