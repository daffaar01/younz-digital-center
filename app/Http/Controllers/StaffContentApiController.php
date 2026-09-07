<?php

namespace App\Http\Controllers;

use App\Jobs\SendTestimonialDiscordNotification;
use App\Models\AiUsageLog;
use App\Models\AnalyticsEvent;
use App\Models\KnowledgeDocument;
use App\Models\Service;
use App\Models\Testimonial;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffContentApiController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeContent($request);
        $data = $request->validate([
            'days' => ['nullable', Rule::in([7, 30, 90])],
        ]);
        $days = (int) ($data['days'] ?? 30);
        $from = now()->subDays($days)->startOfDay();

        $analyticsBase = AnalyticsEvent::query()->where('created_at', '>=', $from);
        $landingViews = (clone $analyticsBase)->where('event_name', 'page_view')->where('path', '/')->count();
        $ctaClicks = (clone $analyticsBase)->where('event_name', 'order_cta_clicked')->count();
        $formStarts = (clone $analyticsBase)->where('event_name', 'order_form_started')->count();
        $submitted = (clone $analyticsBase)->where('event_name', 'order_submitted')->count();

        $aiBase = AiUsageLog::query()
            ->where('feature', 'faq_chat')
            ->where('created_at', '>=', $from);
        $aiTotal = (clone $aiBase)->count();
        $aiSuccess = (clone $aiBase)->where('status', 'success')->count();
        $helpful = (clone $aiBase)->where('feedback', 'helpful')->count();
        $notHelpful = (clone $aiBase)->where('feedback', 'not_helpful')->count();

        return response()->json(['data' => [
            'period_days' => $days,
            'services' => Service::query()
                ->withCount('orders')
                ->orderByDesc('is_active')
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'type', 'base_price', 'unit', 'description', 'is_active', 'updated_at']),
            'knowledge_documents' => KnowledgeDocument::query()
                ->latest('updated_at')
                ->get(['id', 'title', 'type', 'content', 'status', 'updated_at']),
            'testimonials' => Testimonial::query()
                ->orderBy('display_order')
                ->latest()
                ->get(['id', 'customer_name', 'customer_role', 'quote', 'rating', 'source_label', 'is_published', 'consent_at', 'display_order', 'updated_at']),
            'analytics' => [
                'landing_views' => $landingViews,
                'order_cta_clicks' => $ctaClicks,
                'order_form_starts' => $formStarts,
                'orders_submitted' => $submitted,
                'landing_to_order_rate' => $landingViews > 0 ? round(($submitted / $landingViews) * 100, 1) : 0,
                'cta_sources' => (clone $analyticsBase)
                    ->where('event_name', 'order_cta_clicked')
                    ->whereNotNull('source')
                    ->selectRaw('source, COUNT(*) as total')
                    ->groupBy('source')
                    ->orderByDesc('total')
                    ->limit(8)
                    ->get()
                    ->map(fn (AnalyticsEvent $event): array => [
                        'source' => $event->source,
                        'total' => (int) $event->getAttribute('total'),
                    ])->values(),
            ],
            'ai' => [
                'total' => $aiTotal,
                'success' => $aiSuccess,
                'failed' => max(0, $aiTotal - $aiSuccess),
                'helpful' => $helpful,
                'not_helpful' => $notHelpful,
                'helpful_rate' => ($helpful + $notHelpful) > 0
                    ? round(($helpful / ($helpful + $notHelpful)) * 100, 1)
                    : 0,
                'input_tokens' => (int) (clone $aiBase)->sum('input_tokens'),
                'output_tokens' => (int) (clone $aiBase)->sum('output_tokens'),
                'cost_micros' => (int) (clone $aiBase)->sum('cost_micros'),
                'providers' => (clone $aiBase)
                    ->selectRaw('provider, COUNT(*) as total')
                    ->groupBy('provider')
                    ->orderByDesc('total')
                    ->get()
                    ->map(fn (AiUsageLog $log): array => [
                        'provider' => $log->provider,
                        'total' => (int) $log->getAttribute('total'),
                    ])->values(),
                'recent_feedback' => (clone $aiBase)
                    ->whereNotNull('feedback')
                    ->latest('feedback_at')
                    ->limit(10)
                    ->get()
                    ->map(fn (AiUsageLog $log): array => [
                        'id' => $log->id,
                        'provider' => $log->provider,
                        'model' => $log->model,
                        'status' => $log->status,
                        'feedback' => $log->feedback,
                        'grounding' => data_get($log->metadata, 'grounding'),
                        'sources_count' => count((array) data_get($log->metadata, 'sources', [])),
                        'created_at' => $log->created_at?->toIso8601String(),
                        'feedback_at' => $log->feedback_at?->toIso8601String(),
                    ])->values(),
            ],
        ]]);
    }

    public function storeService(Request $request): JsonResponse
    {
        $this->authorizeContent($request);
        $data = $this->validateService($request);
        $service = Service::create($this->servicePayload($request, $data));
        $this->audit->log('service.created', $service, after: $service->toArray());

        return response()->json(['message' => 'Layanan berhasil ditambahkan.', 'data' => $service], 201);
    }

    public function updateService(Request $request, Service $service): JsonResponse
    {
        $this->authorizeContent($request);
        $data = $this->validateService($request);
        $before = $service->toArray();
        $service->update($this->servicePayload($request, $data, $service));
        $this->audit->log('service.updated', $service, $before, $service->fresh()->toArray());

        return response()->json(['message' => 'Layanan berhasil diperbarui.', 'data' => $service->fresh()]);
    }

    public function destroyService(Request $request, Service $service): JsonResponse
    {
        $this->authorizeContent($request);
        if ($service->orders()->exists()) {
            return response()->json([
                'message' => 'Layanan sudah dipakai pesanan. Nonaktifkan layanan agar riwayat tetap utuh.',
            ], 422);
        }

        $before = $service->toArray();
        $this->audit->log('service.deleted', $service, $before);
        $service->delete();

        return response()->json(['message' => 'Layanan berhasil dihapus.']);
    }

    public function storeKnowledge(Request $request): JsonResponse
    {
        $this->authorizeContent($request);
        $data = $this->validateKnowledge($request);
        $document = KnowledgeDocument::create($data + ['created_by' => $request->user()->id]);
        $this->audit->log('knowledge_document.created', $document, after: $document->toArray());

        return response()->json(['message' => 'Dokumen pengetahuan berhasil ditambahkan.', 'data' => $document], 201);
    }

    public function updateKnowledge(Request $request, KnowledgeDocument $document): JsonResponse
    {
        $this->authorizeContent($request);
        $data = $this->validateKnowledge($request);
        $before = $document->toArray();
        $document->update($data);
        $this->audit->log('knowledge_document.updated', $document, $before, $document->fresh()->toArray());

        return response()->json(['message' => 'Dokumen pengetahuan berhasil diperbarui.', 'data' => $document->fresh()]);
    }

    public function destroyKnowledge(Request $request, KnowledgeDocument $document): JsonResponse
    {
        $this->authorizeContent($request);
        $before = $document->toArray();
        $this->audit->log('knowledge_document.deleted', $document, $before);
        $document->delete();

        return response()->json(['message' => 'Dokumen pengetahuan berhasil dihapus.']);
    }

    public function storeTestimonial(Request $request): JsonResponse
    {
        $this->authorizeContent($request);
        $data = $this->validateTestimonial($request);
        $testimonial = Testimonial::create($this->testimonialPayload($request, $data) + [
            'created_by' => $request->user()->id,
        ]);
        $this->audit->log('testimonial.created', $testimonial, after: $testimonial->toArray());

        if ($testimonial->is_published) {
            SendTestimonialDiscordNotification::dispatch($testimonial->id);
        }

        return response()->json(['message' => 'Testimoni berhasil ditambahkan.', 'data' => $testimonial], 201);
    }

    public function updateTestimonial(Request $request, Testimonial $testimonial): JsonResponse
    {
        $this->authorizeContent($request);
        $data = $this->validateTestimonial($request);
        $before = $testimonial->toArray();
        $testimonial->update($this->testimonialPayload($request, $data));
        $this->audit->log('testimonial.updated', $testimonial, $before, $testimonial->fresh()->toArray());

        if ($testimonial->is_published && ! ($before['is_published'] ?? false)) {
            SendTestimonialDiscordNotification::dispatch($testimonial->id);
        }

        return response()->json(['message' => 'Testimoni berhasil diperbarui.', 'data' => $testimonial->fresh()]);
    }

    public function destroyTestimonial(Request $request, Testimonial $testimonial): JsonResponse
    {
        $this->authorizeContent($request);
        $before = $testimonial->toArray();
        $this->audit->log('testimonial.deleted', $testimonial, $before);
        $testimonial->delete();

        return response()->json(['message' => 'Testimoni berhasil dihapus.']);
    }

    private function authorizeContent(Request $request): void
    {
        abort_unless(
            $request->user()->isStaff()
            && (
                $request->user()->tokenCan('staff:content')
                || $request->user()->tokenCan('staff:dashboard')
            )
            && $request->user()->hasRole('owner', 'admin'),
            403,
        );
    }

    /** @return array<string, mixed> */
    private function validateService(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['print', 'fotokopi', 'scan', 'ketik', 'desain', 'website', 'aplikasi'])],
            'base_price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'unit' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function servicePayload(Request $request, array $data, ?Service $service = null): array
    {
        $slugBase = Str::slug($data['name']) ?: 'layanan';
        $slug = $slugBase;
        $suffix = 2;
        while (Service::query()
            ->where('slug', $slug)
            ->when($service, fn ($query) => $query->whereKeyNot($service->id))
            ->exists()) {
            $slug = $slugBase.'-'.$suffix++;
        }

        return $data + [
            'slug' => $slug,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /** @return array<string, mixed> */
    private function validateKnowledge(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::in(['faq', 'policy', 'privacy', 'service', 'guide'])],
            'content' => ['required', 'string', 'max:20000'],
            'status' => ['required', Rule::in(['active', 'draft', 'archived'])],
        ]);
    }

    /** @return array<string, mixed> */
    private function validateTestimonial(Request $request): array
    {
        return $request->validate([
            'customer_name' => ['required', 'string', 'max:80'],
            'customer_role' => ['nullable', 'string', 'max:100'],
            'quote' => ['required', 'string', 'max:800'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'source_label' => ['nullable', 'string', 'max:80'],
            'is_published' => ['required', 'boolean'],
            'consent_at' => ['nullable', 'required_if:is_published,true', 'date', 'before_or_equal:now'],
            'display_order' => ['required', 'integer', 'between:0,999'],
        ]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function testimonialPayload(Request $request, array $data): array
    {
        return $data + ['is_published' => $request->boolean('is_published')];
    }
}
