<x-filament-panels::page>
    <div
        x-data="certificateDesigner(@js($this->record->fields_config ?? []), @js($this->record->canvas_width), @js($this->record->canvas_height), @js($this->record->getFirstMediaUrl('background')))"
        class="space-y-4"
    >
        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-3 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('Add Field') }}:</span>

            @foreach ([
                'student_name' => __('Student Name'),
                'section_name' => __('Section Name'),
                'subject_name' => __('Subject'),
                'serial_number' => __('Serial Number'),
                'issued_date'   => __('Issue Date'),
                'student_number'=> __('Student Number'),
            ] as $key => $label)
                <button type="button" @click="addField('{{ $key }}', '{{ $label }}')"
                        class="px-3 py-1.5 text-sm rounded-lg bg-primary-600 text-white hover:bg-primary-700 transition">
                    + {{ $label }}
                </button>
            @endforeach

            <div class="flex-1"></div>

            <button type="button" @click="saveDesign()"
                    wire:loading.attr="disabled"
                    class="px-5 py-2 bg-success-600 hover:bg-success-700 text-white font-semibold rounded-lg transition">
                {{ __('Save Design') }}
            </button>
        </div>

        {{-- Canvas area --}}
        <div class="flex flex-col lg:flex-row gap-4">
            {{-- Canvas wrapper --}}
            <div class="flex-1 min-w-0 overflow-auto bg-gray-100 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-2">
                <div class="relative inline-block" style="width: {{ $this->record->canvas_width }}px; height: {{ $this->record->canvas_height }}px;">
                    <canvas id="certificate-canvas"></canvas>
                </div>
            </div>

            {{-- Properties panel --}}
            <div class="w-full lg:w-64 shrink-0 space-y-3">
                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <h3 class="font-semibold text-sm mb-3 text-gray-700 dark:text-gray-300">{{ __('Selected Field Properties') }}</h3>

                    <template x-if="selectedField">
                        <div class="space-y-3 text-sm">
                            <div>
                                <label class="block text-gray-600 dark:text-gray-400 mb-1">{{ __('Label') }}</label>
                                <input type="text" x-model="selectedField.label" @input="updateSelected()" readonly
                                       class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-xs">
                            </div>
                            <div>
                                <label class="block text-gray-600 dark:text-gray-400 mb-1">{{ __('Font Size') }}</label>
                                <input type="number" x-model.number="selectedField.font_size" @input="updateSelected()" min="8" max="200"
                                       class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900">
                            </div>
                            <div>
                                <label class="block text-gray-600 dark:text-gray-400 mb-1">{{ __('Color') }}</label>
                                <input type="color" x-model="selectedField.font_color" @input="updateSelected()"
                                       class="w-full h-8 rounded border border-gray-300 dark:border-gray-600 cursor-pointer">
                            </div>
                            <div>
                                <label class="block text-gray-600 dark:text-gray-400 mb-1">{{ __('Font Weight') }}</label>
                                <select x-model="selectedField.font_weight" @change="updateSelected()"
                                        class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900">
                                    <option value="normal">{{ __('Normal') }}</option>
                                    <option value="bold">{{ __('Bold') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-600 dark:text-gray-400 mb-1">{{ __('Text Align') }}</label>
                                <select x-model="selectedField.text_align" @change="updateSelected()"
                                        class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900">
                                    <option value="right">{{ __('Right') }}</option>
                                    <option value="center">{{ __('Center') }}</option>
                                    <option value="left">{{ __('Left') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-600 dark:text-gray-400 mb-1">{{ __('Width (px, 0=auto)') }}</label>
                                <input type="number" x-model.number="selectedField.width" @input="updateSelected()" min="0"
                                       class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900">
                            </div>
                            <div>
                                <label class="block text-gray-600 dark:text-gray-400 mb-1">{{ __('Position X') }}</label>
                                <input type="number" x-model.number="selectedField.x" @input="updateSelectedPos()" min="0"
                                       class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900">
                            </div>
                            <div>
                                <label class="block text-gray-600 dark:text-gray-400 mb-1">{{ __('Position Y') }}</label>
                                <input type="number" x-model.number="selectedField.y" @input="updateSelectedPos()" min="0"
                                       class="w-full px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900">
                            </div>
                            <button type="button" @click="deleteSelected()"
                                    class="w-full px-3 py-1.5 bg-danger-600 hover:bg-danger-700 text-white text-sm rounded-lg">
                                {{ __('Delete Field') }}
                            </button>
                        </div>
                    </template>

                    <template x-if="!selectedField">
                        <p class="text-xs text-gray-400">{{ __('Click a field on the canvas to select it.') }}</p>
                    </template>
                </div>

                {{-- Field list --}}
                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <h3 class="font-semibold text-sm mb-2 text-gray-700 dark:text-gray-300">{{ __('Fields') }}</h3>
                    <div class="space-y-1">
                        <template x-for="(f, i) in fields" :key="i">
                            <div @click="selectFieldByIndex(i)"
                                 :class="selectedIndex === i ? 'bg-primary-100 dark:bg-primary-900/40 text-primary-700' : 'hover:bg-gray-100 dark:hover:bg-gray-700'"
                                 class="px-2 py-1.5 rounded cursor-pointer text-xs">
                                <span x-text="f.label"></span>
                                <span class="text-gray-400 ms-1" x-text="'(' + f.key + ')'"></span>
                            </div>
                        </template>
                        <template x-if="fields.length === 0">
                            <p class="text-xs text-gray-400">{{ __('No fields added yet.') }}</p>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="{{ asset('vendor/fabric.min.js') }}"></script>
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('certificateDesigner', (initialFields, canvasWidth, canvasHeight, bgUrl) => ({
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

                @this.call('saveDesign', syncedFields, this.canvas.width, this.canvas.height);
            },
        }));
    });
    </script>
    @endpush
</x-filament-panels::page>
