<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ServiceOrder;
use App\Models\User;

class ServiceOrderPolicy
{
    public function view(User $user, ServiceOrder $order): bool
    {
        if ($user->hasRole(UserRole::Owner, UserRole::Admin)) {
            return true;
        }

        if ($user->role === UserRole::Cashier) {
            return $order->created_by === $user->id;
        }

        return $this->isAssignedForMatchingService($user, $order);
    }

    public function updateStatus(User $user, ServiceOrder $order): bool
    {
        return $this->view($user, $order);
    }

    public function manageFinancials(User $user, ServiceOrder $order): bool
    {
        return $user->hasRole(UserRole::Owner, UserRole::Admin);
    }

    public function viewFiles(User $user, ServiceOrder $order): bool
    {
        if ($user->hasRole(UserRole::Owner, UserRole::Admin)) {
            return true;
        }

        return $this->isAssignedForMatchingService($user, $order);
    }

    private function isAssignedForMatchingService(User $user, ServiceOrder $order): bool
    {
        return $order->assigned_to === $user->id && match ($user->role) {
            UserRole::PrintOperator => in_array($order->type, ['print', 'fotokopi', 'scan', 'ketik'], true),
            UserRole::Designer => $order->type === 'desain',
            UserRole::Developer => in_array($order->type, ['website', 'aplikasi'], true),
            default => false,
        };
    }

    public function uploadResult(User $user, ServiceOrder $order): bool
    {
        return $this->viewFiles($user, $order);
    }
}
