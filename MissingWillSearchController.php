<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidDataException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MissingWillSearchRequest;
use Illuminate\Http\Request;
use App\Services\MissingWillSearchService;

class MissingWillSearchController extends Controller
{
    protected $missingWillSearchService;

    public function __construct()
    {
        $this->missingWillSearchService = new MissingWillSearchService();
    }

    public function store(MissingWillSearchRequest $request)
    {
        $response = $this->missingWillSearchService->processWillSearch($request);
        if ($response) {
            return $response;
        }
    }

    public function verifySearchPin(Request $request)
    {
        if (isset($request['searching_pin'])) {
            $response = $this->missingWillSearchService->verifySearchPin($request['searching_pin']);
            if ($response) {
                return $response;
            }
        }
        throw new InvalidDataException('Searching PIN CODE is not valid');
    }

    public function sendReportEmail(Request $request)
    {
        return $this->missingWillSearchService->processWillSearchReportEmail($request);
    }
}
