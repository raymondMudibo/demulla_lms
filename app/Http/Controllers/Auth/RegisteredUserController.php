<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|lowercase|email|max:255|unique:'.User::class,
            'phone_number' => ['required', 'string', 'unique:customers,phone_number', 'unique:users,phone_number', 'regex:/^(254|0|\+254)?(7|1)\d{8}$/'],
            'id_number' => 'required|string|unique:customers,id_number|unique:users,id_number|max:50',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Normalize phone number to format 254...
        $phone = preg_replace('/\D/', '', $request->phone_number);
        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        } elseif (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }
        if (! str_starts_with($phone, '254') && strlen($phone) === 9) {
            $phone = '254'.$phone;
        }

        return DB::transaction(function () use ($request, $phone) {
            // Create Customer profile
            $customer = Customer::create([
                'name' => $request->name,
                'phone_number' => $phone,
                'id_number' => $request->id_number,
                'email' => $request->email,
            ]);

            // Create User and link to Customer
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $phone,
                'id_number' => $request->id_number,
                'password' => Hash::make($request->password),
                'role' => 'customer',
                'customer_id' => $customer->id,
            ]);

            event(new Registered($user));

            Auth::login($user);

            return redirect(route('portal.dashboard', absolute: false));
        });
    }
}
