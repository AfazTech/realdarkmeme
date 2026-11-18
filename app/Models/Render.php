<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
class Render extends Model
{
 protected $fillable=['video_id','version','status','output_path','poster_path','title','caption','settings_snapshot','error','completed_at'];
 protected function casts(): array { return ['settings_snapshot'=>'array','completed_at'=>'datetime']; }
 public function video(): BelongsTo { return $this->belongsTo(Video::class); }
 public function publication(): HasOne { return $this->hasOne(Publication::class); }
}
