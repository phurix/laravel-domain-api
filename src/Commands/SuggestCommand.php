<?php

namespace Deaduseful\DomainApi\Commands;

use Deaduseful\DomainApi\Service;
use Deaduseful\DomainApi\ServiceProvider;
use Illuminate\Console\Command;

class SuggestCommand extends Command
{
    protected $signature = ServiceProvider::NAMESPACE . ':suggest {query}';
    protected $description = 'Suggest domains based on a query string';

    public function handle(): int
    {
        $query = $this->argument('query');
        $service = new Service();
        $suggestions = $service
            ->setQuery($query)
            ->suggest();
        print_r($suggestions);
        return self::SUCCESS;
    }
}
