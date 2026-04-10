<?php

declare(strict_types=1);

namespace App\Services\Appointment;

use App\Models\Appointmentimage;
use App\Models\Appointments;
use App\Support\SafeFilename;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppointmentImageService
{
    public function getAppointment(int $id): mixed
    {
        return Appointments::findorfail($id);
    }

    public function storeImages(array $files, string $type, int $appointmentId): array
    {
        if ($type == 'checkedbefore') {
            $typeLabel = 'Before Appointment';
        } else {
            $typeLabel = 'After Appointment';
        }

        foreach ($files as $fileupload) {
            if ($fileupload) {
                $file = $fileupload;
                $ext = strtolower($file->getClientOriginalExtension());

                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
                    return [
                        'success' => false,
                        'error' => 'JPG, JPEG, PNG, GIF Only Allowed.',
                        'id' => $appointmentId,
                    ];
                }

                // Defence against filename injection — never trust the
                // browser-supplied name. SafeFilename strips path
                // components, NUL bytes, and exotic characters before we
                // join it to a storage path.
                $safeName = SafeFilename::sanitize(
                    $file->getClientOriginalName(),
                    ['jpg', 'jpeg', 'png', 'gif'],
                );
                $fileName = time().'-'.$safeName;
                $file->storeAs('public/appointment_image', $fileName);

                $data['image_name'] = $safeName;
                $data['image_path'] = $fileName;
                $data['type'] = $typeLabel;
                $data['appointment_id'] = $appointmentId;
                $appointment = Appointmentimage::createRecord($data, $appointmentId);
            } else {
                return [
                    'success' => false,
                    'error' => 'Kindly Select Image First',
                    'id' => $appointmentId,
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Picture save successfully.',
            'id' => $appointmentId,
        ];
    }

    public function getDatatableData(Request $request, int $accountId, int $appointmentId): array
    {
        $records = [];
        $records['data'] = [];

        $filters = getFilters($request->all());

        if (hasFilter($filters, 'delete')) {
            // Tenant-scoped: only delete images whose parent appointment
            // belongs to the caller's account. This closes a cross-tenant
            // IDOR where bulk-delete previously accepted arbitrary image
            // ids from the request without checking ownership.
            $ids = array_filter(array_map('intval', explode(',', (string) $filters['delete'])));
            if (!empty($ids)) {
                $appointmentimages = Appointmentimage::whereIn('appointmentimages.id', $ids)
                    ->whereHas('appointment', fn ($q) => $q->where('account_id', $accountId))
                    ->get();

                foreach ($appointmentimages as $appointmentimage) {
                    if (! Appointmentimage::isChildExists($appointmentimage->id, $accountId)) {
                        $appointmentimage->delete();
                    }
                }
            }
            $records['status'] = true;
            $records['message'] = 'Records has been deleted successfully!';
        }

        $iTotalRecords = Appointmentimage::getTotalRecords($request, $accountId, $appointmentId);
        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $appointmentimages = Appointmentimage::getRecords($request, $iDisplayStart, $iDisplayLength, $accountId, $appointmentId);
        if ($appointmentimages) {
            foreach ($appointmentimages as $appointmentimg) {
                $records['data'][] = [
                    'id' => $appointmentimg->id,
                    'image_id' => $appointmentimg->id,
                    'patient_id' => $appointmentimg->appointment->patient_id,
                    'image_path' => $appointmentimg->image_path,
                    'type' => $appointmentimg->type,
                    'created_at' => Carbon::parse($appointmentimg->created_at)->format('F j,Y h:i A'),
                ];
            }

            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];
        }

        $records['permissions'] = [
            'delete' => Gate::allows('appointments_image_destroy'),
        ];

        return $records;
    }

    public function deleteImage(int $id): array
    {
        $response = Appointmentimage::DeleteRecord($id);

        return [
            'status' => $response['status'],
            'message' => $response['message'],
        ];
    }
}
