<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        $account = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.-]+$/', Rule::unique('users')->ignore($account)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($account)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'client_id' => ['nullable', 'required_if:role,client', 'prohibited_unless:role,client', 'integer', 'exists:clients,id'],
            'password' => [$account ? 'nullable' : 'required', 'string', 'min:12', 'max:200', 'confirmed'],
        ];
    }
}
