<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Loan::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $amount = $this->faker->numberBetween(1000, 9999);
        return [
            // TODO: Complete factory
            'user_id' => fn () => User::factory()->create(),
            'terms' => $this->faker->randomNumber(1),
            'amount' => $amount,
            'outstanding_amount' => $amount,
            'currency_code' => $this->faker->randomElement([Loan::CURRENCY_SGD, Loan::CURRENCY_VND]),
            'processed_at' => $this->faker->dateTimeThisYear(),
            'status' => Loan::STATUS_DUE,
        ];
    }

    public function configure(): self
    {
        return $this->afterMaking(function (Loan $loan) {
            $loan->outstanding_amount = $loan->amount;
        });
    }
}
