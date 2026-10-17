<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateSimapatAdmin extends Command
{
    protected $signature = 'simapat:admin {username} {email} {--name=Admin SIMAPAT}';

    protected $description = 'Buat akun Admin baru dengan password acak tanpa mengganti akun yang ada.';

    public function handle(): int
    {
        $data = ['username' => $this->argument('username'), 'email' => $this->argument('email'), 'name' => $this->option('name')];
        $validator = Validator::make($data, ['username' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.-]+$/', 'unique:users'],
            'email' => ['required', 'email', 'max:255', 'unique:users'], 'name' => ['required', 'string', 'max:255']]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $password = Str::random(20);
        User::create(array_merge($data, ['role' => 'admin', 'password' => $password]));
        $this->info('Akun Admin dibuat: '.$data['username']);
        $this->line('Password: '.$password);
        $this->warn('Simpan password secara pribadi dan ganti melalui halaman pengguna.');

        return self::SUCCESS;
    }
}
