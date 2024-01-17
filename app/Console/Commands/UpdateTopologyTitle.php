<?php

namespace App\Console\Commands;

use App\Models\Topology;
use Illuminate\Console\Command;

class UpdateTopologyTitle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-topology-title';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all topologies title';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $topologies = Topology::all();

        foreach ($topologies as $topology) {
            $topology->updateTitle();
            $topology->save();
        }

        $this->info('Title updated successfully for all topologies.');
    }
}
