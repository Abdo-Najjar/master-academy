<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        width: {{ $template->canvas_width }}px;
        height: {{ $template->canvas_height }}px;
        position: relative;
        overflow: hidden;
        font-family: 'DejaVu Sans', Arial, sans-serif;
    }
    .bg {
        position: absolute;
        top: 0; left: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
    }
    .field {
        position: absolute;
        z-index: 10;
        white-space: nowrap;
        line-height: 1.2;
    }
    .qr-block {
        position: absolute;
        z-index: 10;
        bottom: 20px;
        right: 20px;
    }
    .qr-block svg {
        width: 90px;
        height: 90px;
    }
    .field svg {
        width: 100%;
        height: 100%;
        display: block;
    }
    .serial {
        font-size: 10px;
        text-align: center;
        color: #666;
        margin-top: 2px;
    }
</style>
</head>
<body>
    @if($bgDataUri)
        <img class="bg" src="{{ $bgDataUri }}" alt="">
    @endif

    @php $hasQrField = false; @endphp
    @foreach($template->fields_config ?? [] as $field)
        @php $key = $field['key'] ?? ''; @endphp
        @if($key === 'qr_code')
            @php $hasQrField = true; $qrSize = (int) ($field['size'] ?? 140); @endphp
            <div class="field" style="left: {{ $field['x'] ?? 0 }}px; top: {{ $field['y'] ?? 0 }}px; width: {{ $qrSize }}px; height: {{ $qrSize }}px;">
                {!! $qrSvg !!}
            </div>
        @elseif(!empty($fieldValues[$key]))
        <div class="field" style="
            left: {{ $field['x'] ?? 0 }}px;
            top: {{ $field['y'] ?? 0 }}px;
            font-size: {{ $field['font_size'] ?? 24 }}px;
            color: {{ $field['font_color'] ?? '#000000' }};
            font-weight: {{ $field['font_weight'] ?? 'normal' }};
            text-align: {{ $field['text_align'] ?? 'center' }};
            font-family: {{ $field['font_family'] ?? 'dejavusans' }};
            @if(!empty($field['width'])) width: {{ $field['width'] }}px; white-space: normal; @endif
        ">{{ $fieldValues[$key] }}</div>
        @endif
    @endforeach

    {{-- Fallback QR (bottom-right) only when no QR field was placed in the design --}}
    @unless($hasQrField)
    <div class="qr-block">
        {!! $qrSvg !!}
        <div class="serial">{{ $certificate->serial_number }}</div>
    </div>
    @endunless
</body>
</html>
