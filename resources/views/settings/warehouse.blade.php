@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">⚙️ Warehouse API Settings</h4>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('settings.warehouse.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label for="warehouse_api_url" class="form-label fw-bold">API URL</label>
                            <input type="url" 
                                   class="form-control @error('warehouse_api_url') is-invalid @enderror" 
                                   id="warehouse_api_url" 
                                   name="warehouse_api_url" 
                                   value="{{ old('warehouse_api_url', $settings->where('key', 'warehouse_api_url')->first()->value ?? '') }}"
                                   required>
                            <div class="form-text">The warehouse API endpoint URL</div>
                            @error('warehouse_api_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="warehouse_api_token" class="form-label fw-bold">API Bearer Token</label>
                            <textarea class="form-control @error('warehouse_api_token') is-invalid @enderror" 
                                      id="warehouse_api_token" 
                                      name="warehouse_api_token" 
                                      rows="8"
                                      required>{{ old('warehouse_api_token', $settings->where('key', 'warehouse_api_token')->first()->value ?? '') }}</textarea>
                            <div class="form-text">The JWT Bearer token for authentication</div>
                            @error('warehouse_api_token')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="default_location_id" class="form-label fw-bold">Default Location</label>
                            <select class="form-select @error('default_location_id') is-invalid @enderror" 
                                    id="default_location_id" 
                                    name="default_location_id">
                                <option value="">-- No Default (Show All Locations) --</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location['id'] }}" 
                                            {{ old('default_location_id', $settings->where('key', 'default_location_id')->first()->value ?? '') == $location['id'] ? 'selected' : '' }}>
                                        {{ $location['name'] }} @if($location['city'])({{ $location['city'] }}, {{ $location['country'] }})@endif
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">This location will be automatically selected on the stock sync page</div>
                            @error('default_location_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-info">
                            <strong>ℹ️ Note:</strong> Changing these settings will clear the warehouse cache. The new credentials will be used for all subsequent API calls.
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                                ← Back to Home
                            </a>
                            <button type="submit" class="btn btn-primary">
                                💾 Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Current Settings Info -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">📊 Current Settings</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <th width="200">API URL:</th>
                                <td><code>{{ $settings->where('key', 'warehouse_api_url')->first()->value ?? 'Not set' }}</code></td>
                            </tr>
                            <tr>
                                <th>Token Status:</th>
                                <td>
                                    @php
                                        $token = $settings->where('key', 'warehouse_api_token')->first()->value ?? '';
                                    @endphp
                                    @if(empty($token))
                                        <span class="badge bg-danger">Not configured</span>
                                    @else
                                        <span class="badge bg-success">Configured</span>
                                        <small class="text-muted">({{ strlen($token) }} characters)</small>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Last Updated:</th>
                                <td>{{ $settings->where('key', 'warehouse_api_token')->first()->updated_at->format('Y-m-d H:i:s') ?? 'Never' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
