<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDesignRequest;
use App\Http\Requests\UpdateDesignRequest;
use App\Models\Design;
use App\Models\Project;
use Illuminate\Http\Request;

class DesignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Project $project)
    {
        $designs = $project->designs()->latest()->paginate(10);

        return view('designs.index', [
            'project' => $project,
            'designs' => $designs,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Project $project)
    {
        return view('designs.create', [
            'project' => $project,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDesignRequest $request, Project $project)
    {
        $data = $request->validated();
        $data['project_id'] = $project->id;

        if (! empty($data['layout_json'])) {
            $data['layout_json'] = json_decode($data['layout_json'], true);
        }

        $design = Design::create($data);

        return redirect()
            ->route('designs.show', $design)
            ->with('status', 'Design created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Design $design)
    {
        $design->load('project');

        return view('designs.show', [
            'design' => $design,
            'project' => $design->project,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Design $design)
    {
        $design->load('project');

        return view('designs.edit', [
            'design' => $design,
            'project' => $design->project,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDesignRequest $request, Design $design)
    {
        $data = $request->validated();

        if (! empty($data['layout_json'])) {
            $data['layout_json'] = json_decode($data['layout_json'], true);
        }

        $design->update($data);

        return redirect()
            ->route('designs.show', $design)
            ->with('status', 'Design updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Design $design)
    {
        $project = $design->project;

        $design->delete();

        return redirect()
            ->route('projects.designs.index', $project)
            ->with('status', 'Design deleted.');
    }
}
