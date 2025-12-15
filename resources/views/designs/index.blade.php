<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Designs') }}
            </h2>
            <div class="flex items-center space-x-2">
                <a href="{{ route('projects.show', $project) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    {{ __('Project') }}
                </a>
                <a href="{{ route('projects.designs.create', $project) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                    {{ __('New Design') }}
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
                <div class="p-6 text-gray-900">
                    @if ($designs->isEmpty())
                        <p class="text-sm text-gray-600">{{ __('No designs yet.') }}</p>
                    @else
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-gray-500">
                                    <th class="py-2 pr-4">{{ __('Name') }}</th>
                                    <th class="py-2 pr-4">{{ __('Updated') }}</th>
                                    <th class="py-2 text-right">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($designs as $design)
                                    <tr class="border-b last:border-0">
                                        <td class="py-2 pr-4 font-medium text-gray-900">
                                            <a href="{{ route('designs.show', $design) }}" class="hover:underline">
                                                {{ $design->name }}
                                            </a>
                                        </td>
                                        <td class="py-2 pr-4 text-gray-500">
                                            {{ $design->updated_at->diffForHumans() }}
                                        </td>
                                        <td class="py-2 text-right space-x-2">
                                            <a href="{{ route('designs.show', $design) }}" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium">{{ __('View') }}</a>
                                            <a href="{{ route('designs.edit', $design) }}" class="text-gray-600 hover:text-gray-900 text-xs font-medium">{{ __('Edit') }}</a>
                                            <form action="{{ route('designs.destroy', $design) }}" method="POST" class="inline-block" onsubmit="return confirm('{{ __('Delete this design?') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900 text-xs font-medium">{{ __('Delete') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="mt-4">
                            {{ $designs->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
