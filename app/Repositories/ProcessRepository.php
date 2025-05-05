<?php 

namespace App\Repositories;

use App\DTO\Processes\CreateProcessDTO;
use App\DTO\Processes\EditProcessDTO;
use App\Models\Api\Process;
use Illuminate\Pagination\LengthAwarePaginator;

class ProcessRepository
{
    public function __construct(protected Process $process)
    {
    }

    public function getPaginate(int $totalPerPage = 15, int $page = 1, string $filter = ''): LengthAwarePaginator
    {
        return $this->process->where(function ($query) use ($filter) {
            if ($filter !== '') {
                $query->where('name', 'LIKE', "%{$filter}%");
            }
        })->paginate($totalPerPage, ['*'], 'page', $page);
    }

    public function createNew(CreateProcessDTO $dto): Process
    {
        return $this->process->create((array)$dto);
    }

    public function findById(string $id): ?Process
    {
        return $this->process->find($id);
    }

    public function update(EditProcessDTO $dto): bool
    {
        if (!$process = $this->findById($dto->id)) {
            return false;
        }
        return $process->update((array)$dto);
    }

    public function delete(string $id): bool
    {
        if (!$process = $this->findById($id)) {
            return false;
        }
        return $process->delete();
    }
}