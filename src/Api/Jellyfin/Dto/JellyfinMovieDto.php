<?php declare(strict_types=1);

namespace Movary\Api\Jellyfin\Dto;

use Movary\ValueObject\Date;

class JellyfinMovieDto
{
    private function __construct(
        private readonly string $jellyfinUserId,
        private readonly string $jellyfinItemId,
        private readonly int $tmdbID,
        private readonly bool $watched,
        private readonly ?Date $lastWatchDate,
    ) {
    }

    public static function create(string $jellyfinUserId, string $jellyfinItemId, int $tmdbId, bool $watched, ?Date $lastWatchDate) : self
    {
        return new self($jellyfinUserId, $jellyfinItemId, $tmdbId, $watched, $lastWatchDate);
    }

    public static function createFromArray(array $movieData) : self
    {
        return self::create(
            $movieData['jellyfin_user_id'],
            $movieData['jellyfin_item_id'],
            $movieData['tmdb_id'],
            (bool)$movieData['watched'],
            isset($movieData['last_watch_date']) === true ? Date::createFromString($movieData['last_watch_date']) : null,
        );
    }

    public function getJellyfinItemId() : string
    {
        return $this->jellyfinItemId;
    }

    public function getJellyfinUserId() : string
    {
        return $this->jellyfinUserId;
    }

    public function getLastWatchDate() : ?Date
    {
        return $this->lastWatchDate;
    }

    public function getWatched() : bool
    {
        return $this->watched;
    }

    public function getTmdbId() : int
    {
        return $this->tmdbID;
    }
}
