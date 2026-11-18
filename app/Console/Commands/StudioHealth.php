<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
class StudioHealth extends Command
{
 protected $signature='rdm:health'; protected $description='Check render binaries, OCR language and private assets';
 public function handle(): int
 {
    $checks=[['FFmpeg',[config('realdarkmeme.ffmpeg'),'-version']],['Python',[config('realdarkmeme.python'),'--version']],['Tesseract languages',[config('realdarkmeme.tesseract'),'--list-langs']]];
    foreach($checks as [$name,$cmd]) {
       try {$p=new Process($cmd);$p->setTimeout(15);$p->run();$output=$p->getOutput().$p->getErrorOutput();
          $good=$p->isSuccessful() && ($name!=='Tesseract languages'||preg_match('/(^|\s)fas(\s|$)/',$output));
          $this->line(($good?'[OK] ':'[FAIL] ').$name);if(!$good)return self::FAILURE;
       }catch(\Throwable $e){$this->error($name.': '.$e->getMessage());return self::FAILURE;}
    }
    foreach(['thumbnail.png','outro.png'] as $name) {
       $ok=\Illuminate\Support\Facades\Storage::disk('private')->exists('assets/'.$name);
       $this->line(($ok?'[OK] ':'[FAIL] ').$name);if(!$ok)return self::FAILURE;
    }
    return self::SUCCESS;
 }
}
