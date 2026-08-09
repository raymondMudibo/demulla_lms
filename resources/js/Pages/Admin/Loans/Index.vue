<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    loans: Array,
});

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
    <Head title="Admin - Loans Overview" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold leading-tight text-gray-900">
                Loan Applications Overview
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                
                <div class="overflow-hidden bg-white shadow rounded-lg border border-gray-100">
                    <div class="border-b border-gray-200 bg-white px-6 py-4">
                        <p class="text-sm text-gray-500">Monitor and process all loan submissions and active portfolio ledgers.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Account</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Borrower</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Product</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Principal</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Repayable</th>
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
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm font-semibold text-gray-900">{{ loan.customer.name }}</div>
                                        <div class="text-xs text-gray-500">+{{ loan.customer.phone_number }}</div>
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
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-semibold">
                                        <Link
                                            :href="route('admin.loans.show', loan.id)"
                                            class="text-indigo-600 hover:text-indigo-900"
                                        >
                                            Manage Account
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="loans.length === 0">
                                    <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500">
                                        No loan applications submitted yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
