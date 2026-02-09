<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'shop_domain',
        'required_order_tag',
        'access_token',
        'is_active',
        'is_default'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the default store
     */
    public static function getDefault()
    {
        return self::where('is_default', true)
                   ->where('is_active', true)
                   ->first();
    }

    /**
     * Set this store as the default store
     */
    public function setAsDefault()
    {
        // Remove default from all other stores
        self::where('id', '!=', $this->id)->update(['is_default' => false]);
        
        // Set this store as default
        $this->update(['is_default' => true, 'is_active' => true]);
        
        return $this;
    }

    /**
     * Get all active stores
     */
    public static function getActive()
    {
        return self::where('is_active', true)->get();
    }

    /**
     * Scope to filter active stores
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get masked access token for display
     */
    public function getMaskedAccessTokenAttribute()
    {
        if (!$this->access_token) {
            return '';
        }

        $token = $this->access_token;
        $length = strlen($token);
        
        if ($length <= 8) {
            return str_repeat('*', $length);
        }
        
        return substr($token, 0, 6) . str_repeat('*', $length - 10) . substr($token, -4);
    }
}
