<?php

namespace App\Imports;

use App\Models\Topology;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class TopologyImport implements ToCollection
{
    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        foreach ($collection as $key => $row) 
        {

            if ($key === 0) {
                //
            } else {
                /* //if (User::find($row[0])) {}
                if ($row[2])
                {
                    Topology::updateOrCreate(
                        ['id' => $row[0],],[
                        'parent_id' => $row[1],
                        'keterangan' => $row[2],
                    ]);
                } else {
                    Topology::updateOrCreate(
                        ['id' => $row[0],],[
                        'parent_id' => $row[1],
                    ]);
                } */
                Topology::updateOrCreate(
                    ['id' => $row[0],],[
                    'parent_id' => $row[1],
                ]);
            }           
        }
    }
}
