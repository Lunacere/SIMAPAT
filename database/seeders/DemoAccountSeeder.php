<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use App\Services\MeetingNegotiation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Akun dummy hanya diizinkan di local/testing.');
        }
        DB::transaction(function (): void {
            $definitions = ['admin' => ['Admin SIMAPAT', 'admin'], 'operator' => ['Operator Rapat', 'operator'],
                'peserta' => ['Anggota Tim', 'user'], 'klien' => ['Klien Demo', 'client']];
            foreach ($definitions as $username => [$name, $role]) {
                $existing = User::where('username', $username)->lockForUpdate()->first();
                if ($existing && ($existing->email !== $username.'@simapat.example' || $existing->role !== $role)) {
                    throw new RuntimeException('Akun '.$username.' bukan akun dummy yang diharapkan; kredensialnya tidak diubah.');
                }
            }
            $client = Client::firstOrCreate(['name' => 'Klien Contoh'], ['contact' => 'kontak@example.com', 'information' => 'Data fiktif untuk demo', 'progress' => 'Evaluasi kebutuhan']);
            foreach ($definitions as $username => [$name, $role]) {
                $password = ($role === 'client' ? 'klien' : $role).'123';
                $account = User::firstOrCreate(['username' => $username], ['name' => $name, 'email' => $username.'@simapat.example',
                    'role' => $role, 'client_id' => $role === 'client' ? $client->id : null, 'password' => $password]);
                $newClientLink = $role === 'client' && ($account->wasRecentlyCreated || ! $account->client_id);
                if ($role === 'client' && ! $account->client_id) {
                    $account->update(['client_id' => $client->id]);
                }
                if (! Hash::check($password, $account->password)) {
                    $account->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                    DB::table('sessions')->where('user_id', $account->id)->delete();
                }
                if ($newClientLink) {
                    app(MeetingNegotiation::class)->notifyClientAccount($account);
                }
                $this->command?->line('Dummy '.$username.' | '.$password);
            }
        });
    }
}
