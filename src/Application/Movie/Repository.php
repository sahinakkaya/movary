<?php declare(strict_types=1);

namespace Movary\Application\Movie;

use Doctrine\DBAL\Connection;
use Movary\Api\Trakt\ValueObject\Movie\TraktId;
use Movary\ValueObject\Date;
use Movary\ValueObject\DateTime;
use Movary\ValueObject\Gender;
use Movary\ValueObject\PersonalRating;
use RuntimeException;

class Repository
{
    public function __construct(private readonly Connection $dbConnection)
    {
    }

    public function create(
        string $title,
        int $tmdbId,
        ?string $tagline = null,
        ?string $overview = null,
        ?string $originalLanguage = null,
        ?Date $releaseDate = null,
        ?int $runtime = null,
        ?float $tmdbVoteAverage = null,
        ?int $tmdbVoteCount = null,
        ?string $tmdbPosterPath = null,
        ?TraktId $traktId = null,
        ?string $imdbId = null,
    ) : Entity {
        $this->dbConnection->insert(
            'movie',
            [
                'title' => $title,
                'tagline' => $tagline,
                'overview' => $overview,
                'original_language' => $originalLanguage,
                'release_date' => $releaseDate,
                'runtime' => $runtime,
                'tmdb_vote_average' => $tmdbVoteAverage,
                'tmdb_vote_count' => $tmdbVoteCount,
                'tmdb_poster_path' => $tmdbPosterPath,
                'trakt_id' => $traktId?->asInt(),
                'imdb_id' => $imdbId,
                'tmdb_id' => $tmdbId,
            ]
        );

        return $this->fetchById((int)$this->dbConnection->lastInsertId());
    }

    public function fetchAll() : EntityList
    {
        $data = $this->dbConnection->fetchAllAssociative('SELECT * FROM `movie`');

        return EntityList::createFromArray($data);
    }

    public function fetchAllOrderedByLastUpdatedAtTmdbAsc() : EntityList
    {
        $data = $this->dbConnection->fetchAllAssociative('SELECT * FROM `movie` ORDER BY updated_at_tmdb ASC');

        return EntityList::createFromArray($data);
    }

    public function fetchAverageRuntime(int $userId) : float
    {
        return (float)$this->dbConnection->executeQuery(
            'SELECT AVG(runtime)
            FROM movie
            WHERE id IN (SELECT DISTINCT movie_id FROM movie_user_watch_dates mh WHERE user_id = ?)',
            [$userId]
        )->fetchFirstColumn()[0];
    }

    public function fetchFirstHistoryWatchDate(int $userId) : ?Date
    {
        $stmt = $this->dbConnection->prepare(
            'SELECT watched_at FROM movie_user_watch_dates WHERE user_id = ? ORDER BY watched_at ASC'
        );

        $stmt->bindValue(1, $userId);
        $watchDate = $stmt->executeQuery()->fetchOne();

        if (empty($watchDate) === true) {
            return null;
        }

        return Date::createFromString($watchDate);
    }

    public function fetchHistoryByMovieId(int $movieId) : array
    {
        return $this->dbConnection->fetchAllAssociative(
            'SELECT * FROM movie_user_watch_dates WHERE movie_id = ?',
            [$movieId]
        );
    }

    public function fetchHistoryCount(int $userId, ?string $searchTerm = null) : int
    {
        if ($searchTerm !== null) {
            return $this->dbConnection->fetchFirstColumn(
                <<<SQL
                SELECT COUNT(*)
                FROM movie_user_watch_dates mh
                JOIN movie m on mh.movie_id = m.id
                WHERE m.title LIKE "%$searchTerm%" AND user_id = $userId
                SQL
            )[0];
        }

        return $this->dbConnection->fetchFirstColumn(
            'SELECT COUNT(*) FROM movie_user_watch_dates'
        )[0];
    }

    public function fetchHistoryOrderedByWatchedAtDesc() : array
    {
        return $this->dbConnection->fetchAllAssociative(
            'SELECT m.*, mh.watched_at
            FROM movie_user_watch_dates mh
            JOIN movie m on mh.movie_id = m.id
            ORDER BY watched_at DESC'
        );
    }

    public function fetchHistoryPaginated(int $limit, int $page, ?string $searchTerm) : array
    {
        $payload = [];
        $offset = ($limit * $page) - $limit;

        $whereQuery = '';
        if ($searchTerm !== null) {
            $payload[] = "%$searchTerm%";
            $whereQuery = 'WHERE m.title LIKE ?';
        }

        return $this->dbConnection->fetchAllAssociative(
            <<<SQL
            SELECT m.*, mh.watched_at
            FROM movie_user_watch_dates mh
            JOIN movie m on mh.movie_id = m.id
            $whereQuery
            ORDER BY watched_at DESC
            LIMIT $offset, $limit
            SQL,
            $payload
        );
    }

