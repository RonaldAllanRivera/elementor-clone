<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $design->name }}
                </h2>
                <div class="text-sm text-gray-500">
                    {{ __('Project:') }}
                    <a href="{{ route('projects.show', $project) }}" class="hover:underline">
                        {{ $project->name }}
                    </a>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <form method="GET" action="{{ route('designs.exportElementor', $design) }}" class="flex items-center space-x-2">
                    <select name="format" id="elementor-format" class="rounded-md border-gray-300 text-xs">
                        <option value="classic" {{ in_array(request('format'), [null, '', 'classic'], true) ? 'selected' : '' }}>{{ __('Classic') }}</option>
                        <option value="classic_simple" {{ request('format') === 'classic_simple' ? 'selected' : '' }}>{{ __('Classic (Simple Sections)') }}</option>
                        <option value="container" {{ request('format') === 'container' ? 'selected' : '' }}>{{ __('Container') }}</option>
                    </select>
                    <span id="elementor-filename" class="hidden sm:inline text-xs text-gray-500"></span>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                        {{ __('Download JSON') }}
                    </button>
                    <button
                        type="submit"
                        formaction="{{ route('designs.elementorJson', $design) }}"
                        formtarget="_blank"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50"
                    >
                        {{ __('View/Copy JSON') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('designs.importFromFigma', $design) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500" {{ $design->figma_url ? '' : 'disabled' }}>
                        {{ __('Import from Figma') }}
                    </button>
                </form>

                <a href="{{ route('designs.diagnostics', $design) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    {{ __('Diagnostics') }}
                </a>

                <a href="{{ route('designs.edit', $design) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    {{ __('Edit') }}
                </a>
                <a href="{{ route('projects.designs.index', $project) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                    {{ __('Designs') }}
                </a>
            </div>
        </div>
    </x-slot>

    <script>
        (function () {
            const select = document.getElementById('elementor-format');
            const label = document.getElementById('elementor-filename');

            if (!select || !label) return;

            const base = @json(Illuminate\Support\Str::slug($design->name) ?: 'design');

            function update() {
                const format = select.value === 'container'
                    ? 'Container'
                    : (select.value === 'classic_simple' ? 'Classic (Simple)' : 'Classic');

                const suffix = select.value === 'container'
                    ? '-container'
                    : (select.value === 'classic_simple' ? '-simple' : '');
                label.textContent = format + ' • ' + base + '-elementor' + suffix + '.json';
            }

            select.addEventListener('change', update);
            update();
        })();
    </script>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="text-sm text-green-600">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    @if ($design->description)
                        <div>
                            <div class="text-sm text-gray-500">{{ __('Description') }}</div>
                            <div class="mt-1 text-sm text-gray-800">{{ $design->description }}</div>
                        </div>
                    @endif

                    <details class="bg-gray-50 border rounded-md p-4">
                        <summary class="cursor-pointer text-sm font-medium text-gray-800">{{ __('Why “Classic” looks cleaner than “Classic (Simple Sections)”') }}</summary>
                        <div class="mt-3 text-sm text-gray-700 space-y-3">
                            <div>
                                <div class="font-semibold">{{ __('1) Classic export is “minimal valid Elementor structure”') }}</div>
                                <div class="mt-1">
                                    {{ __('The classic exporter is optimized to produce the smallest valid tree:') }}
                                </div>
                                <div class="mt-2 space-y-1">
                                    <div>{{ __('Top-level: section') }}</div>
                                    <div>{{ __('Inside: column') }}</div>
                                    <div>{{ __('Inside: widgets (and only the minimum necessary inner wrappers)') }}</div>
                                </div>
                                <div class="mt-2">
                                    {{ __('Then we also run a flattening pass that removes the most common redundant pattern:') }}
                                </div>
                                <div class="mt-2 text-xs font-mono bg-white border rounded p-2 overflow-auto">
                                    <div>column -> inner section (empty) -> column (empty)</div>
                                    <div>becomes</div>
                                    <div>column -> (widgets…)</div>
                                </div>
                                <div class="mt-2">
                                    {{ __('So even if your Figma/layout JSON contains lots of nested “frames”, the classic output tends to collapse them, which is why it looks simpler/cleaner.') }}
                                </div>
                            </div>

                            <div>
                                <div class="font-semibold">{{ __('2) Classic (Simple Sections) intentionally creates “more blocks”') }}</div>
                                <div class="mt-1">
                                    {{ __('Simple Sections Mode is the opposite goal: it tries to make the page easier to edit by turning nested groups into separate top-level sections.') }}
                                </div>
                                <div class="mt-2">
                                    {{ __('To do that safely, it does two things that can increase size:') }}
                                </div>
                                <div class="mt-2 space-y-1">
                                    <div>{{ __('Lift each inner section into the top level') }}</div>
                                    <div class="text-gray-600">{{ __('If your design has many nested frames/sections (very common in Figma), it will create many top-level blocks.') }}</div>
                                    <div class="pt-2">{{ __('Wrap “leftover widgets” into their own top-level section') }}</div>
                                    <div class="text-gray-600">{{ __('When content exists between those inner sections, it can’t just leave them floating at root.') }}</div>
                                    <div class="text-gray-600">{{ __('So it creates a new wrapper: section -> column -> (those widgets)') }}</div>
                                </div>
                                <div class="mt-2">
                                    {{ __('So if your Figma structure is very granular (lots of frames for alignment), Simple Sections Mode will “promote” all those frames into editable Elementor blocks — which can look more confusing, not less.') }}
                                </div>
                            </div>

                            <div>
                                <div class="font-semibold">{{ __('3) The key tradeoff') }}</div>
                                <div class="mt-2 space-y-1">
                                    <div>{{ __('Classic: cleaner JSON + fewer Elementor Navigator items, but blocks may be “merged” together.') }}</div>
                                    <div>{{ __('Classic (Simple Sections): more “editable blocks”, but if the source design has many nested frames, you’ll see many sections and wrappers.') }}</div>
                                </div>
                            </div>

                            <div>
                                <div class="font-semibold">{{ __('4) Why this happens with “senior designer best practice”') }}</div>
                                <div class="mt-1">
                                    {{ __('A senior Elementor designer typically creates sections based on semantic page blocks (Hero, Header, Products, Testimonials…), not based on every internal alignment group.') }}
                                </div>
                                <div class="mt-2">
                                    {{ __('Right now, Simple Sections Mode uses a structural signal (“nested section boundaries”) rather than semantic intent (“this frame is a major block”). That’s why it can oversplit.') }}
                                </div>
                                <div class="mt-2">
                                    {{ __('To make Simple Sections Mode feel like senior best-practice automatically, we’d need smarter rules like:') }}
                                </div>
                                <div class="mt-2 space-y-1">
                                    <div>{{ __('Only split when a frame has a background fill') }}</div>
                                    <div>{{ __('Or when it spans near full width') }}</div>
                                    <div>{{ __('Or when it’s a vertical stack of major blocks with large spacing') }}</div>
                                    <div>{{ __('Or when the node name matches patterns (“Hero”, “Header”, “Section”, etc.)') }}</div>
                                </div>
                            </div>
                        </div>
                    </details>

                    <details class="bg-gray-50 border rounded-md p-4">
                        <summary class="cursor-pointer text-sm font-medium text-gray-800">{{ __('When and why should I use “Container” selection?') }}</summary>
                        <div class="mt-3 text-sm text-gray-700 space-y-3">
                            <div>
                                <div class="font-semibold">{{ __('Use “Container” if…') }}</div>
                                <div class="mt-2 space-y-1">
                                    <div>{{ __('You already build pages in Elementor using Flexbox Containers (modern layout system).') }}</div>
                                    <div>{{ __('You want better responsive control (flex direction, alignment, wrapping, spacing).') }}</div>
                                    <div>{{ __('You expect to nest layout blocks often (container-in-container is a first-class pattern).') }}</div>
                                    <div>{{ __('You want a structure aligned with Elementor’s newer direction (containers vs legacy sections/columns).') }}</div>
                                </div>
                            </div>

                            <div>
                                <div class="font-semibold">{{ __('Why “Container” can be better') }}</div>
                                <div class="mt-2 space-y-1">
                                    <div>{{ __('Closer match to Figma Auto Layout (flexbox-style layout concepts).') }}</div>
                                    <div>{{ __('Often easier to adjust spacing/alignment without fighting the classic column system.') }}</div>
                                </div>
                            </div>

                            <div>
                                <div class="font-semibold">{{ __('When you should NOT use “Container”') }}</div>
                                <div class="mt-2 space-y-1">
                                    <div>{{ __('Your site/workflow still uses classic Sections/Columns and you want the simplest navigator tree.') }}</div>
                                    <div>{{ __('Your Elementor setup does not have containers enabled (Flexbox Container feature).') }}</div>
                                </div>
                            </div>

                            <div>
                                <div class="font-semibold">{{ __('Rule of thumb') }}</div>
                                <div class="mt-2 space-y-1">
                                    <div>{{ __('Choose Classic for simpler structure and faster manual editing.') }}</div>
                                    <div>{{ __('Choose Container for modern flexbox layouts and better responsive behavior.') }}</div>
                                </div>
                            </div>
                        </div>
                    </details>

                    @if ($design->figma_url)
                        <div>
                            <div class="text-sm text-gray-500">{{ __('Figma Frame URL') }}</div>
                            <div class="mt-1 text-sm">
                                <a href="{{ $design->figma_url }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:text-indigo-900 hover:underline break-all">
                                    {{ $design->figma_url }}
                                </a>
                            </div>
                        </div>
                    @endif

                    <div>
                        <div class="text-sm text-gray-500">{{ __('Layout JSON') }}</div>
                        <pre class="mt-2 text-xs bg-gray-50 border rounded-md p-3 overflow-auto">{{ $design->layout_json ? json_encode($design->layout_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '' }}</pre>
                    </div>

                    <div class="pt-2">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-500">{{ __('HTML Preview') }}</div>
                            <a href="{{ route('designs.preview', $design) }}" target="_blank" class="text-xs font-medium text-indigo-600 hover:text-indigo-900">
                                {{ __('Open Preview') }}
                            </a>
                        </div>
                        <div class="mt-2 border rounded-md overflow-hidden bg-white">
                            <iframe
                                title="Design Preview"
                                src="{{ route('designs.preview', $design) }}"
                                class="w-full"
                                style="height: 520px"
                                sandbox
                            ></iframe>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            {{ __('Preview is rendered from stored HTML. For security, it is sandboxed in an iframe.') }}
                        </p>
                    </div>

                    <div class="pt-4 flex items-center justify-between">
                        <a href="{{ route('projects.designs.index', $project) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back to Designs') }}</a>

                        <form action="{{ route('designs.destroy', $design) }}" method="POST" onsubmit="return confirm('{{ __('Delete this design?') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                                {{ __('Delete Design') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
