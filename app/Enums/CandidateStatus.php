<?php

declare(strict_types=1);

namespace App\Enums;

enum CandidateStatus: string
{
    case Scheduled = 'scheduled';
    case InReview = 'in_review';
    case Shortlisted = 'shortlisted';
    case OnHold = 'on_hold';
    case Offer = 'offer';
    case Hired = 'hired';
    case OfferDeclined = 'offer_declined';
    case Rejected = 'rejected';
    case Blacklisted = 'blacklisted';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::InReview => 'In Review',
            self::Shortlisted => 'Shortlisted',
            self::OnHold => 'On Hold',
            self::Offer => 'Offer',
            self::Hired => 'Hired',
            self::OfferDeclined => 'Offer Declined',
            self::Rejected => 'Rejected',
            self::Blacklisted => 'Blacklisted',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Scheduled => 'label-info',
            self::InReview => 'label-primary',
            self::Shortlisted => 'label-light-primary',
            self::OnHold => 'label-warning',
            self::Offer => 'label-light-success',
            self::Hired => 'label-success',
            self::OfferDeclined => 'label-light-danger',
            self::Rejected => 'label-danger',
            self::Blacklisted => 'label-dark',
        };
    }
}
