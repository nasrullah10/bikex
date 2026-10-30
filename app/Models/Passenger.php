<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Passenger extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id', 'home_address', 'home_lat', 'home_lng',
        'work_address', 'work_lat', 'work_lng',
        'total_rides', 'total_spent'
    ];

    protected $casts = [
        'home_lat' => 'decimal:8',
        'home_lng' => 'decimal:8',
        'work_lat' => 'decimal:8',
        'work_lng' => 'decimal:8',
        'total_spent' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    public function favoritePlaces(): HasMany
    {
        return $this->hasMany(FavoritePlace::class);
    }
}