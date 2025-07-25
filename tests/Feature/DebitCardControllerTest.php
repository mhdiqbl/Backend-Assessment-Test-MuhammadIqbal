<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        // get /debit-cards
        // Create debit cards
        $debitCards = DebitCard::factory()->count(2)->active()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->get('/api/debit-cards', [
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(200);
        // Check response contains two data
        $this->assertCount(2, $response->json());

        // Check response structure
        foreach ($debitCards as $card) {
            $response->assertJsonFragment([
                'id' => $card->id,
                'number' => (int)$card->number,
                'type' => $card->type,
                'expiration_date' => $card->expiration_date->format('Y-m-d H:i:s'),
                'is_active' => $card->is_active,
            ]);
        }
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // get /debit-cards
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create debit Card for user 1
        DebitCard::factory()->count(2)->active()->create([
            'user_id' => $user1->id,
        ]);

        // Acting as a user 2
        $this->actingAs($user2, 'api');

        $response = $this->getJson('/api/debit-cards');

        $response->assertStatus(200);
        $response->assertJsonCount(0, $response->json());
    }

    public function testCustomerCanCreateADebitCard()
    {
        // post /debit-cards
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
    }

    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}
    }

    // Extra bonus for extra tests :)
}
