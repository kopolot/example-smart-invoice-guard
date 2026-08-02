<?php

namespace App\Console\Commands;

use App\RabbitMq\RabbitMqTopology;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rabbitmq:setup-topology {--fresh : Delete and recreate work queues so DLX arguments are applied}')]
#[Description('Declare RabbitMQ work queues, dead-letter exchange, and failed queues')]
class SetupRabbitMqTopologyCommand extends Command
{
    public function __construct(private RabbitMqTopology $topology)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $fresh = (bool) $this->option('fresh');

        if ($fresh) {
            $this->components->warn('Recreating work queues — any pending messages on those queues will be lost.');
        }

        try {
            $result = $this->topology->setup(fresh: $fresh);
        } catch (\Throwable $e) {
            $this->components->error('Failed to set up RabbitMQ topology: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Declared dead-letter exchange ['.$result['exchange'].'].');
        $this->components->info('Work queues: '.implode(', ', $result['queues']));
        $this->components->info('Failed queues: '.implode(', ', $result['failed_queues']));

        if ($result['refreshed'] !== []) {
            $this->components->info('Recreated with DLX args: '.implode(', ', $result['refreshed']));
        }

        return self::SUCCESS;
    }
}
