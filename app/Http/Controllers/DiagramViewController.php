<?php

namespace App\Http\Controllers;

use App\Models\Topology;
use Illuminate\Http\Request;

class DiagramViewController extends Controller
{
    public function index()
{
    $topologies = Topology::all();
    $nodeDataArray = $this->convertToNodeDataArray($topologies);
    //dd($nodeDataArray);
    return view('iframe.diagram', ['nodeDataArrayJson' => json_encode($nodeDataArray, JSON_PRETTY_PRINT)]);
}

// Function to convert Eloquent collection to the desired structure
private function convertToNodeDataArray($topologies) {
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
