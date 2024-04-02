<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Topology;

class Diagram extends Component
{
    public function render()
    {
        $topologies = Topology::all();
        $nodeDataArray = $this->convertToNodeDataArray($topologies);
        return view('livewire.diagram', ['nodeDataArrayJson' => json_encode($nodeDataArray, JSON_PRETTY_PRINT)]);
    }

    // Function to convert Eloquent collection to the desired structure
    private function convertToNodeDataArray($topologies)
    {
        $nodeDataArray = [];

        foreach ($topologies as $topology) {
            $node = [
                'key'   => $topology->id,
                'boss'  => $topology->parent_id,
                'name'  => $topology->device->nama,
                'keterangan' => $topology->keterangan,
                //''      =>
                // Add other properties as needed
            ];
            $ip = $topology->device->ip;
            if ($ip) {
                $node['title'] = $topology->device->type->nama . ", " . $ip->nama;
            }
            $ruangan = $topology->device->ruangan;
            if ($ruangan) {
                $node['headOf'] = $ruangan->nama . ", " . $ruangan->lantai->nama . ", " . $ruangan->lantai->gedung->nama;
            }

            $nodeDataArray[] = $node;
        }

        return $nodeDataArray;
    }
}
