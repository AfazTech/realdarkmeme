<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Publication extends Model
{
 protected $fillable=['video_id','render_id','sender_id','destination','status','telegram_message_id','attempts','approved_by','started_at','sent_at','error'];
 protected function casts(): array { return ['attempts'=>'integer','started_at'=>'datetime','sent_at'=>'datetime']; }
 public function sender(): BelongsTo { return $this->belongsTo(Sender::class); }
 public function render(): BelongsTo { return $this->belongsTo(Render::class); }
 public function video(): BelongsTo { return $this->belongsTo(Video::class); }
}
