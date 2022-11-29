<?php declare(strict_types=1);

namespace Movary\Worker;

use Movary\Api\Tmdb\Cache\TmdbImageCache;
use Movary\Application\Service\Letterboxd;
use Movary\Application\Service\Tmdb\SyncMovies;
use Movary\Application\Service\Trakt;
use Movary\Application\User\Api;
use Movary\ValueObject\DateTime;
use Movary\ValueObject\Job;
use Movary\ValueObject\JobStatus;
use Movary\ValueObject\JobType;

class Service
{
    public function __construct(
        private readonly Repository $repository,
        private readonly Trakt\ImportWatchedMovies $traktSyncWatchedMovies,
        private readonly Trakt\ImportRatings $traktSyncRatings,
        private readonly Letterboxd\ImportRatings $letterboxdImportRatings,
        private readonly Letterboxd\ImportHistory $letterboxdImportHistory,
        private readonly SyncMovies $tmdbSyncMovies,
        private readonly Api $userApi,
        private readonly TmdbImageCache $tmdbImageCache,
    ) {
    }

    public function fetchJobsForStatusPage(int $userId) : array
    {
        $jobs = $this->repository->fetchJobs($userId);

        $jobsData = [];
        foreach ($jobs as $job) {
            $jobUserId = $job->getUserId();

            $userName = $jobUserId === null ? null : $this->userApi->fetchUser($jobUserId)->getName();

            $jobsData[] = [
                'id' => $job->getId(),
                'type' => $job->getType(),
                'status' => $job->getStatus(),
                'userName' => $userName,
                'updatedAt' => $job->getUpdatedAt(),
                'createdAt' => $job->getCreatedAt(),
            ];
        }

        return $jobsData;
    }

    public function findLastImdbSync() : ?DateTime
    {
        return $this->repository->findLastDateForJobByType(JobType::createImdbSync());
    }

    public function findLastTmdbSync() : ?DateTime
    {
        return $this->repository->findLastDateForJobByType(JobType::createTmdbSync());
    }

    public function findLastTraktSync(int $userId) : ?DateTime
    {
        $ratingsDate = $this->repository->findLastDateForJobByTypeAndUserId(JobType::createTraktImportRatings(), $userId);
        $historyDate = $this->repository->findLastDateForJobByTypeAndUserId(JobType::createTraktImportHistory(), $userId);

        if ($ratingsDate > $historyDate) {
            return $ratingsDate;
        }

        return $historyDate;
    }

    public function processJob(Job $job) : void
    {
        match (true) {
            $job->getType()->isOfTypeLetterboxdImportRankings() => $this->letterboxdImportRatings->executeJob($job),
            $job->getType()->isOfTypeLetterboxdImportHistory() => $this->letterboxdImportHistory->executeJob($job),
            $job->getType()->isOfTypeTmdbImageCache() => $this->tmdbImageCache->executeJob($job),
            $job->getType()->isOfTypeTraktImportRatings() => $this->traktSyncRatings->executeJob($job),
            $job->getType()->isOfTypeTraktImportHistory() => $this->traktSyncWatchedMovies->executeJob($job),
            $job->getType()->isOfTypeTmdbSync() => $this->tmdbSyncMovies->syncMovies(),
            default => throw new \RuntimeException('Job type not supported: ' . $job->getType()),
        };
    }

    public function setJobToInProgress(int $id) : void
    {
        $this->repository->updateJobStatus($id, JobStatus::createInProgress());
    }
}
