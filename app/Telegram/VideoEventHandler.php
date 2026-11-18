<?php
declare(strict_types=1);

namespace App\Telegram;

use App\Jobs\ProcessVideo;
use App\Models\{Video,Channel,ActivityLog};
use danog\MadelineProto\SimpleEventHandler;
use danog\MadelineProto\EventHandler\Attributes\Handler;
use danog\MadelineProto\EventHandler\Message\ChannelMessage;
use danog\MadelineProto\EventHandler\Media\Document;
use danog\MadelineProto\EventHandler\Media\Video as TelegramVideo;
use danog\MadelineProto\EventHandler\SimpleFilter\{Incoming,HasMedia};
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Dedicated long-running MTProto listener. Only approved source channels and video media. */
final class VideoEventHandler extends SimpleEventHandler
{
    #[Handler]
    public function incomingVideo(Incoming&ChannelMessage&HasMedia $message): void
    {
        $media=$message->media;
        if(!$media instanceof TelegramVideo && !($media instanceof Document && str_starts_with((string)$media->mimeType,'video/')))return;
        $channel=Channel::query()->where('peer_id',(string)$message->chatId)->where('enabled',true)->first();
        if(!$channel)return;
        $size=(int)$media->size;
        if($size<1 || $size>(int)config('realdarkmeme.max_video_mb',500)*1024*1024 || $media->protected)return;
        $video=Video::query()->firstOrCreate(
            ['channel_id'=>$channel->id,'telegram_message_id'=>(int)$message->id],
            ['source_size'=>$size,'source_date'=>date('Y-m-d H:i:s',(int)$message->date),'status'=>'downloading']
        );
        if(!$video->wasRecentlyCreated)return;
        $ext=ltrim(strtolower((string)$media->fileExt),'.');
        if(!in_array($ext,['mp4','mov','mkv','webm','avi','m4v'],true))$ext='mp4';
        $disk=Storage::disk('private');
        $path='inputs/'.$video->id.'.'.$ext;$part=$path.'.part';
        try {
            $media->downloadToFile($disk->path($part));
            if(!$disk->exists($part)||$disk->size($part)<1024)throw new \RuntimeException('Video download is empty');
            if(!rename($disk->path($part),$disk->path($path)))throw new \RuntimeException('Cannot finalize downloaded video');
            $video->update(['source_path'=>$path,'source_size'=>$disk->size($path),'status'=>'queued','progress'=>0]);
            ProcessVideo::dispatch($video->id)->onQueue('render');
            ActivityLog::record('telegram.received','Video '.$video->id.' from channel '.$channel->peer_id);
        } catch(Throwable $e) {
            $disk->delete($part);
            $video->update(['status'=>'failed','error'=>mb_substr($e->getMessage(),0,900)]);
            ActivityLog::record('telegram.download_failed','Video '.$video->id);
        }
    }
}
