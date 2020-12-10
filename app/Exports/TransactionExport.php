<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TransactionExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    protected $collection;
    public function headings():array{
        return [
            "Provider Name",
            "Note",
            "Date Ordered",
            "Total",
            "Est. Total",
            "Status",
            "Status Text",
            "Driver Name",
            "Customer Name"
        ];
    }
    public function __construct($data)
    {
        $this->collection = $data;
    }
    public function collection()
    {
        return $this->collection;
    }
}
