@props(['time' => false])

<input
    type="text"
    autocomplete="off"
    x-data
    x-init="
        flatpickr($el, {
            enableTime: {{ $time ? 'true' : 'false' }},
            time_24hr: true,
            dateFormat: '{{ $time ? 'Y-m-d H:i' : 'Y-m-d' }}',
            altInput: true,
            altFormat: '{{ $time ? 'd/m/Y H:i' : 'd/m/Y' }}',
            locale: '{{ app()->getLocale() === 'ar' ? 'ar' : 'default' }}',
            onChange: () => $el.dispatchEvent(new Event('input')),
        })
    "
    {{ $attributes }}
>
