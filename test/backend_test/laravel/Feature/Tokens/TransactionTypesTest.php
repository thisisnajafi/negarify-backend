<?php

namespace Test\BackendTest\Laravel\Feature\Tokens;

use App\Models\Order;
use App\Models\GenerationJob;
use App\Models\Provider;
use App\Models\TokenBundle;
use App\Models\TokenTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class TransactionTypesTest extends BackendTestCase
{
    /** @test */
    public function it_creates_purchase_transaction_correctly(): void
    {
        $user = User::factory()->create(['tokens_balance' => 0]);
        $bundle = TokenBundle::create([
            'name' => 'Test Pack',
            'token_amount' => 100,
            'bonus_tokens' => 20,
            'price_usd' => 1.00,
            'is_active' => true,
        ]);
        
        $order = Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 120, // 100 + 20 bonus
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000,
            'status' => 'paid',
            'zarinpal_authority' => 'A00000000000000000000000000000000000',
            'zarinpal_ref_id' => 123456789,
            'paid_at' => now(),
        ]);
        
        // Purchase transaction should have positive amount_tokens
        $transaction = TokenTransaction::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'amount_tokens' => 120,
            'amount_usd' => 1.00,
            'type' => 'purchase',
            'description' => "Token purchase - {$bundle->name}",
        ]);
        
        $this->assertEquals('purchase', $transaction->type);
        $this->assertEquals(120, $transaction->amount_tokens); // Positive for purchase
        $this->assertEquals($order->id, $transaction->order_id);
        $this->assertNull($transaction->generation_job_id); // Purchase doesn't link to generation job
        $this->assertEquals($user->id, $transaction->user_id);
        
        // Verify balance reconciliation
        $user->refresh();
        $calculatedBalance = TokenTransaction::where('user_id', $user->id)->sum('amount_tokens');
        $this->assertEquals(120, $calculatedBalance);
        
        $this->assertNoErrorLogs();
    }
    
    /** @test */
    public function it_creates_consume_transaction_correctly(): void
    {
        $user = User::factory()->create(['tokens_balance' => 100]);
        
        // Create provider with required fields (using DB to bypass encryption mutator in tests)
        $providerId = DB::table('providers')->insertGetId([
            'name' => 'Test Provider',
            'api_base_url' => 'https://api.example.com',
            'api_key_encrypted' => 'test-api-key-encrypted', // In real app this would be encrypted
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Create model using DB::table to avoid Model class name conflict with Illuminate\Database\Eloquent\Model
        $modelId = DB::table('models')->insertGetId([
            'provider_id' => $providerId,
            'model_name' => 'Test Model',
            'model_type' => 'image',
            'api_endpoint' => '/test',
            'default_tokens' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'tokens_consumed' => 10,
        ]);
        
        // Consume transaction should have negative amount_tokens
        $transaction = TokenTransaction::create([
            'user_id' => $user->id,
            'generation_job_id' => $job->id,
            'amount_tokens' => -10, // Negative for consumption
            'type' => 'consume',
            'description' => 'Image generation',
        ]);
        
        $this->assertEquals('consume', $transaction->type);
        $this->assertEquals(-10, $transaction->amount_tokens); // Negative for consume
        $this->assertEquals($job->id, $transaction->generation_job_id);
        $this->assertNull($transaction->order_id); // Consume doesn't link to order
        $this->assertEquals($user->id, $transaction->user_id);
        
        // Verify balance reconciliation
        $calculatedBalance = TokenTransaction::where('user_id', $user->id)->sum('amount_tokens');
        $this->assertEquals(-10, $calculatedBalance); // Only this transaction exists
        
        $this->assertNoErrorLogs();
    }
    
    /** @test */
    public function it_creates_refund_transaction_correctly(): void
    {
        $user = User::factory()->create(['tokens_balance' => 100]);
        
        // Create provider with required fields (using DB to bypass encryption mutator in tests)
        $providerId = DB::table('providers')->insertGetId([
            'name' => 'Test Provider',
            'api_base_url' => 'https://api.example.com',
            'api_key_encrypted' => 'test-api-key-encrypted', // In real app this would be encrypted
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Create model using DB::table to avoid Model class name conflict with Illuminate\Database\Eloquent\Model
        $modelId = DB::table('models')->insertGetId([
            'provider_id' => $providerId,
            'model_name' => 'Test Model',
            'model_type' => 'image',
            'api_endpoint' => '/test',
            'default_tokens' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'failed',
            'tokens_consumed' => 10,
        ]);
        
        // Refund transaction should have positive amount_tokens (refunding consumed tokens)
        $transaction = TokenTransaction::create([
            'user_id' => $user->id,
            'generation_job_id' => $job->id,
            'amount_tokens' => 10, // Positive for refund
            'type' => 'refund',
            'description' => 'Refund for failed generation job',
        ]);
        
        $this->assertEquals('refund', $transaction->type);
        $this->assertEquals(10, $transaction->amount_tokens); // Positive for refund
        $this->assertEquals($job->id, $transaction->generation_job_id);
        $this->assertNull($transaction->order_id); // Refund doesn't link to order
        $this->assertEquals($user->id, $transaction->user_id);
        
        // Verify balance reconciliation
        $calculatedBalance = TokenTransaction::where('user_id', $user->id)->sum('amount_tokens');
        $this->assertEquals(10, $calculatedBalance); // Only this transaction exists
        
        $this->assertNoErrorLogs();
    }
    
    /** @test */
    public function it_creates_bonus_transaction_correctly(): void
    {
        $user = User::factory()->create(['tokens_balance' => 100]);
        
        // Bonus transaction should have positive amount_tokens
        $transaction = TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => 50, // Positive for bonus
            'type' => 'bonus',
            'description' => 'Promotional bonus tokens',
        ]);
        
        $this->assertEquals('bonus', $transaction->type);
        $this->assertEquals(50, $transaction->amount_tokens); // Positive for bonus
        $this->assertNull($transaction->order_id); // Bonus doesn't link to order
        $this->assertNull($transaction->generation_job_id); // Bonus doesn't link to generation job
        $this->assertEquals($user->id, $transaction->user_id);
        
        // Verify balance reconciliation
        $calculatedBalance = TokenTransaction::where('user_id', $user->id)->sum('amount_tokens');
        $this->assertEquals(50, $calculatedBalance); // Only this transaction exists
        
        $this->assertNoErrorLogs();
    }
    
    /** @test */
    public function it_creates_adjustment_transaction_correctly(): void
    {
        $user = User::factory()->create(['tokens_balance' => 100]);
        
        // Adjustment transaction can have positive or negative amount_tokens
        // Positive adjustment (adding tokens)
        $positiveAdjustment = TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => 25, // Positive adjustment
            'type' => 'adjustment',
            'description' => 'Manual token adjustment - add',
        ]);
        
        $this->assertEquals('adjustment', $positiveAdjustment->type);
        $this->assertEquals(25, $positiveAdjustment->amount_tokens);
        $this->assertNull($positiveAdjustment->order_id);
        $this->assertNull($positiveAdjustment->generation_job_id);
        $this->assertEquals($user->id, $positiveAdjustment->user_id);
        
        // Negative adjustment (removing tokens)
        $negativeAdjustment = TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => -15, // Negative adjustment
            'type' => 'adjustment',
            'description' => 'Manual token adjustment - remove',
        ]);
        
        $this->assertEquals('adjustment', $negativeAdjustment->type);
        $this->assertEquals(-15, $negativeAdjustment->amount_tokens);
        
        // Verify balance reconciliation
        $calculatedBalance = TokenTransaction::where('user_id', $user->id)->sum('amount_tokens');
        $this->assertEquals(10, $calculatedBalance); // 25 - 15 = 10
        
        $this->assertNoErrorLogs();
    }
    
    /** @test */
    public function it_reconciles_balance_across_all_transaction_types(): void
    {
        $user = User::factory()->create(['tokens_balance' => 0]);
        $bundle = TokenBundle::create([
            'name' => 'Test Pack',
            'token_amount' => 100,
            'bonus_tokens' => 20,
            'price_usd' => 1.00,
            'is_active' => true,
        ]);
        
        $order = Order::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 120,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000,
            'status' => 'paid',
            'zarinpal_authority' => 'A00000000000000000000000000000000000',
            'zarinpal_ref_id' => 123456789,
            'paid_at' => now(),
        ]);
        
        // Create various transaction types
        TokenTransaction::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'amount_tokens' => 120, // Purchase
            'amount_usd' => 1.00,
            'type' => 'purchase',
            'description' => 'Token purchase',
        ]);
        
        // Create provider with required fields (using DB to bypass encryption mutator in tests)
        $providerId = DB::table('providers')->insertGetId([
            'name' => 'Test Provider',
            'api_base_url' => 'https://api.example.com',
            'api_key_encrypted' => 'test-api-key-encrypted', // In real app this would be encrypted
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Create model using DB::table to avoid Model class name conflict with Illuminate\Database\Eloquent\Model
        $modelId = DB::table('models')->insertGetId([
            'provider_id' => $providerId,
            'model_name' => 'Test Model',
            'model_type' => 'image',
            'api_endpoint' => '/test',
            'default_tokens' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $job = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'completed',
            'tokens_consumed' => 30,
        ]);
        
        TokenTransaction::create([
            'user_id' => $user->id,
            'generation_job_id' => $job->id,
            'amount_tokens' => -30, // Consume
            'type' => 'consume',
            'description' => 'Image generation',
        ]);
        
        TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => 10, // Bonus
            'type' => 'bonus',
            'description' => 'Promotional bonus',
        ]);
        
        $failedJob = GenerationJob::create([
            'user_id' => $user->id,
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'job_type' => 'image',
            'prompt' => 'Test prompt',
            'params_json' => [],
            'status' => 'failed',
            'tokens_consumed' => 20,
        ]);
        
        TokenTransaction::create([
            'user_id' => $user->id,
            'generation_job_id' => $failedJob->id,
            'amount_tokens' => 20, // Refund
            'type' => 'refund',
            'description' => 'Refund for failed job',
        ]);
        
        TokenTransaction::create([
            'user_id' => $user->id,
            'amount_tokens' => -5, // Adjustment (negative)
            'type' => 'adjustment',
            'description' => 'Manual adjustment',
        ]);
        
        // Verify balance reconciliation
        $calculatedBalance = TokenTransaction::where('user_id', $user->id)->sum('amount_tokens');
        $expectedBalance = 120 - 30 + 10 + 20 - 5; // 115
        $this->assertEquals($expectedBalance, $calculatedBalance);
        
        $this->assertNoErrorLogs();
    }
}

