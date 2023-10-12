<?php declare(strict_types=1);

namespace Movary\HttpController\Api\RequestMapper;

use Movary\HttpController\Api\Dto\WatchlistRequestDto;
use Movary\ValueObject\Http\Request;

class PlayedRequestMapper
{
    private const DEFAULT_SORT_ORDER = 'asc';

    private const DEFAULT_SORT_BY = 'title';

    public function __construct(private readonly RequestMapper $requestMapper)
    {
    }

    public function mapRequest(Request $request) : WatchlistRequestDto
    {
        $getParameters = $request->getGetParameters();

        $sortBy = $getParameters['sortBy'] ?? self::DEFAULT_SORT_BY;

        return WatchlistRequestDto::create(
            $this->requestMapper->mapUsernameFromRoute($request)->getId(),
            $this->requestMapper->mapSearchTerm($request),
            $this->requestMapper->mapPage($request),
            $this->requestMapper->mapLimit($request),
            $sortBy,
            $this->requestMapper->mapSortOrder($request, self::DEFAULT_SORT_ORDER),
            $this->requestMapper->mapReleaseYear($request),
        );
    }
}
