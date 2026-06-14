<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ __('Certificate Verification') }}</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-green-50 to-emerald-100 flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 text-center">
        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-green-700 mb-2">{{ __('Certificate Verified') }} ✓</h1>
        <p class="text-gray-500 text-sm mb-6">{{ __('This is an authentic certificate issued by') }} <strong>منبع التميز</strong></p>

        <div class="text-start bg-gray-50 rounded-xl p-4 space-y-3 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500">{{ __('Student Name') }}</span>
                <span class="font-semibold">
                    {{ is_array($certificate->student?->name)
                        ? ($certificate->student->name['ar'] ?? reset($certificate->student->name))
                        : $certificate->student?->name }}
                </span>
            </div>
            @if($certificate->section)
            <div class="flex justify-between">
                <span class="text-gray-500">{{ __('Section') }}</span>
                <span class="font-semibold">{{ $certificate->section->getTranslation('name', 'ar', false) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">{{ __('Subject') }}</span>
                <span class="font-semibold">{{ $certificate->section->subject?->getTranslation('name', 'ar', false) }}</span>
            </div>
            @endif
            <div class="flex justify-between">
                <span class="text-gray-500">{{ __('Serial Number') }}</span>
                <span class="font-semibold font-mono">{{ $certificate->serial_number }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">{{ __('Issued Date') }}</span>
                <span class="font-semibold">{{ $certificate->issued_at?->format('Y/m/d') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">{{ __('Template') }}</span>
                <span class="font-semibold">{{ $certificate->template?->name }}</span>
            </div>
        </div>
    </div>
</body>
</html>
