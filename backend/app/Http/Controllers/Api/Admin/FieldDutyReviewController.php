<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewSubmissionRequest;
use App\Http\Resources\FieldDutyResource;
use App\Models\FieldDuty;
use App\Services\SubmissionReviewer;
use Illuminate\Http\JsonResponse;

class FieldDutyReviewController extends Controller
{
    public function update(ReviewSubmissionRequest $request, FieldDuty $fieldDuty, SubmissionReviewer $reviewer): JsonResponse
    {
        $reviewer->review($fieldDuty, $request->user(), $request->status, $request->admin_note);

        return response()->json([
            'message' => $request->status === 'diterima' ? 'Tugas lapangan diterima.' : 'Tugas lapangan ditolak.',
            'data' => new FieldDutyResource($fieldDuty),
        ]);
    }
}
