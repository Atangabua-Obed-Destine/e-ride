<?php

namespace Modules\ChattingManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UserManagement\Entities\User;

class ChannelUser extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'channel_id',
        'user_id',
        'is_read',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_read' => 'boolean',
    ];

    protected static function newFactory()
    {
        return \Modules\ChatModule\Database\factories\ChannelUserFactory::new();
    }

    public function conversations()
    {
        return $this->hasMany(ChannelConversation::class, 'channel_id', 'channel_id')->orderBy('created_at', 'desc');
    }

    public function channel()
    {
        return $this->belongsTo(ChannelList::class);
    }
    public function getIsUnreadCountAttribute()
    {
        return $this->conversations->where('is_read', false)->where('user_id', '!=', auth()->user()->id)->count();
    }

    public function getLastMessageAttribute()
    {
        return $this->conversations->first();
    }

    public function channelConversation(){
        return $this->hasMany(ChannelConversation::class, 'channel_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