    public function fetchHistoryUniqueMovies() : array
    {
        return $this->dbConnection->fetchAllAssociative(
            <<<SQL
            SELECT m.*
            FROM movie_user_watch_dates mh
            JOIN movie m on mh.movie_id = m.id
            GROUP BY mh.movie_id
            SQL,
        );
    }

    public function fetchLastPlays(int $userId) : array
    {
        return $this->dbConnection->executeQuery(
            'SELECT m.*, mh.watched_at
            FROM movie_user_watch_dates mh
            JOIN movie m on mh.movie_id = m.id
            WHERE mh.user_id = ?
            ORDER BY watched_at DESC
            LIMIT 6',
            [$userId]
        )->fetchAllAssociative();
    }

    public function fetchMostWatchedActors(int $userId, int $page = 1, ?int $limit = null, ?Gender $gender = null, ?string $searchTerm = null) : array
    {
        $payload = [$userId];

        $limitQuery = '';
        if ($limit !== null) {
            $offset = ($limit * $page) - $limit;
            $limitQuery = "LIMIT $offset, $limit";
        }
        $genderQuery = '';
        if ($gender !== null) {
            $genderQuery = 'AND p.gender = ?';
            $payload[] = $gender;
        }
        $searchTermQuery = '';
        if ($searchTerm !== null) {
            $searchTermQuery = 'AND p.name LIKE ?';
            $payload[] = "%$searchTerm%";
        }

        return $this->dbConnection->fetchAllAssociative(
            <<<SQL
            SELECT p.id, p.name, COUNT(*) as count, p.gender, p.tmdb_poster_path
            FROM movie m
            JOIN movie_cast mc ON m.id = mc.movie_id
            JOIN person p ON mc.person_id = p.id
            WHERE m.id IN (SELECT DISTINCT movie_id FROM movie_user_watch_dates mh WHERE mh.user_id = ?) AND p.name != "Stan Lee" {$genderQuery} {$searchTermQuery}
            GROUP BY mc.person_id
            ORDER BY COUNT(*) DESC, p.name
            {$limitQuery}
            SQL,
            $payload
        );
    }

    public function fetchMostWatchedActorsCount(?string $searchTerm) : int
    {
        $payload = [];
        $searchTermQuery = '';
        if ($searchTerm !== null) {
            $searchTermQuery = 'AND p.name LIKE ?';
            $payload[] = "%$searchTerm%";
        }

        $count = $this->dbConnection->fetchOne(
            <<<SQL
            SELECT COUNT(DISTINCT p.id)
            FROM movie m
            JOIN movie_cast mc ON m.id = mc.movie_id
            JOIN person p ON mc.person_id = p.id
            WHERE m.id IN (SELECT DISTINCT movie_id FROM movie_user_watch_dates mh) AND p.name != "Stan Lee" {$searchTermQuery}
            SQL,
            $payload
        );

        if ($count === false) {
            throw new \RuntimeException('Could not execute query.');
        }

        return (int)$count;
    }

    public function fetchMostWatchedDirectors(int $userId, int $page = 1, ?int $limit = null, ?string $searchTerm = null) : array
    {
        $limitQuery = '';
        if ($limit !== null) {
            $offset = ($limit * $page) - $limit;
            $limitQuery = "LIMIT $offset, $limit";
        }
        $payload = [$userId];
        $searchTermQuery = '';
        if ($searchTerm !== null) {
            $searchTermQuery = 'AND p.name LIKE ?';
            $payload[] = "%$searchTerm%";
        }

        return $this->dbConnection->fetchAllAssociative(
            <<<SQL
            SELECT p.id, p.name, COUNT(*) as count, p.gender, p.tmdb_poster_path
            FROM movie m
            JOIN movie_crew mc ON m.id = mc.movie_id AND job = "Director"
            JOIN person p ON mc.person_id = p.id
            WHERE m.id IN (SELECT DISTINCT movie_id FROM movie_user_watch_dates mh WHERE user_id = ?) {$searchTermQuery}
            GROUP BY mc.person_id
            ORDER BY COUNT(*) DESC, p.name
            {$limitQuery}
            SQL,
            $payload
        );
    }

