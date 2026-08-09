<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['sometimes', 'required', 'string'],
            'email' => ['sometimes', 'required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identifier = trim((string) ($this->input('login') ?: $this->input('email')));

        // Normalize phone number if digits are provided
        $normalizedPhone = preg_replace('/\D/', '', $identifier);
        if (str_starts_with($normalizedPhone, '0')) {
            $normalizedPhone = '254'.substr($normalizedPhone, 1);
        } elseif (str_starts_with($normalizedPhone, '+')) {
            $normalizedPhone = substr($normalizedPhone, 1);
        }
        if (! str_starts_with($normalizedPhone, '254') && strlen($normalizedPhone) === 9) {
            $normalizedPhone = '254'.$normalizedPhone;
        }

        // Find user by email, phone number, ID number, or associated customer record
        $user = User::where(function ($query) use ($identifier, $normalizedPhone) {
            $query->where('email', $identifier)
                ->orWhere('phone_number', $normalizedPhone)
                ->orWhere('phone_number', $identifier)
                ->orWhere('id_number', $identifier)
                ->orWhereHas('customer', function ($q) use ($identifier, $normalizedPhone) {
                    $q->where('phone_number', $normalizedPhone)
                        ->orWhere('phone_number', $identifier)
                        ->orWhere('id_number', $identifier)
                        ->orWhere('email', $identifier);
                });
        })->first();

        if (! $user || ! Hash::check($this->input('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
                'email' => trans('auth.failed'),
            ]);
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        $login = (string) ($this->input('login') ?: $this->input('email'));

        return Str::transliterate(Str::lower($login).'|'.$this->ip());
    }
}
