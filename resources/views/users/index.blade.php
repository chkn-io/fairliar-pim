@extends('layouts.app')

@section('title', 'Users')
@section('page-title', 'User Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-3">
                <h1 class="h3 mb-0">
                    <i class="bi bi-people text-primary"></i> Users
                </h1>
                @if($canManage)
                    <a href="{{ route('users.create') }}" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Add User
                    </a>
                @endif
            </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">All Users ({{ $users->total() }})</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            @if($canManage)
                                <th>Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-person-circle text-muted me-2 fs-4"></i>
                                    <div>
                                        <strong>{{ $user->name }}</strong>
                                        @if($user->id === Auth::id())
                                            <span class="badge bg-info ms-1">You</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <span class="badge fs-6 {{ $user->isAdmin() ? 'bg-danger' : 'bg-primary' }}">
                                    <i class="bi bi-{{ $user->isAdmin() ? 'shield-fill-check' : 'person' }}"></i>
                                    {{ $user->getRoleDisplayName() }}
                                </span>
                            </td>
                            <td>
                                <span class="badge fs-6 {{ $user->isActive() ? 'bg-success' : 'bg-secondary' }}">
                                    <i class="bi bi-{{ $user->isActive() ? 'check-circle' : 'x-circle' }}"></i>
                                    {{ $user->isActive() ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <small>{{ $user->created_at->format('M j, Y') }}</small>
                                <br><small class="text-muted">{{ $user->created_at->format('g:i A') }}</small>
                            </td>
                            @if($canManage)
                            <td>
                                <div class="btn-group-vertical btn-group-sm">
                                    <a href="{{ route('users.show', $user) }}" class="btn btn-outline-info btn-sm">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    @if($user->id !== Auth::id())
                                        <form method="POST" action="{{ route('users.destroy', $user) }}" 
                                              onsubmit="return confirm('Are you sure you want to delete this user?')" 
                                              style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $canManage ? 6 : 5 }}" class="text-center py-4">
                                <i class="bi bi-people text-muted fs-1"></i>
                                <p class="text-muted mb-0">No users found.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($users->hasPages())
            <div class="card-footer">
                {{ $users->links() }}
            </div>
            @endif
        </div>
        </div>
    </div>
</div>
@endsection