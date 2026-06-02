<?php

namespace App\Filament\Admin\Resources\Complaints\Pages;

use App\Filament\Admin\Resources\Complaints\ComplaintResource;
use Filament\Resources\Pages\ListRecords;

class ListComplaints extends ListRecords
{
    protected static string $resource = ComplaintResource::class;
}
