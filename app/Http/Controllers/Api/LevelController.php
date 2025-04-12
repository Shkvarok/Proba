<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LevelRequest;
use App\Http\Resources\LevelResource;
use App\Models\Level;
use App\Services\LevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LevelController extends Controller
{
    /**
     * @var LevelService
     */
    protected $levelService;

    /**
     * LevelController constructor.
     *
     * @param LevelService $levelService
     */
    public function __construct(LevelService $levelService)
    {
        $this->levelService = $levelService;
    }

    /**
     * Display a listing of the levels.
     */
    public function index(): AnonymousResourceCollection
    {
        return LevelResource::collection(
            $this->levelService->getAllLevels()
        );
    }

    /**
     * Store a newly created level in storage.
     */
    public function store(LevelRequest $request): LevelResource
    {
        $level = $this->levelService->createLevel($request->validated());

        return new LevelResource($level);
    }

    /**
     * Display the specified level.
     */
    public function show(Level $level): LevelResource
    {
        return new LevelResource($level);
    }

    /**
     * Update the specified level in storage.
     */
    public function update(LevelRequest $request, Level $level): LevelResource
    {
        $level = $this->levelService->updateLevel($level, $request->validated());

        return new LevelResource($level);
    }

    /**
     * Remove the specified level from storage.
     */
    public function destroy(Level $level): JsonResponse
    {
        $this->levelService->deleteLevel($level);

        return response()->json([
            'message' => 'Рівень успішно видалено'
        ]);
    }
}