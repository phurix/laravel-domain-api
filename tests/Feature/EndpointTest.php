<?php

namespace Deaduseful\DomainApi\Tests\Feature;

namespace Deaduseful\DomainApi\Tests\Feature;

use Deaduseful\DomainApi\Tests\TestCase;

class EndpointTest extends TestCase
{
    public function testAvailable()
    {
        $route = 'domain-api/example.com/available';
        $content = $this->withoutMiddleware()->getJson($route)->assertOk()->getContent();
        $response = json_decode($content, true);
        $this->assertIsString($response['response']);
        $this->assertIsInt($response['code']);
        $this->assertIsString($response['status']);
    }

    public function testTransferable()
    {
        $route = 'domain-api/example.com/transferable';
        $response = $this->withoutMiddleware()->getJson($route)->assertOk()->getContent();
        $content = json_decode($response, true);
        $this->assertIsString($content['response']);
        $this->assertIsInt($content['code']);
        $this->assertIsString($content['status']);
    }

    public function testWhois()
    {
        $route = 'domain-api/example.com/whois';
        $content = $this->withoutMiddleware()->getJson($route)->assertOk()->getContent();
        $response = json_decode($content, true);
        $this->assertIsString($response['tld']);
        $this->assertIsString($response['server']);
        $this->assertIsString($response['result']);
    }
}
