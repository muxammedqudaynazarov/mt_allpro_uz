<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return AuditLogResource::collection(AuditLog::with('user')->latest('id')->paginate(30));
    }
}
