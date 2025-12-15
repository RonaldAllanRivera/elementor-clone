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
                        <option value="classic" {{ $format === 'container' ? '' : 'selected' }}>{{ __('Classic') }}</option>
                        <option value="container" {{ $format === 'container' ? 'selected' : '' }}>{{ __('Container') }}</option>
                    </select>
                    <span id="elementor-filename" class="hidden sm:inline text-xs text-gray-500"></span>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                        {{ __('Download JSON') }}
                    </button>
                </form>
                <a href="{{ route('designs.show', $design) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                    {{ __('Back') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500">
                            {{ __('Elementor JSON') }}
                        </div>
                        <button
                            type="button"
                            id="copy-json"
                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50"
                        >
                            {{ __('Copy') }}
                        </button>
                    </div>

                    <textarea
                        id="elementor-json"
                        class="w-full text-xs font-mono bg-gray-50 border rounded-md p-3"
                        rows="24"
                        readonly
                    >{{ $json }}</textarea>

                    <p class="text-xs text-gray-500">
                        {{ __('Tip: download is required for Elementor import. View/Copy is useful for inspection or saving a file manually.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const btn = document.getElementById('copy-json');
            const el = document.getElementById('elementor-json');

            if (!btn || !el) return;

            btn.addEventListener('click', async function () {
                try {
                    await navigator.clipboard.writeText(el.value);
                    btn.textContent = 'Copied';
                    setTimeout(() => (btn.textContent = 'Copy'), 1200);
                } catch (e) {
                    el.focus();
                    el.select();
                }
            });

            const select = document.getElementById('elementor-format');
            const label = document.getElementById('elementor-filename');

            if (!select || !label) return;

            const base = @json(Illuminate\Support\Str::slug($design->name) ?: 'design');

            function update() {
                const format = select.value === 'container' ? 'Container' : 'Classic';
                const suffix = select.value === 'container' ? '-container' : '';
                label.textContent = format + ' • ' + base + '-elementor' + suffix + '.json';
            }

            select.addEventListener('change', update);
            update();
        })();
    </script>
</x-app-layout>
