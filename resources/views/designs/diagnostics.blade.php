<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Design Diagnostics') }}
                </h2>
                <div class="text-sm text-gray-500">
                    {{ $design->name }}
                    <span class="mx-2">/</span>
                    <a href="{{ route('designs.show', $design) }}" class="hover:underline">
                        {{ __('Back to Design') }}
                    </a>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (!empty($report['warnings']))
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-md p-4">
                    <div class="font-semibold text-sm">{{ __('Warnings') }}</div>
                    <ul class="mt-2 text-sm list-disc list-inside">
                        @foreach ($report['warnings'] as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <div class="text-sm text-gray-500">{{ __('Totals') }}</div>
                            <div class="text-sm"><span class="font-semibold">{{ __('Total nodes:') }}</span> {{ $report['total_nodes'] ?? 0 }}</div>
                            <div class="text-sm"><span class="font-semibold">{{ __('Images:') }}</span> {{ $report['images']['total'] ?? 0 }}</div>
                            <div class="text-sm"><span class="font-semibold">{{ __('Placeholder images:') }}</span> {{ $report['images']['placeholders'] ?? 0 }}</div>
                        </div>

                        <div class="space-y-2">
                            <div class="text-sm text-gray-500">{{ __('Style coverage') }}</div>
                            @php($styles = $report['styles'] ?? [])
                            <div class="text-sm"><span class="font-semibold">{{ __('Backgrounds:') }}</span> {{ $styles['backgroundColor'] ?? 0 }}</div>
                            <div class="text-sm"><span class="font-semibold">{{ __('Borders:') }}</span> {{ $styles['border'] ?? 0 }}</div>
                            <div class="text-sm"><span class="font-semibold">{{ __('Radius:') }}</span> {{ $styles['borderRadius'] ?? 0 }}</div>
                            <div class="text-sm"><span class="font-semibold">{{ __('Shadows:') }}</span> {{ $styles['boxShadow'] ?? 0 }}</div>
                            <div class="text-sm"><span class="font-semibold">{{ __('Typography:') }}</span> {{ $styles['typography'] ?? 0 }}</div>
                            <div class="text-sm"><span class="font-semibold">{{ __('Padding:') }}</span> {{ $styles['padding'] ?? 0 }}</div>
                            <div class="text-sm"><span class="font-semibold">{{ __('Gaps:') }}</span> {{ $styles['gap'] ?? 0 }}</div>
                            <div class="text-sm"><span class="font-semibold">{{ __('Width % hints:') }}</span> {{ $styles['widthPercent'] ?? 0 }}</div>
                            <div class="text-sm"><span class="font-semibold">{{ __('Flex grow hints:') }}</span> {{ $styles['flexGrow'] ?? 0 }}</div>
                        </div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">{{ __('Node types') }}</div>
                        <div class="mt-2 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-gray-500">
                                        <th class="py-2 pr-4">{{ __('Type') }}</th>
                                        <th class="py-2">{{ __('Count') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php($types = $report['types'] ?? [])
                                    @foreach ($types as $type => $count)
                                        <tr class="border-t">
                                            <td class="py-2 pr-4 font-medium text-gray-800">{{ $type }}</td>
                                            <td class="py-2 text-gray-700">{{ $count }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">{{ __('Raw layout JSON') }}</div>
                        <pre class="mt-2 text-xs bg-gray-50 border rounded-md p-3 overflow-auto">{{ $design->layout_json ? json_encode($design->layout_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '' }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
