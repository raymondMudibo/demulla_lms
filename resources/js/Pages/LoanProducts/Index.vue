<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    products: Array
});

const isModalOpen = ref(false);

const form = useForm({
    name: '',
    interest_type: 'flat',
    interest_rate: '',
    term_length: '',
    term_unit: 'months',
    processing_fee: '0.00',
});

const submit = () => {
    form.post(route('loan-products.store'), {
        onSuccess: () => {
            isModalOpen.value = false;
            form.reset();
        }
    });
};
</script>

<template>
    <Head title="Loan Products" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Loan Products
                </h2>
                <button
                    @click="isModalOpen = true"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition"
                >
                    Add Product
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Flash Messages -->
                <div v-if="$page.props.flash?.success" class="mb-4 rounded-md bg-green-50 p-4 border border-green-200">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">
                                {{ $page.props.flash?.success }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="product in products"
                        :key="product.id"
                        class="overflow-hidden bg-white shadow rounded-lg border border-gray-100 flex flex-col justify-between"
                    >
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">{{ product.name }}</h3>
                                <span
                                    :class="product.interest_type === 'flat' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-purple-50 text-purple-700 border-purple-200'"
                                    class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                                >
                                    {{ product.interest_type === 'flat' ? 'Flat Rate' : 'Reducing Balance' }}
                                </span>
                            </div>
                            
                            <div class="mt-4 space-y-2 text-sm text-gray-600">
                                <div class="flex justify-between">
                                    <span>Interest Rate:</span>
                                    <span class="font-medium text-gray-900">{{ product.interest_rate }}%</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Term Length:</span>
                                    <span class="font-medium text-gray-900">{{ product.term_length }} {{ product.term_unit }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Processing Fee:</span>
                                    <span class="font-medium text-gray-900">KES {{ parseFloat(product.processing_fee).toLocaleString(undefined, {minimumFractionDigits: 2}) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                            <span>Created: {{ new Date(product.created_at).toLocaleDateString() }}</span>
                        </div>
                    </div>

                    <div v-if="products.length === 0" class="col-span-full bg-white rounded-lg p-12 text-center text-gray-500 border border-gray-100 shadow-sm">
                        No loan products configured yet. Click "Add Product" to get started.
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Product Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="isModalOpen = false"></div>

                <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                    <form @submit.prevent="submit">
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mt-3 w-full text-left sm:mt-0">
                                    <h3 class="text-lg font-medium leading-6 text-gray-900" id="modal-title">
                                        Add New Loan Product
                                    </h3>
                                    
                                    <div class="mt-6 space-y-4">
                                        <div>
                                            <label for="name" class="block text-sm font-medium text-gray-700">Product Name</label>
                                            <input
                                                type="text"
                                                v-model="form.name"
                                                id="name"
                                                placeholder="e.g. Weekly Micro Loan"
                                                required
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            />
                                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label for="interest_type" class="block text-sm font-medium text-gray-700">Interest Calculation</label>
                                                <select
                                                    v-model="form.interest_type"
                                                    id="interest_type"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                >
                                                    <option value="flat">Flat Rate</option>
                                                    <option value="reducing_balance">Reducing Balance</option>
                                                </select>
                                                <p v-if="form.errors.interest_type" class="mt-1 text-xs text-red-600">{{ form.errors.interest_type }}</p>
                                            </div>

                                            <div>
                                                <label for="interest_rate" class="block text-sm font-medium text-gray-700">Interest Rate (%)</label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    v-model="form.interest_rate"
                                                    id="interest_rate"
                                                    placeholder="e.g. 5.00"
                                                    required
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                />
                                                <p v-if="form.errors.interest_rate" class="mt-1 text-xs text-red-600">{{ form.errors.interest_rate }}</p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label for="term_length" class="block text-sm font-medium text-gray-700">Term Length</label>
                                                <input
                                                    type="number"
                                                    v-model="form.term_length"
                                                    id="term_length"
                                                    placeholder="e.g. 12"
                                                    required
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                />
                                                <p v-if="form.errors.term_length" class="mt-1 text-xs text-red-600">{{ form.errors.term_length }}</p>
                                            </div>

                                            <div>
                                                <label for="term_unit" class="block text-sm font-medium text-gray-700">Term Unit</label>
                                                <select
                                                    v-model="form.term_unit"
                                                    id="term_unit"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                >
                                                    <option value="weeks">Weeks</option>
                                                    <option value="months">Months</option>
                                                </select>
                                                <p v-if="form.errors.term_unit" class="mt-1 text-xs text-red-600">{{ form.errors.term_unit }}</p>
                                            </div>
                                        </div>

                                        <div>
                                            <label for="processing_fee" class="block text-sm font-medium text-gray-700">Processing Fee (KES)</label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                v-model="form.processing_fee"
                                                id="processing_fee"
                                                required
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            />
                                            <p v-if="form.errors.processing_fee" class="mt-1 text-xs text-red-600">{{ form.errors.processing_fee }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex w-full justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ form.processing ? 'Saving...' : 'Save Product' }}
                            </button>
                            <button
                                type="button"
                                @click="isModalOpen = false"
                                class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
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
