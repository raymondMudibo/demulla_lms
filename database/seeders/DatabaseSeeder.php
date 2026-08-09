<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Admin User
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'customer_id' => null,
            ]
        );

        // 2. Create Customers & Linked Users
        // Customer 1: John Doe
        $customer1 = Customer::updateOrCreate(
            ['id_number' => '12345678'],
            [
                'name' => 'John Doe',
                'phone_number' => '254712345678',
                'email' => 'john@example.com',
            ]
        );

        User::updateOrCreate(
            ['email' => 'john@example.com'],
            [
                'name' => 'John Doe',
                'phone_number' => '254712345678',
                'id_number' => '12345678',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'customer_id' => $customer1->id,
            ]
        );

        // Customer 2: Jane Smith
        $customer2 = Customer::updateOrCreate(
            ['id_number' => '87654321'],
            [
                'name' => 'Jane Smith',
                'phone_number' => '254787654321',
                'email' => 'jane@example.com',
            ]
        );

        User::updateOrCreate(
            ['email' => 'jane@example.com'],
            [
                'name' => 'Jane Smith',
                'phone_number' => '254787654321',
                'id_number' => '87654321',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'customer_id' => $customer2->id,
            ]
        );

        // 3. Create Loan Products (5 Products)
        $products = [
            [
                'name' => 'Emergency Micro-Loan',
                'interest_type' => 'flat',
                'interest_rate' => 5.00,
                'term_length' => 4,
                'term_unit' => 'weeks',
                'processing_fee' => 200.00,
            ],
            [
                'name' => 'SME Working Capital',
                'interest_type' => 'reducing_balance',
                'interest_rate' => 12.00,
                'term_length' => 3,
                'term_unit' => 'months',
                'processing_fee' => 500.00,
            ],
            [
                'name' => 'Bi-Weekly Salary Advance',
                'interest_type' => 'flat',
                'interest_rate' => 8.00,
                'term_length' => 2,
                'term_unit' => 'weeks',
                'processing_fee' => 150.00,
            ],
            [
                'name' => 'Asset Financing',
                'interest_type' => 'reducing_balance',
                'interest_rate' => 15.00,
                'term_length' => 6,
                'term_unit' => 'months',
                'processing_fee' => 1000.00,
            ],
            [
                'name' => 'School Fees Loan',
                'interest_type' => 'flat',
                'interest_rate' => 10.00,
                'term_length' => 2,
                'term_unit' => 'months',
                'processing_fee' => 300.00,
            ],
        ];

        foreach ($products as $productData) {
            LoanProduct::updateOrCreate(
                ['name' => $productData['name']],
                $productData
            );
        }
    }
}
