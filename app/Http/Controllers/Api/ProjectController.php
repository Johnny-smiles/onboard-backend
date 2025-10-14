<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $q = Project::query(); if ($request->client_id) $q->where('client_id', $request->client_id);
        return response()->json($q->with('client')->get());
    }
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date'
        ]);
        $project = Project::create($data);
        return response()->json($project, 201);
    }
    public function show(Project $project) { return response()->json($project->load('client','photos')); }
}
