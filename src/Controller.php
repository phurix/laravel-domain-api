<?php

namespace Deaduseful\DomainApi;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class Controller extends BaseController
{
    private Service $service;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    public function availability(string $query): JsonResponse
    {
        $result = $this->service
            ->setQuery($query)
            ->availability();
        return new JsonResponse($result);
    }

    public function transferable(Request $request, string $query): JsonResponse
    {
        $test = $request->get('test', false);
        $result = $this->service
            ->setQuery($query)
            ->transferable($test);
        return new JsonResponse($result);
    }

    public function whois(string $query, ?string $server = null): JsonResponse
    {
        try {
            $result = $this->service
                ->setQuery($query)
                ->whois($server);
            return new JsonResponse($result);
        } catch (DomainException|UnexpectedValueException $exception) {
            $result = ['error' => $exception->getMessage()];
            return new JsonResponse($result, Response::HTTP_BAD_REQUEST);
        }
    }

    public function rdap(string $query, ?string $server = null): JsonResponse
    {
        try {
            $result = $this->service
                ->setQuery($query)
                ->rdap($server);
            return new JsonResponse($result);
        } catch (DomainException|UnexpectedValueException $exception) {
            $result = ['error' => $exception->getMessage()];
            return new JsonResponse($result, Response::HTTP_BAD_REQUEST);
        }
    }

    public function suggest(Request $request, string $query): JsonResponse
    {
        $test = $request->get('test', false);
        $tlds = $request->has('tlds') ? explode(',', $request->get('tlds')) : Service::TLDS;
        $services = $request->has('services') ? explode(',', $request->get('services')) : Service::SUGGEST_SERVICES;
        $result = $this->service
            ->setQuery($query)
            ->suggest($tlds, $services, $test);
        return new JsonResponse($result);
    }
}
