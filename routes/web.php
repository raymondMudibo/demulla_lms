<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\LoanController;
use App\Http\Controllers\Admin\LoanProductController;
use App\Http\Controllers\DisbursementController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\Portal\PortalLoanController;
use App\Http\Controllers\WebhookSimulationController;

Route::get('/dashboard', function () {
    $user = auth()->user();
    if ($user && $user->role === 'admin') {
        return redirect()->route('admin.loans.index');
    }

    return redirect()->route('portal.dashboard');
})->middleware(['auth'])->name('dashboard');

// Admin Space
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Customers
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');

    // Loan Products
    Route::get('/loan-products', [LoanProductController::class, 'index'])->name('loan-products.index');
    Route::post('/loan-products', [LoanProductController::class, 'store'])->name('loan-products.store');

    // Loans & Disbursement
    Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
    Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
    Route::post('/loans/{loan}/approve', [LoanController::class, 'approve'])->name('loans.approve');
    Route::post('/loans/{loan}/reject', [LoanController::class, 'reject'])->name('loans.reject');
    Route::post('/loans/{loan}/disburse', [DisbursementController::class, 'disburse'])->name('loans.disburse');
});

// Customer Portal Space
Route::middleware(['auth', 'role:customer'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/dashboard', [PortalDashboardController::class, 'index'])->name('dashboard');

    // Customer Loans
    Route::post('/loans', [PortalLoanController::class, 'store'])->name('loans.store');
    Route::get('/loans/{loan}', [PortalLoanController::class, 'show'])->name('loans.show');
    Route::post('/loans/{loan}/repay', [PortalLoanController::class, 'repay'])->name('loans.repay');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Daraja webhook callbacks (CSRF exempt)
Route::post('/api/daraja/stk-callback/{checkout_reference}', [PaymentController::class, 'handleStkCallback'])->name('api.payment.callback');
Route::post('/api/daraja/b2c-callback/{reference}', [DisbursementController::class, 'handleB2cCallback'])->name('api.disbursement.callback');

// Simulation Endpoints
Route::post('/api/daraja/simulate-b2c-callback/{reference}', [WebhookSimulationController::class, 'simulateB2cCallback'])->name('api.daraja.simulate-b2c');
Route::post('/api/daraja/simulate-stk-callback/{checkout_reference}', [WebhookSimulationController::class, 'simulateStkCallback'])->name('api.daraja.simulate-stk');

require __DIR__.'/auth.php';
