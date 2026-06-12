<?php

namespace App\Http\Controllers;

use App\Role;
use App\User;
use App\Http\Requests\RoleRequest;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Role::class);
    }

    /**
     * Display a listing of the roles
     *
     * @param \App\Role  $model
     * @return \Illuminate\View\View
     */
    public function index(Role $model)
    {
        $this->authorize('manage-users', User::class);

        return view('roles.index', [
            'roles' => $model->whereIn('id', config('users.assignable_role_ids', [1, 4, 5]))->orderBy('id')->get(),
        ]);
    }

    /**
     * Show the form for creating a new role
     *
     * @return \Illuminate\View\View
     */
    // public function create()
    // {
    //     return view('roles.create');
    // }

    /**
     * Store a newly created role in storage
     *
     * @param  \App\Http\Requests\RoleRequest  $request
     * @param  \App\Role  $model
     * @return \Illuminate\Http\RedirectResponse
     */
    // public function store(RoleRequest $request, Role $model)
    // {
    //     $model->create($request->all());

    //     return redirect()->route('role.index')->withStatus(__('Role successfully created.'));
    // }

    /**
     * Show the form for editing the specified role
     *
     * @param  \App\Role  $role
     * @return \Illuminate\View\View
     */
    public function edit(Role $role)
    {
        if (!in_array((int) $role->id, config('users.assignable_role_ids', [1, 4, 5]), true)) {
            abort(404);
        }

        return view('roles.edit', compact('role'));
    }

    /**
     * Update the specified role in storage
     *
     * @param  \App\Http\Requests\RoleRequest  $request
     * @param  \App\Role  $role
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(RoleRequest $request, Role $role)
    {
        $role->update($request->all());

        return redirect()->route('role.index')->withStatus(__('Role successfully updated.'));
    }
}
