@extends('layouts.app')

@section('page-title', 'Settings')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">🏪 Shopify Store API Keys</h4>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#storeModal" onclick="openAddStoreModal()">
                        <i class="bi bi-plus-circle"></i> Add Shopify Store
                    </button>
                </div>
                <div class="card-body">
                    @if($stores->isEmpty())
                        <div class="text-center py-5">
                            <i class="bi bi-shop" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3 text-muted">No Shopify store configured yet.</p>
                            <p class="text-muted">Click "Add Shopify Store" to configure your first store.</p>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> <strong>Note:</strong> All access tokens are encrypted for security. The default store will be used for all system operations.
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Store Name</th>
                                        <th>Shop Domain</th>
                                        <th>Order Tag</th>
                                        <th>Access Token</th>
                                        <th>Status</th>
                                        <th>Default</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($stores as $store)
                                        <tr id="store-row-{{ $store->id }}">
                                            <td>
                                                <strong>{{ $store->name }}</strong>
                                                @if($store->is_default)
                                                    <span class="badge bg-primary ms-2">Default</span>
                                                @endif
                                            </td>
                                            <td>
                                                <code>{{ $store->shop_domain }}</code>
                                            </td>
                                            <td>
                                                @if($store->required_order_tag)
                                                    <span class="badge bg-secondary">{{ $store->required_order_tag }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <code class="text-muted">{{ $store->masked_access_token }}</code>
                                            </td>
                                            <td>
                                                <button type="button" 
                                                        class="btn btn-sm btn-toggle-status {{ $store->is_active ? 'btn-success' : 'btn-secondary' }}"
                                                        onclick="toggleStoreStatus({{ $store->id }})"
                                                        {{ $store->is_default ? 'disabled' : '' }}>
                                                    <i class="bi bi-{{ $store->is_active ? 'check-circle' : 'x-circle' }}"></i>
                                                    <span class="status-text">{{ $store->is_active ? 'Active' : 'Inactive' }}</span>
                                                </button>
                                            </td>
                                            <td>
                                                @if(!$store->is_default)
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-primary"
                                                            onclick="setDefaultStore({{ $store->id }})">
                                                        Set as Default
                                                    </button>
                                                @else
                                                    <span class="text-success">
                                                        <i class="bi bi-check-circle-fill"></i> Default
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-secondary"
                                                            onclick="editStore({{ $store->id }})"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#storeModal">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="deleteStore({{ $store->id }})"
                                                            {{ $store->is_default ? 'disabled' : '' }}>
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Store Modal -->
<div class="modal fade" id="storeModal" tabindex="-1" aria-labelledby="storeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="storeModalLabel">Add Shopify Store</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="storeForm">
                @csrf
                <input type="hidden" id="storeId" name="store_id">
                <input type="hidden" id="formMethod" value="POST">
                
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="formErrors"></div>

                    <div class="mb-3">
                        <label for="storeName" class="form-label">Store Name</label>
                        <input type="text" 
                               class="form-control" 
                               id="storeName" 
                               name="name" 
                               placeholder="My Store"
                               required>
                        <div class="invalid-feedback" id="error-name"></div>
                    </div>

                    <div class="mb-3">
                        <label for="shopDomain" class="form-label">Shop Domain</label>
                        <input type="text" 
                               class="form-control" 
                               id="shopDomain" 
                               name="shop_domain" 
                               placeholder="store.myshopify.com"
                               required>
                        <div class="invalid-feedback" id="error-shop_domain"></div>
                    </div>

                    <div class="mb-3">
                        <label for="requiredOrderTag" class="form-label">Required Order Tag (optional)</label>
                        <input type="text" 
                               class="form-control" 
                               id="requiredOrderTag" 
                               name="required_order_tag" 
                               placeholder="member-purchase">
                        <small class="form-text text-muted">If set, only orders with this tag will be fetched for voucher eligibility.</small>
                        <div class="invalid-feedback" id="error-required_order_tag"></div>
                    </div>

                    <div class="mb-3">
                        <label for="accessToken" class="form-label">Access Token</label>
                        <textarea class="form-control" 
                                  id="accessToken" 
                                  name="access_token" 
                                  rows="3"
                                  placeholder="shpat_..."
                                  required></textarea>
                        <div class="invalid-feedback" id="error-access_token"></div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" 
                               class="form-check-input" 
                               id="isActive" 
                               name="is_active" 
                               value="1"
                               checked>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" 
                               class="form-check-input" 
                               id="isDefault" 
                               name="is_default" 
                               value="1">
                        <label class="form-check-label" for="isDefault">Set as Default Store</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Add Store</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
let currentStoreId = null;

// Open modal for adding new store
function openAddStoreModal() {
    currentStoreId = null;
    document.getElementById('storeModalLabel').textContent = 'Add Shopify Store';
    document.getElementById('submitBtn').textContent = 'Add Store';
    document.getElementById('storeForm').reset();
    document.getElementById('storeId').value = '';
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('formErrors').classList.add('d-none');
    
    // Clear all error states
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
}

// Edit store
async function editStore(storeId) {
    currentStoreId = storeId;
    document.getElementById('storeModalLabel').textContent = 'Edit Shopify Store';
    document.getElementById('submitBtn').textContent = 'Update Store';
    document.getElementById('storeId').value = storeId;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('formErrors').classList.add('d-none');

    try {
        const response = await fetch(`/settings/stores/${storeId}`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('storeName').value = data.store.name;
            document.getElementById('shopDomain').value = data.store.shop_domain;
            document.getElementById('requiredOrderTag').value = data.store.required_order_tag || '';
            document.getElementById('accessToken').value = data.store.access_token;
            document.getElementById('isActive').checked = data.store.is_active;
            document.getElementById('isDefault').checked = data.store.is_default;
        }
    } catch (error) {
        console.error('Error fetching store:', error);
        alert('Error loading store details');
    }
}

// Submit form (Add or Edit)
document.getElementById('storeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const method = document.getElementById('formMethod').value;
    const storeId = document.getElementById('storeId').value;
    const url = storeId ? `/settings/stores/${storeId}` : '/settings/stores';
    
    // Build data object from form fields
    const data = {
        name: document.getElementById('storeName').value,
        shop_domain: document.getElementById('shopDomain').value,
        required_order_tag: document.getElementById('requiredOrderTag').value || null,
        access_token: document.getElementById('accessToken').value,
        is_active: document.getElementById('isActive').checked,
        is_default: document.getElementById('isDefault').checked
    };

    try {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            // Close modal and reload page
            const modal = bootstrap.Modal.getInstance(document.getElementById('storeModal'));
            if (modal) {
                modal.hide();
            }
            location.reload();
        } else {
            // Show errors
            displayErrors(result.errors);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
});

// Display form errors
function displayErrors(errors) {
    // Clear previous errors
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    
    let errorHtml = '<ul class="mb-0">';
    for (const [field, messages] of Object.entries(errors)) {
        messages.forEach(message => {
            errorHtml += `<li>${message}</li>`;
        });
        
        // Mark field as invalid
        const fieldElement = document.getElementById(field === 'shop_domain' ? 'shopDomain' : 
                                                     field === 'required_order_tag' ? 'requiredOrderTag' :
                                                     field === 'access_token' ? 'accessToken' :
                                                     field === 'name' ? 'storeName' : field);
        if (fieldElement) {
            fieldElement.classList.add('is-invalid');
            const errorDiv = document.getElementById(`error-${field}`);
            if (errorDiv) {
                errorDiv.textContent = messages[0];
            }
        }
    }
    errorHtml += '</ul>';
    
    const errorDiv = document.getElementById('formErrors');
    errorDiv.innerHTML = errorHtml;
    errorDiv.classList.remove('d-none');
}

// Delete store
async function deleteStore(storeId) {
    if (!confirm('Are you sure you want to delete this store? This action cannot be undone.')) {
        return;
    }

    try {
        const response = await fetch(`/settings/stores/${storeId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const result = await response.json();

        if (result.success) {
            location.reload();
        } else {
            alert(result.message || 'Error deleting store');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
}

// Set default store
async function setDefaultStore(storeId) {
    try {
        const response = await fetch(`/settings/stores/${storeId}/set-default`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const result = await response.json();

        if (result.success) {
            location.reload();
        } else {
            alert(result.message || 'Error setting default store');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
}

// Toggle store status
async function toggleStoreStatus(storeId) {
    try {
        const response = await fetch(`/settings/stores/${storeId}/toggle-active`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const result = await response.json();

        if (result.success) {
            location.reload();
        } else {
            alert(result.message || 'Error toggling store status');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
}
</script>
@endsection
