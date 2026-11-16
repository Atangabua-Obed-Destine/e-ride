<?php

namespace Modules\HotelManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A kind of room guests choose from, "Standard double", with photos and a price from. */
class HotelRoomType extends Model
{
    use HasUuid;

    public const MAX_PHOTOS = 6;

    /** In-room amenity keys, in display order. The app has translated labels and icons for them. */
    public const AMENITIES = ['wifi', 'hot_water', 'tv', 'air_conditioning', 'fan', 'breakfast', 'backup_power', 'fridge', 'balcony', 'desk'];

    protected $fillable = ['hotel_id', 'name', 'beds', 'max_guests', 'price_from', 'amenities', 'photos', 'description', 'sort', 'active'];

    protected $casts = [
        'max_guests' => 'integer',
        'price_from' => 'integer',
        'amenities' => 'array',
        'photos' => 'array',
        'sort' => 'integer',
        'active' => 'boolean',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(HotelRoom::class, 'room_type_id')->orderBy('number');
    }

    public static function amenityLabels(): array
    {
        return [
            'wifi' => translate('Wi-Fi'),
            'hot_water' => translate('Hot water'),
            'tv' => translate('TV'),
            'air_conditioning' => translate('Air conditioning'),
            'fan' => translate('Fan'),
            'breakfast' => translate('Breakfast included'),
            'backup_power' => translate('Generator or backup power'),
            'fridge' => translate('Fridge'),
            'balcony' => translate('Balcony'),
            'desk' => translate('Desk'),
        ];
    }
}
