<?php

namespace Deaduseful\DomainApi\Commands;

use Deaduseful\DomainApi\Service;
use Deaduseful\DomainApi\ServiceProvider;
use Illuminate\Console\Command;

class RdapCommand extends Command
{
    protected $signature = ServiceProvider::NAMESPACE . ':rdap {domain} {--server=}';
    protected $description = 'Perform an RDAP lookup on a domain';

    public function handle(Service $service): int
    {
        $domain = $this->argument('domain');
        $server = $this->option('server') ?: null;
        $result = $service
            ->setQuery($domain)
            ->rdap($server);

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
