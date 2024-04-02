<?php

namespace App\Jobs;

use Acamposm\Ping\Ping;
use App\Models\IpAddress;
use Illuminate\Bus\Queueable;
use Acamposm\Ping\PingCommandBuilder;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class PingIpAddress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $ipAddresses = IpAddress::where('booked', true)->get(); // Assuming IPAddress is your Eloquent model

        foreach ($ipAddresses as $ipAddress) {

            $output = exec("ping -c 1 ".$ipAddress->nama, $results, $return);
            dd($results);
            if ($return === 0) {
                $ipAddress->status = true;
                $ipAddress->save();
            }
        }
    }
}
