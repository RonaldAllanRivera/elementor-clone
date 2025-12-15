<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDesignRequest;
use App\Http\Requests\UpdateDesignRequest;
use App\Models\Design;
use App\Models\Project;
use App\Services\LayoutToHtmlService;
use Illuminate\Http\Response;

class DesignController extends Controller
{
    private function assertProjectOwnership(Project $project): void
    {
        abort_unless($project->user_id === auth()->id(), 403);
    }

    private function assertDesignOwnership(Design $design): void
    {
        abort_unless($design->project && $design->project->user_id === auth()->id(), 403);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Project $project)
    {
        $this->assertProjectOwnership($project);
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
        $this->assertProjectOwnership($project);
        return view('designs.create', [
            'project' => $project,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDesignRequest $request, Project $project, LayoutToHtmlService $layoutToHtml)
    {
        $this->assertProjectOwnership($project);
        $data = $request->validated();
        $data['project_id'] = $project->id;

        if (array_key_exists('layout_json', $data)) {
            if ($data['layout_json'] === null || $data['layout_json'] === '') {
                $data['layout_json'] = null;
            } else {
                $data['layout_json'] = json_decode($data['layout_json'], true);
            }

            $data['html'] = $layoutToHtml->render($data['layout_json']);
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
        $this->assertDesignOwnership($design);

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
        $this->assertDesignOwnership($design);

        return view('designs.edit', [
            'design' => $design,
            'project' => $design->project,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDesignRequest $request, Design $design, LayoutToHtmlService $layoutToHtml)
    {
        $design->load('project');
        $this->assertDesignOwnership($design);
        $data = $request->validated();

        if (array_key_exists('layout_json', $data)) {
            if ($data['layout_json'] === null || $data['layout_json'] === '') {
                $data['layout_json'] = null;
            } else {
                $data['layout_json'] = json_decode($data['layout_json'], true);
            }

            $data['html'] = $layoutToHtml->render($data['layout_json']);
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
        $design->load('project');
        $this->assertDesignOwnership($design);

        $project = $design->project;

        $design->delete();

        return redirect()
            ->route('projects.designs.index', $project)
            ->with('status', 'Design deleted.');
    }

    public function preview(Design $design): Response
    {
        $design->load('project');
        $this->assertDesignOwnership($design);

        return response($design->html ?? '<!doctype html><html><body><p>No preview available.</p></body></html>')
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
