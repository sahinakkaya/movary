<?php declare(strict_types=1);

namespace Movary\HttpController;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\Movie\MovieApi;
use Movary\Service\Tmdb\SyncMovie;
use Movary\Service\Netflix\ImportNetflixActivity;
use Movary\ValueObject\Date;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use Movary\ValueObject\PersonalRating;
use Movary\Api\Tmdb\TmdbApi;
use Movary\Util\Json;
use RuntimeException;
use Psr\Log\LoggerInterface;

class NetflixController
{
    public function __construct(
        private readonly Authentication $authenticationService,
        private readonly MovieApi $movieApi,
        private readonly SyncMovie $tmdbMovieSyncService,
        private readonly LoggerInterface $logger,
        private readonly TmdbApi $tmdbapi,
        private readonly ImportNetflixActivity $importActivity
    ){}

    /**
     * importNetflixActivity receives a CSV file with all the Netflix activity history and tries to process this. 
     * It filters the movies out with regex patterns, and then compiles an array of all the movie items.
     *
     * @param Request $request the CSV file containing the Netflix data
     * @return Response HTTP response with either an error code or JSON object containing the TMDB results 
     */
    public function processNetflixActivity(Request $request) : Response
    {
        if ($this->authenticationService->isUserAuthenticated() === false) {
            return Response::createSeeOther('/');
        }

        $searchresults = [];
        $files = $request->getFileParameters();
        if(empty($files)) {
            return Response::createBadRequest();
        }
        $csv = $files['netflixviewactivity'];
        if($csv['size'] == 0) {
            return Response::createBadRequest();
        }
        // finfo_open is way more reliable to detect MIME type than 'type' from the $_FILES variable
        // It does however require the magic module, which may be a pain to set up.
        // https://www.php.net/manual/en/function.finfo-open.php
        if($csv['type'] != "application/vnd.ms-excel" && $csv['type'] != 'text/csv') {
            return Response::createUnsupportedMediaType();
        }
        $rows = $this->importActivity->parseNetflixCSV($csv['tmp_name']);
        if($rows != []) {
            foreach($rows as $row) {
                $date = date_parse_from_format('d/m/Y', $row['Date']);
                $data = $this->importActivity->checkMediaData($row['Title']);
                
                if($data != false) {
                    if($data['type'] == 'Movie') {
                        $search = $this->tmdbapi->searchMovie($data['movieName']);
                        $searchresults[$data['movieName']] = [
                            'result' => $search[0] ?? 'Unknown',
                            'date' => $date,
                            'originalname' => $data['movieName']
                        ];
                        $this->logger->info('Item is a movie: ' . $data['movieName']);
                    }
                }
            }
            $jsonresponse = Json::encode($searchresults);
            return Response::createJson($jsonresponse);
        } else {
            return Response::createBadRequest();
        }
    }

    /**
     * searchTMDB receives an HTTP POST request and searches for the TMDB item. It returns a JSON object with all the results from TMDB
     *
     * @param Request $request The HTTP POST request
     * @return Response the JSON object with the TMDB data
     */
    public function searchTMDB(Request $request) : Response
    {
        if ($this->authenticationService->isUserAuthenticated() === false) {
            return Response::createSeeOther('/');
        }
        $jsondata = $request->getBody();
        $input = Json::decode($jsondata);
        $tmdbresults = $this->tmdbapi->searchMovie($input['query']);
        $response = Json::encode($tmdbresults);
        return Response::createJson($response);
    }

    /**
     * importNetflixData receives an HTTP POST request containing a JSON object with TMDB items matches with Netflix data. It converts the JSON object to an array and loops through this.
     *
     * @param Request $request The HTTP POST request
     * @return Response 
     */
    public function importNetflixData(Request $request) : Response
    {
        if ($this->authenticationService->isUserAuthenticated() === false) {
            return Response::createSeeOther('/');
        }
        $userId = $this->authenticationService->getCurrentUserId();
        $items = Json::decode($request->getBody());
        foreach($items as $item) {
            if(isset($item['watchDate'], $item['tmdbId'], $item['dateFormat']) === false) {
                throw new RuntimeException('Missing parameters');
            }
            $watchDate = Date::createFromStringAndFormat($item['watchDate'], $item['dateFormat']);
            
            $tmdbId = (int)$item['tmdbId'];
            $personalRating = $item['personalRating'] === 0 ? null : PersonalRating::create((int)$item['personalRating']);
    
            $movie = $this->movieApi->findByTmdbId($tmdbId);
    
            if ($movie === null) {
                $movie = $this->tmdbMovieSyncService->syncMovie($tmdbId);
            }
    
            $this->movieApi->updateUserRating($movie->getId(), $userId, $personalRating);
            $this->movieApi->increaseHistoryPlaysForMovieOnDate($movie->getId(), $userId, $watchDate);
            $this->logger->info('Movie has been logged: '. $tmdbId);
        }
        $this->logger->info('All the movies from Netflix have been imported');
        return Response::create(StatusCode::createOk());
    }
}