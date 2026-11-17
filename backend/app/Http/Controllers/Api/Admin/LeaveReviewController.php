<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewSubmissionRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Services\SubmissionReviewer;
use Illuminate\Http\JsonResponse;

class LeaveReviewController extends Controller
{
    public function update(ReviewSubmissionRequest $request, LeaveRequest $leave, SubmissionReviewer $reviewer): JsonResponse
    {
        $reviewer->review($leave, $request->user(), $request->status, $request->admin_note);

        return response()->json([
            'message' => $request->status === 'diterima' ? 'Izin diterima.' : 'Izin ditolak.',
            'data' => new LeaveRequestResource($leave),
        ]);
    }
}
