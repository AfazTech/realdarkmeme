<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Video extends Model
{
 protected $fillable=['channel_id','telegram_message_id','source_path','source_size','source_date','first_frame_path','ocr_text','title','caption_body','caption','overrides','status','progress','error','current_render_id'];
 protected function casts(): array { return ['overrides'=>'array','source_date'=>'datetime','source_size'=>'integer','progress'=>'integer']; }
 public function channel(): BelongsTo { return $this->belongsTo(Channel::class); }
 public function renders(): HasMany { return $this->hasMany(Render::class)->orderByDesc('version'); }
 public function currentRender(): BelongsTo { return $this->belongsTo(Render::class,'current_render_id'); }
 public function publications(): HasMany { return $this->hasMany(Publication::class); }
}
