<?php

namespace App\Http\Controllers\Api;

use App\DTO\Processes\CreateProcessDTO;
use App\DTO\Processes\EditProcessDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProcessRequest;
use App\Http\Requests\Api\UpdateProcessRequest;
use App\Http\Resources\ProcessResource;
use App\Repositories\ProcessRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProcessController extends Controller
{
    public function __construct(private ProcessRepository $processRepository)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $processes = $this->processRepository->getPaginate(
            totalPerPage: $request->total_per_page ?? 15,
            page: $request->page ?? 1,
            filter: $request->get('filter', '')
        );
        return ProcessResource::collection($processes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProcessRequest $request)
    {
        $process = $this->processRepository->createNew(new CreateProcessDTO(... $request->validated()));
        return new ProcessResource($process);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if (!$process = $this->processRepository->findById($id)){
            return response()->json(['message' => 'process not found'], Response::HTTP_NOT_FOUND);
        }
        return new ProcessResource($process);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProcessRequest $request, string $id)
    {
        $response = $this->processRepository->update(new EditProcessDTO(...[$id, ...$request->validated()]));
        if (!$response){
            return response()->json(['message' => 'process not found'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['message' => 'process updated with sucess']);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (!$response = $this->processRepository->delete($id)){
            return response()->json(['message' => 'process not found'], Response::HTTP_NOT_FOUND);
        }
        return response()->json([], Response::HTTP_NO_CONTENT);
    }
}
