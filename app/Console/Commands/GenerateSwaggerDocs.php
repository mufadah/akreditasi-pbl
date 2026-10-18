<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateSwaggerDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'l5-swagger:generate {documentation? : The name of the documentation interface}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate Swagger/OpenAPI documentation via project standard swagger:sync';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Regenerating docs default via project standard swagger:sync...');

        return $this->call('swagger:sync');
    }
}
