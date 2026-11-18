<?php
return [
 'php_cli'=>env('RDM_PHP_CLI','php'),
 'python'=>env('RDM_PYTHON','python3'), 'ffmpeg'=>env('RDM_FFMPEG','ffmpeg'), 'tesseract'=>env('RDM_TESSERACT','tesseract'),
 'max_video_mb'=>(int)env('RDM_MAX_VIDEO_MB',500), 'render_timeout'=>(int)env('RDM_RENDER_TIMEOUT',3600),
 'bot_api_url'=>env('RDM_BOT_API_URL','https://api.telegram.org/bot'),
 'bot_api_hosts'=>array_map('trim',explode(',',env('RDM_ALLOWED_BOT_API_HOSTS','api.telegram.org,t.afaztech.ir'))),
];
