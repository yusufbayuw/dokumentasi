<?php

namespace App\Jobs;

use App\Models\IpAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SinglePingIpAddress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */

    public $ipAddress;
    public $ipId;

    public function __construct($ipAddress, $ipId)
    {
        $this->ipAddress = $ipAddress;
        $this->ipId = $ipId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $output = exec("ping -c 1 " . $this->ipAddress, $results, $return);

        if (strpos($results[4] ?? '0', '0% packet loss') !== false) {
            $IpAddr = IpAddress::find($this->ipId);
            $IpAddr->status = true;
            $IpAddr->save();
        }
    }
}
