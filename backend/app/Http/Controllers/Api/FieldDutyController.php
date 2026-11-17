<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFieldDutyRequest;
use App\Http\Resources\FieldDutyResource;
use App\Models\FieldDuty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldDutyController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', 'in:menunggu,diterima,ditolak']]);

        $duties = $request->user()->fieldDuties()
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20);

        return FieldDutyResource::collection($duties);
    }

    public function store(StoreFieldDutyRequest $request): JsonResponse
    {
        $data = $request->safe()->except('foto');
        $data['status'] = 'menunggu';
        $data['photo_path'] = $request->file('foto')->store('tugas-lapangan');

        $duty = $request->user()->fieldDuties()->create($data)->refresh();

        return response()->json([
            'message' => 'Laporan tugas lapangan berhasil dikirim dan menunggu konfirmasi admin.',
            'data' => new FieldDutyResource($duty),
        ], 201);
    }

    public function show(FieldDuty $fieldDuty): FieldDutyResource
    {
        Gate::authorize('view', $fieldDuty);

        return new FieldDutyResource($fieldDuty);
    }

    public function destroy(FieldDuty $fieldDuty): JsonResponse
    {
        Gate::authorize('delete', $fieldDuty);

        Storage::delete($fieldDuty->photo_path);
        $fieldDuty->delete();

        return response()->json(['message' => 'Laporan tugas lapangan berhasil dibatalkan.']);
    }

    public function photo(FieldDuty $fieldDuty): StreamedResponse|JsonResponse
    {
        Gate::authorize('view', $fieldDuty);

        if (! Storage::exists($fieldDuty->photo_path)) {
            return response()->json(['message' => 'Foto tidak ditemukan.'], 404);
        }

        return Storage::response($fieldDuty->photo_path);
    }
}
