@props(['route', 'label', 'icon', 'matches' => []])
@php($active = request()->routeIs(...($matches ?: [$route])))

<a href="{{ route($route) }}"
   @class(['app-nav-link', 'app-nav-link-active' => $active])
   @if($active) aria-current="page" @endif>
    <span class="app-nav-icon" aria-hidden="true">
        @switch($icon)
            @case('dashboard')
                
                @break
            @case('pos')
                
                @break
            @case('sales')
                
                @break
            @case('customers')
                
                @break
            @case('digital')
                
                @break
            @case('orders')
                
                @break
            @case('products')
                
                @break
            @case('suppliers')
                
                @break
            @case('expenses')
                
                @break
            @case('reports')
                
                @break
            @case('approvals')
                
                @break
            @case('knowledge')
                
                @break
            @case('testimonials')
                
                @break
            @case('employees')
                
                @break
            @case('whatsapp')
                
                @break
        @endswitch
    </span>
    <span>{{ $label }}</span>
</a>
