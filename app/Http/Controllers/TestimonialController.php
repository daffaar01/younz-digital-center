<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestimonialRequest;
use App\Jobs\SendTestimonialDiscordNotification;
use App\Models\Testimonial;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TestimonialController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('testimonials.index', [
            'testimonials' => Testimonial::query()->orderBy('display_order')->latest()->paginate(15),
        ]);
    }

    public function store(TestimonialRequest $request): RedirectResponse
    {
        $testimonial = Testimonial::create($this->payload($request) + [
            'created_by' => $request->user()->id,
        ]);
        $this->audit->log('testimonial.created', $testimonial, after: $testimonial->toArray());

        if ($testimonial->is_published) {
            SendTestimonialDiscordNotification::dispatch($testimonial->id);
        }

        return back()->with('status', 'Testimoni berhasil ditambahkan.');
    }

    public function update(TestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $before = $testimonial->toArray();
        $testimonial->update($this->payload($request));
        $this->audit->log('testimonial.updated', $testimonial, $before, $testimonial->fresh()->toArray());

        if ($testimonial->is_published && ! ($before['is_published'] ?? false)) {
            SendTestimonialDiscordNotification::dispatch($testimonial->id);
        }

        return back()->with('status', 'Testimoni berhasil diperbarui.');
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $before = $testimonial->toArray();
        $this->audit->log('testimonial.deleted', $testimonial, $before);
        $testimonial->delete();

        return back()->with('status', 'Testimoni berhasil dihapus.');
    }

    private function payload(TestimonialRequest $request): array
    {
        return $request->safe()->except('is_published') + [
            'is_published' => $request->boolean('is_published'),
        ];
    }
}
