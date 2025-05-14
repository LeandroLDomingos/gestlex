<?php

namespace App\Http\Controllers;

use App\Models\Process;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProcessController extends Controller
{
    /**
     * Display a listing of the processes.
     */
    public function index()
    {
        $processes = Process::get();
        return Inertia::render('processes/Index', [
            'processes'       => $processes,
        ]);
    }
    /**
     * Show the form for creating a new process.
     */
    public function create()
    {
        return Inertia::render('processes/Create', [
            'workflowOptions' => Process::getWorkflowOptions(),
        ]);
    }

    /**
     * Store a newly created process in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'origin'           => 'nullable|string|max:255',
            'negotiated_value' => 'nullable|numeric',
            'description'      => 'nullable|string',
            'responsible_id'   => 'required|uuid|exists:users,id',
            'workflow'         => ['required', Rule::in(array_keys(Process::getWorkflowOptions()))],
            'stage'            => 'nullable|integer',
        ]);

        Process::create($data);

        return redirect()->route('processes.index')
                         ->with('success', 'Process created successfully.');
    }

    /**
     * Display the specified process.
     */
    public function show(Process $process)
    {
        $process->load('responsible', 'contacts');

        return Inertia::render('Processes/Show', [
            'process' => $process,
        ]);
    }

    /**
     * Show the form for editing the specified process.
     */
    public function edit(Process $process)
    {
        return Inertia::render('Processes/Edit', [
            'process'         => $process,
            'workflowOptions' => Process::getWorkflowOptions(),
        ]);
    }

    /**
     * Update the specified process in storage.
     */
    public function update(Request $request, Process $process)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'origin'           => 'nullable|string|max:255',
            'negotiated_value' => 'nullable|numeric',
            'description'      => 'nullable|string',
            'responsible_id'   => 'required|uuid|exists:users,id',
            'workflow'         => ['required', Rule::in(array_keys(Process::getWorkflowOptions()))],
            'stage'            => 'nullable|integer',
        ]);

        $process->update($data);

        return redirect()->route('processes.show', $process)
                         ->with('success', 'Process updated successfully.');
    }

    /**
     * Remove the specified process from storage.
     */
    public function destroy(Process $process)
    {
        $process->delete();

        return redirect()->route('processes.index')
                         ->with('success', 'Process deleted successfully.');
    }
}