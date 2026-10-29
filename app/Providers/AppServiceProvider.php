<?php

namespace App\Providers;

use App\Services\Mail\MailProviderFactory;
use App\Services\Replies\ImapInboxReader;
use App\Services\Replies\InboxReader;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MailProviderFactory::class);
        $this->app->bind(InboxReader::class, ImapInboxReader::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
