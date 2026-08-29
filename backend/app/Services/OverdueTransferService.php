<?php

namespace App\Services;

use App\Models\Transfer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OverdueTransferService
{
    /**
     * Query for pending transfers whose due date has passed.
     *
     * Overdue is a derived condition; it never mutates the transfer or the
     * file's confirmed custody.
     */
    public function overdueQuery(): Builder
    {
        return Transfer::query()->overdue();
    }

    public function findOverdue(): Collection
    {
        return $this->overdueQuery()->get();
    }

    public function countOverdue(): int
    {
        return $this->overdueQuery()->count();
    }
}
