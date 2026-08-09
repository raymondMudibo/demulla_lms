<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    loan: Object,
});

const isRepayModalOpen = ref(false);
const repayForm = useForm({
    amount: '',
    phone_number: props.loan.customer.phone_number,
});

const handleRepaySubmit = () => {
    repayForm.post(route('portal.loans.repay', props.loan.id), {
        onSuccess: () => {
            isRepayModalOpen.value = false;
            repayForm.amount = '';
        }
    });
};

// Simulation forms
const simulateStkForm = useForm({ status: 'success' });

const triggerStkSimulation = (checkoutRef, status) => {
    simulateStkForm.status = status;
    simulateStkForm.post(route('api.daraja.simulate-stk', checkoutRef));
};

const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'pending':
            return 'bg-yellow-50 text-yellow-800 border-yellow-200';
        case 'approved':
            return 'bg-blue-50 text-blue-800 border-blue-200';
        case 'disbursed':
            return 'bg-purple-50 text-purple-800 border-purple-200';
        case 'active':
            return 'bg-green-50 text-green-800 border-green-200';
        case 'closed':
            return 'bg-gray-50 text-gray-800 border-gray-200';
        case 'rejected':
            return 'bg-red-50 text-red-800 border-red-200';
        default:
            return 'bg-gray-50 text-gray-800 border-gray-200';
    }
};

const getInstallmentStatusClass = (status) => {
    switch (status) {
        case 'pending':
            return 'bg-gray-50 text-gray-700 border-gray-200';
        case 'partially_paid':
            return 'bg-yellow-50 text-yellow-700 border-yellow-200';
        case 'paid':
            return 'bg-green-50 text-green-700 border-green-200';
        case 'overdue':
            return 'bg-red-50 text-red-700 border-red-200';
        default:
            return 'bg-gray-50 text-gray-700 border-gray-200';
    }
};
</script>

