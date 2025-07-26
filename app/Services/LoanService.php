<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanService
{
    /**
     * Create a Loan
     *
     * @param  User  $user
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  int  $terms
     * @param  string  $processedAt
     *
     * @return Loan
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        DB::beginTransaction();
        try {
            $loan = Loan::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'currency_code' => $currencyCode,
                'terms' => $terms,
                'outstanding_amount' => $amount,
                'processed_at' => $processedAt,
                'status' => Loan::STATUS_DUE,
            ]);

            $basic = intdiv($amount, $terms);
            $remainder = $amount % $terms;


            for ($i=1;$i <= $terms ;$i++) {
                $dueDate = Carbon::parse($processedAt)->addMonth($i)->format('Y-m-d');

                $installments = $basic + ($i === $terms ? $remainder : 0);

                //create the scheduled repayment
                $loan->scheduledRepayments()->create([
                    'amount' => $installments,
                    'outstanding_amount' => $installments,
                    'currency_code' => $currencyCode,
                    'due_date' => $dueDate,
                    'status' => ScheduledRepayment::STATUS_DUE,
                ]);
            }

            DB::commit();
            return $loan;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  string  $receivedAt
     *
     * @return ReceivedRepayment
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        //
    }
}
