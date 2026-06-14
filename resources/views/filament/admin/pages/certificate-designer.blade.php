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
        .cd-spacer{flex:1;min-width:1rem;}
        .cd-save{padding:.6rem 1.5rem;font-size:.9rem;font-weight:800;color:#fff;border:none;border-radius:.7rem;cursor:pointer;background:linear-gradient(135deg,#16a34a,#15803d);box-shadow:0 6px 16px -6px rgba(22,163,74,.55);transition:filter .15s,transform .15s;}
        .cd-save:hover{filter:brightness(1.08);transform:translateY(-1px);}
        .cd-main{display:flex;flex-direction:column;gap:1rem;}
        @media(min-width:1024px){.cd-main{flex-direction:row;align-items:flex-start;}}
        .cd-canvas{flex:1;min-width:0;overflow:auto;background:#f1f5f9;border:1px solid #e5e7eb;border-radius:1rem;padding:1rem;}
        .dark .cd-canvas{background:#0f172a;border-color:#334155;}
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
                'student_name' => __('Student Name'),
                'section_name' => __('Section Name'),
                'subject_name' => __('Subject'),
                'serial_number' => __('Serial Number'),
                'issued_date'   => __('Issue Date'),
                'student_number'=> __('Student Number'),
            ] as $key => $label)
                <button type="button" class="cd-chip" @click="addField('{{ $key }}', '{{ $label }}')">+ {{ $label }}</button>
            @endforeach

            <span class="cd-spacer"></span>

            <button type="button" class="cd-save" @click="saveDesign()" wire:loading.attr="disabled">{{ __('Save Design') }}</button>
        </div>

        {{-- Canvas + properties --}}
        <div class="cd-main">
            {{-- Canvas --}}
            <div class="cd-canvas">
                <div style="position:relative;display:inline-block;width: {{ $this->record->canvas_width }}px; height: {{ $this->record->canvas_height }}px;">
                    <canvas id="certificate-canvas"></canvas>
                </div>
            </div>

            {{-- Side panel --}}
            <div class="cd-side">
                <div class="cd-card">
                    <h3 class="cd-ctitle">{{ __('Selected Field Properties') }}</h3>

                    <template x-if="selectedField">
                        <div class="cd-grid">
                            <div class="cd-f cd-f--full">
                                <label class="cd-lbl">{{ __('Label') }}</label>
                                <input class="cd-in" type="text" x-model="selectedField.label" readonly>
                            </div>
                            <div class="cd-f">
                                <label class="cd-lbl">{{ __('Font Size') }}</label>
                                <input class="cd-in" type="number" x-model.number="selectedField.font_size" @input="updateSelected()" min="8" max="200">
                            </div>
                            <div class="cd-f">
                                <label class="cd-lbl">{{ __('Color') }}</label>
                                <input class="cd-color" type="color" x-model="selectedField.font_color" @input="updateSelected()">
                            </div>
                            <div class="cd-f">
                                <label class="cd-lbl">{{ __('Font Weight') }}</label>
                                <select class="cd-sel" x-model="selectedField.font_weight" @change="updateSelected()">
                                    <option value="normal">{{ __('Normal') }}</option>
                                    <option value="bold">{{ __('Bold') }}</option>
                                </select>
                            </div>
                            <div class="cd-f">
                                <label class="cd-lbl">{{ __('Text Align') }}</label>
                                <select class="cd-sel" x-model="selectedField.text_align" @change="updateSelected()">
                                    <option value="right">{{ __('Right') }}</option>
                                    <option value="center">{{ __('Center') }}</option>
                                    <option value="left">{{ __('Left') }}</option>
                                </select>
                            </div>
                            <div class="cd-f">
                                <label class="cd-lbl">{{ __('Position X') }}</label>
                                <input class="cd-in" type="number" x-model.number="selectedField.x" @input="updateSelectedPos()" min="0">
                            </div>
                            <div class="cd-f">
                                <label class="cd-lbl">{{ __('Position Y') }}</label>
                                <input class="cd-in" type="number" x-model.number="selectedField.y" @input="updateSelectedPos()" min="0">
                            </div>
                            <div class="cd-f cd-f--full">
                                <label class="cd-lbl">{{ __('Width (px, 0=auto)') }}</label>
                                <input class="cd-in" type="number" x-model.number="selectedField.width" @input="updateSelected()" min="0">
                            </div>
                            <button type="button" class="cd-del" @click="deleteSelected()">{{ __('Delete Field') }}</button>
                        </div>
                    </template>

                    <template x-if="!selectedField">
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
            selectedField: null,
            selectedIndex: -1,
            fabricObjects: [],

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
                this.canvas.on('selection:cleared', () => { this.selectedField = null; this.selectedIndex = -1; });
                this.canvas.on('object:modified', (e) => this.onMoved(e.target));
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
                    text_align: 'center',
                    width: 0,
                };
                this.addFieldFromConfig(config);
            },

            addFieldFromConfig(config) {
                const idx = this.fields.length;
                this.fields.push({ ...config });

                const txt = new fabric.IText(config.label + ': [' + config.key + ']', {
                    left: config.x,
                    top: config.y,
                    fontSize: config.font_size,
                    fill: config.font_color,
                    fontWeight: config.font_weight,
                    textAlign: config.text_align,
                    width: config.width || undefined,
                    fontFamily: 'Arial',
                    fieldIndex: idx,
                    editable: false,
                });

                this.canvas.add(txt);
                this.fabricObjects.push(txt);
                this.canvas.renderAll();
            },

            onSelect(obj) {
                if (!obj || obj.fieldIndex === undefined) { this.selectedField = null; this.selectedIndex = -1; return; }
                this.selectedIndex = obj.fieldIndex;
                this.selectedField = this.fields[obj.fieldIndex];
            },

            onMoved(obj) {
                if (!obj || obj.fieldIndex === undefined) return;
                const idx = obj.fieldIndex;
                this.fields[idx].x = Math.round(obj.left);
                this.fields[idx].y = Math.round(obj.top);
                if (this.selectedIndex === idx) {
                    this.selectedField = { ...this.fields[idx] };
                }
            },

            updateSelected() {
                if (this.selectedIndex < 0) return;
                const f = this.selectedField;
                this.fields[this.selectedIndex] = { ...f };
                const obj = this.fabricObjects[this.selectedIndex];
                if (!obj) return;
                obj.set({
                    fontSize: f.font_size,
                    fill: f.font_color,
                    fontWeight: f.font_weight,
                    textAlign: f.text_align,
                    width: f.width > 0 ? f.width : undefined,
                });
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
                const obj = this.fabricObjects[i];
                if (obj) {
                    this.canvas.setActiveObject(obj);
                    this.canvas.renderAll();
                }
            },

            deleteSelected() {
                if (this.selectedIndex < 0) return;
                const obj = this.fabricObjects[this.selectedIndex];
                if (obj) { this.canvas.remove(obj); }
                this.fields.splice(this.selectedIndex, 1);
                this.fabricObjects.splice(this.selectedIndex, 1);
                // Re-index remaining objects
                this.fabricObjects.forEach((o, i) => { if (o) o.fieldIndex = i; });
                this.selectedField = null;
                this.selectedIndex = -1;
                this.canvas.discardActiveObject();
                this.canvas.renderAll();
            },

            saveDesign() {
                const syncedFields = this.fields.map((f, i) => {
                    const obj = this.fabricObjects[i];
                    if (obj) {
                        return { ...f, x: Math.round(obj.left), y: Math.round(obj.top) };
                    }
                    return f;
                });

                this.$wire.call('saveDesign', syncedFields, this.canvas.width, this.canvas.height);
            },
        };
    };
    </script>
    @endassets
</x-filament-panels::page>
