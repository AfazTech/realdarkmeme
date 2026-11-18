<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ActivityLog extends Model
{
 protected $fillable=['event','detail','user_id'];
 public static function record(string $event, string $detail, ?int $userId=null): void
 { static::query()->create(['event'=>$event,'detail'=>mb_substr($detail,0,1800),'user_id'=>$userId]); }
}
