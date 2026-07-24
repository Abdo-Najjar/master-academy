<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Download Certificates') }}</title>
    <style>
        body { margin: 0; font-family: 'Tajawal', 'Segoe UI', Tahoma, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem; padding: 2rem; }
        #status { font-size: 1.05rem; font-weight: 600; text-align: center; }
        #progress { font-size: .9rem; color: #94a3b8; }
        .spin { width: 38px; height: 38px; border: 4px solid #334155; border-top-color: #22c55e; border-radius: 50%; animation: sp 1s linear infinite; }
        @keyframes sp { to { transform: rotate(360deg); } }
        #preview { max-width: 92vw; max-height: 55vh; border-radius: 12px; box-shadow: 0 20px 50px -20px rgba(0,0,0,.7); display: none; background: #fff; }
        .btn { display: none; padding: 12px 28px; border-radius: 12px; background: linear-gradient(135deg,#16a34a,#15803d); color: #fff; font-weight: 800; font-size: 1rem; text-decoration: none; border: none; cursor: pointer; }
        .btn:disabled { opacity: .6; cursor: default; }
        .btn.secondary { background: linear-gradient(135deg,#334155,#1e293b); }
        .row { display: flex; gap: .75rem; }
        canvas#stage { display: none; }
        #list { font-size: .85rem; color: #94a3b8; text-align: center; max-width: 480px; }
    </style>
</head>
<body>
    <div class="spin" id="spin"></div>
    <div id="status">{{ __('Generating certificates...') }}</div>
    <div id="progress"></div>
    <img id="preview" alt="">
    <button class="btn" id="dl">{{ __('Download ZIP') }}</button>
    <div id="list"></div>

    <canvas id="stage"></canvas>

    <script src="{{ asset('vendor/fabric.min.js') }}"></script>
    <script src="{{ asset('vendor/jszip.min.js') }}"></script>
    <script>
        const payloads = @json($payloads);
        const MULT = 3; // export at 3x for print-quality output
        const dataUrls = new Array(payloads.length);

        function renderOne(payload) {
            return new Promise((resolve) => {
                const canvas = new fabric.StaticCanvas('stage', {
                    width: payload.width,
                    height: payload.height,
                    backgroundColor: '#ffffff',
                });

                const drawOverlay = () => {
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

                    const exportPng = () => {
                        canvas.renderAll();
                        setTimeout(() => {
                            resolve(canvas.toDataURL({ format: 'png', multiplier: MULT }));
                        }, 150);
                    };

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

                if (payload.bgUrl) {
                    fabric.Image.fromURL(payload.bgUrl, (img) => {
                        if (img) {
                            img.set({
                                left: 0, top: 0, selectable: false,
                                scaleX: payload.width / img.width,
                                scaleY: payload.height / img.height,
                            });
                            canvas.add(img);
                        }
                        drawOverlay();
                    }, { crossOrigin: 'anonymous' });
                } else {
                    drawOverlay();
                }
            });
        }

        async function downloadZip() {
            const zip = new JSZip();
            payloads.forEach((p, i) => {
                zip.file(p.filename, dataUrls[i].split(',')[1], { base64: true });
            });
            const blob = await zip.generateAsync({ type: 'blob' });

            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'certificates.zip';
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(url), 5000);
        }

        async function renderAll() {
            if (payloads.length === 0) {
                document.getElementById('spin').style.display = 'none';
                document.getElementById('status').textContent = @json(__('No certificates to export.'));
                return;
            }

            for (let i = 0; i < payloads.length; i++) {
                document.getElementById('progress').textContent = (i + 1) + ' / ' + payloads.length;
                dataUrls[i] = await renderOne(payloads[i]);
            }

            document.getElementById('preview').src = dataUrls[0];
            document.getElementById('preview').style.display = 'block';
            document.getElementById('spin').style.display = 'none';
            document.getElementById('status').textContent = @json(__('Your certificates are ready.'));
            document.getElementById('progress').textContent = '1 / ' + payloads.length;
            document.getElementById('list').textContent = payloads.map(p => p.filename).join('  •  ');

            const dlBtn = document.getElementById('dl');
            dlBtn.style.display = 'inline-block';
            dlBtn.onclick = async () => {
                dlBtn.disabled = true;
                dlBtn.textContent = @json(__('Compressing...'));
                await downloadZip();
                dlBtn.disabled = false;
                dlBtn.textContent = @json(__('Download ZIP'));
            };

        }

        if (window.fabric && window.JSZip) {
            renderAll();
        } else {
            document.getElementById('status').textContent = @json(__('Failed to load. Please try again.'));
        }
    </script>
</body>
</html>
