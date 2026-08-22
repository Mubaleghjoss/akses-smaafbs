<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountCategory extends Model
{
    protected $table = 'account_categories';

    protected $fillable = [
        'nama', 'keterangan',
    ];

    public function belajarIdAccounts(): HasMany
    {
        return $this->hasMany(BelajarIdAccount::class, 'category_id');
    }

    public function hotspotUsers(): HasMany
    {
        return $this->hasMany(HotspotUser::class, 'category_id');
    }
}
