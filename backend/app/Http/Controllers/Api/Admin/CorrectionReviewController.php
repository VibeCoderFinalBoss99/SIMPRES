<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewSubmissionRequest;
use App\Http\Resources\AttendanceCorrectionResource;
use App\Models\AttendanceCorrection;
use App\Services\SubmissionReviewer;
use Illuminate\Http\JsonResponse;

class CorrectionReviewController extends Controller
{
    public function update(ReviewSubmissionRequest $request, AttendanceCorrection $correction, SubmissionReviewer $reviewer): JsonResponse
    {
        $reviewer->review($correction, $request->user(), $request->status, $request->admin_note);

        return response()->json([
            'message' => $request->status === 'diterima' ? 'Koreksi absensi diterima.' : 'Koreksi absensi ditolak.',
            'data' => new AttendanceCorrectionResource($correction),
        ]);
    }
}
