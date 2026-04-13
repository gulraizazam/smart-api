<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DriveUploadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDocument extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'file_name',
        'file_path',
        'google_drive_url',
        'google_drive_file_id',
        'drive_upload_status',
        'uploaded_by',
        'account_id',
    ];

    protected function casts(): array
    {
        return [
            'drive_upload_status' => DriveUploadStatus::class,
        ];
    }

    // ── Relationships ──

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    // ── Scopes ──

    public function scopeForEmployee(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopePendingUpload(Builder $query): Builder
    {
        return $query->whereIn('drive_upload_status', [
            DriveUploadStatus::Pending,
            DriveUploadStatus::Failed,
        ]);
    }

    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }
}
