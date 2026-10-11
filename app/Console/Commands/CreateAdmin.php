<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'shop:admin';

    protected $description = 'অ্যাডমিন ইউজার তৈরি করে (একই ইমেইল দিলে পাসওয়ার্ড রিসেট হয়)';

    public function handle()
    {
        $name     = $this->ask('নাম', 'Admin');
        $email    = $this->ask('ইমেইল');
        $password = $this->secret('পাসওয়ার্ড (কমপক্ষে ৮ অক্ষর)');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => 'required|email', 'password' => 'required|min:8']
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return 1;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name     = $name;
        $user->password = Hash::make($password);
        $user->is_admin = true;
        $user->save();

        $this->info('অ্যাডমিন তৈরি হয়েছে। লগইন: /admin/login');
        return 0;
    }
}
