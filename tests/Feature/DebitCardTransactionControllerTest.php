<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DebitCard $debitCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        // get /debit-card-transactions
        DebitCardTransaction::factory()->count(2)->create([
            'debit_card_id' => $this->debitCard->id,
        ]);

        $response = $this->getJson("api/debit-card-transactions?debit_card_id={$this->debitCard->id}");

        $response->assertOk()->assertJsonCount(2)
            ->assertJsonStructure(['*' => ['amount', 'currency_code']]);
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // get /debit-card-transactions
        $debitCard = DebitCard::factory()->create();

        $response = $this->getJson("api/debit-card-transactions?debit_card_id={$debitCard->id}");
        $response->assertForbidden();
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        // post /debit-card-transactions
        $payload = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 1000,
            'currency_code' => DebitCardTransaction::CURRENCY_IDR
        ];
        $response = $this->postJson('api/debit-card-transactions', $payload);

        $response
            ->assertCreated()
            ->assertJsonStructure(['amount', 'currency_code'])
            ->assertJson([
                'amount' => $payload['amount'],
                'currency_code' => $payload['currency_code']
            ]);

        $this->assertDatabaseHas('debit_card_transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => $payload['amount'],
            'currency_code' => $payload['currency_code']
        ]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        // post /debit-card-transactions
        $debitCard = DebitCard::factory()->create();

        $payload = [
            'debit_card_id' => $debitCard->id,
            'amount' => 1000,
            'currency_code' => DebitCardTransaction::CURRENCY_IDR
        ];

        $response = $this->postJson('api/debit-card-transactions', $payload);

        $response->assertForbidden();
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $this->debitCard->id
        ]);

        $response = $this->getJson("api/debit-card-transactions/{$transaction->id}");
        $response
            ->assertOk()
            ->assertJsonStructure(['amount', 'currency_code'])
            ->assertJson([
                'amount' => $transaction->amount,
                'currency_code' => $transaction->currency_code
            ]);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $debitCard = DebitCard::factory()->create();
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $debitCard->id
        ]);

        $response = $this->getJson("api/debit-card-transactions/{$transaction->id}");
        $response
            ->assertForbidden()
            ->assertJsonStructure([]);
    }

    // Extra bonus for extra tests :)
    public function testCustomerCannotCreateTransactionWithInvalidCurrency()
    {
        $payload = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 5000,
            'currency_code' => 'USD'
        ];

        $response = $this->postJson('/api/debit-card-transactions', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['currency_code']);
    }

    public function testCustomerCannotCreateTransactionWithInvalidAmount()
    {
        $payload = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 'one thousand',
            'currency_code' => DebitCardTransaction::CURRENCY_IDR
        ];

        $response = $this->postJson('/api/debit-card-transactions', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }
}
