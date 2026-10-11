<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Small client for the Steadfast Courier merchant API.
 * Every method returns an array and never throws, so a courier outage cannot break an admin page.
 */
class Steadfast
{
    // Statuses after which Steadfast will not change the parcel any more
    const FINAL = ['delivered', 'partial_delivered', 'cancelled'];

    public static function configured(): bool
    {
        return config('shop.steadfast_key') !== '' && config('shop.steadfast_secret') !== '';
    }

    public static function statuses(): array
    {
        return [
            'pending'                           => 'Pending',
            'in_review'                         => 'In review',
            'hold'                              => 'On hold',
            'delivered_approval_pending'        => 'Delivered (awaiting approval)',
            'partial_delivered_approval_pending' => 'Partly delivered (awaiting approval)',
            'cancelled_approval_pending'        => 'Cancelled (awaiting approval)',
            'unknown_approval_pending'          => 'Unknown (awaiting approval)',
            'delivered'                         => 'Delivered',
            'partial_delivered'                 => 'Partly delivered',
            'cancelled'                         => 'Cancelled',
            'unknown'                           => 'Unknown',
        ];
    }

    public static function label(?string $status): string
    {
        return $status ? (self::statuses()[$status] ?? ucfirst(str_replace('_', ' ', $status))) : '—';
    }

    /** CSS class (from admin.css) for a courier status badge */
    public static function badge(?string $status): string
    {
        $status = (string) $status;
        if (Str::startsWith($status, 'cancelled')) return 'st-cancelled';
        if (Str::startsWith($status, 'partial'))   return 'st-confirmed';
        if (Str::startsWith($status, 'delivered')) return 'st-delivered';
        if ($status === 'pending')                 return 'st-pending';
        return 'st-courier_sent';
    }

    private static function http()
    {
        return Http::baseUrl(rtrim(config('shop.steadfast_base_url'), '/'))
            ->withHeaders([
                'Api-Key'    => config('shop.steadfast_key'),
                'Secret-Key' => config('shop.steadfast_secret'),
            ])
            ->acceptJson()
            ->timeout(15);
    }

    /** @return array{ok:bool, message:string, data:array} */
    private static function call(string $method, string $path, array $payload = []): array
    {
        if (!self::configured()) {
            return ['ok' => false, 'message' => 'Steadfast API keys are not set yet.', 'data' => []];
        }

        try {
            $res  = $method === 'post' ? self::http()->asJson()->post($path, $payload) : self::http()->get($path, $payload);
            $json = $res->json();
            $json = is_array($json) ? $json : [];

            // Steadfast puts its own status code in the body
            $code = (int) ($json['status'] ?? $res->status());
            $ok   = $res->successful() && $code === 200;

            $message = (string) ($json['message'] ?? '');
            if (!$ok && $message === '') {
                $message = $res->status() === 401 || $res->status() === 403
                    ? 'Steadfast rejected the API keys. Please check them.'
                    : 'Steadfast returned an error (HTTP ' . $res->status() . ').';
            }
            if (!$ok && !empty($json['errors']) && is_array($json['errors'])) {
                $message .= ' ' . implode(' ', array_map(fn ($e) => is_array($e) ? implode(' ', $e) : (string) $e, $json['errors']));
            }

            return ['ok' => $ok, 'message' => trim($message), 'data' => $json];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach Steadfast. Please try again in a moment.', 'data' => []];
        }
    }

    /** Books a parcel. On success data['consignment'] holds consignment_id and tracking_code. */
    public static function createOrder(Order $order): array
    {
        $items = $order->items->map(fn ($i) => $i->title . ' x' . $i->quantity)->implode(', ');

        return self::call('post', '/create_order', array_filter([
            'invoice'           => self::invoice($order),
            'recipient_name'    => $order->name,
            'recipient_phone'   => $order->phone,
            'recipient_address' => $order->address,
            'cod_amount'        => (int) $order->total,
            'note'              => $order->note,
            'item_description'  => mb_substr($items, 0, 500),
        ], fn ($v) => $v !== null && $v !== ''));
    }

    public static function invoice(Order $order): string
    {
        return 'AT-' . $order->id;
    }

    /** Returns the courier's delivery_status for a consignment (in data['delivery_status']). */
    public static function status(string $consignmentId): array
    {
        return self::call('get', '/status_by_cid/' . rawurlencode($consignmentId));
    }

    public static function balance(): array
    {
        return self::call('get', '/get_balance');
    }

    public static function createReturn(string $consignmentId, string $reason = ''): array
    {
        return self::call('post', '/create_return_request', array_filter([
            'consignment_id' => $consignmentId,
            'reason'         => $reason,
        ]));
    }

    public static function returnRequests(): array
    {
        return self::call('get', '/get_return_requests');
    }
}
