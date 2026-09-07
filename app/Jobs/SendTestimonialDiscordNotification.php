<?php

namespace App\Jobs;

use App\Integrations\Discord\DiscordNotifier;
use App\Models\Testimonial;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTestimonialDiscordNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 30;
    public array $backoff = [30, 120, 300, 900];
    public int $uniqueFor = 86400;

    public function __construct(public readonly int $testimonialId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return "testimonial:{$this->testimonialId}:discord";
    }

    public function handle(DiscordNotifier $notifier): void
    {
        $testimonial = Testimonial::query()->find($this->testimonialId);
        if ($testimonial === null || ! $testimonial->is_published) {
            return;
        }

        $rating = (int) ($testimonial->rating ?? 0);
        $stars = $rating > 0 ? str_repeat('⭐', min(5, $rating)) : '-';

        $notifier->notify(
            event: 'testimonial.approved',
            title: 'Testimoni Baru',
            message: (string) $testimonial->quote,
            fields: [
                ['name' => 'Nama', 'value' => (string) ($testimonial->customer_name ?? 'Pelanggan'), 'inline' => true],
                ['name' => 'Rating', 'value' => $stars, 'inline' => true],
            ],
            reference: 'TESTI-'.$testimonial->id,
        );
    }
}
