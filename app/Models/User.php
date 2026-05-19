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
     * ¿Existe al menos un usuario con el rol en Backpack? (p. ej. decidir fallback de notificaciones a administradora.)
     */
    public static function backpackHasAnyUserWithRole(string $roleName, string $guard = 'backpack'): bool
    {
        return static::query()
            ->whereHas('roles', function ($q) use ($roleName, $guard) {
                $q->where('guard_name', $guard)->where('name', $roleName);
            })
            ->exists();
    }

    /**
     * Responsable de compras, o administradora del instituto si en el sistema no hay ningún usuario con rol de compras.
     */
    public function effectivelyHasResponsableComprasRole(): bool
    {
        if ($this->hasRole('role_responsable_compras', 'backpack') || $this->hasRole('role_responsable_compras', 'web')) {
            return true;
        }

        if (! static::backpackHasAnyUserWithRole('role_responsable_compras')) {
            return $this->hasRole('role_admin_institucion', 'backpack') || $this->hasRole('role_admin_institucion', 'web');
        }

        return false;
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

    public function hasInstituteAuthorityRole(): bool
    {
        if ($this->hasRole('role_autoridad_instituto', 'backpack')) {
            return true;
        }

        if ($this->hasRole('role_autoridad_instituto', 'web')) {
            return true;
        }

        return $this->getRoleNames()->contains('role_autoridad_instituto');
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

    /**
     * Sector de compras, administradora del instituto (o admin. de sistema). No área ni representante legal.
     */
    public function canGeneratePurchaseOrders(): bool
    {
        if ($this->hasResponsableAreaOrInstituteAuthorityRole()) {
            return false;
        }

        if ($this->hasRole('role_representante_legal', 'backpack')
            || $this->hasRole('role_representante_legal', 'web')
            || $this->getRoleNames()->contains('role_representante_legal')) {
            return false;
        }

        if ($this->hasRole('role_admin_sistema', 'backpack') || $this->hasRole('role_admin_sistema', 'web')) {
            return true;
        }

        if ($this->hasAdministradoraInstitucionRole()) {
            return true;
        }

        return $this->effectivelyHasResponsableComprasRole();
    }

    /**
     * Etiquetas legibles de roles Backpack para pie de correos del circuito de compras.
     */
    public function formatBackpackRolesForMail(string $guard = 'backpack'): string
    {
        $this->loadMissing('roles');
        $names = $this->roles
            ->where('guard_name', $guard)
            ->pluck('name')
            ->filter(fn ($n) => is_string($n) && str_starts_with($n, 'role_'))
            ->values();
        if ($names->isEmpty()) {
            return 'Sin rol asignado en el sistema';
        }

        $map = [
            'role_admin_sistema' => 'Administración del sistema',
            'role_admin_institucion' => 'Administración del instituto',
            'role_responsable_compras' => 'Responsable de compras',
            'role_responsable_area' => 'Responsable de depósito/área',
            'role_autoridad_instituto' => 'Autoridad del instituto',
            'role_apoderado' => 'Apoderado',
            'role_representante_legal' => 'Representante legal',
            'role_contabilidad' => 'Contabilidad',
            'role_personal' => 'Personal',
        ];

        return $names->map(fn (string $n) => $map[$n] ?? $n)->implode(', ');
    }
}
