<?php

namespace App\Enums;

enum ServiceOrderStatus: string
{
    case Draft = 'draft';
    case AwaitingReview = 'menunggu_pemeriksaan';
    case AiAnalyzed = 'dianalisis_ai';
    case AwaitingOperator = 'menunggu_konfirmasi_operator';
    case AwaitingCustomer = 'menunggu_persetujuan_pelanggan';
    case AwaitingPayment = 'menunggu_pembayaran';
    case Queued = 'masuk_antrean';
    case InProgress = 'sedang_dikerjakan';
    case AwaitingRevision = 'menunggu_revisi';
    case Ready = 'siap_diambil';
    case Completed = 'selesai';
    case Cancelled = 'dibatalkan';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::AwaitingReview, self::Cancelled],
            self::AwaitingReview => [self::AiAnalyzed, self::AwaitingOperator, self::Cancelled],
            self::AiAnalyzed => [self::AwaitingOperator, self::Cancelled],
            self::AwaitingOperator => [self::AwaitingCustomer, self::Queued, self::Cancelled],
            self::AwaitingCustomer => [self::AwaitingPayment, self::Queued, self::Cancelled],
            self::AwaitingPayment => [self::Queued, self::Cancelled],
            self::Queued => [self::InProgress, self::Cancelled],
            self::InProgress => [self::AwaitingRevision, self::Ready, self::Cancelled],
            self::AwaitingRevision => [self::InProgress, self::Ready, self::Cancelled],
            self::Ready => [self::AwaitingRevision, self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
