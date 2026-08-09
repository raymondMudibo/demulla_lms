<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    loans: Array,
    totalOutstanding: Number,
    products: Array,
});

const isModalOpen = ref(false);

const form = useForm({
    loan_product_id: '',
    principal_amount: '',
});

const submit = () => {
    form.post(route('portal.loans.store'), {
        onSuccess: () => {
            isModalOpen.value = false;
            form.reset();
        }
    });
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
</script>

<template>
    <Head title="Customer Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm font-semibold text-indigo-600 uppercase tracking-wider font-mono">Customer Portal</span>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        My Dashboard
                    </h2>
                </div>
                <button
                    @click="isModalOpen = true"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                >
                    Apply for a Loan
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-8">
                
                <!-- Flash Messages -->
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

                <!-- Metrics Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="overflow-hidden bg-white shadow rounded-lg border border-gray-100 p-6 flex flex-col justify-between">
                        <div>
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Outstanding Balance</span>
                            <div class="text-3xl font-black text-indigo-950 mt-2">
                                KES {{ totalOutstanding.toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-4">Calculated across all active loans in repayments.</p>
                    </div>

                    <div class="overflow-hidden bg-white shadow rounded-lg border border-gray-100 p-6 flex flex-col justify-between">
                        <div>
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">My Active Applications</span>
                            <div class="text-3xl font-black text-gray-900 mt-2">
                                {{ loans.filter(l => l.status !== 'closed' && l.status !== 'rejected').length }}
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-4">Includes pending review, approved, and active schedules.</p>
                    </div>
                </div>

                <!-- My Loans Table -->
                <div class="overflow-hidden bg-white shadow rounded-lg border border-gray-100">
                    <div class="border-b border-gray-200 bg-white px-6 py-4">
                        <h3 class="text-lg font-bold text-gray-900">My Loan History</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Account</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Loan Product</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Principal</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Total Repayable</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Balance</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                                    <th scope="col" class="relative px-6 py-3">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="loan in loans" :key="loan.id" class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-6 py-4 font-mono text-sm text-gray-900">
                                        {{ loan.loan_account_number }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                        {{ loan.loan_product.name }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-900">
                                        KES {{ parseFloat(loan.principal_amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-900">
                                        KES {{ parseFloat(loan.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-bold text-gray-950">
                                        KES {{ parseFloat(loan.balance).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span
                                            :class="getStatusBadgeClass(loan.status)"
                                            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold uppercase"
                                        >
                                            {{ loan.status }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-bold">
                                        <Link
                                            :href="route('portal.loans.show', loan.id)"
                                            class="text-indigo-600 hover:text-indigo-900"
                                        >
                                            Workspace
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="loans.length === 0">
                                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">
                                        You have not applied for any loans yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- Apply for Loan Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="isModalOpen = false"></div>

                <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                    <form @submit.prevent="submit">
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-bold leading-6 text-gray-900 mb-6">
                                Apply for a New Loan
                            </h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label for="loan_product_id" class="block text-sm font-medium text-gray-700">Select Loan Product</label>
                                    <select
                                        v-model="form.loan_product_id"
                                        id="loan_product_id"
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option value="" disabled>-- Select Product Option --</option>
                                        <option v-for="p in products" :key="p.id" :value="p.id">
                                            {{ p.name }} ({{ p.interest_rate }}% {{ p.interest_type === 'flat' ? 'Flat' : 'Reducing' }} - {{ p.term_length }} {{ p.term_unit }})
                                        </option>
                                    </select>
                                    <p v-if="form.errors.loan_product_id" class="mt-1 text-xs text-red-600">{{ form.errors.loan_product_id }}</p>
                                </div>

                                <div>
                                    <label for="principal_amount" class="block text-sm font-medium text-gray-700">Principal Amount (KES)</label>
                                    <input
                                        type="number"
                                        v-model="form.principal_amount"
                                        id="principal_amount"
                                        placeholder="e.g. 5000"
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    />
                                    <p v-if="form.errors.principal_amount" class="mt-1 text-xs text-red-600">{{ form.errors.principal_amount }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex w-full justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-indigo-500 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ form.processing ? 'Submitting...' : 'Apply' }}
                            </button>
                            <button
                                type="button"
                                @click="isModalOpen = false"
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
