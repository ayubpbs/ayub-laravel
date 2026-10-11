<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $available = ProductReview::available();
        $status    = (string) $request->query('status', 'pending');
        if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $reviews = null;
        $counts  = collect();

        if ($available) {
            $query = ProductReview::with('product:id,title')->latest('id');
            if ($status !== 'all') {
                $query->where('status', $status);
            }
            $reviews = $query->paginate(20)->withQueryString();
            $counts  = ProductReview::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        }

        return view('admin.reviews.index', compact('available', 'status', 'reviews', 'counts'));
    }

    public function approve($id)
    {
        return $this->change($id, ProductReview::APPROVED, 'Review approved. It is now visible on the product page.');
    }

    public function reject($id)
    {
        return $this->change($id, ProductReview::REJECTED, 'Review rejected. It is hidden from the product page.');
    }

    public function destroy($id)
    {
        $review = ProductReview::findOrFail($id);
        $pid    = (int) $review->product_id;
        $review->delete();
        ProductReview::refreshProduct($pid);

        return back()->with('success', 'Review deleted.');
    }

    private function change($id, string $status, string $message)
    {
        $review         = ProductReview::findOrFail($id);
        $review->status = $status;
        $review->save();
        ProductReview::refreshProduct((int) $review->product_id);

        return back()->with('success', $message);
    }
}
