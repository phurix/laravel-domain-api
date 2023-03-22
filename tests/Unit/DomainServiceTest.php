<?php

namespace Deaduseful\DomainApi\Tests\Unit;

use Deaduseful\DomainApi\Services\DomainService;
use Deaduseful\DomainApi\Tests\TestCase;

class DomainServiceTest extends TestCase
{
    public function testAvailable()
    {
        $query = 'example.com';
        $service = new DomainService();
        $result = $service
            ->setQuery($query)
            ->availability();
        $this->assertIsString($result['response']);
        $this->assertIsInt($result['code']);
        $this->assertIsString($result['status']);
    }

    public function testTransferable()
    {
        $query = 'example.com';
        $service = new DomainService();
        $result = $service
            ->setQuery($query)
            ->transferable();
        $this->assertIsString($result['response']);
        $this->assertIsInt($result['code']);
        $this->assertIsString($result['status']);
    }

    public function testWhois()
    {
        $query = 'example.com';
        $service = new DomainService();
        $result = $service
            ->setQuery($query)
            ->whois();
        $this->assertIsArray($result);
    }

    public function testTransferableInt()
    {
        $query = 'example.int';
        $service = new DomainService();
        $result = $service
            ->setQuery($query)
            ->transferable();
        $this->assertIsString($result['response']);
        $this->assertIsInt($result['code']);
        $this->assertIsString($result['status']);
    }
}
