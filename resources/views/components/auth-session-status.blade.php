@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-madrasah-600']) }}>
        {{ $status }}
    </div>
@endif
