<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void { Schema::table('conversation_participants',fn(Blueprint $t)=>$t->timestamp('muted_at')->nullable()->after('archived_at'));Schema::create('push_subscriptions',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->text('endpoint')->unique();$t->text('public_key')->nullable();$t->text('auth_token')->nullable();$t->string('content_encoding',30)->default('aesgcm');$t->timestamps();});}
 public function down():void {Schema::dropIfExists('push_subscriptions');Schema::table('conversation_participants',fn(Blueprint $t)=>$t->dropColumn('muted_at'));}
};
