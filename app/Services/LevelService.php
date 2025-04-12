<?php

namespace App\Services;

use App\Models\Level;
use App\Repositories\LevelRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class LevelService
{
    /**
     * @var LevelRepository
     */
    protected $levelRepository;

    /**
     * LevelService constructor.
     *
     * @param LevelRepository $levelRepository
     */
    public function __construct(LevelRepository $levelRepository)
    {
        $this->levelRepository = $levelRepository;
    }

    /**
     * Get all levels.
     */
    public function getAllLevels(): Collection
    {
        return $this->levelRepository->getAll();
    }

    /**
     * Create a new level.
     *
     * @param array $data
     * @return Level
     */
    public function createLevel(array $data): Level
    {
        // Нормалізуємо код рівня
        if (isset($data['code'])) {
            $data['code'] = Str::slug($data['code']);
        }

        return $this->levelRepository->create($data);
    }

    /**
     * Update a level.
     *
     * @param Level $level
     * @param array $data
     * @return Level
     */
    public function updateLevel(Level $level, array $data): Level
    {
        // Нормалізуємо код рівня
        if (isset($data['code'])) {
            $data['code'] = Str::slug($data['code']);
        }

        return $this->levelRepository->update($level, $data);
    }

    /**
     * Delete a level.
     *
     * @param Level $level
     * @return bool|null
     */
    public function deleteLevel(Level $level): ?bool
    {
        return $this->levelRepository->delete($level);
    }

    /**
     * Get level by code.
     *
     * @param string $code
     * @return Level|null
     */
    public function getLevelByCode(string $code): ?Level
    {
        return $this->levelRepository->findByCode($code);
    }
}