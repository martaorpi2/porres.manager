<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseAuthorizationLimit extends Model
{
    protected $fillable = [
        'role_name',
        'role_display_name',
        'limit_amount',
        'description',
        'is_active',
    ];

    protected $casts = [
        'limit_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the limit for a specific role
     */
    public static function getLimitForRole($roleName)
    {
        $limit = static::where('role_name', $roleName)
            ->where('is_active', true)
            ->first();
        
        return $limit ? $limit->limit_amount : 0;
    }

    /**
     * Check if a user can authorize a purchase amount
     */
    public static function canAuthorize($user, $amount)
    {
        if (!$user) {
            return false;
        }

        // Check all roles the user has
        $roles = \DB::table('model_has_roles')
            ->where('model_type', get_class($user))
            ->where('model_id', $user->id)
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->pluck('roles.name');

        foreach ($roles as $roleName) {
            $limit = static::getLimitForRole($roleName);
            if ($limit > 0 && $amount <= $limit) {
                return true;
            }
        }

        return false;
    }
}
