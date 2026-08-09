<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoanProductController extends Controller
{
    public function index(): Response
    {
        $products = LoanProduct::orderBy('created_at', 'desc')->get();

        return Inertia::render('Admin/LoanProducts/Index', [
            'products' => $products,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'interest_type' => 'required|in:flat,reducing_balance',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'term_length' => 'required|integer|min:1',
            'term_unit' => 'required|in:weeks,months',
            'processing_fee' => 'required|numeric|min:0',
        ]);

        LoanProduct::create($validated);

        return redirect()->route('admin.loan-products.index')->with('success', 'Loan product created successfully.');
    }
}
