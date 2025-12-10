<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Projects') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    @if (session('status'))
                        <div class="text-sm text-green-600">
                            {{ session('status') }}
                        </div>
                    @endif
                </div>
                <a href="{{ route('projects.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    {{ __('New Project') }}
                </a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($projects->isEmpty())
                        <p class="text-sm text-gray-600">{{ __('You do not have any projects yet.') }}</p>
                    @else
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-gray-500">
                                    <th class="py-2 pr-4">{{ __('Name') }}</th>
                                    <th class="py-2 pr-4">{{ __('Status') }}</th>
                                    <th class="py-2 pr-4">{{ __('Updated') }}</th>
                                    <th class="py-2 text-right">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($projects as $project)
                                    <tr class="border-b last:border-0">
                                        <td class="py-2 pr-4 font-medium text-gray-900">
                                            <a href="{{ route('projects.show', $project) }}" class="hover:underline">
                                                {{ $project->name }}
                                            </a>
                                        </td>
                                        <td class="py-2 pr-4">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $project->status === 'published' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                {{ ucfirst($project->status) }}
                                            </span>
                                        </td>
                                        <td class="py-2 pr-4 text-gray-500">
                                            {{ $project->updated_at->diffForHumans() }}
                                        </td>
                                        <td class="py-2 text-right space-x-2">
                                            <a href="{{ route('projects.edit', $project) }}" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium">{{ __('Edit') }}</a>
                                            <a href="{{ route('projects.show', $project) }}" class="text-gray-600 hover:text-gray-900 text-xs font-medium">{{ __('View') }}</a>
                                            <form action="{{ route('projects.destroy', $project) }}" method="POST" class="inline-block" onsubmit="return confirm('{{ __('Delete this project?') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900 text-xs font-medium">
                                                    {{ __('Delete') }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="mt-4">
                            {{ $projects->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
