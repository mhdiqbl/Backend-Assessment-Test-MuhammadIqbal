<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        $payload = [
            'type' => 'Visa',
        ];

        $response = $this->postJson('api/debit-cards', [
            'type' => $payload['type']
        ]);

        // Check the JSON structure and make sure the type is the same as the payload.
        $response
            ->assertCreated()
            ->assertJson(['type' => 'Visa'])
            ->assertJsonStructure([
                'id',
                'number',
                'type',
                'expiration_date',
                'is_active'
            ]);

        // Check that the data is in the DB
        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'type' => $payload['type'],
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->active()->create([
                'user_id' => $this->user->id,
            ]);

        $response = $this->getJson("api/debit-cards/{$debitCard->id}");

        $response
            ->assertOk()
            ->assertJson([
                'id' => $debitCard->id,
                'number' => (int)$debitCard->number,
                'type' => $debitCard->type,
                'expiration_date' => $debitCard->expiration_date->format('Y-m-d H:i:s'),
                'is_active' => $debitCard->is_active,
            ]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create debit Card for user 1
        $debitCardUser1 = DebitCard::factory()->active()->create([
            'user_id' => $user1->id,
        ]);

        // Acting as a user 2
        $this->actingAs($user2, 'api');

        $response = $this->getJson("api/debit-cards/{$debitCardUser1->id}");

        $response->assertStatus(403);
        $response->assertJsonStructure([]);
    }

    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->expired()->create([
                'user_id' => $this->user->id,
            ]);

        $payload = [
            'is_active' => true
        ];

        $response = $this->putJson("api/debit-cards/{$debitCard->id}", $payload);

        $response
            ->assertOk()
            ->assertJson([
                'id' => $debitCard->id,
                'number' => (int)$debitCard->number,
                'type' => $debitCard->type,
                'expiration_date' => $debitCard->expiration_date->format('Y-m-d H:i:s'),
                'is_active' => $payload['is_active'],
            ]);

        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'disabled_at' => null,
            'type' => $debitCard->type,
            'number' => $debitCard->number,
        ]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id,
        ]);

        $payload = [
            'is_active' => false
        ];

        $response = $this->putJson("api/debit-cards/{$debitCard->id}", $payload);

        $response
            ->assertOk()
            ->assertJson([
                'id' => $debitCard->id,
                'number' => (int)$debitCard->number,
                'type' => $debitCard->type,
                'expiration_date' => $debitCard->expiration_date->format('Y-m-d H:i:s'),
                'is_active' => $payload['is_active'],
            ]);

        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'disabled_at' => Carbon::now(),
            'type' => $debitCard->type,
            'number' => $debitCard->number,
        ]);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->putJson("api/debit-cards/{$debitCard->id}", [
            'is_active' => 'wrong value'
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_active');
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("api/debit-cards/{$debitCard->id}");

        $response->assertStatus(204);

        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'deleted_at' => Carbon::now(),
        ]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id,
        ]);
        DebitCardTransaction::factory()->count(2)->create([
            'debit_card_id' => $debitCard->id,
        ]);

        $response = $this->deleteJson("api/debit-cards/{$debitCard->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
        ]);
    }

    // Extra bonus for extra tests :)
    public function testOnlyActiveDebitCardsAreListedInIndex()
    {
        DebitCard::factory()->active()->count(5)->create(['user_id' => $this->user->id]);
        DebitCard::factory()->expired()->count(1)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/debit-cards');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json());
    }

    public function testCustomerCannotUpdateDebitCardOfAnotherUser()
    {
        $otherUser = User::factory()->create();
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $otherUser->id,
        ]);
        $payload = [
            'is_active' => false
        ];

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", $payload);

        $response->assertForbidden();
        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $debitCard->user_id,
            'disabled_at' => null,
        ]);
    }

    public function testCustomerCannotDeleteDebitCardOfAnotherUser()
    {
        $otherUser = User::factory()->create();
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $debitCard->user_id,
            'disabled_at' => null,
        ]);
    }

    public function testCreateADebitCardFailsWithoutType()
    {
        $response = $this->postJson('/api/debit-cards', []);

        $response->assertStatus(422)->assertJsonValidationErrors('type');
    }

    public function testCreatedDebitCardHasOneYearExpiry()
    {
        $response = $this->postJson('/api/debit-cards', ['type' => 'MasterCard']);

        $expirationDate = Carbon::parse($response->json('expiration_date'));

        $this->assertTrue($expirationDate->isSameDay(now()->addYear()));
    }

}
