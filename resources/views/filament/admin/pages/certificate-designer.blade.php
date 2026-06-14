<x-filament-panels::page>
    <style>
        .cd-wrap{display:flex;flex-direction:column;gap:1rem;}
        .cd-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:.6rem;padding:1rem 1.25rem;background:#fff;border:1px solid #e5e7eb;border-radius:1rem;box-shadow:0 4px 14px -8px rgba(0,0,0,.12);}
        .dark .cd-toolbar{background:#1e293b;border-color:#334155;}
        .cd-tlabel{font-size:.875rem;font-weight:700;color:#334155;}
        .dark .cd-tlabel{color:#cbd5e1;}
        .cd-chip{display:inline-flex;align-items:center;gap:.25rem;padding:.45rem .85rem;font-size:.8rem;font-weight:700;border:none;border-radius:.6rem;cursor:pointer;background:#eef2f9;color:#1e40af;transition:background .15s,transform .15s;}
        .cd-chip:hover{background:#dbe4f4;transform:translateY(-1px);}
        .dark .cd-chip{background:#0f1e3d;color:#93c5fd;}
        .cd-chip--qr{background:#fef3c7;color:#92400e;}
        .cd-chip--qr:hover{background:#fde68a;}
        .dark .cd-chip--qr{background:#422006;color:#fcd34d;}
        .cd-spacer{flex:1;min-width:1rem;}
        .cd-save{padding:.6rem 1.5rem;font-size:.9rem;font-weight:800;color:#fff;border:none;border-radius:.7rem;cursor:pointer;background:linear-gradient(135deg,#16a34a,#15803d);box-shadow:0 6px 16px -6px rgba(22,163,74,.55);transition:filter .15s,transform .15s;}
        .cd-save:hover{filter:brightness(1.08);transform:translateY(-1px);}
        .cd-main{display:flex;flex-direction:column;gap:1rem;}
        @media(min-width:1024px){.cd-main{flex-direction:row;align-items:flex-start;}}
        .cd-canvas{flex:1;min-width:0;overflow:auto;background:#f1f5f9;border:1px solid #e5e7eb;border-radius:1rem;padding:1rem;}
        .dark .cd-canvas{background:#0f172a;border-color:#334155;}
        .cd-zoombar{display:flex;align-items:center;gap:.4rem;margin-bottom:.75rem;}
        .cd-zbtn{min-width:32px;height:32px;padding:0 .6rem;font-size:1rem;font-weight:700;line-height:1;color:#334155;background:#fff;border:1px solid #d1d5db;border-radius:.5rem;cursor:pointer;transition:background .12s;}
        .cd-zbtn:hover{background:#eef2f7;}
        .cd-zbtn--fit{font-size:.8rem;font-weight:600;}
        .dark .cd-zbtn{background:#1e293b;border-color:#334155;color:#cbd5e1;}
        .cd-zlevel{min-width:48px;text-align:center;font-size:.82rem;font-weight:700;color:#64748b;}
        .cd-side{width:100%;display:flex;flex-direction:column;gap:1rem;}
        @media(min-width:1024px){.cd-side{width:290px;flex-shrink:0;position:sticky;top:1rem;}}
        .cd-card{background:#fff;border:1px solid #e5e7eb;border-radius:1rem;padding:1.25rem;box-shadow:0 4px 14px -8px rgba(0,0,0,.12);}
        .dark .cd-card{background:#1e293b;border-color:#334155;}
        .cd-ctitle{font-size:.9rem;font-weight:800;color:#1e293b;margin:0 0 1rem;padding-bottom:.65rem;border-bottom:1px solid #eef2f7;}
        .dark .cd-ctitle{color:#e2e8f0;border-color:#334155;}
        .cd-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;}
        .cd-f{display:flex;flex-direction:column;gap:.3rem;}
        .cd-f--full{grid-column:1 / -1;}
        .cd-lbl{font-size:.72rem;font-weight:600;color:#64748b;}
        .cd-in,.cd-sel{width:100%;padding:.5rem .6rem;font-size:.85rem;font-family:inherit;color:#0f172a;background:#fff;border:1px solid #d1d5db;border-radius:.55rem;outline:none;transition:border-color .15s,box-shadow .15s;}
        .cd-in:focus,.cd-sel:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.16);}
        .dark .cd-in,.dark .cd-sel{background:#0f172a;border-color:#334155;color:#e2e8f0;}
        .cd-in[readonly]{background:#f8fafc;color:#64748b;cursor:default;}
        .dark .cd-in[readonly]{background:#162033;}
        .cd-color{width:100%;height:40px;padding:3px;border:1px solid #d1d5db;border-radius:.55rem;cursor:pointer;background:#fff;}
        .dark .cd-color{background:#0f172a;border-color:#334155;}
        .cd-del{grid-column:1 / -1;margin-top:.3rem;padding:.6rem;font-size:.83rem;font-weight:700;color:#fff;background:#dc2626;border:none;border-radius:.6rem;cursor:pointer;transition:background .15s;}
        .cd-del:hover{background:#b91c1c;}
        .cd-hint{font-size:.8rem;color:#94a3b8;text-align:center;padding:.75rem 0;margin:0;}
        .cd-list{display:flex;flex-direction:column;gap:.35rem;}
        .cd-item{display:flex;align-items:center;gap:.4rem;padding:.55rem .75rem;border-radius:.6rem;cursor:pointer;font-size:.83rem;color:#334155;border:1px solid transparent;transition:background .12s;}
        .cd-item:hover{background:#f1f5f9;}
        .dark .cd-item{color:#cbd5e1;}
        .dark .cd-item:hover{background:#273449;}
        .cd-item--on{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8;font-weight:700;}
        .dark .cd-item--on{background:#1e3a5f;border-color:#1e40af;color:#93c5fd;}
        .cd-key{color:#94a3b8;font-size:.72rem;}
    </style>

    <div
        x-data="certificateDesigner(@js($this->record->fields_config ?? []), @js($this->record->canvas_width), @js($this->record->canvas_height), @js($this->record->getFirstMediaUrl('background')))"
        class="cd-wrap"
    >
        {{-- Toolbar --}}
        <div class="cd-toolbar">
            <span class="cd-tlabel">{{ __('Add Field') }}:</span>

            @foreach ([
                'student_name_ar' => __('Student Name') . ' (ع)',
                'student_name_en' => __('Student Name') . ' (EN)',
                'section_name_ar' => __('Section Name') . ' (ع)',
                'section_name_en' => __('Section Name') . ' (EN)',
                'subject_name_ar' => __('Subject') . ' (ع)',
                'subject_name_en' => __('Subject') . ' (EN)',
                'serial_number' => __('Serial Number'),
                'issued_date'   => __('Issue Date'),
                'student_number'=> __('Student Number'),
                'student_ssn'   => __('SSN'),
            ] as $key => $label)
                <button type="button" class="cd-chip" @click="addField('{{ $key }}', '{{ $label }}')">+ {{ $label }}</button>
            @endforeach

            <button type="button" class="cd-chip cd-chip--qr" @click="addQr()">+ {{ __('QR Code') }}</button>

            <span class="cd-spacer"></span>

            <button type="button" class="cd-save" @click="saveDesign()" :disabled="saving">
                <span x-show="!saving">{{ __('Save Design') }}</span>
                <span x-show="saving">{{ __('Saving...') }}</span>
            </button>
        </div>

        {{-- Canvas + properties --}}
        <div class="cd-main">
            {{-- Canvas --}}
            <div class="cd-canvas" x-ref="canvasWrap">
                <div class="cd-zoombar">
                    <button type="button" class="cd-zbtn" @click="zoomBy(-0.1)">−</button>
                    <span class="cd-zlevel" x-text="Math.round(zoom * 100) + '%'"></span>
                    <button type="button" class="cd-zbtn" @click="zoomBy(0.1)">+</button>
                    <button type="button" class="cd-zbtn cd-zbtn--fit" @click="fitToView()">{{ __('Fit') }}</button>
                </div>
                <div style="position:relative;display:inline-block;">
                    <canvas id="certificate-canvas"></canvas>
                </div>
            </div>

            {{-- Side panel --}}
            <div class="cd-side">
                <div class="cd-card">
                    <h3 class="cd-ctitle">{{ __('Selected Field Properties') }}</h3>

                    <template x-if="hasSelection">
                        <div class="cd-grid">
                            <div class="cd-f cd-f--full">
                                <label class="cd-lbl">{{ __('Label') }}</label>
                                <input class="cd-in" type="text" x-model="selectedField.label" readonly>
                            </div>
                            {{-- Text field properties --}}
                            <div class="cd-f" x-show="selectedField.key !== 'qr_code'">
                                <label class="cd-lbl">{{ __('Font Size') }}</label>
                                <input class="cd-in" type="number" x-model.number="selectedField.font_size" @input="updateSelected()" min="8" max="200">
                            </div>
                            <div class="cd-f" x-show="selectedField.key !== 'qr_code'">
                                <label class="cd-lbl">{{ __('Color') }}</label>
                                <input class="cd-color" type="color" x-model="selectedField.font_color" @input="updateSelected()">
                            </div>
                            <div class="cd-f cd-f--full" x-show="selectedField.key !== 'qr_code'">
                                <label class="cd-lbl">{{ __('Font') }}</label>
                                <select class="cd-sel" x-model="selectedField.font_family" @change="updateSelected()">
                                    <option value="dejavusans">{{ __('Sans') }}</option>
                                    <option value="dejavuserif">{{ __('Serif') }}</option>
                                    <option value="dejavusansmono">{{ __('Monospace') }}</option>
                                </select>
                            </div>
                            <div class="cd-f" x-show="selectedField.key !== 'qr_code'">
                                <label class="cd-lbl">{{ __('Font Weight') }}</label>
                                <select class="cd-sel" x-model="selectedField.font_weight" @change="updateSelected()">
                                    <option value="normal">{{ __('Normal') }}</option>
                                    <option value="bold">{{ __('Bold') }}</option>
                                </select>
                            </div>
                            <div class="cd-f" x-show="selectedField.key !== 'qr_code'">
                                <label class="cd-lbl">{{ __('Text Align') }}</label>
                                <select class="cd-sel" x-model="selectedField.text_align" @change="updateSelected()">
                                    <option value="right">{{ __('Right') }}</option>
                                    <option value="center">{{ __('Center') }}</option>
                                    <option value="left">{{ __('Left') }}</option>
                                </select>
                            </div>
                            <div class="cd-f cd-f--full" x-show="selectedField.key !== 'qr_code'">
                                <label class="cd-lbl">{{ __('Width (px, 0=auto)') }}</label>
                                <input class="cd-in" type="number" x-model.number="selectedField.width" @input="updateSelected()" min="0">
                            </div>

                            {{-- QR-only property --}}
                            <div class="cd-f cd-f--full" x-show="selectedField.key === 'qr_code'">
                                <label class="cd-lbl">{{ __('QR Size (px)') }}</label>
                                <input class="cd-in" type="number" x-model.number="selectedField.size" @input="updateQrSize()" min="40" max="600">
                            </div>

                            {{-- Position (all) --}}
                            <div class="cd-f">
                                <label class="cd-lbl">{{ __('Position X') }}</label>
                                <input class="cd-in" type="number" x-model.number="selectedField.x" @input="updateSelectedPos()" min="0">
                            </div>
                            <div class="cd-f">
                                <label class="cd-lbl">{{ __('Position Y') }}</label>
                                <input class="cd-in" type="number" x-model.number="selectedField.y" @input="updateSelectedPos()" min="0">
                            </div>
                            <button type="button" class="cd-del" @click="deleteSelected()">{{ __('Delete Field') }}</button>
                        </div>
                    </template>

                    <template x-if="!hasSelection">
                        <p class="cd-hint">{{ __('Click a field on the canvas to select it.') }}</p>
                    </template>
                </div>

                {{-- Field list --}}
                <div class="cd-card">
                    <h3 class="cd-ctitle">{{ __('Fields') }}</h3>
                    <div class="cd-list">
                        <template x-for="(f, i) in fields" :key="i">
                            <div class="cd-item" :class="selectedIndex === i ? 'cd-item--on' : ''" @click="selectFieldByIndex(i)">
                                <span x-text="f.label"></span>
                                <span class="cd-key" x-text="'(' + f.key + ')'"></span>
                            </div>
                        </template>
                        <template x-if="fields.length === 0">
                            <p class="cd-hint">{{ __('No fields added yet.') }}</p>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @assets
    <script src="{{ asset('vendor/fabric.min.js') }}"></script>
    <script>
    window.certificateDesigner = function (initialFields, canvasWidth, canvasHeight, bgUrl) {
        return {
            canvas: null,
            fields: [],
            selectedField: {},
            hasSelection: false,
            selectedIndex: -1,
            fabricObjects: [],
            zoom: 1,
            saving: false,

            init() {
                this.canvas = new fabric.Canvas('certificate-canvas', {
                    width: canvasWidth,
                    height: canvasHeight,
                    backgroundColor: '#ffffff',
                });

                // Load background
                if (bgUrl) {
                    fabric.Image.fromURL(bgUrl, (img) => {
                        img.set({ selectable: false, evented: false, crossOrigin: 'anonymous' });
                        img.scaleToWidth(canvasWidth);
                        img.scaleToHeight(canvasHeight);
                        this.canvas.setBackgroundImage(img, this.canvas.renderAll.bind(this.canvas));
                    }, { crossOrigin: 'anonymous' });
                }

                // Load existing fields
                if (Array.isArray(initialFields)) {
                    initialFields.forEach(f => this.addFieldFromConfig(f));
                }

                // Selection events
                this.canvas.on('selection:created', (e) => this.onSelect(e.selected[0]));
                this.canvas.on('selection:updated', (e) => this.onSelect(e.selected[0]));
                this.canvas.on('selection:cleared', () => { this.clearSelection(); });
                this.canvas.on('object:modified', (e) => this.onMoved(e.target));

                // Scale the (often large) canvas down so the whole certificate is visible.
                this.$nextTick(() => this.fitToView());
                window.addEventListener('resize', () => this.fitToView());
            },

            applyZoom(scale) {
                this.zoom = Math.max(0.1, Math.min(scale, 3));
                this.canvas.setZoom(this.zoom);
                this.canvas.setDimensions({
                    width: Math.round(canvasWidth * this.zoom),
                    height: Math.round(canvasHeight * this.zoom),
                });
                this.canvas.renderAll();
            },

            fitToView() {
                const wrap = this.$refs.canvasWrap;
                if (!wrap) return;
                const avail = wrap.clientWidth - 34; // minus padding
                this.applyZoom(Math.min(avail / canvasWidth, 1));
            },

            zoomBy(delta) {
                this.applyZoom(this.zoom + delta);
            },

            fontMap: {
                dejavusans: 'Arial, sans-serif',
                dejavuserif: 'Georgia, serif',
                dejavusansmono: '"Courier New", monospace',
            },

            addField(key, label) {
                const config = {
                    key,
                    label,
                    x: 100,
                    y: 100,
                    font_size: 36,
                    font_color: '#000000',
                    font_weight: 'bold',
                    font_family: 'dejavusans',
                    text_align: 'center',
                    width: 0,
                };
                this.addFieldFromConfig(config);
            },

            addQr() {
                // Only one QR per template.
                const existing = this.fields.findIndex(f => f.key === 'qr_code');
                if (existing >= 0) { this.selectFieldByIndex(existing); return; }
                this.addFieldFromConfig({ key: 'qr_code', label: 'QR', x: 100, y: 100, size: 140 });
            },

            addFieldFromConfig(config) {
                const idx = this.fields.length;
                this.fields.push({ ...config });

                let obj;
                if (config.key === 'qr_code') {
                    const size = config.size || 140;
                    const rect = new fabric.Rect({ width: size, height: size, fill: '#eef2f7', stroke: '#64748b', strokeWidth: 2, rx: 6, ry: 6, originX: 'center', originY: 'center' });
                    const label = new fabric.Text('QR', { fontSize: Math.round(size * 0.28), fill: '#475569', fontWeight: 'bold', originX: 'center', originY: 'center' });
                    obj = new fabric.Group([rect, label], {
                        left: config.x, top: config.y,
                        fieldIndex: idx, lockUniScaling: true, lockRotation: true,
                    });
                    obj.setControlsVisibility({ mtr: false });
                } else {
                    obj = new fabric.IText(config.label + ': [' + config.key + ']', {
                        left: config.x,
                        top: config.y,
                        fontSize: config.font_size,
                        fill: config.font_color,
                        fontWeight: config.font_weight,
                        textAlign: config.text_align,
                        width: config.width || undefined,
                        fontFamily: this.fontMap[config.font_family] || 'Arial, sans-serif',
                        fieldIndex: idx,
                        editable: false,
                    });
                }

                this.canvas.add(obj);
                this.fabricObjects.push(obj);
                this.canvas.renderAll();
            },

            clearSelection() {
                this.hasSelection = false;
                this.selectedIndex = -1;
                this.selectedField = {};
            },

            onSelect(obj) {
                if (!obj || obj.fieldIndex === undefined) { this.clearSelection(); return; }
                this.selectedIndex = obj.fieldIndex;
                this.selectedField = { ...this.fields[obj.fieldIndex] };
                this.hasSelection = true;
            },

            onMoved(obj) {
                if (!obj || obj.fieldIndex === undefined) return;
                const idx = obj.fieldIndex;
                this.fields[idx].x = Math.round(obj.left);
                this.fields[idx].y = Math.round(obj.top);
                // For a resized QR group, capture the new size.
                if (this.fields[idx].key === 'qr_code') {
                    this.fields[idx].size = Math.round(obj.width * obj.scaleX);
                }
                if (this.selectedIndex === idx) {
                    this.selectedField = { ...this.fields[idx] };
                }
            },

            updateSelected() {
                if (this.selectedIndex < 0) return;
                const f = this.selectedField;
                this.fields[this.selectedIndex] = { ...f };
                const obj = this.fabricObjects[this.selectedIndex];
                if (!obj || f.key === 'qr_code') { this.canvas.renderAll(); return; }
                obj.set({
                    fontSize: f.font_size,
                    fill: f.font_color,
                    fontWeight: f.font_weight,
                    textAlign: f.text_align,
                    fontFamily: this.fontMap[f.font_family] || 'Arial, sans-serif',
                    width: f.width > 0 ? f.width : undefined,
                });
                this.canvas.renderAll();
            },

            updateQrSize() {
                if (this.selectedIndex < 0) return;
                const f = this.selectedField;
                this.fields[this.selectedIndex].size = f.size;
                const obj = this.fabricObjects[this.selectedIndex];
                if (!obj) return;
                const scale = (f.size || 140) / obj.width;
                obj.set({ scaleX: scale, scaleY: scale });
                obj.setCoords();
                this.canvas.renderAll();
            },

            updateSelectedPos() {
                if (this.selectedIndex < 0) return;
                const f = this.selectedField;
                this.fields[this.selectedIndex].x = f.x;
                this.fields[this.selectedIndex].y = f.y;
                const obj = this.fabricObjects[this.selectedIndex];
                if (!obj) return;
                obj.set({ left: f.x, top: f.y });
                this.canvas.renderAll();
            },

            selectFieldByIndex(i) {
                this.selectedIndex = i;
                this.selectedField = { ...this.fields[i] };
                this.hasSelection = true;
                const obj = this.fabricObjects[i];
                if (obj) {
                    this.canvas.setActiveObject(obj);
                    this.canvas.renderAll();
                }
            },

            deleteSelected() {
                if (this.selectedIndex < 0) return;
                const idx = this.selectedIndex;
                // Clear selection state BEFORE removing from canvas so reactive
                // bindings never read a stale/removed field.
                this.clearSelection();
                this.canvas.discardActiveObject();
                const obj = this.fabricObjects[idx];
                if (obj) { this.canvas.remove(obj); }
                this.fields.splice(idx, 1);
                this.fabricObjects.splice(idx, 1);
                // Re-index remaining objects
                this.fabricObjects.forEach((o, i) => { if (o) o.fieldIndex = i; });
                this.canvas.renderAll();
            },

            saveDesign() {
                const syncedFields = this.fields.map((f, i) => {
                    const obj = this.fabricObjects[i];
                    if (!obj) return f;
                    const synced = { ...f, x: Math.round(obj.left), y: Math.round(obj.top) };
                    if (f.key === 'qr_code') {
                        synced.size = Math.round(obj.width * obj.scaleX);
                    }
                    return synced;
                });

                this.saving = true;
                // Pass the REAL design dimensions, not the zoomed canvas size.
                Promise.resolve(this.$wire.call('saveDesign', syncedFields, canvasWidth, canvasHeight))
                    .finally(() => { this.saving = false; });
            },
        };
    };
    </script>
    @endassets
</x-filament-panels::page>
