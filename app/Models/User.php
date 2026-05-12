<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, CrudTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the attribute that should be used to identify the user in Backpack CRUD
     */
    public function identifiableAttribute()
    {
        return $this->name;
    }

    /**
     * Rol contabilidad (Backpack / web / nombre), usado en recepciones y permisos afines.
     */
    public function hasContabilidadRole(): bool
    {
        if ($this->hasRole('role_contabilidad', 'backpack')) {
            return true;
        }

        if ($this->hasRole('role_contabilidad', 'web')) {
            return true;
        }

        return $this->getRoleNames()->contains('role_contabilidad');
    }

    /**
     * Rol responsable de compras (Backpack / web / nombre), p. ej. órdenes de pago y flujo de compras.
     */
    public function hasResponsableComprasRole(): bool
    {
        if ($this->hasRole('role_responsable_compras', 'backpack')) {
            return true;
        }

        if ($this->hasRole('role_responsable_compras', 'web')) {
            return true;
        }

        return $this->getRoleNames()->contains('role_responsable_compras');
    }

    /**
     * Responsable de depósito/área o autoridad del instituto (mismos permisos Spatie y mismas reglas de flujo).
     */
    public function hasResponsableAreaOrInstituteAuthorityRole(): bool
    {
        if ($this->hasRole(['role_responsable_area', 'role_autoridad_instituto'], 'backpack')) {
            return true;
        }

        if ($this->hasRole(['role_responsable_area', 'role_autoridad_instituto'], 'web')) {
            return true;
        }

        $names = $this->getRoleNames();

        return $names->contains('role_responsable_area') || $names->contains('role_autoridad_instituto');
    }

    /**
     * Administradora del instituto (órdenes de pago, anulaciones, etc.).
     */
    public function hasAdministradoraInstitucionRole(): bool
    {
        if ($this->hasRole('role_admin_institucion', 'backpack')) {
            return true;
        }

        if ($this->hasRole('role_admin_institucion', 'web')) {
            return true;
        }

        return $this->getRoleNames()->contains('role_admin_institucion');
    }
}
