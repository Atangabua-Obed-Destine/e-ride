<?php

namespace Modules\TripManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripProofImage extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'trip_id',
        'pickup_proof_images',
        'delivery_proof_images',
    ];

    protected $casts = [
        'pickup_proof_images' => 'array',
        'delivery_proof_images' => 'array',
    ];

    public function trip()
    {
        return $this->belongsTo(TripRequest::class, 'trip_id');
    }
}
