<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Download Image') }}</title>
    <style>
        body { margin: 0; font-family: 'Tajawal', 'Segoe UI', Tahoma, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem; padding: 2rem; }
        #status { font-size: 1.05rem; font-weight: 600; }
        .spin { width: 38px; height: 38px; border: 4px solid #334155; border-top-color: #22c55e; border-radius: 50%; animation: sp 1s linear infinite; }
        @keyframes sp { to { transform: rotate(360deg); } }
        #preview { max-width: 92vw; max-height: 60vh; border-radius: 12px; box-shadow: 0 20px 50px -20px rgba(0,0,0,.7); display: none; background: #fff; }
        .btn { display: none; padding: 12px 28px; border-radius: 12px; background: linear-gradient(135deg,#16a34a,#15803d); color: #fff; font-weight: 800; font-size: 1rem; text-decoration: none; border: none; cursor: pointer; }
        canvas#stage { display: none; }
    </style>
</head>
<body>
    <div class="spin" id="spin"></div>
    <div id="status">{{ __('Generating image...') }}</div>
    <img id="preview" alt="">
    <button class="btn" id="dl">{{ __('Download Image') }}</button>
    <canvas id="stage"></canvas>

    <script src="{{ asset('vendor/fabric.min.js') }}"></script>
    <script>
        const payload = @json($payload);
        const MULT = 3; // export at 3x for print-quality output

        function finish(dataUrl) {
            const img = document.getElementById('preview');
            img.src = dataUrl;
            img.style.display = 'block';
            document.getElementById('spin').style.display = 'none';
            document.getElementById('status').textContent = @json(__('Your image is ready.'));

            const trigger = () => {
                const a = document.createElement('a');
                a.href = dataUrl;
                a.download = payload.filename || 'certificate.png';
                document.body.appendChild(a);
                a.click();
                a.remove();
            };

            const btn = document.getElementById('dl');
            btn.style.display = 'inline-block';
            btn.onclick = trigger;
            trigger(); // auto-download once
        }

        function render() {
            const canvas = new fabric.StaticCanvas('stage', {
                width: payload.width,
                height: payload.height,
                backgroundColor: '#ffffff',
            });

            const drawOverlay = () => {
                // Text fields
                (payload.fields || []).forEach(f => {
                    const t = new fabric.Text(f.value, {
                        left: f.x,
                        top: f.y,
                        fontSize: f.fontSize,
                        fill: f.fill,
                        fontWeight: f.fontWeight,
                        fontFamily: f.fontFamily,
                        textAlign: f.textAlign,
                        width: f.width > 0 ? f.width : undefined,
                    });
                    canvas.add(t);
                });

                // QR (from SVG)
                if (payload.qr && payload.qr.svg) {
                    fabric.loadSVGFromString(payload.qr.svg, (objects, options) => {
                        const g = fabric.util.groupSVGElements(objects, options);
                        const scale = payload.qr.size / (g.width || payload.qr.size);
                        g.set({ left: payload.qr.x, top: payload.qr.y, scaleX: scale, scaleY: scale });
                        canvas.add(g);
                        exportPng();
                    });
                } else {
                    exportPng();
                }
            };

            const exportPng = () => {
                canvas.renderAll();
                // Render a touch later so fonts/SVG are painted.
                setTimeout(() => {
                    const dataUrl = canvas.toDataURL({ format: 'png', multiplier: MULT });
                    finish(dataUrl);
                }, 150);
            };

            if (payload.bgUrl) {
                fabric.Image.fromURL(payload.bgUrl, (img) => {
                    if (img) {
                        img.set({ left: 0, top: 0, selectable: false });
                        img.scaleToWidth(payload.width);
                        img.scaleToHeight(payload.height);
                        canvas.add(img);
                    }
                    drawOverlay();
                }, { crossOrigin: 'anonymous' });
            } else {
                drawOverlay();
            }
        }

        if (window.fabric) {
            render();
        } else {
            document.getElementById('status').textContent = @json(__('Failed to load. Please try again.'));
        }
    </script>
</body>
</html>
