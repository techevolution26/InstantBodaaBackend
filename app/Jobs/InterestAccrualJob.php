<?php

namespace App\Jobs;

use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class InterestAccrualJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle()
    {
        // we'll credit interest once per day
        WalletAccount::chunk(100, function($accounts) {
            foreach ($accounts as $wallet) {
                DB::transaction(function() use ($wallet) {
                    // days = 1 (daily job)
                    $days = 1;
                    // daily rate = APR / 365
                    $savingsDailyRate = $wallet->savings_rate / 100 / 365;
                    $loanDailyRate    = $wallet->loan_rate    / 100 / 365;

                    // compute interest amounts
                    $savingsInterest = round($wallet->savings_balance * $savingsDailyRate * $days, 2);
                    $loanInterest    = round($wallet->loan_balance   * $loanDailyRate    * $days, 2);

                    // credit savings interest
                    if ($savingsInterest > 0) {
                        $wallet->savings_balance += $savingsInterest;
                        WalletTransaction::create([
                            'account_id'  => $wallet->id,
                            'type'        => 'interest_credit',
                            'amount'      => $savingsInterest,
                            'description' => 'Daily savings interest',
                        ]);
                    }

                    // debit loan interest
                    if ($loanInterest > 0) {
                        $wallet->loan_balance += $loanInterest;
                        WalletTransaction::create([
                            'account_id'  => $wallet->id,
                            'type'        => 'interest_charge',
                            'amount'      => -$loanInterest,
                            'description' => 'Daily loan interest',
                        ]);
                    }

                    $wallet->save();
                });
            }
        });
    }
}
