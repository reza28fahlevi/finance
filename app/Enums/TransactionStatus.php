<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Draft = 'DRAFT';
    case PendingReview = 'PENDING_REVIEW';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Journaled = 'JOURNALED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Journaled => 'Journaled',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Transisi status yang sah (RANCANGAN.md 4.1).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingReview, self::Cancelled],
            self::PendingReview => [self::Approved, self::Rejected],
            self::Rejected => [self::Draft],
            self::Approved => [self::Journaled],
            self::Journaled, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** Status yang masih boleh diedit oleh maker. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-surface-container-highest text-on-surface-variant',
            self::PendingReview => 'bg-secondary-container/70 text-on-secondary-container',
            self::Approved => 'bg-primary-container/20 text-primary',
            self::Rejected => 'bg-error-container/60 text-on-error-container',
            self::Journaled => 'bg-success-container/60 text-success',
            self::Cancelled => 'bg-surface-container-lowest text-outline',
        };
    }
}
