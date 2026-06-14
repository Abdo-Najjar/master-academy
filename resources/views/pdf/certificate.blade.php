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
    .qr-block img {
        width: 90px;
        height: 90px;
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

    @foreach($template->fields_config ?? [] as $field)
        @if(!empty($fieldValues[$field['key']]))
        <div class="field" style="
            left: {{ $field['x'] ?? 0 }}px;
            top: {{ $field['y'] ?? 0 }}px;
            font-size: {{ $field['font_size'] ?? 24 }}px;
            color: {{ $field['font_color'] ?? '#000000' }};
            font-weight: {{ $field['font_weight'] ?? 'normal' }};
            text-align: {{ $field['text_align'] ?? 'center' }};
            @if(!empty($field['width'])) width: {{ $field['width'] }}px; white-space: normal; @endif
        ">{{ $fieldValues[$field['key']] }}</div>
        @endif
    @endforeach

    <div class="qr-block">
        <img src="data:image/png;base64,{{ $qrPng }}" alt="QR">
        <div class="serial">{{ $certificate->serial_number }}</div>
    </div>
</body>
</html>
