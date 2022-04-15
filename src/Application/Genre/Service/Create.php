<?php declare(strict_types=1);

namespace Movary\Application\Genre\Service;

use Movary\Application\Genre\Entity;
use Movary\Application\Genre\Repository;

class Create
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function create(string $name, int $tmdbId) : Entity
    {
        return $this->repository->create($name, $tmdbId);
    }
}