    public function fetchMostWatchedDirectorsCount(?string $searchTerm = null) : int
    {
        $payload = [];
        $searchTermQuery = '';
        if ($searchTerm !== null) {
            $searchTermQuery = 'AND p.name LIKE ?';
            $payload[] = "%$searchTerm%";
        }

        $count = $this->dbConnection->fetchOne(
            <<<SQL
            SELECT COUNT(DISTINCT p.id)
            FROM movie m
            JOIN movie_crew mc ON m.id = mc.movie_id AND job = "Director"
            JOIN person p ON mc.person_id = p.id
            WHERE m.id IN (SELECT DISTINCT movie_id FROM movie_user_watch_dates mh) {$searchTermQuery}
            SQL,
            $payload
        );

        if ($count === false) {
            throw new \RuntimeException('Could not execute query.');
        }

        return (int)$count;
    }

    public function fetchMostWatchedGenres(int $userId) : array
    {
        return $this->dbConnection->fetchAllAssociative(
            'SELECT g.name, COUNT(*) as count
            FROM movie m
            JOIN movie_genre mg ON m.id = mg.movie_id
            JOIN genre g ON mg.genre_id = g.id
            WHERE m.id IN (SELECT DISTINCT movie_id FROM movie_user_watch_dates mh WHERE user_id = ?)
            GROUP BY g.name
            ORDER BY COUNT(*) DESC, g.name',
            [$userId]
        );
    }

    public function fetchMostWatchedLanguages(int $userId) : array
    {
        return $this->dbConnection->fetchAllAssociative(
            'SELECT DISTINCT original_language AS language, COUNT(*) AS count
            FROM movie m
            WHERE m.id IN (SELECT DISTINCT movie_id FROM movie_user_watch_dates mh WHERE user_id = ?)
            GROUP BY original_language
            ORDER BY COUNT(*) DESC, original_language',
            [$userId]
        );
    }

    public function fetchMostWatchedProductionCompanies(int $userId, ?int $limit = null) : array
    {
        $limitQuery = '';
        if ($limit !== null) {
            $limitQuery = 'LIMIT ' . $limit;
        }

        return $this->dbConnection->fetchAllAssociative(
            <<<SQL
            SELECT c.id, c.name, COUNT(*) as count, c.origin_country
            FROM movie m
                     JOIN movie_production_company mpc ON m.id = mpc.movie_id
                     JOIN company c ON mpc.company_id = c.id
            WHERE m.id IN (SELECT DISTINCT movie_id FROM movie_user_watch_dates mh WHERE user_id = ?)
            GROUP BY mpc.company_id
            ORDER BY COUNT(*) DESC, c.name
            {$limitQuery}
            SQL,
            [$userId]
        );
    }

    public function fetchMostWatchedReleaseYears(int $userId) : array
    {
        return $this->dbConnection->fetchAllAssociative(
            <<<SQL
            SELECT year(release_date) as name, COUNT(*) as count
            FROM movie m
            WHERE m.id IN (SELECT DISTINCT movie_id FROM movie_user_watch_dates mh WHERE user_id = ?)
            GROUP BY year(release_date)
            ORDER BY COUNT(*) DESC, year(release_date)
            SQL,
            [$userId]
        );
    }

    public function fetchMoviesByProductionCompany(int $id) : array
    {
        return $this->dbConnection->fetchAllAssociative(
            'SELECT m.title 
            FROM movie m
            JOIN movie_production_company mpc ON m.id = mpc.movie_id
            WHERE mpc.company_id = ?',
            [$id]
        );
    }

    public function fetchMoviesOrderedByMostWatchedDesc() : array
    {
        return $this->dbConnection->fetchAllAssociative(
            'SELECT m.title, COUNT(*) AS views
            FROM movie_user_watch_dates mh
            JOIN movie m on mh.movie_id = m.id
            GROUP BY m.title
            ORDER BY COUNT(*) DESC, m.title'
        );
    }

    public function fetchPersonalRating(int $userId) : float
    {
        return (float)$this->dbConnection->fetchFirstColumn(
            'SELECT AVG(rating)
            FROM movie_user_rating
            WHERE user_id = ?',
            [$userId]
        )[0];
    }

    public function fetchPlaysForMovieIdAtDate(int $movieId, Date $watchedAt) : int
    {
        $result = $this->dbConnection->fetchOne(
            'SELECT plays FROM movie_user_watch_dates WHERE movie_id = ? AND watched_at = ?',
            [$movieId, $watchedAt]
        );

        if ($result === false) {
            return 0;
        }

        return $result;
    }

    public function fetchTotalMinutesWatched(int $userId) : int
    {
        return (int)$this->dbConnection->executeQuery(
            'SELECT SUM(m.runtime)
            FROM movie_user_watch_dates mh
            JOIN movie m ON mh.movie_id = m.id
            WHERE mh.user_id = ?',
            [$userId]
        )->fetchFirstColumn()[0];
    }

