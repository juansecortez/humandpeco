<?php

namespace App\Http\Controllers;

use App\Role;
use App\User;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Hash;

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
    // Trae los roles con orden estable
    $roles = \App\Role::orderBy('id')->get(['id','name']);
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
    $data = $request->only(['name','email','role_id']); // <- incluye role_id

    if ($request->filled('password')) {
        $data['password'] = Hash::make($request->input('password'));
    }

    if ($request->hasFile('photo')) {
        $data['picture'] = $request->file('photo')->store('profile', 'public');
    }

    $model->create($data);

    return redirect()->route('user.index')->withStatus(__('User successfully created.'));
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
            'roles' => Role::all(['id','name']),
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
        $data = $request->only(['name', 'email', 'role_id']); // <- incluye role_id

        if ($request->hasFile('photo')) {
            $data['picture'] = $request->file('photo')->store('profile', 'public');
        } else {
            $data['picture'] = $user->picture;
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        $user->update($data);

        return redirect()
            ->route('user.index')
            ->withStatus(__('User successfully updated.'));
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

        return redirect()->route('user.index')->withStatus(__('User successfully deleted.'));
    }
}
