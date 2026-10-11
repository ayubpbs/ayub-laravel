<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Sample data (replace it with real data from the admin panel later).
     */
    public function run()
    {
        $cats = [
            ['Lamps & Lights', '💡'],
            ['Fans', '🌀'],
            ['Switches & Sockets', '🔌'],
            ['Wires & Cables', '🧵'],
            ['Smart Devices', '📡'],
            ['Tools', '🔧'],
        ];

        $ids = [];
        foreach ($cats as $i => $c) {
            $ids[$i] = Category::create(['label' => $c[0], 'icon' => $c[1], 'sort_order' => $i + 1])->id;
        }

        $brandIds = [];
        foreach (['Ayub Tech', 'Premium'] as $i => $name) {
            $brandIds[$name] = Brand::create(['name' => $name, 'sort_order' => $i + 1])->id;
        }

        // [category index, title, brand, price, old price, badge, rating, reviews, section, stock, upcoming]
        $products = [
            [0, 'LED Bulb 9W', 'Ayub Tech', 120, 150, '-20%', 4.5, 38, 'top', 50, false],
            [0, 'LED Tube Light 18W', 'Ayub Tech', 380, null, 'New', 4.0, 12, 'new', 30, false],
            [0, 'Solar Garden Light', 'Premium', 650, 800, '-19%', 4.2, 9, null, 15, false],
            [1, 'Ceiling Fan 56"', 'Premium', 3200, 3600, '-11%', 4.6, 74, 'top', 20, false],
            [1, 'Exhaust Fan 8"', 'Ayub Tech', 1150, null, null, 4.1, 21, null, 12, false],
            [1, 'Table Fan 12"', 'Premium', 2450, null, 'New', 4.3, 17, 'new', 0, false],
            [2, 'Multi Plug 5 Socket', 'Ayub Tech', 450, 520, '-13%', 4.4, 55, 'top', 80, false],
            [2, 'Touch Switch 2 Gang', 'Premium', 890, null, 'New', 4.0, 6, 'new', 25, false],
            [2, 'Switch Board 6 inch', 'Ayub Tech', 180, null, null, 4.2, 30, null, 60, false],
            [3, 'Cable 3/22 (1 Coil)', 'Ayub Tech', 1650, 1800, '-8%', 4.5, 45, 'top', 40, false],
            [3, 'Extension Cord 5m', 'Premium', 520, null, null, 4.1, 19, null, 35, false],
            [3, 'HDMI Cable 2m', 'Ayub Tech', 350, null, 'New', 4.0, 8, 'new', 45, false],
            [4, 'Smart WiFi Plug', 'Premium', 990, 1200, '-18%', 4.3, 27, 'top', 22, false],
            [4, 'Smart LED Strip 5m', 'Premium', 1450, null, 'New', 4.2, 11, 'new', 18, false],
            [4, 'Smart Door Sensor', 'Premium', 1250, null, null, 0, 0, null, 0, true],
            [5, 'Digital Multimeter', 'Ayub Tech', 1350, 1500, '-10%', 4.7, 92, 'top', 28, false],
            [5, 'Soldering Iron 60W', 'Ayub Tech', 480, null, null, 4.3, 33, null, 40, false],
            [5, 'Wire Stripper', 'Premium', 420, null, 'New', 4.1, 10, 'new', 30, false],
        ];

        foreach ($products as $n => $p) {
            Product::create([
                'category_id'    => $ids[$p[0]],
                'title'          => $p[1],
                'brand'          => $p[2],
                'brand_id'       => $brandIds[$p[2]],
                'icon'           => $cats[$p[0]][1],
                'description'    => 'This is a sample product. Real descriptions will appear here once you add them from the admin panel.',
                'price'          => $p[3],
                'old_price'      => $p[4],
                'badge'          => $p[5],
                'rating'         => $p[6],
                'reviews'        => $p[7],
                'section'        => $p[8],
                'stock_quantity' => $p[9],
                'is_upcoming'    => $p[10],
                'is_published'   => true,
                'published_at'   => now()->subDays(count($products) - $n),
            ]);
        }
    }
}
