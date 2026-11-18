<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('senders', function (Blueprint $t) {
            $t->id();
            $t->string('name',120);
            $t->string('type',40)->index();
            $t->boolean('enabled')->default(true)->index();
            $t->string('destination',120);
            $t->string('api_url',255)->nullable();
            $t->text('token')->nullable();
            $t->timestamps();
        });

        Schema::table('publications', function (Blueprint $t) {
            $t->foreignId('sender_id')->nullable()->constrained('senders')->nullOnDelete();
        });

        // Preserve the legacy Telegram bot and its existing publication history.
        $settings = DB::table('settings')->whereIn('key',['bot_token','bot_chat','bot_api_url'])->pluck('value','key');
        $destination = (string) ($settings['bot_chat'] ?? DB::table('publications')->value('destination') ?? '');
        if (($settings['bot_token'] ?? '') !== '' || $destination !== '' || DB::table('publications')->exists()) {
            $id = DB::table('senders')->insertGetId([
                'name'=>'Telegram (migrated)', 'type'=>'telegram',
                'enabled'=>(($settings['bot_token'] ?? '') !== '' && $destination !== ''),
                'destination'=>$destination,
                'api_url'=>(string) ($settings['bot_api_url'] ?? 'https://api.telegram.org/bot'),
                'token'=>$settings['bot_token'] ?? null,
                'created_at'=>now(), 'updated_at'=>now(),
            ]);
            DB::table('publications')->update(['sender_id'=>$id]);
        }

        Schema::table('publications', function (Blueprint $t) {
            $t->dropUnique('publications_render_id_unique');
            $t->unique(['render_id','sender_id'],'publications_render_sender_unique');
        });
        Schema::table('channels', fn (Blueprint $t) => $t->dropColumn('identifier'));
    }

    public function down(): void
    {
        // Restoring the original unique-per-render index would delete delivery history.
        throw new RuntimeException('Sender migration cannot be rolled back without discarding publication history.');
    }
};
