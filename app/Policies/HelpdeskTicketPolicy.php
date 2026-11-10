<?php

namespace App\Policies;

use App\Models\HelpdeskTicket;
use App\Models\User;

class HelpdeskTicketPolicy
{
    public function before(User $user): ?bool
    {
        return $user->type === 'superadmin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('manage-helpdesk-tickets');
    }

    public function view(User $user, HelpdeskTicket $ticket): bool
    {
        return $user->can('view-helpdesk-tickets') && $ticket->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('create-helpdesk-tickets');
    }

    public function update(User $user, HelpdeskTicket $ticket): bool
    {
        return $user->can('edit-helpdesk-tickets') && $ticket->isVisibleTo($user);
    }

    public function assign(User $user, HelpdeskTicket $ticket): bool
    {
        return $user->can('assign-helpdesk-tickets') && $ticket->isVisibleTo($user);
    }

    public function delete(User $user, HelpdeskTicket $ticket): bool
    {
        return $user->can('delete-helpdesk-tickets') && $ticket->isVisibleTo($user);
    }

    public function reply(User $user, HelpdeskTicket $ticket): bool
    {
        return $user->can('create-helpdesk-replies') && $ticket->isVisibleTo($user);
    }

    public function deleteReply(User $user, HelpdeskTicket $ticket): bool
    {
        return $user->can('delete-helpdesk-replies') && $ticket->isVisibleTo($user);
    }

    public function viewInternalNotes(User $user, HelpdeskTicket $ticket): bool
    {
        return $user->isSupportSide() && $user->can('view-helpdesk-internal-notes') && $ticket->isVisibleTo($user);
    }
}
