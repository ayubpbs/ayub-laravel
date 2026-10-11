<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'brand_id', 'title', 'brand', 'description', 'icon', 'image',
        'price', 'old_price', 'badge', 'rating', 'reviews', 'section',
        'stock_quantity', 'is_upcoming', 'is_published', 'published_at', 'video_urls', 'specifications', 'sku',
        'slug', 'short_description', 'product_type',
    ];

    protected $casts = [
        'is_upcoming'  => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'specifications' => 'array',
    ];

    /** Product types the admin can choose (variations arrive in a later step). */
    const TYPES = [
        'simple'   => 'Simple product',
        'variable' => 'Variable product (has variations)',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brandItem()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', 1);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function inStock(): bool
    {
        return (int) $this->stock_quantity > 0;
    }

    /** Extra gallery images (the main image stays in the "image" column). */
    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Approved customer reviews, newest first (empty list when the reviews migration has not been run). */
    public function approvedReviews(int $limit = 20): \Illuminate\Support\Collection
    {
        if (!ProductReview::available()) {
            return collect();
        }

        return ProductReview::approved()->where('product_id', $this->id)->latest('id')->limit($limit)->get();
    }

    /** ['count' => n, 'avg' => x.x, 'dist' => [5 => n, 4 => n, ... 1 => n]] for approved reviews. */
    public function reviewStats(): array
    {
        $stats = ['count' => 0, 'avg' => 0.0, 'dist' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];

        if (!ProductReview::available()) {
            return $stats;
        }

        $rows = ProductReview::approved()->where('product_id', $this->id)
            ->selectRaw('rating, COUNT(*) as c')->groupBy('rating')->pluck('c', 'rating');

        $sum = 0;
        foreach ($rows as $rating => $c) {
            if (isset($stats['dist'][(int) $rating])) {
                $stats['dist'][(int) $rating] = (int) $c;
                $stats['count'] += (int) $c;
                $sum += (int) $rating * (int) $c;
            }
        }
        $stats['avg'] = $stats['count'] ? round($sum / $stats['count'], 1) : 0.0;

        return $stats;
    }

    /** Gallery rows; an empty list (instead of an error) when the product_images migration has not been run yet. */
    public function galleryImages(): \Illuminate\Support\Collection
    {
        try {
            return $this->images()->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** Every image URL for the product page: the main image first, then the gallery images. */
    public function galleryUrls(): array
    {
        $urls = [];
        if ($this->image) {
            $urls[] = $this->imageUrl();
        }
        foreach ($this->galleryImages() as $g) {
            $urls[] = $g->url();
        }

        return $urls;
    }

    /** The 11-character YouTube video id from a watch / youtu.be / embed / shorts link, or null if it is not a YouTube link. */
    public static function youtubeId(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $p = parse_url($url);
        if (!$p || empty($p['host'])) {
            return null;
        }

        $host = strtolower(preg_replace('/^(www\.|m\.)/', '', $p['host']));
        $id   = null;

        if ($host === 'youtu.be') {
            $id = explode('/', trim($p['path'] ?? '', '/'))[0];
        } elseif ($host === 'youtube.com' || $host === 'youtube-nocookie.com') {
            $path = $p['path'] ?? '';
            if ($path === '/watch') {
                parse_str($p['query'] ?? '', $q);
                $id = (isset($q['v']) && is_string($q['v'])) ? $q['v'] : null;
            } elseif (preg_match('#^/(embed|shorts|live|v)/([^/?]+)#', $path, $m)) {
                $id = $m[2];
            }
        }

        return ($id && preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) ? $id : null;
    }

    /** The product's YouTube videos (one link per line in video_urls), at most $max. */
    public function videos(int $max = 5): array
    {
        $out  = [];
        $seen = [];

        foreach ((array) preg_split('/\R+/', (string) $this->video_urls) as $line) {
            $id = self::youtubeId($line);
            if (!$id || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = [
                'id'    => $id,
                'thumb' => "https://i.ytimg.com/vi/{$id}/mqdefault.jpg",
                'hq'    => "https://i.ytimg.com/vi/{$id}/hqdefault.jpg",
                'embed' => "https://www.youtube-nocookie.com/embed/{$id}?autoplay=1&rel=0&modestbranding=1",
            ];
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /** Specification rows for the product page: [['name' => 'Watt', 'value' => '12W'], ...] (incomplete rows are skipped). */
    public function specs(): array
    {
        $out = [];

        foreach ((array) ($this->specifications ?? []) as $row) {
            $name  = trim((string) ($row['name'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($name !== '' && $value !== '') {
                $out[] = ['name' => $name, 'value' => $value];
            }
        }

        return $out;
    }

    /** Public URL of the product image, or null when there is none. */
    public function imageUrl(): ?string
    {
        return $this->image ? asset('uploads/products/' . $this->image) : null;
    }

    /** Discount in whole percent, derived from old_price (0 when there is no discount). */
    public function discountPercent(): int
    {
        $old = (int) $this->old_price;
        $now = (int) $this->price;

        return ($old > $now && $old > 0) ? (int) round(($old - $now) / $old * 100) : 0;
    }

    /** How many taka the customer saves compared with old_price. */
    public function savings(): int
    {
        return max(0, (int) $this->old_price - (int) $this->price);
    }

    /** One of: upcoming, out, low, in. "low" uses the same threshold as the admin low-stock alert. */
    public function stockState(): string
    {
        if ($this->is_upcoming) {
            return 'upcoming';
        }
        if (!$this->inStock()) {
            return 'out';
        }

        return (int) $this->stock_quantity <= (int) config('shop.low_stock_threshold', 5) ? 'low' : 'in';
    }

    /** Short plain-text excerpt of the description (for meta tags). */
    public function summary(int $limit = 160): string
    {
        $source = trim((string) ($this->short_description ?? '')) !== '' ? $this->short_description : $this->description;

        return \Illuminate\Support\Str::limit(\App\Support\HtmlCleaner::plain($source), $limit);
    }

    /** The slug text for a product name. Bengali (and other) letters are kept, spaces and symbols become hyphens. */
    public static function makeSlug(?string $text): string
    {
        $slug = trim((string) preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', mb_strtolower(trim((string) $text))), '-');

        return mb_substr($slug, 0, 150);
    }

    /** A slug that no other product uses (a number is added at the end when needed). */
    public static function uniqueSlug(string $base, $ignoreId = null): string
    {
        $base = $base === '' || ctype_digit($base) ? 'product' . ($base !== '' ? '-' . $base : '') : $base;
        $slug = $base;

        for ($n = 2; static::where('slug', $slug)->when($ignoreId, function ($q) use ($ignoreId) {
            $q->where('id', '<>', $ignoreId);
        })->exists(); $n++) {
            $slug = $base . '-' . $n;
        }

        return $slug;
    }

    /** Address of the product page: /product/{slug}, or /product/{id} when the product has no slug yet. */
    public function url(): string
    {
        return route('product.show', ($this->slug ?? '') !== '' ? $this->slug : $this->id);
    }

    /** True when the text contains HTML tags (older products have plain text with line breaks). */
    private static function looksLikeHtml(string $text): bool
    {
        return (bool) preg_match('/<\/?[a-z][a-z0-9]*(\s[^>]*)?>/i', $text);
    }

    /** Safe HTML of a description for the shop pages. Plain text (older products) keeps its line breaks. */
    private static function renderHtml(?string $text): string
    {
        $text = (string) $text;
        if (trim($text) === '') {
            return '';
        }

        return self::looksLikeHtml($text) ? \App\Support\HtmlCleaner::clean($text) : nl2br(e($text));
    }

    public function descriptionHtml(): string
    {
        return self::renderHtml($this->description);
    }

    public function shortDescriptionHtml(): string
    {
        return self::renderHtml($this->short_description);
    }

    public function hasDescription(): bool
    {
        return !\App\Support\HtmlCleaner::isEmpty($this->descriptionHtml());
    }

    public function hasShortDescription(): bool
    {
        return !\App\Support\HtmlCleaner::isEmpty($this->shortDescriptionHtml());
    }

    /** What the editor starts with: HTML as saved, or plain text turned into paragraphs. */
    public static function editorHtml(?string $text): string
    {
        $text = (string) $text;
        if (trim($text) === '' || self::looksLikeHtml($text)) {
            return $text;
        }

        $paras = array_filter(array_map('trim', preg_split('/\R{2,}/', $text)));

        return implode('', array_map(function ($p) {
            return '<p>' . nl2br(e($p)) . '</p>';
        }, $paras));
    }

    public function stars(): string
    {
        $r    = (float) $this->rating;
        $full = (int) floor($r);
        $half = ($r - $full) >= 0.5;
        return str_repeat('★', $full) . ($half ? '⯨' : '') . str_repeat('☆', 5 - $full - ($half ? 1 : 0));
    }
}
