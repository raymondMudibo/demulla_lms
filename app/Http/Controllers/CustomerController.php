<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(): Response
    {
        $customers = Customer::orderBy('created_at', 'desc')->get();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => ['required', 'string', 'unique:customers,phone_number', 'regex:/^(254|0|\+254)?(7|1)\d{8}$/'],
            'id_number' => 'required|string|unique:customers,id_number|max:50',
            'email' => 'nullable|email|max:255',
        ], [
            'phone_number.regex' => 'The phone number format must be a valid Kenyan mobile number (e.g. 0712345678 or 254712345678).',
        ]);

        // Normalize phone number to format 254...
        $phone = preg_replace('/\D/', '', $validated['phone_number']);
        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        } elseif (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }
        if (! str_starts_with($phone, '254') && strlen($phone) === 9) {
            $phone = '254'.$phone;
        }
        $validated['phone_number'] = $phone;

        Customer::create($validated);

        return redirect()->route('customers.index')->with('success', 'Customer created successfully.');
    }
}
