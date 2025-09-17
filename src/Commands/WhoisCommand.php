<?php

namespace Deaduseful\DomainApi\Commands;

use Deaduseful\DomainApi\Service;
use Deaduseful\DomainApi\ServiceProvider;
use Illuminate\Console\Command;

class WhoisCommand extends Command
{
    protected $signature = ServiceProvider::NAMESPACE . ':whois {domain}';
    protected $description = 'Perform a whois lookup on a domain';

    public function handle(Service $service): int
    {
        $domain = $this->argument('domain');
        $result = $service
            ->setQuery($domain)
            ->whois();
        print_r($result);
        return self::SUCCESS;
    }
}
