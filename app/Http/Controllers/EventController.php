<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Queries\EventQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function index(EventQuery $events): AnonymousResourceCollection
    {
        return EventResource::collection($events->upcoming());
    }
}
