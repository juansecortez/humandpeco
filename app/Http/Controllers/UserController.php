<?php

namespace App\Http\Controllers;

use App\Role;
use App\User;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class);
    }

    /**
     * Display a listing of the users
     *
     * @param  \App\User  $model
     * @return \Illuminate\View\View
     */
    public function index(User $model)
    {
        $this->authorize('manage-users', User::class);

        return view('users.index', ['users' => $model->with('role')->get()]);
    }

    /**
     * Show the form for creating a new user
     *
     * @param  \App\Role  $model
     * @return \Illuminate\View\View
     */
public function create()
{
    $roles = Role::whereIn('id', config('users.assignable_role_ids', [1, 4, 5]))
        ->orderBy('id')
        ->get(['id', 'name']);

    return view('users.create', compact('roles'));
}

    /**
     * Store a newly created user in storage
     *
     * @param  \App\Http\Requests\UserRequest  $request
     * @param  \App\User  $model
     * @return \Illuminate\Http\RedirectResponse
     */
public function store(UserRequest $request, User $model)
{
    $data = $request->only(['name', 'email', 'role_id']);
    $data['picture'] = config('users.default_avatar', 'material/img/default-avatar.png');

    if ($request->filled('password')) {
        $data['password'] = Hash::make($request->input('password'));
    } else {
        $data['password'] = Hash::make(Str::random(32));
    }

    $model->create($data);

    return redirect()->route('user.index')->with('status', 'Usuario creado correctamente.');
}

    /**
     * Show the form for editing the specified user
     *
     * @param  \App\User  $user
     * @param  \App\Role  $model
     * @return \Illuminate\View\View
     */
    public function edit(User $user, Role $model)
    {
        return view('users.edit', [
            'user'  => $user->load('role'),
            'roles' => Role::whereIn('id', config('users.assignable_role_ids', [1, 4, 5]))
                ->orderBy('id')
                ->get(['id', 'name']),
        ]);
    }


    /**
     * Update the specified user in storage
     *
     * @param  \App\Http\Requests\UserRequest  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UserRequest $request, User $user)
    {
        $data = $request->only(['name', 'email', 'role_id']);
        $data['picture'] = $user->picture ?: config('users.default_avatar', 'material/img/default-avatar.png');

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        $user->update($data);

        return redirect()->route('user.index')->with('status', 'Usuario actualizado correctamente.');
    }


    /**
     * Remove the specified user from storage
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('user.index')->with('status', 'Usuario eliminado correctamente.');
    }
}
