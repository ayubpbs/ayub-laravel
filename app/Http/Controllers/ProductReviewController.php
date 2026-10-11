<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    /** A customer sends a review. It stays hidden until an admin approves it. */
    public function store(Request $request, $id)
    {
        $product = Product::published()->findOrFail($id);
        $back    = $product->url() . '#reviews';

        if (!ProductReview::available()) {
            return redirect($back)->with('error', 'Reviews are not available right now.');
        }

        $thanks = 'Thank you! Your review was received and will appear after it is approved.';

        // Hidden "website" field: real visitors never fill it, spam bots do. Pretend it worked.
        if ($request->filled('website')) {
            return redirect($back)->with('review_sent', $thanks);
        }

        $sent = (array) session('reviewed_products', []);
        if (in_array($product->id, $sent, true)) {
            return redirect($back)->with('error', 'You have already sent a review for this product.');
        }

        $data = $request->validate([
            'name'    => 'required|string|max:80',
            'rating'  => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000',
        ], [
            'name.required'   => 'Please enter your name.',
            'rating.required' => 'Please choose a star rating.',
            'rating.between'  => 'Please choose a rating from 1 to 5 stars.',
            'comment.max'     => 'Your review must not be longer than 1000 characters.',
        ]);

        ProductReview::create([
            'product_id' => $product->id,
            'name'       => $data['name'],
            'rating'     => (int) $data['rating'],
            'comment'    => $data['comment'] ?? null,
            'status'     => ProductReview::PENDING,
        ]);

        $sent[] = $product->id;
        session(['reviewed_products' => $sent]);

        return redirect($back)->with('review_sent', $thanks);
    }
}
