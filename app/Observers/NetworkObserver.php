<?php

namespace App\Observers;

use App\Models\IpAddress;
use App\Models\Network;

class NetworkObserver
{
    /**
     * Handle the Network "created" event.
     */
    public function created(Network $network): void
    {
        $startIP = $network->hostMin;
        $endIP = $network->hostMax;

        $network_id = $network->id;

        $startLong = ip2long($startIP);
        $endLong = ip2long($endIP);

        for ($i = $startLong; $i <= $endLong; $i++) {
            IpAddress::create([
                'network_id' => $network_id,
                'nama' => long2ip($i),
            ]);
        }
    }

    /**
     * Handle the Network "updated" event.
     */
    public function updated(Network $network): void
    {
        $startIPOld = $network->getOriginal('hostMin');
        $endIPOld = $network->getOriginal('hostMax');

        $startIP = $network->hostMin;
        $endIP = $network->hostMax;

        $network_id = $network->id;

        $startLongOld = ip2long($startIPOld);
        $endLongOld = ip2long($endIPOld);

        $startLong = ip2long($startIP);
        $endLong = ip2long($endIP);

        $availOld = (int) $network->getOriginal('hostPerNet');
        $avail = (int) $network->getOriginal('hostPerNet');

        if ($avail > $availOld) {
            //sekarang lebih banyak
            if ($startIP === $startIPOld) {
                //IP tidak berubah
                $startLong = $endLongOld + 1;
                for ($i = $startLong; $i <= $endLong; $i++) {
                    IpAddress::create([
                        'network_id' => $network_id,
                        'nama' => ($i),
                    ]);
                }
            } else {
                //Ip berubah
                $idStart = IpAddress::where('nama', $startIPOld)->first()->id;
                $idEnd = IpAddress::where('nama', $endIPOld)->first()->id;
                for ($i = $startLong; $i <= $endLong; $i++) {
                    if ($idStart <= $idEnd) {
                        $ipNew = IpAddress::find($idStart);
                        $ipNew->nama = ($i);
                        $ipNew->save();
                        $idStart += 1;
                    } else {
                        IpAddress::create([
                            'network_id' => $network_id,
                            'nama' => ($i),
                        ]);
                    }
                }
                for ($i = $startLong; $i <= $endLong; $i++) {
                    IpAddress::create([
                        'network_id' => $network_id,
                        'nama' => ($i),
                    ]);
                }
            }
        } elseif ($avail < $availOld) {
            //sekarang lebih sedikit
            if ($startIP === $startIPOld) {
                //ip tidak berubah
                $startLong = $endLong + 1;
                for ($i = $startLong; $i <= $endLongOld; $i++) {
                    $ipNew = IpAddress::where('nama', $i)->first();
                    $ipNew->delete();
                }
            } else {
                //ip berubah
            
                $idStart = IpAddress::where('nama', $startIPOld)->first()->id;
                $idEnd = IpAddress::where('nama', $endIPOld)->first()->id;
                $stopper = $network->hostPerNet;
                $count = 1;
                for ($i = $idStart; $i <= $idEnd; $i++) {
                    if ($count <= $stopper){
                        $ipNew = IpAddress::find($i);
                        $ipNew->nama = ($startLong);
                        $ipNew->save();
                        $startLong += 1;
                    } else {
                        $ipNew = IpAddress::find($i);
                        $ipNew->delete();
                    }
                    $count += 1;
                } 
            }
        } else {
            $idStart = IpAddress::where('nama', $startIPOld)->first()->id;
            $idEnd = IpAddress::where('nama', $endIPOld)->first()->id;
            for ($i = $startLong; $i <= $endLong; $i++) {
                $ipNew = IpAddress::find($idStart);
                $ipNew->nama = ($i);
                $ipNew->save();
            }
        }
    }

    /**
     * Handle the Network "deleted" event.
     */
    public function deleted(Network $network): void
    {
        //
    }

    /**
     * Handle the Network "restored" event.
     */
    public function restored(Network $network): void
    {
        //
    }

    /**
     * Handle the Network "force deleted" event.
     */
    public function forceDeleted(Network $network): void
    {
        //
    }
}
