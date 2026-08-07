<?php

namespace Deaduseful\DomainApi\Commands;

use Deaduseful\DomainApi\Service;
use Deaduseful\DomainApi\ServiceProvider;
use Illuminate\Console\Command;

class LookupCommand extends Command
{
    protected $signature = ServiceProvider::NAMESPACE . ':lookup {domain}';
    protected $description = 'Perform a domain lookup (RDAP with WHOIS fallback)';

    public function handle(Service $service): int
    {
        $domain = $this->argument('domain');
        $result = $service
            ->setQuery($domain)
            ->lookup();

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
