<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuppliersHeading extends Model
{
    use CrudTrait;
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'suppliers_headings';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Rubros visibles para el usuario (misma regla que el alta de proveedores).
     * null = sin filtro; array vacío = ninguno.
     *
     * @return list<string>|null
     */
    public static function allowedHeadingNamesForUser($user): ?array
    {
        if (! $user || ! method_exists($user, 'hasResponsableAreaOrInstituteAuthorityRole') || ! $user->hasResponsableAreaOrInstituteAuthorityRole()) {
            return null;
        }

        $userAreas = ResponsibilityArea::where('responsible_user_id', $user->id)->get();
        if ($userAreas->isEmpty()) {
            return [];
        }

        $areaRubroMap = [
            'Informática' => ['Tecnología', 'Plataforma de e-commerce', 'Plataforma e-commerce'],
            'Salud' => ['Salud'],
            'Insumos de Salud' => ['Salud'],
            'Mantenimiento' => ['Herramientas'],
            'Insumos Generales' => ['Oficina', 'Insumos Generales'],
        ];

        $allowedRubroNames = collect();
        foreach ($userAreas as $area) {
            $areaName = $area->name;
            if (isset($areaRubroMap[$areaName])) {
                $allowedRubroNames = $allowedRubroNames->merge($areaRubroMap[$areaName]);
            }
        }

        return $allowedRubroNames->unique()->values()->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\SuppliersHeading>  $query
     */
    public function scopeVisibleForBackpackUser($query, $user)
    {
        $names = static::allowedHeadingNamesForUser($user);
        if ($names === null) {
            return $query->orderBy('name');
        }
        if ($names === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('name', $names)->orderBy('name');
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
