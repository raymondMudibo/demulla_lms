<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    loan: Object,
});

const approveForm = useForm({});
const rejectForm = useForm({});
const disburseForm = useForm({});

const handleApprove = () => {
    approveForm.post(route('admin.loans.approve', props.loan.id));
};

const handleReject = () => {
    rejectForm.post(route('admin.loans.reject', props.loan.id));
};

const handleDisburse = () => {
    disburseForm.post(route('admin.loans.disburse', props.loan.id));
};

// Simulation forms
const simulateB2cForm = useForm({ status: 'success' });
const simulateStkForm = useForm({ status: 'success' });

const triggerB2cSimulation = (refId, status) => {
    simulateB2cForm.status = status;
    simulateB2cForm.post(route('api.daraja.simulate-b2c', refId));
};

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
    <Head :title="`Admin Workspace - #${loan.loan_account_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm font-semibold text-indigo-600 uppercase tracking-wider font-mono">Admin Control</span>
                    <h2 class="text-xl font-bold leading-tight text-gray-900 flex items-center gap-3">
                        Loan Account #{{ loan.loan_account_number }}
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
                        :href="route('admin.loans.index')"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition"
                    >
                        Back to Overview
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-8">
                
                <!-- Flash Notification Banner -->
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
                    
                    <!-- Left Control Panel -->
                    <div class="lg:col-span-1 space-y-6">
                        
                        <!-- Profile Card -->
                        <div class="overflow-hidden bg-white shadow rounded-lg border border-gray-100 p-6">
                            <h3 class="text-lg font-bold text-gray-900 border-b border-gray-100 pb-4 mb-4">Borrower Profile</h3>
                            <dl class="space-y-4 text-sm">
                                <div>
                                    <dt class="text-gray-500 font-medium">Customer</dt>
                                    <dd class="text-gray-950 font-bold mt-1 text-base">{{ loan.customer.name }}</dd>
                                    <dd class="text-xs text-gray-500 font-mono mt-0.5">Phone: +{{ loan.customer.phone_number }}</dd>
                                    <dd class="text-xs text-gray-500 mt-0.5">ID: {{ loan.customer.id_number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 font-medium">Loan Product</dt>
                                    <dd class="text-gray-900 font-semibold mt-1">{{ loan.loan_product.name }}</dd>
                                    <dd class="text-xs text-gray-500 mt-0.5 uppercase font-semibold">
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
                            <h3 class="text-lg font-bold text-gray-900 border-b border-gray-100 pb-4 mb-4">Verification & Payout</h3>
                            
                            <div class="space-y-4">
                                <!-- Approve / Reject Buttons -->
                                <div v-if="loan.status === 'pending'" class="space-y-3">
                                    <p class="text-xs text-gray-500">Approve this request to generate the repayment schedule. Rejecting marks it permanently rejected.</p>
                                    <button
                                        @click="handleApprove"
                                        :disabled="approveForm.processing"
                                        class="w-full justify-center inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                                    >
                                        {{ approveForm.processing ? 'Approving...' : 'Approve Application' }}
                                    </button>
                                    <button
                                        @click="handleReject"
                                        :disabled="rejectForm.processing"
                                        class="w-full justify-center inline-flex items-center rounded-lg bg-red-50 text-red-700 border border-red-200 px-4 py-2.5 text-sm font-semibold hover:bg-red-100 transition"
                                    >
                                        {{ rejectForm.processing ? 'Rejecting...' : 'Reject Request' }}
                                    </button>
                                </div>

                                <!-- Disburse Trigger -->
                                <div v-if="loan.status === 'approved'">
                                    <p class="text-xs text-gray-500 mb-2">Execute B2C payout. Funds will be transferred from corporate M-Pesa account directly to customer's mobile number.</p>
                                    <button
                                        @click="handleDisburse"
                                        :disabled="disburseForm.processing"
                                        class="w-full justify-center inline-flex items-center rounded-lg bg-purple-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-purple-500 transition"
                                    >
                                        {{ disburseForm.processing ? 'Initiating Payout...' : 'Disburse Payout (M-Pesa B2C)' }}
                                    </button>
                                </div>

                                <div v-if="loan.status === 'rejected'" class="rounded-md bg-red-50 border border-red-200 p-4 text-center">
                                    <p class="text-sm font-bold text-red-800">Application Rejected</p>
                                </div>

                                <div v-if="loan.status === 'closed'" class="rounded-md bg-green-50 border border-green-200 p-4 text-center">
                                    <p class="text-sm font-bold text-green-800">Closed & Fully Repaid</p>
                                </div>

                                <div v-if="loan.status === 'active' || loan.status === 'disbursed'" class="rounded-md bg-blue-50 border border-blue-200 p-4 text-center">
                                    <p class="text-sm font-bold text-blue-800">Active Repayment Schedule</p>
                                </div>
                            </div>
                        </div>

                        <!-- Webhook Callback Simulation Panel -->
                        <div class="overflow-hidden bg-gray-50 shadow rounded-lg border border-dashed border-gray-300 p-6">
                            <h3 class="text-base font-bold text-gray-900 border-b border-gray-200 pb-3 mb-4">Developer Webhook Sandbox</h3>
                            <div class="space-y-4">
                                
                                <!-- B2C Simulation -->
                                <div v-if="loan.disbursements.some(d => d.status === 'initiated')">
                                    <h4 class="text-xs font-bold text-gray-700 uppercase mb-2">Simulate Disbursement Result</h4>
                                    <div class="flex gap-2">
                                        <button
                                            @click="triggerB2cSimulation(loan.disbursements.find(d => d.status === 'initiated').reference, 'success')"
                                            class="flex-1 text-center bg-green-100 text-green-800 border border-green-300 rounded py-1.5 text-xs font-semibold hover:bg-green-200 transition"
                                        >
                                            Payout Success
                                        </button>
                                        <button
                                            @click="triggerB2cSimulation(loan.disbursements.find(d => d.status === 'initiated').reference, 'failed')"
                                            class="flex-1 text-center bg-red-100 text-red-800 border border-red-300 rounded py-1.5 text-xs font-semibold hover:bg-red-200 transition"
                                        >
                                            Payout Fail
                                        </button>
                                    </div>
                                </div>

                                <!-- STK Simulation -->
                                <div v-if="loan.stk_requests.some(s => s.status === 'pending')">
                                    <h4 class="text-xs font-bold text-gray-700 uppercase mb-2">Simulate STK Repayment Callback</h4>
                                    <div class="grid grid-cols-3 gap-2">
                                        <button
                                            @click="triggerStkSimulation(loan.stk_requests.find(s => s.status === 'pending').checkout_reference, 'success')"
                                            class="text-center bg-green-100 text-green-800 border border-green-300 rounded py-1.5 text-xs font-semibold hover:bg-green-200 transition"
                                        >
                                            Success
                                        </button>
                                        <button
                                            @click="triggerStkSimulation(loan.stk_requests.find(s => s.status === 'pending').checkout_reference, 'mismatch')"
                                            class="text-center bg-yellow-100 text-yellow-800 border border-yellow-300 rounded py-1.5 text-xs font-semibold hover:bg-yellow-200 transition"
                                        >
                                            Mismatch
                                        </button>
                                        <button
                                            @click="triggerStkSimulation(loan.stk_requests.find(s => s.status === 'pending').checkout_reference, 'cancelled')"
                                            class="text-center bg-red-100 text-red-800 border border-red-300 rounded py-1.5 text-xs font-semibold hover:bg-red-200 transition"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>

                                <div v-if="!loan.disbursements.some(d => d.status === 'initiated') && !loan.stk_requests.some(s => s.status === 'pending')">
                                    <p class="text-xs text-gray-500 text-center italic">No active payouts or repayment requests awaiting callbacks.</p>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column: Table Schedule & Transaction Logs -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- Installments Schedule Table -->
                        <div class="overflow-hidden bg-white shadow rounded-lg border border-gray-100 p-6">
                            <h3 class="text-lg font-bold text-gray-900 border-b border-gray-100 pb-4 mb-4">Payment Installments Schedule</h3>
                            
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
                                            <td class="px-4 py-3 text-right text-gray-900">KES {{ parseFloat(installment.principal_amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-900">KES {{ parseFloat(installment.interest_amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-900 font-semibold">KES {{ parseFloat(installment.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }}</td>
                                            <td class="px-4 py-3 text-right text-green-700 font-bold">KES {{ parseFloat(installment.amount_paid).toLocaleString(undefined, {minimumFractionDigits: 2}) }}</td>
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
                                                No installments schedule generated. App and disburse loan to activate schedule.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- System Audit Transactions Log -->
                        <div class="overflow-hidden bg-white shadow rounded-lg border border-gray-100 p-6 space-y-6">
                            <h3 class="text-lg font-bold text-gray-900 border-b border-gray-100 pb-4 mb-2">Audit Ledger Transactions</h3>
                            
                            <!-- B2C Payouts -->
                            <div>
                                <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wide mb-2">Disbursement Payments</h4>
                                <div class="overflow-x-auto border border-gray-100 rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-500">Ref</th>
                                                <th class="px-3 py-2 text-right font-semibold text-gray-500">Amount</th>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-500">M-Pesa Receipt</th>
                                                <th class="px-3 py-2 text-center font-semibold text-gray-500">Status</th>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-500">Disbursed At</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            <tr v-for="d in loan.disbursements" :key="d.id">
                                                <td class="px-3 py-2 font-mono text-gray-500 truncate max-w-[120px]" :title="d.reference">{{ d.reference }}</td>
                                                <td class="px-3 py-2 text-right text-gray-900 font-semibold">KES {{ parseFloat(d.amount).toLocaleString() }}</td>
                                                <td class="px-3 py-2 text-gray-900 font-mono">{{ d.mpesa_receipt_number || '-' }}</td>
                                                <td class="px-3 py-2 text-center">
                                                    <span :class="d.status === 'successful' ? 'bg-green-100 text-green-800' : (d.status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800')" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase">
                                                        {{ d.status }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-gray-500">{{ d.disbursed_at ? new Date(d.disbursed_at).toLocaleString() : '-' }}</td>
                                            </tr>
                                            <tr v-if="loan.disbursements.length === 0">
                                                <td colspan="5" class="px-3 py-4 text-center text-gray-400 italic">No disbursement payout history logs.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- STK Requests -->
                            <div>
                                <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wide mb-2">STK Push Requests</h4>
                                <div class="overflow-x-auto border border-gray-100 rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-500">Checkout Reference</th>
                                                <th class="px-3 py-2 text-right font-semibold text-gray-500">Requested</th>
                                                <th class="px-3 py-2 text-center font-semibold text-gray-500">Status</th>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-500">Created</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            <tr v-for="stk in loan.stk_requests" :key="stk.id">
                                                <td class="px-3 py-2 font-mono text-gray-500 truncate max-w-[120px]" :title="stk.checkout_reference">{{ stk.checkout_reference }}</td>
                                                <td class="px-3 py-2 text-right text-gray-900 font-semibold">KES {{ parseFloat(stk.amount_requested).toLocaleString() }}</td>
                                                <td class="px-3 py-2 text-center">
                                                    <span :class="stk.status === 'completed' ? 'bg-green-100 text-green-800' : (stk.status === 'failed' || stk.status === 'cancelled' ? 'bg-red-100 text-red-800' : (stk.status === 'mismatched' ? 'bg-orange-100 text-orange-800' : 'bg-yellow-100 text-yellow-800'))" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase">
                                                        {{ stk.status }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2 text-gray-500">{{ new Date(stk.created_at).toLocaleString() }}</td>
                                            </tr>
                                            <tr v-if="loan.stk_requests.length === 0">
                                                <td colspan="4" class="px-3 py-4 text-center text-gray-400 italic">No STK requests submitted.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Reconciled payments -->
                            <div>
                                <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wide mb-2">Reconciled Cash Payments</h4>
                                <div class="overflow-x-auto border border-gray-100 rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-500">M-Pesa Receipt</th>
                                                <th class="px-3 py-2 text-right font-semibold text-gray-500">Amount Paid</th>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-500">Payer Phone</th>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-500">Paid At</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            <tr v-for="p in loan.payments" :key="p.id">
                                                <td class="px-3 py-2 font-mono text-gray-900 font-bold">{{ p.mpesa_receipt_number }}</td>
                                                <td class="px-3 py-2 text-right text-green-700 font-bold">KES {{ parseFloat(p.amount_paid).toLocaleString() }}</td>
                                                <td class="px-3 py-2 text-gray-600">+{{ p.payer_phone_number }}</td>
                                                <td class="px-3 py-2 text-gray-500">{{ new Date(p.paid_at).toLocaleString() }}</td>
                                            </tr>
                                            <tr v-if="loan.payments.length === 0">
                                                <td colspan="4" class="px-3 py-4 text-center text-gray-400 italic">No payments processed.</td>
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
    </AuthenticatedLayout>
</template>
