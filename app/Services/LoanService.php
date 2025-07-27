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
        DB::beginTransaction();
        try {
            $received = ReceivedRepayment::create([
                'loan_id' => $loan->id,
                'amount' => $amount,
                'currency_code' => $currencyCode,
                'received_at' => $receivedAt,
            ]);

            $repayments = $loan->scheduledRepayments()
                ->whereIn('status', [
                    ScheduledRepayment::STATUS_DUE,
                    ScheduledRepayment::STATUS_PARTIAL,
                ])
                ->orderBy('due_date')
                ->get();

            $remainingAmount = $amount;

            foreach ($repayments as $repayment) {
                if ($remainingAmount <= 0) break;
                $outstanding = $repayment->outstanding_amount;

                if ($remainingAmount >= $outstanding) {
                    // Repaid
                    $repayment->update([
                        'outstanding_amount' => 0,
                        'status' => ScheduledRepayment::STATUS_REPAID,
                    ]);
                    $remainingAmount -= $outstanding;
                } else {
                    // Partial
                    $repayment->update([
                        'outstanding_amount' => $remainingAmount,
                        'status' => ScheduledRepayment::STATUS_PARTIAL,
                    ]);
                    $remainingAmount = 0;
                }
            }

            $amountReceipt = collect($loan->scheduledRepayments)
                ->where('due_date', '<=', $receivedAt)
                ->sum('amount');

            $amountPartial = collect($loan->scheduledRepayments)
                ->where('status','==','partial')
                ->sum('outstanding_amount');

            // Update the outstanding amount of the loan
            $loan->outstanding_amount = max($loan->outstanding_amount - ($amountReceipt + $amountPartial), 0);

            // Handling cases with outstanding balance of 1
            if($loan->outstanding_amount === 1){
                $loan->outstanding_amount = 0;
            }

            $loanStatus = $loan->outstanding_amount === 0
                ? Loan::STATUS_REPAID
                : Loan::STATUS_DUE;

            $loan->update([
                'outstanding_amount' => $loan->outstanding_amount,
                'status' => $loanStatus,
            ]);

            DB::commit();
            return $received;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
