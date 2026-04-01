<?php

namespace App\Models;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'shop_domain',
        'required_order_tag',
        'access_token',
        'client_id',
        'client_secret',
        'is_active',
        'is_default'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Return a valid Shopify access token for this store.
     *
     * If the store has client_id + client_secret configured, the token is
     * obtained via the OAuth client-credentials flow and cached until Shopify
     * says it expires (with a 60-second safety buffer).
     * Falls back to the static access_token stored in the database.
     */
    public function getToken(): string
    {
        if (empty($this->client_id) || empty($this->client_secret)) {
            return $this->access_token ?? '';
        }

        $cacheKey = 'shopify_store_token_' . md5($this->shop_domain . $this->client_id);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = (new Client())->post("https://{$this->shop_domain}/admin/oauth/access_token", [
                'json' => [
                    'client_id'     => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'grant_type'    => 'client_credentials',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data['access_token'])) {
                Log::error("Shopify OAuth [{$this->name}]: unexpected response", $data ?? []);
                return $this->access_token ?? '';
            }

            $ttl   = max(60, (int) ($data['expires_in'] ?? 86399) - 60);
            $token = $data['access_token'];

            Cache::put($cacheKey, $token, now()->addSeconds($ttl));

            Log::info("Shopify OAuth [{$this->name}]: new access token obtained", [
                'expires_in' => $data['expires_in'] ?? 'unknown',
                'cached_ttl' => $ttl,
            ]);

            return $token;
        } catch (RequestException $e) {
            Log::error("Shopify OAuth [{$this->name}]: token request failed", [
                'message'  => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);

            return $this->access_token ?? '';
        }
    }

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
