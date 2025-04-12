<?php

namespace App\Repositories;

use App\Models\Level;
use Illuminate\Database\Eloquent\Collection;

class LevelRepository
{
    /**
     * Get all levels.
     */
    public function getAll(): Collection
    {
        return Level::all();
    }

    /**
     * Get level by code.
     *
     * @param string $code
     * @return Level|null
     */
    public function findByCode(string $code): ?Level
    {
        return Level::where('code', $code)->first();
    }

    /**
     * Create a new level.
     *
     * @param array $data
     * @return Level
     */
    public function create(array $data): Level
    {
        return Level::create($data);
    }

    /**
     * Update a level.
     *
     * @param Level $level
     * @param array $data
     * @return Level
     */
    public function update(Level $level, array $data): Level
    {
        $level->update($data);
        return $level;
    }

    /**
     * Delete a level.
     *
     * @param Level $level
     * @return bool|null
     */
    public function delete(Level $level): ?bool
    {
        return $level->delete();
    }
}