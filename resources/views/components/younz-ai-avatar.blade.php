@props(['alt' => 'Avatar Younz AI'])

<span
    @if($alt !== '') role="img" aria-label="{{ $alt }}" @else aria-hidden="true" @endif
    {{ $attributes->class(['younz-ai-avatar block h-full w-full']) }}
>
    <span class="younz-ai-monogram" aria-hidden="true">Y</span>
</span>
