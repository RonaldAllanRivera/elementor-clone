<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $project->name }}
            </h2>
            <div class="flex items-center space-x-2">
                <a href="{{ route('projects.edit', $project) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    {{ __('Edit') }}
                </a>
                <a href="{{ route('projects.designs.index', $project) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                    {{ __('Designs') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 text-sm text-green-600">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm text-gray-500">{{ __('Slug') }}</div>
                            <div class="font-medium">{{ $project->slug }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">{{ __('Status') }}</div>
                            <div>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $project->status === 'published' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($project->status) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    @if ($project->description)
                        <div>
                            <div class="text-sm text-gray-500">{{ __('Description') }}</div>
                            <div class="mt-1 text-sm text-gray-800">{{ $project->description }}</div>
                        </div>
                    @endif

                    <div class="pt-2">
                        <div class="text-sm text-gray-500">{{ __('Designs') }}</div>
                        <div class="mt-2">
                            @if ($project->designs->isEmpty())
                                <p class="text-sm text-gray-600">{{ __('No designs yet.') }}</p>
                            @else
                                <ul class="divide-y border rounded-md">
                                    @foreach ($project->designs as $design)
                                        <li class="p-3 flex items-center justify-between">
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $design->name }}</div>
                                                <div class="text-xs text-gray-500">{{ $design->updated_at->diffForHumans() }}</div>
                                            </div>
                                            <div class="space-x-2">
                                                <a href="{{ route('designs.show', $design) }}" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium">{{ __('View') }}</a>
                                                <a href="{{ route('designs.edit', $design) }}" class="text-gray-600 hover:text-gray-900 text-xs font-medium">{{ __('Edit') }}</a>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>

                    <div class="pt-4 flex items-center justify-between">
                        <a href="{{ route('projects.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back to Projects') }}</a>

                        <form action="{{ route('projects.destroy', $project) }}" method="POST" onsubmit="return confirm('{{ __('Delete this project?') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                                {{ __('Delete Project') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
