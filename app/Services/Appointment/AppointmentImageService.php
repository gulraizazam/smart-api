<?php

declare(strict_types=1);

namespace App\Services\Appointment;

use App\Models\Appointmentimage;
use App\Models\Appointments;
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

                $fileName = time().'-'.str_replace(' ', '-', $file->getClientOriginalName());
                $file->storeAs('public/appointment_image', $fileName);

                $data['image_name'] = $file->getClientOriginalName();
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
            $ids = explode(',', $filters['delete']);
            $appointmentimages = Appointmentimage::getBulkData_forimage($ids);
            if ($appointmentimages) {
                foreach ($appointmentimages as $appointmentimages) {
                    if (! Appointmentimage::isChildExists($appointmentimages->id, $accountId)) {
                        $appointmentimages->delete();
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
