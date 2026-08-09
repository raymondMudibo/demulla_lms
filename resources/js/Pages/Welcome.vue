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
</script>

<template>
    <Head title="Mini Loan Management System" />

    <div class="min-h-screen flex flex-col items-center justify-center bg-gray-100 px-4 sm:px-6 lg:px-8 py-12">
        <div class="w-full sm:max-w-md space-y-6">
            <!-- Title Header -->
            <div class="text-center space-y-1">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">
                    Mini Loan Management System
                </h1>
                <p class="text-sm text-gray-600">
                    M-Pesa Integrated Payouts & Repayments
                </p>
            </div>

            <!-- If Already Logged In -->
            <div v-if="$page.props.auth?.user" class="overflow-hidden bg-white px-6 py-6 shadow-md sm:rounded-lg border border-gray-200 text-center space-y-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Welcome back, {{ $page.props.auth.user.name }}</h3>
                    <p class="text-xs text-gray-500 mt-1">You are currently logged in as <span class="font-medium text-gray-900 capitalize">{{ $page.props.auth.user.role || 'customer' }}</span>.</p>
                </div>
                <Link
                    :href="route('dashboard')"
                    class="w-full inline-flex justify-center items-center rounded-md border border-transparent bg-gray-800 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700 focus:bg-gray-700 transition"
                >
                    Go to Dashboard
                </Link>
            </div>

            <!-- Auth Form Card with Toggle -->
            <div v-else class="overflow-hidden bg-white px-6 py-6 shadow-md sm:rounded-lg border border-gray-200 space-y-6">
                <!-- Toggle Switch -->
                <div class="grid grid-cols-2 p-1 rounded-lg bg-gray-100 border border-gray-200">
                    <button
                        type="button"
                        @click="activeTab = 'login'"
                        :class="activeTab === 'login' ? 'bg-white text-gray-900 font-semibold shadow-sm' : 'text-gray-600 hover:text-gray-900 font-medium'"
                        class="py-2 text-xs sm:text-sm rounded-md transition text-center"
                    >
                        Log in
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'register'"
                        :class="activeTab === 'register' ? 'bg-white text-gray-900 font-semibold shadow-sm' : 'text-gray-600 hover:text-gray-900 font-medium'"
                        class="py-2 text-xs sm:text-sm rounded-md transition text-center"
                    >
                        Register
                    </button>
                </div>

                <!-- Flash Status -->
                <div v-if="status" class="rounded-md bg-green-50 p-3 text-sm font-medium text-green-700 border border-green-200">
                    {{ status }}
                </div>

                <!-- 1. LOGIN TAB -->
                <form v-if="activeTab === 'login'" @submit.prevent="submitLogin" class="space-y-4">
                    <div>
                        <InputLabel for="login" value="Phone Number / Email / ID Number" />
                        <TextInput
                            id="login"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="loginForm.login"
                            required
                            autofocus
                            placeholder="e.g. 0712345678, ID or Email"
                            autocomplete="username"
                        />
                        <InputError class="mt-2" :message="loginForm.errors.login || loginForm.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="login_password" value="Password" />
                        <TextInput
                            id="login_password"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="loginForm.password"
                            required
                            autocomplete="current-password"
                        />
                        <InputError class="mt-2" :message="loginForm.errors.password" />
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center cursor-pointer">
                            <Checkbox name="remember" v-model:checked="loginForm.remember" />
                            <span class="ms-2 text-sm text-gray-600">Remember me</span>
                        </label>

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-xs text-gray-600 underline hover:text-gray-900"
                        >
                            Forgot password?
                        </Link>
                    </div>

                    <div class="pt-2">
                        <PrimaryButton
                            class="w-full justify-center py-2.5 text-center"
                            :class="{ 'opacity-25': loginForm.processing }"
                            :disabled="loginForm.processing"
                        >
                            {{ loginForm.processing ? 'Logging in...' : 'Log in' }}
                        </PrimaryButton>
                    </div>
                </form>

                <!-- 2. REGISTER TAB -->
                <form v-else-if="activeTab === 'register'" @submit.prevent="submitRegister" class="space-y-4">
                    <div>
                        <InputLabel for="register_name" value="Name" />
                        <TextInput
                            id="register_name"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="registerForm.name"
                            required
                            autofocus
                            placeholder="Full Name"
                            autocomplete="name"
                        />
                        <InputError class="mt-2" :message="registerForm.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="register_phone" value="Phone Number" />
                        <TextInput
                            id="register_phone"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="registerForm.phone_number"
                            required
                            placeholder="e.g. 0712345678"
                        />
                        <InputError class="mt-2" :message="registerForm.errors.phone_number" />
                    </div>

                    <div>
                        <InputLabel for="register_id" value="ID Number" />
                        <TextInput
                            id="register_id"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="registerForm.id_number"
                            required
                            placeholder="National ID"
                        />
                        <InputError class="mt-2" :message="registerForm.errors.id_number" />
                    </div>

                    <div>
                        <InputLabel for="register_email" value="Email (Optional)" />
                        <TextInput
                            id="register_email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="registerForm.email"
                            placeholder="name@example.com"
                            autocomplete="email"
                        />
                        <InputError class="mt-2" :message="registerForm.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="register_password" value="Password" />
                        <TextInput
                            id="register_password"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="registerForm.password"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-2" :message="registerForm.errors.password" />
                    </div>

                    <div>
                        <InputLabel for="register_password_confirmation" value="Confirm Password" />
                        <TextInput
                            id="register_password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="registerForm.password_confirmation"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-2" :message="registerForm.errors.password_confirmation" />
                    </div>

                    <div class="pt-2">
                        <PrimaryButton
                            class="w-full justify-center py-2.5 text-center"
                            :class="{ 'opacity-25': registerForm.processing }"
                            :disabled="registerForm.processing"
                        >
                            {{ registerForm.processing ? 'Registering...' : 'Register' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
