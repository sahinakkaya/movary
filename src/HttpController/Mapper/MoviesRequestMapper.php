<?php declare(strict_types=1);

namespace Movary\HttpController\Mapper;

use Movary\Domain\User\Service\UserPageAuthorizationChecker;
use Movary\HttpController\Dto\MoviesRequestDto;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Year;

class MoviesRequestMapper
{
    private const DEFAULT_GENRE = null;

    private const DEFAULT_RELEASE_YEAR = null;

    private const DEFAULT_LIMIT = 24;

    private const DEFAULT_SORT_BY = 'title';

    private const DEFAULT_SORT_ORDER = 'ASC';

    public function __construct(
        private readonly UserPageAuthorizationChecker $userPageAuthorizationChecker,
    ) {
    }

    public function mapRenderPageRequest(Request $request) : MoviesRequestDto
    {
        $userId = $this->userPageAuthorizationChecker->findUserIdIfCurrentVisitorIsAllowedToSeeUser((string)$request->getRouteParameters()['username']);

        $getParameters = $request->getGetParameters();

        $searchTerm = $getParameters['s'] ?? null;
        $page = $getParameters['p'] ?? 1;
        $limit = $getParameters['pp'] ?? self::DEFAULT_LIMIT;
        $sortBy = $getParameters['sb'] ?? self::DEFAULT_SORT_BY;
        $sortOrder = $getParameters['so'] ?? self::DEFAULT_SORT_ORDER;
        $releaseYear = $getParameters['ry'] ?? self::DEFAULT_RELEASE_YEAR;
        $releaseYear = empty($releaseYear) === false ? Year::createFromString($releaseYear) : null;
        $language = $getParameters['la'] ?? null;
        $genre = $getParameters['ge'] ?? self::DEFAULT_GENRE;

        return MoviesRequestDto::createFromParameters(
            $userId,
            $searchTerm,
            (int)$page,
            (int)$limit,
            $sortBy,
            $sortOrder,
            $releaseYear,
            $language,
            $genre,
        );
    }
}