    public function fetchUniqueMovieInHistoryCount(int $userId) : int
    {
        return $this->dbConnection->executeQuery(
            'SELECT COUNT(DISTINCT movie_id) FROM movie_user_watch_dates WHERE user_id = ?',
            [$userId]
        )->fetchFirstColumn()[0];
    }

    public function fetchWithActor(int $personId) : array
    {
        return $this->dbConnection->fetchAllAssociative(
            <<<SQL
            SELECT m.*
            FROM movie m
            JOIN movie_cast mc ON m.id = mc.movie_id
            JOIN person p ON mc.person_id = p.id
            WHERE p.id = ? AND m.id IN (SELECT DISTINCT movie_id FROM movie_user_watch_dates mh)
            ORDER BY m.title
            SQL,
            [$personId]
        );
    }

    public function fetchWithDirector(int $personId) : array
    {
        return $this->dbConnection->fetchAllAssociative(
            <<<SQL
            SELECT m.*
            FROM movie m
            JOIN movie_crew mc ON m.id = mc.movie_id AND job = "Director"
            JOIN person p ON mc.person_id = p.id
            WHERE p.id = ? AND m.id IN (SELECT DISTINCT movie_id FROM movie_user_watch_dates mh)
            ORDER BY m.title
            SQL,
            [$personId]
        );
    }

    public function findById(int $movieId) : ?Entity
    {
        $data = $this->dbConnection->fetchAssociative('SELECT * FROM `movie` WHERE id = ?', [$movieId]);

        return $data === false ? null : Entity::createFromArray($data);
    }

    public function findByLetterboxdId(string $letterboxdId) : ?Entity
    {
        $data = $this->dbConnection->fetchAssociative('SELECT * FROM `movie` WHERE letterboxd_id = ?', [$letterboxdId]);

        return $data === false ? null : Entity::createFromArray($data);
    }

    public function findByTmdbId(int $tmdbId) : ?Entity
    {
        $data = $this->dbConnection->fetchAssociative('SELECT * FROM `movie` WHERE tmdb_id = ?', [$tmdbId]);

        return $data === false ? null : Entity::createFromArray($data);
    }

    public function findByTraktId(TraktId $traktId) : ?Entity
    {
        $data = $this->dbConnection->fetchAssociative('SELECT * FROM `movie` WHERE trakt_id = ?', [$traktId->asInt()]);

        return $data === false ? null : Entity::createFromArray($data);
    }

    public function findPlaysForMovieIdAndDate(int $movieId, Date $watchedAt) : ?int
    {
        $result = $this->dbConnection->fetchFirstColumn(
            <<<SQL
            SELECT plays
            FROM movie_user_watch_dates
            WHERE movie_id = ? AND watched_at = ?
            SQL,
            [$movieId, $watchedAt]
        );

        return $result[0] ?? null;
    }

    public function updateDetails(
        int $id,
        ?string $tagline,
        ?string $overview,
        ?string $originalLanguage,
        ?DateTime $releaseDate,
        ?int $runtime,
        ?float $tmdbVoteAverage,
        ?int $tmdbVoteCount,
        ?string $tmdbPosterPath,
        ?string $imdbId,
    ) : Entity {
        $this->dbConnection->update(
            'movie',
            [
                'tagline' => $tagline,
                'overview' => $overview,
                'original_language' => $originalLanguage,
                'release_date' => $releaseDate === null ? null : Date::createFromDateTime($releaseDate),
                'runtime' => $runtime,
                'tmdb_vote_average' => $tmdbVoteAverage,
                'tmdb_vote_count' => $tmdbVoteCount,
                'tmdb_poster_path' => $tmdbPosterPath,
                'updated_at_tmdb' => (string)DateTime::create(),
                'imdb_id' => $imdbId,
            ],
            ['id' => $id]
        );

        return $this->fetchById($id);
    }

    public function updateLetterboxdId(int $id, string $letterboxdId) : void
    {
        $this->dbConnection->update('movie', ['letterboxd_id' => $letterboxdId], ['id' => $id]);
    }

    public function updatePersonalRating(int $id, int $userId, ?PersonalRating $personalRating) : void
    {
        $this->dbConnection->executeQuery(
            'INSERT INTO movie_user_rating (movie_id, user_id, rating) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating=?',
            [$id, $userId, $personalRating, $personalRating]
        );
    }

    public function updateTraktId(int $id, TraktId $traktId) : void
    {
        $this->dbConnection->update('movie', ['trakt_id' => $traktId->asInt()], ['id' => $id]);
    }

    private function fetchById(int $id) : Entity
    {
        $data = $this->dbConnection->fetchAssociative('SELECT * FROM `movie` WHERE id = ?', [$id]);

        if ($data === false) {
            throw new RuntimeException('No movie found by id: ' . $id);
        }

        return Entity::createFromArray($data);
    }
}
