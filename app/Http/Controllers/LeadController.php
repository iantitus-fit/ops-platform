<?php

namespace App\Http\Controllers;

use App\Actions\InboundData;
use App\Actions\RecordInbound;
use App\Http\Requests\StoreLeadRequest;
use Illuminate\Http\JsonResponse;

class LeadController extends Controller
{
    /**
     * Record a web form lead and answer that it was received.
     */
    public function store(StoreLeadRequest $request, RecordInbound $recordInbound): JsonResponse
    {
        $recordInbound->handle(InboundData::fromLeadForm($request->validated()));

        return response()->json(['received' => true]);
    }
}
