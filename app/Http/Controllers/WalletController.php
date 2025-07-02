<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;

class WalletController extends Controller
{

  /** GET /api/wallet */
    public function overview(Request $request)
    {
        $user = $request->user();

        // ensure the user has a wallet account
        $wallet = WalletAccount::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'savings_balance' => 0, 'loan_balance' => 0]
        );

        return response()->json([
            'balance'          => $wallet->balance,
            'savings_balance'  => $wallet->savings_balance,
            'loan_balance'     => $wallet->loan_balance,
        ]);
    }

    /** GET /api/wallet/transactions */
    public function transactions(Request $request)
    {
        $user = $request->user();
        $wallet = WalletAccount::where('user_id', $user->id)->firstOrFail();

        $txs = WalletTransaction::where('account_id', $wallet->id)
            ->latest()
            ->paginate(20);

        return response()->json($txs);
    }

    /**
     * POST /api/wallet/savings
     * body: { amount: 100.00, type: 'deposit'|'withdraw' }
     */
    public function modifySavings(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'type'   => 'required|in:deposit,withdraw',
        ]);

        $wallet = WalletAccount::where('user_id', $user->id)->firstOrFail();

        return DB::transaction(function () use ($wallet, $data) {
            $amt = $data['amount'];
            if ($data['type'] === 'withdraw') {
                if ($wallet->savings_balance < $amt) {
                    throw ValidationException::withMessages([
                        'amount' => 'Insufficient savings balance',
                    ]);
                }
                // deduct from savings, credit to main balance
                $wallet->savings_balance   -= $amt;
                $wallet->balance           += $amt;
            } else {
                // deposit: move from main balance into savings
                if ($wallet->balance < $amt) {
                    throw ValidationException::withMessages([
                        'amount' => 'Insufficient wallet balance',
                    ]);
                }
                $wallet->balance           -= $amt;
                $wallet->savings_balance   += $amt;
            }
            $wallet->save();

            // record transaction
            WalletTransaction::create([
                'account_id'  => $wallet->id,
                'type'        => $data['type'] === 'deposit' ? 'deposit' : 'withdraw',
                'amount'      => $data['type'] === 'deposit' ? -$amt : $amt,
                'description' => $data['type'] === 'deposit' ? 'Saved funds' : 'Withdrew savings',
            ]);

            return response()->json([
                'balance'         => $wallet->balance,
                'savings_balance' => $wallet->savings_balance,
            ]);
        });
    }

    /**
     * POST /api/wallet/loan
     * body: { amount: 100.00 }
     */
    public function requestLoan(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $wallet = WalletAccount::where('user_id', $user->id)->firstOrFail();

        return DB::transaction(function () use ($wallet, $data) {
            $amt = $data['amount'];

            // if ($wallet->loan_balance + $amt > $wallet->balance * 0.5) { ... }
            // disburse: credit wallet balance, increase loan balance
            $wallet->balance      += $amt;
            $wallet->loan_balance += $amt;
            $wallet->save();

            WalletTransaction::create([
                'account_id'  => $wallet->id,
                'type'        => 'loan_disbursement',
                'amount'      => $amt,
                'description' => 'Loan disbursed',
            ]);

            return response()->json([
                'balance'      => $wallet->balance,
                'loan_balance' => $wallet->loan_balance,
            ], 201);
        });
    }

    /**
     * POST /api/wallet/loan/repay
     * body: { amount: 50.00 }
     */
    public function repayLoan(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $wallet = WalletAccount::where('user_id', $user->id)->firstOrFail();

        return DB::transaction(function () use ($wallet, $data) {
            $amt = $data['amount'];

            if ($wallet->balance < $amt) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient wallet balance to repay',
                ]);
            }
            if ($wallet->loan_balance <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'No outstanding loan to repay',
                ]);
            }

            // apply repayment: deduct main balance & reduce loan balance
            $wallet->balance      -= $amt;
            $wallet->loan_balance -= $amt;
            $wallet->save();

            WalletTransaction::create([
                'account_id'  => $wallet->id,
                'type'        => 'loan_repayment',
                'amount'      => -$amt,
                'description' => 'Loan repayment',
            ]);

            return response()->json([
                'balance'      => $wallet->balance,
                'loan_balance' => $wallet->loan_balance,
            ]);
        });
    }
}
