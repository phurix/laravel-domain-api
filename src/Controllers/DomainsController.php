<?php

namespace Deaduseful\Controllers;

use Deaduseful\Services\DomainService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class DomainsController extends Controller
{
    public function availability(string $query): JsonResponse
    {
        $result = DomainService::availability($query);
        return new JsonResponse($result);
    }

    public function transferable(Request $request, string $query): JsonResponse
    {
        $test = $request->get('test', false);
        $result = DomainService::transferable($query, $test);
        return new JsonResponse($result);
    }

    public function whois(string $query, ?string $server = null): JsonResponse
    {
        try {
            $result = DomainService::whois($query, $server);
            return new JsonResponse($result);
        } catch (DomainException|UnexpectedValueException $exception) {
            $result = ['error' => $exception->getMessage()];
            return new JsonResponse($result, Response::HTTP_BAD_REQUEST);
        }
    }

    public function suggest(Request $request, string $query): JsonResponse
    {
        $test = $request->get('test', false);
        $tlds = $request->has('tlds') ? explode(',', $request->get('tlds')) : DomainService::TLDS;
        $services = $request->has('services') ? explode(',', $request->get('services')) : DomainService::SUGGEST_SERVICES;
        $result = DomainService::suggest($query, $tlds, $services, $test);
        return new JsonResponse($result);
    }
}
