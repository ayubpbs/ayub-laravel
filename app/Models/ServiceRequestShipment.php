<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A parcel that belongs to a claim: normally the goods coming back from the customer (inbound). */
class ServiceRequestShipment extends Model
{
    protected $fillable = [
        'service_request_id', 'direction', 'courier', 'tracking_code', 'status', 'shipped_at', 'received_at', 'note', 'created_by_name',
    ];

    protected $casts = ['shipped_at' => 'datetime', 'received_at' => 'datetime'];

    public static function directions(): array
    {
        return ['inbound' => 'Coming back from the customer', 'outbound' => 'Going to the customer'];
    }

    public static function statuses(): array
    {
        return ['pending' => 'Not shipped yet', 'in_transit' => 'On the way', 'received' => 'Received', 'failed' => 'Failed / lost'];
    }

    public static function badge(string $status): string
    {
        return ['pending' => 'st-pending', 'in_transit' => 'st-courier_sent', 'received' => 'st-delivered', 'failed' => 'st-cancelled'][$status] ?? 'st-pending';
    }

    public function request()
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }
}
