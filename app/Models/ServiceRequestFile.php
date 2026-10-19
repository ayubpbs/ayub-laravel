<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One private evidence file (photo or video). It lives in storage/app, outside public/, and is only served to signed-in admins. */
class ServiceRequestFile extends Model
{
    protected $fillable = ['service_request_id', 'path', 'original_name', 'mime', 'size', 'kind', 'note', 'uploaded_by_name'];

    public function request()
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }

    public function isVideo(): bool
    {
        return $this->kind === 'video';
    }

    public function sizeLabel(): string
    {
        $b = (int) $this->size;

        return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
    }
}
