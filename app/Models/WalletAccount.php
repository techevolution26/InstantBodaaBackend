<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletAccount extends Model {
    protected $table = 'wallet_accounts';

    protected $fillable = [
        'user_id',
        'balance',
        'savings_balance',
        'loan_balance',
    ];

    protected $casts = [
        'balance'         => 'decimal:2',
        'savings_balance' => 'decimal:2',
        'loan_balance'    => 'decimal:2',
    ];

    /**
    * The user who owns this wallet.
    */

    public function user(): BelongsTo {
        return $this->belongsTo( User::class );
    }

    /**
    * All transactions against this wallet.
    */

    public function transactions(): HasMany {
        return $this->hasMany( WalletTransaction::class, 'account_id' );
    }
}
