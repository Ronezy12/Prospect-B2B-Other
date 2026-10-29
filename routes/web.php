<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\BrevoWebhookController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CompanyBulkController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyExportController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\IndividualEmailController;
use App\Http\Controllers\MetaWebhookController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\PipelineBoardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SegmentController;
use App\Http\Controllers\Settings\InboxController;
use App\Http\Controllers\Settings\MailAccountController;
use App\Http\Controllers\Settings\PipelineSettingsController;
use App\Http\Controllers\Settings\SettingsHomeController;
use App\Http\Controllers\Settings\SocialAccountController;
use App\Http\Controllers\Settings\TrackingSettingsController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\SocialInboxController;
use App\Http\Controllers\SocialProfileController;
use App\Http\Controllers\SocialTemplateController;
use App\Http\Controllers\SuppressionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/prospection');

// Webhooks fournisseurs (authentifiés par jeton) et suivi interne (liens signés).
Route::post('/webhooks/brevo/{token}', BrevoWebhookController::class)->middleware('throttle:600,1')->name('webhooks.brevo');
Route::get('/webhooks/meta/{token}', [MetaWebhookController::class, 'verify'])->middleware('throttle:30,1')->name('webhooks.meta.verify');
Route::post('/webhooks/meta/{token}', [MetaWebhookController::class, 'receive'])->middleware('throttle:600,1')->name('webhooks.meta');
Route::get('/t/o/{recipient}/{signature}', [TrackingController::class, 'open'])->middleware('throttle:120,1')->name('tracking.open');
Route::get('/t/c/{recipient}/{signature}', [TrackingController::class, 'click'])->middleware('throttle:120,1')->name('tracking.click');

