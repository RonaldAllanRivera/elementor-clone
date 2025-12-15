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
                <a href="{{ route('designs.edit', $design) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    {{ __('Edit') }}
                </a>
                <a href="{{ route('projects.designs.index', $project) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                    {{ __('Designs') }}
                </a>
            </div>
        </div>
    </x-slot>

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
