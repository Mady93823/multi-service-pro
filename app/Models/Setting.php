<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $group
 * @property string $key
 * @property string|null $value
 * @property string $type
 */
class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
    ];
}
