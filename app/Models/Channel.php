<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Channel extends Model
{
 protected $fillable=['peer_id','identifier','label','enabled'];
 protected function casts(): array { return ['enabled'=>'boolean']; }
 public function videos(): HasMany { return $this->hasMany(Video::class); }
}
