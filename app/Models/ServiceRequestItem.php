<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequestItem extends Model
{
    protected $fillable = [
        'service_request_id', 'order_item_id', 'product_id', 'received_product_id', 'title', 'price', 'quantity',
        'qty_restock', 'qty_supplier', 'qty_scrap', 'inspected_at', 'inspected_by_name', 'inspection_note',
    ];

    protected $casts = ['inspected_at' => 'datetime'];

    public function request()
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function receivedProduct()
    {
        return $this->belongsTo(Product::class, 'received_product_id');
    }

    public function isInspected(): bool
    {
        return $this->inspected_at !== null;
    }

    /** The product whose stock goes up when goods are put back on the shelf */
    public function stockProductId(): ?int
    {
        $id = $this->received_product_id ?: $this->product_id;

        return $id ? (int) $id : null;
    }
}