// Désinscription publique : lien signé par destinataire, sans authentification.
Route::middleware(['signed', 'throttle:30,1'])->group(function () {
    Route::get('/desinscription/{recipient}', [UnsubscribeController::class, 'show'])->name('unsubscribe.show');
    Route::post('/desinscription/{recipient}', [UnsubscribeController::class, 'store'])->name('unsubscribe.store');
});

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [LoginController::class, 'create'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [LoginController::class, 'destroy'])->name('logout');

    Route::prefix('prospection')->name('pm.')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::post('prospects/actions-groupees', CompanyBulkController::class)->name('companies.bulk');
        Route::get('prospects/export', CompanyExportController::class)->middleware('throttle:10,1')->name('companies.export');
        Route::post('prospects/{company}/convertir', [CompanyController::class, 'convert'])->name('companies.convert');
        Route::post('prospects/{company}/activites', [ActivityController::class, 'store'])->name('companies.activities.store');
        Route::resource('prospects', CompanyController::class)->parameters(['prospects' => 'company'])->names('companies');

        Route::post('contacts/{contact}/opposition', [SuppressionController::class, 'store'])->name('contacts.suppress');
        Route::resource('contacts', ContactController::class)->except('show');

        Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
        Route::get('imports/nouveau', [ImportController::class, 'create'])->name('imports.create');
        Route::post('imports', [ImportController::class, 'store'])->middleware('throttle:10,1')->name('imports.store');
        Route::get('imports/{import}/correspondance', [ImportController::class, 'mapping'])->name('imports.mapping');
        Route::post('imports/{import}/lancer', [ImportController::class, 'start'])->name('imports.start');
        Route::get('imports/{import}', [ImportController::class, 'show'])->name('imports.show');
        Route::post('imports/{import}/annuler', [ImportController::class, 'cancel'])->name('imports.cancel');
        Route::get('imports/{import}/rapport', [ImportController::class, 'report'])->name('imports.report');

        Route::get('pipelines/vue-globale', [PipelineBoardController::class, 'overview'])->name('pipelines.overview');
        Route::get('pipelines/{pipeline?}', [PipelineBoardController::class, 'show'])->name('pipelines.show');

        Route::post('opportunites/{opportunity}/deplacer', [OpportunityController::class, 'move'])->middleware('throttle:120,1')->name('opportunities.move');
        Route::resource('opportunites', OpportunityController::class)->parameters(['opportunites' => 'opportunity'])->names('opportunities');

        Route::post('taches/{task}/statut', [TaskController::class, 'complete'])->name('tasks.complete');
        Route::resource('taches', TaskController::class)->except('show')->parameters(['taches' => 'task'])->names('tasks');

        Route::get('contacts/{contact}/email', [IndividualEmailController::class, 'create'])->name('contacts.email.create');
        Route::post('contacts/{contact}/email', [IndividualEmailController::class, 'store'])->middleware('throttle:20,1')->name('contacts.email.store');

        Route::get('modeles/{template}/apercu', [TemplateController::class, 'preview'])->name('templates.preview');
        Route::post('modeles/{template}/test', [TemplateController::class, 'test'])->middleware('throttle:10,1')->name('templates.test');
        Route::resource('modeles', TemplateController::class)->parameters(['modeles' => 'template'])->names('templates');

        Route::resource('segments', SegmentController::class)->except('show');

        Route::get('newsletters', [NewsletterController::class, 'index'])->name('newsletters.index');
        Route::post('newsletters', [NewsletterController::class, 'store'])->name('newsletters.store');
        Route::get('newsletters/{list}', [NewsletterController::class, 'show'])->name('newsletters.show');
        Route::post('newsletters/{list}/abonnes', [NewsletterController::class, 'subscribe'])->name('newsletters.subscribe');
        Route::post('abonnements/{subscription}/desabonner', [NewsletterController::class, 'unsubscribe'])->name('subscriptions.unsubscribe');

        Route::post('campagnes/{campaign}/test', [CampaignController::class, 'test'])->middleware('throttle:10,1')->name('campaigns.test');
        Route::post('campagnes/{campaign}/programmer', [CampaignController::class, 'schedule'])->name('campaigns.schedule');
        Route::post('campagnes/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
        Route::post('campagnes/{campaign}/reprendre', [CampaignController::class, 'resume'])->name('campaigns.resume');
        Route::post('campagnes/{campaign}/annuler', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
        Route::post('campagnes/{campaign}/dupliquer', [CampaignController::class, 'duplicate'])->name('campaigns.duplicate');
        Route::resource('campagnes', CampaignController::class)->parameters(['campagnes' => 'campaign'])->names('campaigns');

        Route::get('reseaux-sociaux', [SocialProfileController::class, 'index'])->name('social.index');
        Route::post('prospects/{company}/reseaux-sociaux', [SocialProfileController::class, 'store'])->name('social.store');
        Route::get('reseaux-sociaux/profils/{profile}', [SocialProfileController::class, 'show'])->name('social.show');
        Route::put('reseaux-sociaux/profils/{profile}', [SocialProfileController::class, 'update'])->name('social.update');
        Route::delete('reseaux-sociaux/profils/{profile}', [SocialProfileController::class, 'destroy'])->name('social.destroy');
        Route::post('reseaux-sociaux/profils/{profile}/actions', [SocialProfileController::class, 'interaction'])->middleware('throttle:120,1')->name('social.interaction');
        Route::post('reseaux-sociaux/profils/{profile}/opposition', [SocialProfileController::class, 'oppose'])->name('social.oppose');
        Route::resource('reseaux-sociaux/modeles', SocialTemplateController::class)->except(['show', 'destroy'])->parameters(['modeles' => 'template'])->names('social.templates');
        Route::get('reseaux-sociaux/messages', [SocialInboxController::class, 'index'])->name('social.inbox.index');
        Route::get('reseaux-sociaux/messages/{conversation}', [SocialInboxController::class, 'show'])->name('social.inbox.show');
        Route::put('reseaux-sociaux/messages/{conversation}', [SocialInboxController::class, 'link'])->name('social.inbox.link');
        Route::post('reseaux-sociaux/messages/{conversation}/repondre', [SocialInboxController::class, 'reply'])->middleware('throttle:30,1')->name('social.inbox.reply');
        Route::post('reseaux-sociaux/messages/{conversation}/statut', [SocialInboxController::class, 'archive'])->name('social.inbox.archive');

        Route::get('statistiques', ReportController::class)->name('reports.index');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/{notification}', [NotificationController::class, 'open'])->name('notifications.open');
        Route::post('notifications/tout-lire', [NotificationController::class, 'readAll'])->name('notifications.read_all');

        Route::post('automatisations/{automation}/activer', [AutomationController::class, 'activate'])->name('automations.activate');
        Route::post('automatisations/{automation}/pause', [AutomationController::class, 'pause'])->name('automations.pause');
        Route::post('automatisations/{automation}/reprendre', [AutomationController::class, 'resume'])->name('automations.resume');
        Route::post('automatisations/{automation}/arreter', [AutomationController::class, 'stop'])->name('automations.stop');
        Route::post('automatisations/{automation}/tester', [AutomationController::class, 'test'])->middleware('throttle:20,1')->name('automations.test');
        Route::resource('automatisations', AutomationController::class)->except('create')->parameters(['automatisations' => 'automation'])->names('automations');

        Route::prefix('parametres')->name('settings.')->group(function () {
            Route::get('/', SettingsHomeController::class)->name('index');
            Route::post('meta/{account}/test', [SocialAccountController::class, 'test'])->middleware('throttle:10,1')->name('social.test');
            Route::resource('meta', SocialAccountController::class)->except(['show', 'destroy'])->parameters(['meta' => 'account'])->names('social');
            Route::get('suivi', [TrackingSettingsController::class, 'edit'])->name('tracking.edit');
            Route::put('suivi', [TrackingSettingsController::class, 'update'])->name('tracking.update');
            Route::post('boites/{inbox}/verifier', [InboxController::class, 'check'])->middleware('throttle:10,1')->name('inboxes.check');
            Route::resource('boites', InboxController::class)->except(['show', 'destroy'])->parameters(['boites' => 'inbox'])->names('inboxes');
            Route::post('envoi/{account}/test', [MailAccountController::class, 'test'])->middleware('throttle:10,1')->name('mail.test');
            Route::resource('envoi', MailAccountController::class)->except(['show', 'destroy'])->parameters(['envoi' => 'account'])->names('mail');
            Route::get('pipelines', [PipelineSettingsController::class, 'index'])->name('pipelines.index');
            Route::get('pipelines/{pipeline}', [PipelineSettingsController::class, 'edit'])->name('pipelines.edit');
            Route::put('pipelines/{pipeline}', [PipelineSettingsController::class, 'update'])->name('pipelines.update');
            Route::post('pipelines/{pipeline}/etapes', [PipelineSettingsController::class, 'storeStage'])->name('stages.store');
            Route::put('etapes/{stage}', [PipelineSettingsController::class, 'updateStage'])->name('stages.update');
            Route::post('etapes/{stage}/deplacer', [PipelineSettingsController::class, 'moveStage'])->name('stages.move');
            Route::delete('etapes/{stage}', [PipelineSettingsController::class, 'destroyStage'])->name('stages.destroy');
            Route::post('secteurs', [PipelineSettingsController::class, 'storeSector'])->name('sectors.store');
            Route::put('secteurs/{sector}', [PipelineSettingsController::class, 'updateSector'])->name('sectors.update');
            Route::post('sources', [PipelineSettingsController::class, 'storeSource'])->name('sources.store');
            Route::put('sources/{source}', [PipelineSettingsController::class, 'updateSource'])->name('sources.update');

            Route::resource('utilisateurs', UserController::class)->except(['show', 'destroy'])
                ->parameters(['utilisateurs' => 'user'])->names('users');
        });
    });
});
