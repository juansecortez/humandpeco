<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class OrganigramaUser extends Authenticatable
{
    protected $connection = 'organigrama';
    protected $table      = 'OrganigramaCompleto';

    // MUY IMPORTANTE: usa la PK exacta de tu tabla
    protected $primaryKey = 'UsuarioId';
    public $incrementing  = false;
    protected $keyType    = 'string';
    protected $rememberTokenName = null;

    /**
     * Nombre “genérico” para que en las vistas puedas usar auth()->user()->name
     * aunque la columna real sea NombreCompleto/Nombre/etc.
     */
    public function getNameAttribute()
    {
        return $this->NombreCompleto
            ?? $this->nombre
            ?? ($this->attributes[$this->primaryKey] ?? '');
    }

    /**
     * Devuelve la URL/URI de la foto de perfil (base64 si viene de BD o sesión),
     * o un placeholder si no hay foto.
     */
    public function profilePicture(): string
    {
        // Si ya la guardaste en sesión en el login
        if (session()->has('fotoBase64')) {
            return (string) session('fotoBase64');
        }

        // Si la tabla trae la foto binaria (varbinary) en FotoAD
        if (!empty($this->FotoAD)) {
            return 'data:image/jpeg;base64,' . base64_encode($this->FotoAD);
        }

        // Ajusta este asset si tu template usa otra ruta
        return asset('material/img/default-avatar.png');
    }

    // Opcional, ayuda explícita al guard
    public function getAuthIdentifierName()
    {
        return $this->getKeyName(); // 'UsuarioId'
    }
    public function isAdmin(): bool
{
    // Ajusta según tu lógica real (ej. $this->rol === 1)
    return false;
}

public function isCreator(): bool
{
    // Ajusta según tu lógica real
    return false;
}
}
