<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    canLogin: {
        type: Boolean,
        default: true,
    },
    canRegister: {
        type: Boolean,
        default: true,
    },
    canResetPassword: {
        type: Boolean,
        default: true,
    },
    status: {
        type: String,
    },
});

// Active Tab: 'login' | 'register'
const activeTab = ref('login');

// Login Form
const loginForm = useForm({
    login: '',
    password: '',
    remember: false,
});

const submitLogin = () => {
    loginForm.post(route('login'), {
        onFinish: () => loginForm.reset('password'),
    });
};

// Register Form
const registerForm = useForm({
    name: '',
    email: '',
    phone_number: '',
    id_number: '',
    password: '',
    password_confirmation: '',
});

const submitRegister = () => {
    registerForm.post(route('register'), {
        onFinish: () => registerForm.reset('password', 'password_confirmation'),
    });
};

// Quick Fill Helpers for Testing
const fillAdminCredentials = () => {
    activeTab.value = 'login';
    loginForm.login = 'admin@example.com';
    loginForm.password = 'password';
};

const fillDemoCustomer = (email = 'john@example.com') => {
    activeTab.value = 'login';
    loginForm.login = email;
    loginForm.password = 'password';
};

const switchToRegister = () => {
    activeTab.value = 'register';
};
</script>

