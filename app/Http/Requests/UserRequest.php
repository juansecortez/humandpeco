<?php

namespace App\Http\Requests;

use App\Role;
use App\User;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        $assignable = config('users.assignable_role_ids', [1, 4, 5]);
        $roleId = (int) $this->input('role_id');
        $needsPassword = $roleId === 1 && !$this->route('user');

        return [
            'name' => ['required', 'min:2', 'max:120'],
            'email' => [
                'required', 'email',
                Rule::unique((new User)->getTable())->ignore($this->route('user')?->id),
            ],
            'role_id' => ['required', Rule::in($assignable)],
            'password' => [
                $needsPassword ? 'required' : 'nullable',
                'confirmed', 'min:6',
            ],
        ];
    }

    public function attributes()
    {
        return [
            'role_id'  => 'rol',
            'name'     => 'usuario',
        ];
    }
}
