<?php declare(strict_types=1);

namespace Movary\Api\Github;

use Exception;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class GithubApi
{
    private const GITHUB_LATEST_RELEASES_URL = 'https://api.github.com/repos/leepeuker/movary/releases/latest';

    public function __construct(
        private readonly Client $httpClient,
        private readonly ReleaseMapper $releaseMapper,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function fetchLatestMovaryRelease() : ?ReleaseDto
    {
        try {
            $response = $this->httpClient->get(self::GITHUB_LATEST_RELEASES_URL);
        } catch (Exception $e) {
            $this->logger->warning('Could not send request to fetch latest github releases.', ['exception' => $e]);

            return null;
        }

        if ($response->getStatusCode() !== 200) {
            $this->logger->warning('Request to fetch latest github releases failed with status code: ' . $response->getStatusCode());

            return null;
        }

        return $this->releaseMapper->mapFromApiJsonResponse($response->getBody()->getContents());
    }
}
