<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Setting extends Model
{
 protected $primaryKey='key'; public $incrementing=false; protected $keyType='string';
 protected $fillable=['key','value'];
 public static function fetch(string $key, mixed $default=null): mixed { return static::query()->whereKey($key)->value('value') ?? $default; }
 public static function store(string $key, string $value): void { static::query()->updateOrCreate(['key'=>$key],['value'=>$value]); }
}
