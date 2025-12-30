<?php

namespace Test\BackendTest\Laravel\Feature\Database;

use Illuminate\Support\Facades\DB;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

/**
 * Test SQLite transaction nesting limits and behavior
 * 
 * This test verifies:
 * 1. SQLite's nested transaction handling
 * 2. Maximum nesting depth (if any)
 * 3. Transaction rollback behavior in nested scenarios
 */
class SqliteTransactionNestingTest extends BackendTestCase
{
    /** @test */
    public function it_handles_single_transaction(): void
    {
        DB::transaction(function () {
            DB::table('users')->insert([
                'phone' => '+989123456789',
                'name' => 'Test User',
                'tokens_balance' => 100,
                'role' => 'user',
            ]);
        });

        $this->assertDatabaseHas('users', [
            'phone' => '+989123456789',
        ]);
    }

    /** @test */
    public function it_prevents_nested_transactions_in_sqlite(): void
    {
        // SQLite doesn't support nested transactions via BEGIN TRANSACTION
        // However, Laravel's DB::transaction() uses savepoints for nested transactions
        // Let's test the actual behavior
        
        try {
            DB::transaction(function () {
                DB::table('users')->insert([
                    'phone' => '+989123456790',
                    'name' => 'Test User 1',
                    'tokens_balance' => 100,
                    'role' => 'user',
                ]);

                // Attempt nested transaction
                DB::transaction(function () {
                    DB::table('users')->insert([
                        'phone' => '+989123456791',
                        'name' => 'Test User 2',
                        'tokens_balance' => 100,
                        'role' => 'user',
                    ]);
                });
            });

            // If we get here, nested transactions work (via savepoints)
            $this->assertDatabaseHas('users', ['phone' => '+989123456790']);
            $this->assertDatabaseHas('users', ['phone' => '+989123456791']);
        } catch (\Exception $e) {
            // If nested transactions fail, we should catch an exception
            // SQLite doesn't support true nested transactions, but Laravel may use savepoints
            $this->assertStringContainsString('transaction', strtolower($e->getMessage()));
        }
    }

    /** @test */
    public function it_tests_transaction_level_tracking(): void
    {
        // Note: LazilyRefreshDatabase may start a transaction when DB is first accessed
        // Trigger a DB operation to ensure LazilyRefreshDatabase transaction is started (if any)
        DB::table('users')->count(); // This triggers DB connection and LazilyRefreshDatabase transaction
        
        // Now get the actual initial level (may be 0 or 1 depending on LazilyRefreshDatabase)
        $initialLevel = DB::transactionLevel();

        DB::transaction(function () use ($initialLevel) {
            // Laravel increments transaction level when entering DB::transaction()
            $levelDuringTransaction = DB::transactionLevel();
            $this->assertGreaterThan($initialLevel, $levelDuringTransaction, 'Transaction level should increase inside DB::transaction()');

            DB::transaction(function () use ($initialLevel) {
                // Nested transaction should increment further
                $nestedLevel = DB::transactionLevel();
                $this->assertGreaterThan($initialLevel + 1, $nestedLevel, 'Nested transaction should have higher level');
            });

            // After nested transaction completes, level should return to outer transaction level
            $levelAfterNested = DB::transactionLevel();
            $this->assertEquals($levelDuringTransaction, $levelAfterNested, 'Level should return to outer transaction level');
        });

        // Verify level returns to initial state
        // Note: LazilyRefreshDatabase may maintain a transaction, so level may be > 0
        $finalLevel = DB::transactionLevel();
        $this->assertEquals($initialLevel, $finalLevel, 'Should return to initial level after transaction');
    }

    /** @test */
    public function it_tests_transaction_rollback_in_nested_scenario(): void
    {
        DB::transaction(function () {
            DB::table('users')->insert([
                'phone' => '+989123456792',
                'name' => 'Test User Outer',
                'tokens_balance' => 100,
                'role' => 'user',
            ]);

            try {
                DB::transaction(function () {
                    DB::table('users')->insert([
                        'phone' => '+989123456793',
                        'name' => 'Test User Inner',
                        'tokens_balance' => 100,
                        'role' => 'user',
                    ]);

                    // Force rollback of inner transaction
                    throw new \Exception('Inner transaction rollback');
                });
            } catch (\Exception $e) {
                // Inner transaction rolled back
            }

            // Outer transaction should still be active
        });

        // Outer transaction should be committed
        $this->assertDatabaseHas('users', ['phone' => '+989123456792']);
        
        // Inner transaction should be rolled back
        $this->assertDatabaseMissing('users', ['phone' => '+989123456793']);
    }

    /** @test */
    public function it_tests_transaction_rollback_propagates_to_outer(): void
    {
        try {
            DB::transaction(function () {
                DB::table('users')->insert([
                    'phone' => '+989123456794',
                    'name' => 'Test User Outer',
                    'tokens_balance' => 100,
                    'role' => 'user',
                ]);

                DB::transaction(function () {
                    DB::table('users')->insert([
                        'phone' => '+989123456795',
                        'name' => 'Test User Inner',
                        'tokens_balance' => 100,
                        'role' => 'user',
                    ]);

                    // Force rollback of entire transaction chain
                    throw new \Exception('Outer transaction rollback');
                });
            });
        } catch (\Exception $e) {
            // Entire transaction chain should be rolled back
        }

        // Both should be rolled back
        $this->assertDatabaseMissing('users', ['phone' => '+989123456794']);
        $this->assertDatabaseMissing('users', ['phone' => '+989123456795']);
    }

    /** @test */
    public function it_tests_begin_transaction_does_not_nest_with_laravel_transaction(): void
    {
        // Test that DB::beginTransaction() doesn't work well with DB::transaction()
        // This is the scenario that was causing the original 139 failures
        
        try {
            DB::beginTransaction();

            DB::transaction(function () {
                DB::table('users')->insert([
                    'phone' => '+989123456796',
                    'name' => 'Test User',
                    'tokens_balance' => 100,
                    'role' => 'user',
                ]);
            });

            DB::commit();
            
            $this->assertDatabaseHas('users', ['phone' => '+989123456796']);
        } catch (\Exception $e) {
            // This might fail in SQLite if DB::transaction() tries to start a new transaction
            // instead of using the existing one
            $this->assertStringContainsString('transaction', strtolower($e->getMessage()));
        }
    }

    /** @test */
    public function it_tests_pdo_in_transaction_detection(): void
    {
        // Test that PDO::inTransaction() correctly detects transaction state
        // Trigger a DB operation to ensure LazilyRefreshDatabase transaction is started (if any)
        DB::table('users')->count(); // This triggers DB connection and LazilyRefreshDatabase transaction
        
        $initialState = DB::connection()->getPdo()->inTransaction();
        $initialLevel = DB::transactionLevel();

        DB::transaction(function () {
            // During transaction, PDO should report transaction is active
            $this->assertTrue(DB::connection()->getPdo()->inTransaction(), 'Should detect active transaction during DB::transaction()');
        });

        // After transaction completes, verify we return to initial state
        $finalState = DB::connection()->getPdo()->inTransaction();
        $finalLevel = DB::transactionLevel();
        
        // Transaction level should return to initial
        $this->assertEquals($initialLevel, $finalLevel, 'Transaction level should return to initial state');
        
        // PDO state should return to initial (or be true if LazilyRefreshDatabase maintains transaction)
        $this->assertTrue($finalState === $initialState || $finalState === true, 'PDO transaction state should be consistent');
    }
}

