<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Get the products for the category.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
