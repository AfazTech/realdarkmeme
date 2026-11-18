<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sender extends Model
{
    protected $fillable = ['name','type','enabled','destination','api_url','token'];

    protected function casts(): array
    {
        return ['enabled'=>'boolean', 'token'=>'encrypted'];
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }
}
