<?php

namespace Deaduseful\DomainApi\Commands;

use Deaduseful\DomainApi\Service;
use Deaduseful\DomainApi\ServiceProvider;
use Illuminate\Console\Command;

class CheckCommand extends Command
{
    protected $signature = ServiceProvider::NAMESPACE . ':check {domain}';
    protected $description = 'Check if a domain is available or transferable';

    public function handle(Service $service): int
    {
        $domain = $this->argument('domain');
        $service->setQuery($domain);
        if ($service->isAvailable()) {
            $this->info(sprintf('Domain %s is available for registration.', $domain));
        } elseif ($service->canTransfer()) {
            $this->warn(sprintf('Domain %s is available for transfer.', $domain));
        } else {
            $this->error(sprintf('Domain %s is not available.', $domain));
        }
        return self::SUCCESS;
    }
}
