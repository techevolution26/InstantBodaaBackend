<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MpesaService;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;

class MpesaController extends Controller
{
    protected $mpesa;

    public function __construct(MpesaService $mpesa)
    {
        $this->mpesa = $mpesa;
    }

    //Initiate STK Push
    public function fund(Request $request)
    {
        $request->validate([
            'phone'  => 'required|regex:/^254[0-9]{9}$/',
            'amount' => 'required|numeric|min:1',
        ]);

        $user   = $request->user();
        $wallet = WalletAccount::firstOrCreate(['user_id'=>$user->id]);
        $accountRef = 'WAL'.$wallet->id;

        $res = $this->mpesa->stkPush($request->phone, $request->amount, $accountRef);
        // store the CheckoutRequestID to match callbacks
        session(['mpesa_checkout_id' => $res['CheckoutRequestID']]);

        return response()->json($res);
    }

    // Callback endpoint
    public function callback(Request $request)
    {
        $data = $request->all()['Body']['stkCallback'];
        $checkoutID = $data['CheckoutRequestID'];
        if ($data['ResultCode'] !== 0) {
            // failed or cancelled
            return;
        }
        // successful
        $amount   = collect($data['CallbackMetadata']['Item'])
                       ->first(fn($i)=>$i['Name']=='Amount')['Value'];
        $accountRef = collect($data['CallbackMetadata']['Item'])
                       ->first(fn($i)=>$i['Name']=='AccountReference')['Value'];
        // extract wallet id from ref
        $walletId = intval(substr($accountRef,3));
        $wallet   = WalletAccount::findOrFail($walletId);
        $wallet->balance += $amount;
        $wallet->save();

        WalletTransaction::create([
            'account_id'  => $walletId,
            'type'        => 'mpesa_deposit',
            'amount'      => $amount,
            'description' => 'M‑PESA top‑up',
        ]);
    }
}
