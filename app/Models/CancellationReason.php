<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationReason extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['ride_id', 'cancelled_by', 'reason_code', 'custom_reason'];

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }
}