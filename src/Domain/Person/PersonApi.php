<?php declare(strict_types=1);

namespace Movary\Domain\Person;

use Movary\ValueObject\Date;
use Movary\ValueObject\DateTime;
use Movary\ValueObject\Gender;

class PersonApi
{
    public function __construct(
        private readonly PersonRepository $repository,
    ) {
    }

    public function create(
        int $tmdbId,
        string $name,
        Gender $gender,
        ?string $knownForDepartment,
        ?string $tmdbPosterPath,
        ?Date $birthDate = null,
        ?Date $deathDate = null,
        ?string $placeOfBirth = null,
        ?DateTime $updatedAtTmdb = null,
    ) : PersonEntity {
        return $this->repository->create($tmdbId, $name, $gender, $knownForDepartment, $tmdbPosterPath, $birthDate, $deathDate, $placeOfBirth, $updatedAtTmdb);
    }

    public function createOrUpdatePersonByTmdbId(
        int $tmdbId,
        string $name,
        Gender $gender,
        ?string $knownForDepartment,
        ?string $posterPath,
    ) : PersonEntity {
        $person = $this->findByTmdbId($tmdbId);

        if ($person === null) {
            return $this->create(
                tmdbId: $tmdbId,
                name: $name,
                gender: $gender,
                knownForDepartment: $knownForDepartment,
                tmdbPosterPath: $posterPath,
            );
        }

        if ($person->getName() !== $name ||
            $person->getGender() !== $gender ||
            $person->getKnownForDepartment() !== $knownForDepartment ||
            $person->getTmdbPosterPath() !== $posterPath
        ) {
            $this->update(
                $person->getId(),
                $tmdbId,
                $name,
                $gender,
                $knownForDepartment,
                $posterPath,
            );
        }

        return $person;
    }

    public function fetchAllOrderedByLastUpdatedAtTmdbAsc(?int $limit = null) : \Traversable
    {
        return $this->repository->fetchAllOrderedByLastUpdatedAtTmdbAsc($limit);
    }

    public function findById(int $personId) : ?PersonEntity
    {
        return $this->repository->findByPersonId($personId);
    }

    public function findByTmdbId(int $tmdbId) : ?PersonEntity
    {
        return $this->repository->findByTmdbId($tmdbId);
    }

    public function update(
        int $id,
        int $tmdbId,
        string $name,
        Gender $gender,
        ?string $knownForDepartment,
        ?string $tmdbPosterPath,
        ?Date $birthDate = null,
        ?Date $deathDate = null,
        ?string $placeOfBirth = null,
        ?DateTime $updatedAtTmdb = null,
    ) : PersonEntity {
        return $this->repository->update($id, $tmdbId, $name, $gender, $knownForDepartment, $tmdbPosterPath, $birthDate, $deathDate, $placeOfBirth, $updatedAtTmdb);
    }
}