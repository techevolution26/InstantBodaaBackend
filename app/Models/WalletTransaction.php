<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model {
    protected $table = 'wallet_transactions';

    protected $fillable = [
        'account_id',
        'type',         // deposit, withdraw, loan_disbursement, loan_repayment, etc.
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
    * The wallet account this transaction belongs to.
    */

    public function account(): BelongsTo {
        return $this->belongsTo( WalletAccount::class, 'account_id' );
    }
}
