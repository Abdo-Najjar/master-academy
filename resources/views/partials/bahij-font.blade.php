{{--
    Bahij TheSans Arabic — self-hosted (not available on Google Fonts), so the
    certificate designer / canvas renderers get the same typeface the mPDF
    export embeds from resources/fonts.
--}}
<style>
    @font-face {
        font-family: 'Bahij TheSans Arabic';
        src: url('{{ asset('fonts/bahij/BahijTheSansArabic-Regular.ttf') }}') format('truetype');
        font-weight: 400;
        font-style: normal;
        font-display: swap;
    }
    @font-face {
        font-family: 'Bahij TheSans Arabic';
        src: url('{{ asset('fonts/bahij/BahijTheSansArabic-Bold.ttf') }}') format('truetype');
        font-weight: 700;
        font-style: normal;
        font-display: swap;
    }
    .bahij-preload {
        position: absolute;
        left: -9999px;
        top: -9999px;
        font-family: 'Bahij TheSans Arabic';
        font-size: 12px;
    }
</style>
{{-- Rendered (off-screen) so the face actually downloads and document.fonts.ready
     waits for it before the canvas bakes text in with a fallback font. --}}
<span class="bahij-preload" aria-hidden="true" style="font-weight:400;">أ</span>
<span class="bahij-preload" aria-hidden="true" style="font-weight:700;">أ</span>