<template>
    <Head title="Mini Loan Management System" />

    <div class="min-h-screen bg-gray-50 text-gray-900 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 selection:bg-black selection:text-white">
        <div class="max-w-6xl w-full mx-auto space-y-8">
            
            <!-- Header Section -->
            <div class="text-center space-y-2.5">
                <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-gray-900 uppercase">
                    Mini Loan Management System
                </h1>               
            </div>

            <!-- If Already Logged In Banner -->
            <div v-if="$page.props.auth?.user" class="max-w-xl mx-auto overflow-hidden bg-white rounded-2xl border border-gray-300 p-6 text-center space-y-4 shadow-sm">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Welcome back, {{ $page.props.auth.user.name }}!</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        You are currently signed in as 
                        <span class="font-bold text-black uppercase tracking-wider underline">
                            {{ $page.props.auth.user.role || 'customer' }}
                        </span>
                    </p>
                </div>
                <div class="flex flex-wrap justify-center gap-3">
                    <Link
                        :href="route('dashboard')"
                        class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg font-bold text-sm bg-black text-white hover:bg-gray-800 shadow-sm transition"
                    >
                        Go to Dashboard &rarr;
                    </Link>
                    <Link
                        v-if="$page.props.auth.user.role === 'admin'"
                        :href="route('admin.customers.index')"
                        class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg font-bold text-sm bg-white text-black border border-black hover:bg-gray-100 shadow-sm transition"
                    >
                        View All Customers
                    </Link>
                </div>
            </div>

            <!-- Main Split Layout -->
            <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Left Column: Guidance & Instructions (Admin & Customer) -->
                <div class="lg:col-span-7 space-y-6">
                    
                    <!-- 1. Admin Instructions Card -->
                    <div class="bg-white rounded-2xl border border-gray-300 p-6 shadow-sm relative overflow-hidden">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-gray-100 border border-gray-300 flex items-center justify-center text-gray-900">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-gray-900 uppercase tracking-wide">Administrator Access</h2>
                                    <p class="text-xs text-gray-500">Log in to review and manage customers & loans</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="fillAdminCredentials"
                                class="inline-flex items-center text-xs font-bold px-3 py-1.5 rounded-lg bg-black text-white hover:bg-gray-800 transition shadow-sm shrink-0"
                            >
                                Autofill Admin
                            </button>
                        </div>

                        <!-- Credentials & Steps -->
                        <div class="space-y-3.5 text-xs sm:text-sm text-gray-700">
                            <div class="bg-gray-50 rounded-xl p-3.5 border border-gray-200 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span class="text-gray-500 block text-xs font-medium">Admin Email:</span>
                                    <span class="font-mono text-black font-bold">admin@example.com</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 block text-xs font-medium">Password:</span>
                                    <span class="font-mono text-black font-bold">password</span>
                                </div>
                            </div>

                            <div class="space-y-1.5 pt-1">
                                <p class="font-bold text-gray-900 text-xs uppercase tracking-wider">How to View Registered Customers:</p>
                                <ol class="list-decimal list-inside space-y-1 text-gray-600 text-xs leading-relaxed">
                                    <li>Log in with the administrator credentials above.</li>
                                    <li>Click on the <strong class="text-black">"Customers"</strong> tab in the top navigation bar (<code class="text-gray-900 font-mono bg-gray-100 border border-gray-200 px-1 py-0.5 rounded">/admin/customers</code>).</li>
                                    <li>View all registered customer profiles, National IDs, phone numbers, and loan history.</li>
                                    <li>Navigate to <strong class="text-black">"Loans"</strong> to approve, reject, or disburse loans.</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Customer Onboarding & Setup Instructions Card -->
                    <div class="bg-white rounded-2xl border border-gray-300 p-6 shadow-sm relative overflow-hidden">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-gray-100 border border-gray-300 flex items-center justify-center text-gray-900">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-gray-900 uppercase tracking-wide">Customer Getting Started</h2>
                                    <p class="text-xs text-gray-500">Create a borrower profile or test existing customer logins</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="switchToRegister"
                                class="inline-flex items-center text-xs font-bold px-3 py-1.5 rounded-lg bg-white text-black border border-black hover:bg-gray-100 transition shadow-sm shrink-0"
                            >
                                Register New
                            </button>
                        </div>

                        <!-- Customer Setup Steps -->
                        <div class="space-y-3.5 text-xs sm:text-sm text-gray-700">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                <div class="bg-gray-50 p-3 rounded-xl border border-gray-200 space-y-1">
                                    <div class="text-gray-900 font-bold text-xs flex items-center gap-1.5">
                                        <span class="w-4 h-4 rounded-full bg-black text-white flex items-center justify-center text-[10px]">1</span>
                                        Register
                                    </div>
                                    <p class="text-[11px] text-gray-600 leading-tight">
                                        Fill in Name, M-Pesa Phone (<code class="text-gray-900 font-mono">07...</code>), National ID, and Password.
                                    </p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-xl border border-gray-200 space-y-1">
                                    <div class="text-gray-900 font-bold text-xs flex items-center gap-1.5">
                                        <span class="w-4 h-4 rounded-full bg-black text-white flex items-center justify-center text-[10px]">2</span>
                                        Portal Access
                                    </div>
                                    <p class="text-[11px] text-gray-600 leading-tight">
                                        Your customer profile is auto-linked and you are redirected to the customer portal.
                                    </p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-xl border border-gray-200 space-y-1">
                                    <div class="text-gray-900 font-bold text-xs flex items-center gap-1.5">
                                        <span class="w-4 h-4 rounded-full bg-black text-white flex items-center justify-center text-[10px]">3</span>
                                        Borrow & Repay
                                    </div>
                                    <p class="text-[11px] text-gray-600 leading-tight">
                                        Apply for loan products and make repayments anytime via M-Pesa STK push.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Authentication Card (Login / Register Tabs) -->
                <div class="lg:col-span-5">
                    <div class="bg-white rounded-2xl border border-gray-300 p-6 sm:p-7 shadow-sm space-y-6">
                        
                        <!-- Toggle Switch -->
                        <div class="grid grid-cols-2 p-1 rounded-xl bg-gray-100 border border-gray-300">
                            <button
                                type="button"
                                @click="activeTab = 'login'"
                                :class="activeTab === 'login' ? 'bg-white text-black font-bold shadow-sm' : 'text-gray-600 hover:text-black font-medium'"
                                class="py-2 text-xs sm:text-sm rounded-lg transition text-center"
                            >
                                Log In
                            </button>
                            <button
                                type="button"
                                @click="activeTab = 'register'"
                                :class="activeTab === 'register' ? 'bg-white text-black font-bold shadow-sm' : 'text-gray-600 hover:text-black font-medium'"
                                class="py-2 text-xs sm:text-sm rounded-lg transition text-center"
                            >
                                Register
                            </button>
                        </div>

                        <!-- Status Message -->
                        <div v-if="status" class="rounded-xl bg-gray-100 p-3 text-xs sm:text-sm font-medium text-black border border-gray-300">
                            {{ status }}
                        </div>

                        <!-- 1. LOGIN FORM -->
                        <form v-if="activeTab === 'login'" @submit.prevent="submitLogin" class="space-y-4">
                            <div>
                                <InputLabel for="login" value="Email / Phone / National ID" class="text-gray-700 text-xs font-semibold uppercase tracking-wider" />
                                <TextInput
                                    id="login"
                                    type="text"
                                    class="mt-1 block w-full bg-white border-gray-300 text-gray-900 placeholder-gray-400 focus:border-black focus:ring-black rounded-lg text-sm"
                                    v-model="loginForm.login"
                                    required
                                    autofocus
                                    placeholder="e.g. admin@example.com or 0712345678"
                                    autocomplete="username"
                                />
                                <InputError class="mt-1.5" :message="loginForm.errors.login || loginForm.errors.email" />
                            </div>

                            <div>
                                <InputLabel for="login_password" value="Password" class="text-gray-700 text-xs font-semibold uppercase tracking-wider" />
                                <TextInput
                                    id="login_password"
                                    type="password"
                                    class="mt-1 block w-full bg-white border-gray-300 text-gray-900 placeholder-gray-400 focus:border-black focus:ring-black rounded-lg text-sm"
                                    v-model="loginForm.password"
                                    required
                                    placeholder="••••••••"
                                    autocomplete="current-password"
                                />
                                <InputError class="mt-1.5" :message="loginForm.errors.password" />
                            </div>

                            <div class="flex items-center justify-between pt-1">
                                <label class="flex items-center cursor-pointer">
                                    <Checkbox name="remember" v-model:checked="loginForm.remember" class="border-gray-300 bg-white text-black focus:ring-black rounded" />
                                    <span class="ms-2 text-xs text-gray-600">Remember me</span>
                                </label>

                                <Link
                                    v-if="canResetPassword"
                                    :href="route('password.request')"
                                    class="text-xs text-gray-600 hover:text-black underline transition"
                                >
                                    Forgot password?
                                </Link>
                            </div>

                            <div class="pt-2">
                                <button
                                    type="submit"
                                    class="w-full inline-flex justify-center items-center py-2.5 px-4 rounded-lg font-bold text-sm text-white bg-black hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2 focus:ring-offset-white transition shadow-sm uppercase tracking-wider"
                                    :class="{ 'opacity-50 cursor-not-allowed': loginForm.processing }"
                                    :disabled="loginForm.processing"
                                >
                                    {{ loginForm.processing ? 'Signing in...' : 'Sign In' }}
                                </button>
                            </div>
                        </form>

                        <!-- 2. REGISTER FORM -->
                        <form v-else-if="activeTab === 'register'" @submit.prevent="submitRegister" class="space-y-3.5">
                            <div>
                                <InputLabel for="register_name" value="Full Name" class="text-gray-700 text-xs font-semibold uppercase tracking-wider" />
                                <TextInput
                                    id="register_name"
                                    type="text"
                                    class="mt-1 block w-full bg-white border-gray-300 text-gray-900 placeholder-gray-400 focus:border-black focus:ring-black rounded-lg text-sm"
                                    v-model="registerForm.name"
                                    required
                                    autofocus
                                    placeholder="e.g. Raymond Mudibo"
                                    autocomplete="name"
                                />
                                <InputError class="mt-1.5" :message="registerForm.errors.name" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <InputLabel for="register_phone" value="M-Pesa Phone Number" class="text-gray-700 text-xs font-semibold uppercase tracking-wider" />
                                    <TextInput
                                        id="register_phone"
                                        type="text"
                                        class="mt-1 block w-full bg-white border-gray-300 text-gray-900 placeholder-gray-400 focus:border-black focus:ring-black rounded-lg text-sm"
                                        v-model="registerForm.phone_number"
                                        required
                                        placeholder="0712345678"
                                    />
                                    <InputError class="mt-1.5" :message="registerForm.errors.phone_number" />
                                </div>

                                <div>
                                    <InputLabel for="register_id" value="National ID Number" class="text-gray-700 text-xs font-semibold uppercase tracking-wider" />
                                    <TextInput
                                        id="register_id"
                                        type="text"
                                        class="mt-1 block w-full bg-white border-gray-300 text-gray-900 placeholder-gray-400 focus:border-black focus:ring-black rounded-lg text-sm"
                                        v-model="registerForm.id_number"
                                        required
                                        placeholder="e.g. 33445566"
                                    />
                                    <InputError class="mt-1.5" :message="registerForm.errors.id_number" />
                                </div>
                            </div>

                            <div>
                                <InputLabel for="register_email" value="Email Address (Optional)" class="text-gray-700 text-xs font-semibold uppercase tracking-wider" />
                                <TextInput
                                    id="register_email"
                                    type="email"
                                    class="mt-1 block w-full bg-white border-gray-300 text-gray-900 placeholder-gray-400 focus:border-black focus:ring-black rounded-lg text-sm"
                                    v-model="registerForm.email"
                                    placeholder="name@example.com"
                                    autocomplete="email"
                                />
                                <InputError class="mt-1.5" :message="registerForm.errors.email" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <InputLabel for="register_password" value="Password" class="text-gray-700 text-xs font-semibold uppercase tracking-wider" />
                                    <TextInput
                                        id="register_password"
                                        type="password"
                                        class="mt-1 block w-full bg-white border-gray-300 text-gray-900 placeholder-gray-400 focus:border-black focus:ring-black rounded-lg text-sm"
                                        v-model="registerForm.password"
                                        required
                                        placeholder="••••••••"
                                        autocomplete="new-password"
                                    />
                                    <InputError class="mt-1.5" :message="registerForm.errors.password" />
                                </div>

                                <div>
                                    <InputLabel for="register_password_confirmation" value="Confirm Password" class="text-gray-700 text-xs font-semibold uppercase tracking-wider" />
                                    <TextInput
                                        id="register_password_confirmation"
                                        type="password"
                                        class="mt-1 block w-full bg-white border-gray-300 text-gray-900 placeholder-gray-400 focus:border-black focus:ring-black rounded-lg text-sm"
                                        v-model="registerForm.password_confirmation"
                                        required
                                        placeholder="••••••••"
                                        autocomplete="new-password"
                                    />
                                    <InputError class="mt-1.5" :message="registerForm.errors.password_confirmation" />
                                </div>
                            </div>

                            <div class="pt-2">
                                <button
                                    type="submit"
                                    class="w-full inline-flex justify-center items-center py-2.5 px-4 rounded-lg font-bold text-sm text-white bg-black hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2 focus:ring-offset-white transition shadow-sm uppercase tracking-wider"
                                    :class="{ 'opacity-50 cursor-not-allowed': registerForm.processing }"
                                    :disabled="registerForm.processing"
                                >
                                    {{ registerForm.processing ? 'Creating account...' : 'Create Customer Account' }}
                                </button>
                            </div>
                        </form>

                    </div>
                </div>

            </div>
        </div>
    </div>
</template>