<template>
    <Head :title="`Loan Workspace - #${loan.loan_account_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm font-semibold text-indigo-600 uppercase tracking-wider font-mono">Workspace</span>
                    <h2 class="text-xl font-bold leading-tight text-gray-900 flex items-center gap-3">
                        Loan #{{ loan.loan_account_number }}
                        <span
                            :class="getStatusBadgeClass(loan.status)"
                            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold uppercase"
                        >
                            {{ loan.status }}
                        </span>
                    </h2>
                </div>
                <div>
                    <Link
                        :href="route('portal.dashboard')"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition"
                    >
                        Back to Dashboard
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-8">
                
                <!-- Notification Banner -->
                <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 p-4 border border-green-200">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-semibold text-green-800">{{ $page.props.flash?.success }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="$page.props.flash?.error" class="rounded-md bg-red-50 p-4 border border-red-200">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v4a1 1 0 002 0V7zm-1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-semibold text-red-800">{{ $page.props.flash?.error }}</p>
                        </div>
                    </div>
                </div>

                <!-- Main Layout Columns -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Left: Details & Payment Trigger -->
                    <div class="lg:col-span-1 space-y-6">
                        
                        <!-- Profile Card -->
                        <div class="overflow-hidden bg-white shadow rounded-lg border border-gray-100 p-6">
                            <h3 class="text-lg font-bold text-gray-900 border-b border-gray-100 pb-4 mb-4">Loan Details</h3>
                            <dl class="space-y-4 text-sm">
                                <div>
                                    <dt class="text-gray-500 font-medium">Borrower Profile</dt>
                                    <dd class="text-gray-950 font-bold mt-1">{{ loan.customer.name }}</dd>
                                    <dd class="text-xs text-gray-500 font-mono mt-0.5">Phone: +{{ loan.customer.phone_number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 font-medium">Product Option</dt>
                                    <dd class="text-gray-900 font-semibold mt-1">{{ loan.loan_product.name }}</dd>
                                    <dd class="text-xs text-gray-500 mt-0.5">
                                        {{ loan.loan_product.interest_rate }}% {{ loan.loan_product.interest_type === 'flat' ? 'Flat' : 'Reducing' }} Rate
                                    </dd>
                                </div>
                                <div class="border-t border-gray-100 pt-4">
                                    <dt class="text-gray-500 font-medium">Principal Amount</dt>
                                    <dd class="text-lg font-bold text-gray-900 mt-1">KES {{ parseFloat(loan.principal_amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 font-medium">Calculated Interest</dt>
                                    <dd class="text-base font-semibold text-gray-900 mt-1">KES {{ parseFloat(loan.interest_amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 font-medium">Total Balance Due</dt>
                                    <dd class="text-base font-semibold text-gray-900 mt-1">KES {{ parseFloat(loan.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }}</dd>
                                </div>
                                <div class="border-t border-gray-100 pt-4 bg-indigo-50/50 p-4 rounded-lg">
                                    <dt class="text-indigo-700 font-bold">Outstanding Ledger Balance</dt>
                                    <dd class="text-2xl font-black text-indigo-950 mt-1">KES {{ parseFloat(loan.balance).toLocaleString(undefined, {minimumFractionDigits: 2}) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Actions Panel -->
                        <div class="overflow-hidden bg-white shadow rounded-lg border border-gray-100 p-6">
                            <h3 class="text-lg font-bold text-gray-900 border-b border-gray-100 pb-4 mb-4">Repayment Console</h3>
                            
                            <div class="space-y-4">
                                <div v-if="loan.status === 'active'">
                                    <p class="text-xs text-gray-500 mb-3">Pay loan installments in real time by triggering M-Pesa STK push directly to your mobile phone.</p>
                                    <button
                                        @click="isRepayModalOpen = true"
                                        class="w-full justify-center inline-flex items-center rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-500 transition"
                                    >
                                        Repay via M-Pesa STK Push
                                    </button>
                                </div>

                                <div v-if="loan.status === 'pending'" class="rounded-md bg-yellow-50 border border-yellow-200 p-4 text-center">
                                    <p class="text-sm font-bold text-yellow-800">Pending Review</p>
                                    <p class="text-xs text-yellow-600 mt-1">This application is awaiting administrator validation and approval.</p>
                                </div>

                                <div v-if="loan.status === 'approved'" class="rounded-md bg-blue-50 border border-blue-200 p-4 text-center">
                                    <p class="text-sm font-bold text-blue-800">Awaiting Disbursement</p>
                                    <p class="text-xs text-blue-600 mt-1">This application is approved and is awaiting fund payout disbursement.</p>
                                </div>

                                <div v-if="loan.status === 'closed'" class="rounded-md bg-green-50 border border-green-200 p-4 text-center">
                                    <p class="text-sm font-bold text-green-800">Fully Repaid & Closed</p>
                                </div>
                            </div>
                        </div>

                        <!-- STK webhook Simulation -->
                        <div class="overflow-hidden bg-gray-50 shadow rounded-lg border border-dashed border-gray-300 p-6">
                            <h3 class="text-base font-bold text-gray-900 border-b border-gray-200 pb-3 mb-4">M-Pesa Webhook Sandbox</h3>
                            <div class="space-y-4">
                                <div v-if="loan.stk_requests.some(s => s.status === 'pending')">
                                    <p class="text-xs text-gray-500 mb-2">Simulate Safaricom STK Push payment callback results for your pending checkout request.</p>
                                    <div class="grid grid-cols-3 gap-2">
                                        <button
                                            @click="triggerStkSimulation(loan.stk_requests.find(s => s.status === 'pending').checkout_reference, 'success')"
                                            class="text-center bg-green-100 text-green-800 border border-green-300 rounded py-1 text-xs font-semibold hover:bg-green-200 transition"
                                        >
                                            Success
                                        </button>
                                        <button
                                            @click="triggerStkSimulation(loan.stk_requests.find(s => s.status === 'pending').checkout_reference, 'mismatch')"
                                            class="text-center bg-yellow-100 text-yellow-800 border border-yellow-300 rounded py-1 text-xs font-semibold hover:bg-yellow-200 transition"
                                        >
                                            Mismatch
                                        </button>
                                        <button
                                            @click="triggerStkSimulation(loan.stk_requests.find(s => s.status === 'pending').checkout_reference, 'cancelled')"
                                            class="text-center bg-red-100 text-red-800 border border-red-300 rounded py-1 text-xs font-semibold hover:bg-red-200 transition"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>

                                <div v-else>
                                    <p class="text-xs text-gray-500 text-center italic">No pending STK Push payments awaiting callback.</p>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Right: Repayment Schedule Table & Transaction History Logs -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- Installment Table Schedule -->
                        <div class="overflow-hidden bg-white shadow rounded-lg border border-gray-100 p-6">
                            <h3 class="text-lg font-bold text-gray-900 border-b border-gray-100 pb-4 mb-4">Repayment Schedule</h3>
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-600">No.</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Due Date</th>
                                            <th class="px-4 py-3 text-right font-semibold text-gray-600">Principal</th>
                                            <th class="px-4 py-3 text-right font-semibold text-gray-600">Interest</th>
                                            <th class="px-4 py-3 text-right font-semibold text-gray-600">Installment</th>
                                            <th class="px-4 py-3 text-right font-semibold text-gray-600">Paid</th>
                                            <th class="px-4 py-3 text-center font-semibold text-gray-600">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="installment in loan.installments" :key="installment.id" class="hover:bg-gray-50">
                                            <td class="px-4 py-3 text-gray-900 font-semibold">{{ installment.installment_number }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ new Date(installment.due_date).toLocaleDateString() }}</td>
                                            <td class="px-4 py-3 text-right text-gray-900 font-mono">KES {{ parseFloat(installment.principal_amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-900 font-mono">KES {{ parseFloat(installment.interest_amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-900 font-semibold font-mono">KES {{ parseFloat(installment.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }}</td>
                                            <td class="px-4 py-3 text-right text-green-700 font-bold font-mono">KES {{ parseFloat(installment.amount_paid).toLocaleString(undefined, {minimumFractionDigits: 2}) }}</td>
                                            <td class="px-4 py-3 text-center">
                                                <span
                                                    :class="getInstallmentStatusClass(installment.status)"
                                                    class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold uppercase"
                                                >
                                                    {{ installment.status }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr v-if="loan.installments.length === 0">
                                            <td colspan="7" class="px-4 py-8 text-center text-gray-400 italic">
                                                Schedule will be activated and generated upon successful disbursement of funds.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Customer Transaction Log -->
                        <div class="overflow-hidden bg-white shadow rounded-lg border border-gray-100 p-6 space-y-6">
                            <h3 class="text-lg font-bold text-gray-900 border-b border-gray-100 pb-4 mb-2">My Repayment History</h3>
                            
                            <div>
                                <div class="overflow-x-auto border border-gray-100 rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-500">M-Pesa Receipt</th>
                                                <th class="px-3 py-2 text-right font-semibold text-gray-500">Amount Paid</th>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-500">Paid At</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            <tr v-for="p in loan.payments" :key="p.id">
                                                <td class="px-3 py-2 font-mono text-gray-900 font-bold">{{ p.mpesa_receipt_number }}</td>
                                                <td class="px-3 py-2 text-right text-green-700 font-bold">KES {{ parseFloat(p.amount_paid).toLocaleString() }}</td>
                                                <td class="px-3 py-2 text-gray-500">{{ new Date(p.paid_at).toLocaleString() }}</td>
                                            </tr>
                                            <tr v-if="loan.payments.length === 0">
                                                <td colspan="3" class="px-3 py-4 text-center text-gray-400 italic">No payments processed yet.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>

        <!-- Repayment STK Modal -->
        <div v-if="isRepayModalOpen" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="isRepayModalOpen = false"></div>

                <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                    <form @submit.prevent="handleRepaySubmit">
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-bold leading-6 text-gray-900 mb-6">
                                Repay Loan via M-Pesa STK Push
                            </h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label for="amount" class="block text-sm font-medium text-gray-700">Payment Amount (KES)</label>
                                    <input
                                        type="number"
                                        v-model="repayForm.amount"
                                        id="amount"
                                        placeholder="e.g. 1000"
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    />
                                    <p v-if="repayForm.errors.amount" class="mt-1 text-xs text-red-600">{{ repayForm.errors.amount }}</p>
                                </div>

                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700">M-Pesa Payer Mobile Number</label>
                                    <input
                                        type="text"
                                        v-model="repayForm.phone_number"
                                        id="phone"
                                        placeholder="e.g. 0712345678"
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    />
                                    <p v-if="repayForm.errors.phone_number" class="mt-1 text-xs text-red-600">{{ repayForm.errors.phone_number }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button
                                type="submit"
                                :disabled="repayForm.processing"
                                class="inline-flex w-full justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ repayForm.processing ? 'Initiating STK Push...' : 'Send STK Push' }}
                            </button>
                            <button
                                type="button"
                                @click="isRepayModalOpen = false"
                                class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
