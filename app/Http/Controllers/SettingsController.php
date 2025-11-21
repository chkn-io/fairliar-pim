<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Services\ShopifyService;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    private $shopifyService;

    public function __construct(ShopifyService $shopifyService)
    {
        $this->shopifyService = $shopifyService;
    }

    /**
     * Display warehouse settings
     */
    public function warehouse()
    {
        $settings = Setting::where('group', 'warehouse')->get();
        $locations = $this->shopifyService->getLocations();
        
        return view('settings.warehouse', [
            'settings' => $settings,
            'locations' => $locations
        ]);
    }

    /**
     * Update warehouse settings
     */
    public function updateWarehouse(Request $request)
    {
        $request->validate([
            'warehouse_api_url' => 'required|url',
            'warehouse_api_token' => 'required|string',
            'default_location_id' => 'nullable|string'
        ]);

        Setting::set('warehouse_api_url', $request->warehouse_api_url, 'string', 'warehouse', 'Warehouse API endpoint URL');
        Setting::set('warehouse_api_token', $request->warehouse_api_token, 'text', 'warehouse', 'Warehouse API Bearer Token');
        Setting::set('default_location_id', $request->default_location_id, 'string', 'warehouse', 'Default Shopify Location ID');

        // Clear warehouse cache when settings change
        Cache::forget('warehouse_all_variants');

        return redirect()->route('settings.warehouse')->with('success', 'Warehouse settings updated successfully');
    }
}
