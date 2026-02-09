<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StoreSettingsController extends Controller
{
    /**
     * Display a listing of stores
     */
    public function index()
    {
        $stores = Store::orderBy('is_default', 'desc')
                       ->orderBy('name')
                       ->get();

        return view('settings.stores', compact('stores'));
    }

    /**
     * Store a newly created store
     */
    public function store(Request $request)
    {
        \Log::info('Store creation request:', $request->all());
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'shop_domain' => 'required|string|max:255|unique:stores,shop_domain',
            'required_order_tag' => 'nullable|string|max:255',
            'access_token' => 'required|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            \Log::error('Store validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $store = Store::create([
                'name' => $request->name,
                'shop_domain' => $request->shop_domain,
                'required_order_tag' => $request->required_order_tag,
                'access_token' => $request->access_token,
                'is_active' => $request->input('is_active', true),
                'is_default' => false, // Don't allow setting default on creation
            ]);

            // If set as default checkbox was checked, set it as default
            if ($request->input('is_default', false)) {
                $store->setAsDefault();
            }

            \Log::info('Store created successfully:', ['id' => $store->id]);

            return response()->json([
                'success' => true,
                'message' => 'Store added successfully',
                'store' => $store
            ]);
        } catch (\Exception $e) {
            \Log::error('Store creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating store: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified store
     */
    public function update(Request $request, Store $store)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'shop_domain' => 'required|string|max:255|unique:stores,shop_domain,' . $store->id,
            'required_order_tag' => 'nullable|string|max:255',
            'access_token' => 'required|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $store->update([
            'name' => $request->name,
            'shop_domain' => $request->shop_domain,
            'required_order_tag' => $request->required_order_tag,
            'access_token' => $request->access_token,
            'is_active' => $request->input('is_active', false),
        ]);

        // Handle default store setting
        if ($request->input('is_default', false)) {
            $store->setAsDefault();
        } elseif ($store->is_default && !$request->input('is_default', false)) {
            $store->update(['is_default' => false]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Store updated successfully',
            'store' => $store->fresh()
        ]);
    }

    /**
     * Remove the specified store
     */
    public function destroy(Store $store)
    {
        // Prevent deleting the default store
        if ($store->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the default store. Please set another store as default first.'
            ], 422);
        }

        $store->delete();

        return response()->json([
            'success' => true,
            'message' => 'Store deleted successfully'
        ]);
    }

    /**
     * Set a store as the default store
     */
    public function setDefault(Store $store)
    {
        $store->setAsDefault();

        return response()->json([
            'success' => true,
            'message' => 'Default store updated successfully'
        ]);
    }

    /**
     * Toggle store active status
     */
    public function toggleActive(Store $store)
    {
        // Prevent deactivating the default store
        if ($store->is_default && $store->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot deactivate the default store.'
            ], 422);
        }

        $store->update(['is_active' => !$store->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Store status updated successfully',
            'is_active' => $store->is_active
        ]);
    }

    /**
     * Get a specific store (for editing)
     */
    public function show(Store $store)
    {
        return response()->json([
            'success' => true,
            'store' => $store
        ]);
    }
}
