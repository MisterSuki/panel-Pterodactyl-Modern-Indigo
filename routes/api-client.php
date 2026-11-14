<?php

use Pterodactyl\Enum\ResourceLimit;
use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\Client;
use Pterodactyl\Http\Middleware\Activity\ServerSubject;
use Pterodactyl\Http\Middleware\Activity\AccountSubject;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;

/*
|--------------------------------------------------------------------------
| Client Control API
|--------------------------------------------------------------------------
|
| Endpoint: /api/client
|
*/
Route::get('/', [Client\ClientController::class, 'index'])->name('api:client.index');
Route::get('/permissions', [Client\ClientController::class, 'permissions']);
Route::get('/resources', Client\ServerResourcesController::class)->name('api:client.resources');

// Where the person is on the dashboard, told every half minute for the "active now" list of the administration.
Route::post('/presence', Client\PresenceController::class)->middleware('throttle:120,1')->name('api:client.presence');

// The support tickets of the person who is signed in.
Route::prefix('/tickets')->group(function () {
    Route::get('/', [Client\TicketController::class, 'index']);
    Route::get('/unread', [Client\TicketController::class, 'unread']);
    Route::post('/', [Client\TicketController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/{id}', [Client\TicketController::class, 'show'])->whereNumber('id');
    Route::get('/{id}/messages', [Client\TicketController::class, 'messages'])->whereNumber('id');
    Route::post('/{id}/messages', [Client\TicketController::class, 'reply'])->whereNumber('id')->middleware('throttle:30,1');
    Route::post('/{id}/close', [Client\TicketController::class, 'close'])->whereNumber('id');
    Route::post('/{id}/reopen', [Client\TicketController::class, 'reopen'])->whereNumber('id');
    Route::get('/{id}/transcript', [Client\TicketController::class, 'transcript'])->whereNumber('id');
});


// The shop: what is for sale, the credit of the person, what they bought, and paying.
Route::prefix('/shop')->group(function () {
    Route::get('/', [Client\ShopController::class, 'index'])->middleware('throttle:60,1');
    Route::post('/buy', [Client\ShopController::class, 'buy'])->middleware('throttle:20,1');
    Route::post('/renew', [Client\ShopController::class, 'renew'])->middleware('throttle:20,1');
    Route::post('/cancel', [Client\ShopController::class, 'cancel'])->middleware('throttle:20,1');
    Route::post('/topup', [Client\ShopController::class, 'topup'])->middleware('throttle:10,1');
    // Changing the resources of a server bought in the shop (billed monthly).
    Route::post('/custom', [Client\ShopController::class, 'createCustom'])->middleware('throttle:20,1');
    Route::get('/upgradeable', [Client\ShopController::class, 'upgradeable'])->middleware('throttle:120,1');
    Route::get('/servers/{server}/resources', [Client\ShopController::class, 'resources'])->middleware('throttle:120,1');
    Route::put('/servers/{server}/resources', [Client\ShopController::class, 'updateResources'])->middleware('throttle:30,1');
});

// The file hosting of the person (pictures, videos, any file), when the administration opened it.
Route::prefix('/hosting')->group(function () {
    Route::get('/', [Client\FileHostingController::class, 'index'])->middleware('throttle:120,1,hosting-list');
    Route::post('/uploads', [Client\FileHostingController::class, 'begin'])->middleware('throttle:60,1,hosting-upload');
    Route::post('/uploads/{upload}', [Client\FileHostingController::class, 'chunk'])->where('upload', '[A-Za-z0-9]{32}')->middleware('throttle:900,1,hosting-chunk');
    Route::post('/uploads/{upload}/complete', [Client\FileHostingController::class, 'complete'])->where('upload', '[A-Za-z0-9]{32}')->middleware('throttle:60,1,hosting-upload');
    Route::delete('/uploads/{upload}', [Client\FileHostingController::class, 'cancel'])->where('upload', '[A-Za-z0-9]{32}');
    Route::patch('/{uuid}', [Client\FileHostingController::class, 'update'])->whereUuid('uuid')->middleware('throttle:60,1,hosting-edit');
    Route::delete('/{uuid}', [Client\FileHostingController::class, 'delete'])->whereUuid('uuid')->middleware('throttle:60,1,hosting-edit');
});

// The VPS of the person (virtual machines of Proxmox given to them by the administration). Each limit has its own
// counter (the third parameter): without it, every "throttle" of a person shares one, and the page that refreshes every
// few seconds would use up the limit of the power buttons and of the console.
Route::prefix('/vps')->group(function () {
    Route::get('/', [Client\VpsController::class, 'index'])->middleware('throttle:120,1,vps-list');
    Route::get('/{uuid}', [Client\VpsController::class, 'show'])->whereUuid('uuid')->middleware('throttle:120,1,vps-show');
    Route::get('/{uuid}/history', [Client\VpsController::class, 'history'])->whereUuid('uuid')->middleware('throttle:30,1,vps-history');
    Route::post('/{uuid}/power', [Client\VpsController::class, 'power'])->whereUuid('uuid')->middleware('throttle:10,1,vps-power');
    Route::post('/{uuid}/console', [Client\VpsController::class, 'console'])->whereUuid('uuid')->middleware('throttle:30,1,vps-console');
    Route::get('/{uuid}/users', [Client\VpsController::class, 'users'])->whereUuid('uuid')->middleware('throttle:60,1,vps-users');
    Route::post('/{uuid}/users', [Client\VpsController::class, 'addUser'])->whereUuid('uuid')->middleware('throttle:20,1,vps-users-edit');
    Route::put('/{uuid}/users/{id}', [Client\VpsController::class, 'updateUser'])->whereUuid('uuid')->whereNumber('id')->middleware('throttle:20,1,vps-users-edit');
    Route::delete('/{uuid}/users/{id}', [Client\VpsController::class, 'removeUser'])->whereUuid('uuid')->whereNumber('id')->middleware('throttle:20,1,vps-users-edit');
});

// Somebody of the staff asks to see the screen: the person answers here, and only about their own session.
Route::prefix('/screen')->group(function () {
    Route::get('/', [Client\ScreenShareController::class, 'show'])->middleware('throttle:120,1')->name('api:client.screen');
    Route::post('/offer', [Client\ScreenShareController::class, 'offer'])->middleware('throttle:10,1');
    Route::post('/decline', [Client\ScreenShareController::class, 'decline'])->middleware('throttle:10,1');
    Route::delete('/', [Client\ScreenShareController::class, 'stop']);
});

Route::prefix('/account')->middleware(AccountSubject::class)->group(function () {
    Route::prefix('/')->withoutMiddleware(RequireTwoFactorAuthentication::class)->group(function () {
        Route::get('/', [Client\AccountController::class, 'index'])->name('api:client.account');
        Route::get('/two-factor', [Client\TwoFactorController::class, 'index']);
        Route::post('/two-factor', [Client\TwoFactorController::class, 'store']);
        Route::post('/two-factor/disable', [Client\TwoFactorController::class, 'delete']);
    });

    Route::put('/language', [Client\AccountController::class, 'updateLanguage'])->name('api:client.account.update-language');
    Route::get('/notifications', [Client\NotificationController::class, 'index'])->name('api:client.account.notifications');
    Route::post('/notifications/read', [Client\NotificationController::class, 'read'])->name('api:client.account.notifications.read');
    Route::post('/notifications/delete', [Client\NotificationController::class, 'delete'])->name('api:client.account.notifications.delete');
    Route::put('/appearance', [Client\AccountController::class, 'updateAppearance'])->middleware('throttle:60,1')->name('api:client.account.update-appearance');

    Route::post('/avatar', [Client\AvatarController::class, 'store'])->middleware('throttle:10,1')->name('api:client.account.avatar');
    Route::delete('/avatar', [Client\AvatarController::class, 'destroy'])->name('api:client.account.avatar.remove');

    Route::put('/email', [Client\AccountController::class, 'updateEmail'])
        ->middleware('throttle')
        ->name('api:client.account.update-email');
    Route::put('/password', [Client\AccountController::class, 'updatePassword'])->name('api:client.account.update-password');

    Route::get('/activity', Client\ActivityLogController::class)->name('api:client.account.activity');

    Route::delete('/discord', [Client\AccountController::class, 'unlinkDiscord'])->name('api:client.account.unlink-discord');

    Route::get('/api-keys', [Client\ApiKeyController::class, 'index']);
    Route::post('/api-keys', [Client\ApiKeyController::class, 'store']);
    Route::delete('/api-keys/{identifier}', [Client\ApiKeyController::class, 'delete']);

    Route::prefix('/passkeys')->group(function () {
        Route::get('/', [Client\PasskeyController::class, 'index']);
        Route::post('/options', [Client\PasskeyController::class, 'options'])->middleware('throttle:20,1');
        Route::post('/', [Client\PasskeyController::class, 'store'])->middleware('throttle:20,1');
        Route::delete('/{passkey}', [Client\PasskeyController::class, 'delete'])->whereNumber('passkey');
    });

    Route::prefix('/ssh-keys')->group(function () {
        Route::get('/', [Client\SSHKeyController::class, 'index']);
        Route::post('/', [Client\SSHKeyController::class, 'store']);
        Route::post('/remove', [Client\SSHKeyController::class, 'delete']);
    });
});

/*
|--------------------------------------------------------------------------
| Client Control API
|--------------------------------------------------------------------------
|
| Endpoint: /api/client/servers/{server}
|
*/
Route::group([
    'prefix' => '/servers/{server}',
    'middleware' => [
        ServerSubject::class,
        AuthenticateServerAccess::class,
        ResourceBelongsToServer::class,
    ],
], function () {
    Route::get('/', [Client\Servers\ServerController::class, 'index'])->name('api:client:server.view');
    Route::middleware([ResourceLimit::Websocket->middleware()])
        ->get('/websocket', Client\Servers\WebsocketController::class)
        ->name('api:client:server.ws');
    Route::get('/resources', Client\Servers\ResourceUtilizationController::class)->name('api:client:server.resources');
    Route::get('/activity', Client\Servers\ActivityLogController::class)->name('api:client:server.activity');
    Route::get('/fivem', Client\Servers\FiveMController::class)->name('api:client:server.fivem');
    Route::get('/game-status', Client\Servers\GameStatusController::class)->name('api:client:server.game-status');
    Route::get('/players-history', [Client\Servers\ServerInsightsController::class, 'players']);
    Route::get('/players-live', [Client\Servers\ServerInsightsController::class, 'playersLive']);
    Route::get('/resources-history', [Client\Servers\ServerInsightsController::class, 'resources']);
    Route::get('/alerts', [Client\Servers\ServerInsightsController::class, 'alerts']);
    Route::put('/alerts', [Client\Servers\ServerInsightsController::class, 'saveAlerts']);
    Route::post('/alerts/test', [Client\Servers\ServerInsightsController::class, 'testAlert'])->middleware('throttle:subdomains');

    Route::post('/command', [Client\Servers\CommandController::class, 'index']);
    Route::post('/power', [Client\Servers\PowerController::class, 'index']);

    Route::group(['prefix' => '/databases'], function () {
        Route::get('/', [Client\Servers\DatabaseController::class, 'index']);
        Route::middleware([ResourceLimit::Database->middleware()])
            ->post('/', [Client\Servers\DatabaseController::class, 'store']);
        Route::post('/{database}/rotate-password', [Client\Servers\DatabaseController::class, 'rotatePassword']);
        Route::post('/{database}/phpmyadmin', Client\Servers\DatabasePhpMyAdminController::class);
        Route::delete('/{database}', [Client\Servers\DatabaseController::class, 'delete']);
    });

    Route::group(['prefix' => '/files'], function () {
        Route::get('/list', [Client\Servers\FileController::class, 'directory']);
        Route::get('/contents', [Client\Servers\FileController::class, 'contents']);
        Route::get('/download', [Client\Servers\FileController::class, 'download']);
        Route::put('/rename', [Client\Servers\FileController::class, 'rename']);
        Route::post('/copy', [Client\Servers\FileController::class, 'copy']);
        Route::post('/write', [Client\Servers\FileController::class, 'write']);
        Route::post('/compress', [Client\Servers\FileController::class, 'compress']);
        Route::post('/decompress', [Client\Servers\FileController::class, 'decompress']);
        Route::post('/delete', [Client\Servers\FileController::class, 'delete']);
        Route::post('/create-folder', [Client\Servers\FileController::class, 'create']);
        Route::post('/chmod', [Client\Servers\FileController::class, 'chmod']);
        Route::middleware([ResourceLimit::FilePull->middleware()])
            ->post('/pull', [Client\Servers\FileController::class, 'pull']);
        Route::get('/upload', Client\Servers\FileUploadController::class);
    });

    Route::group(['prefix' => '/schedules'], function () {
        Route::get('/', [Client\Servers\ScheduleController::class, 'index']);
        Route::middleware([ResourceLimit::Schedule->middleware()])
            ->post('/', [Client\Servers\ScheduleController::class, 'store']);
        Route::get('/{schedule}', [Client\Servers\ScheduleController::class, 'view']);
        Route::post('/{schedule}', [Client\Servers\ScheduleController::class, 'update']);
        Route::post('/{schedule}/execute', [Client\Servers\ScheduleController::class, 'execute']);
        Route::delete('/{schedule}', [Client\Servers\ScheduleController::class, 'delete']);

        Route::post('/{schedule}/tasks', [Client\Servers\ScheduleTaskController::class, 'store']);
        Route::post('/{schedule}/tasks/{task}', [Client\Servers\ScheduleTaskController::class, 'update']);
        Route::delete('/{schedule}/tasks/{task}', [Client\Servers\ScheduleTaskController::class, 'delete']);
    });

    Route::group(['prefix' => '/network'], function () {
        Route::get('/allocations', [Client\Servers\NetworkAllocationController::class, 'index']);
        Route::middleware([ResourceLimit::Allocation->middleware()])
            ->post('/allocations', [Client\Servers\NetworkAllocationController::class, 'store']);
        Route::post('/allocations/{allocation}', [Client\Servers\NetworkAllocationController::class, 'update']);
        Route::post('/allocations/{allocation}/primary', [Client\Servers\NetworkAllocationController::class, 'setPrimary']);
        Route::delete('/allocations/{allocation}', [Client\Servers\NetworkAllocationController::class, 'delete']);
    });

    Route::group(['prefix' => '/web'], function () {
        Route::get('/', [Client\Servers\WebHostingController::class, 'index']);
        Route::get('/status', [Client\Servers\WebHostingController::class, 'status']);
        Route::post('/domains', [Client\Servers\WebHostingController::class, 'addDomain'])->middleware('throttle:subdomains');
        Route::post('/subdomains', [Client\Servers\WebHostingController::class, 'addSubdomain'])->middleware('throttle:subdomains');
        Route::post('/domains/{domain}/check', [Client\Servers\WebHostingController::class, 'checkDomain'])->whereNumber('domain')->middleware('throttle:subdomains');
        Route::delete('/domains/{domain}', [Client\Servers\WebHostingController::class, 'deleteDomain'])->whereNumber('domain');
        Route::post('/install', [Client\Servers\WebHostingController::class, 'install'])->middleware('throttle:subdomains');
        Route::post('/uninstall', [Client\Servers\WebHostingController::class, 'uninstall'])->middleware('throttle:subdomains');
    });

    Route::group(['prefix' => '/mail'], function () {
        Route::get('/', [Client\Servers\MailController::class, 'index']);
        Route::post('/check', [Client\Servers\MailController::class, 'check'])->middleware('throttle:subdomains');
        Route::post('/enable', [Client\Servers\MailController::class, 'enable'])->middleware('throttle:subdomains');
        Route::post('/disable', [Client\Servers\MailController::class, 'disable']);
        Route::post('/boxes', [Client\Servers\MailController::class, 'store'])->middleware('throttle:subdomains');
        Route::post('/boxes/{box}/password', [Client\Servers\MailController::class, 'password'])->whereNumber('box')->middleware('throttle:subdomains');
        Route::delete('/boxes/{box}', [Client\Servers\MailController::class, 'delete'])->whereNumber('box');
    });

    Route::group(['prefix' => '/subdomains'], function () {
        Route::get('/', [Client\Servers\SubdomainController::class, 'index']);
        Route::post('/', [Client\Servers\SubdomainController::class, 'store'])->middleware('throttle:subdomains');
        Route::post('/{subdomain}', [Client\Servers\SubdomainController::class, 'update'])->whereNumber('subdomain');
        Route::delete('/{subdomain}', [Client\Servers\SubdomainController::class, 'delete'])->whereNumber('subdomain');
        Route::post('/custom', [Client\Servers\SubdomainController::class, 'storeCustom'])->middleware('throttle:subdomains');
        Route::post('/custom/{custom}', [Client\Servers\SubdomainController::class, 'updateCustom'])->whereNumber('custom');
        Route::post('/custom/{custom}/verify', [Client\Servers\SubdomainController::class, 'verifyCustom'])->whereNumber('custom')->middleware('throttle:subdomains');
        Route::delete('/custom/{custom}', [Client\Servers\SubdomainController::class, 'deleteCustom'])->whereNumber('custom');
    });

    Route::group(['prefix' => '/users'], function () {
        Route::get('/', [Client\Servers\SubuserController::class, 'index']);
        Route::middleware([ResourceLimit::Subuser->middleware()])
            ->post('/', [Client\Servers\SubuserController::class, 'store']);
        Route::get('/{user}', [Client\Servers\SubuserController::class, 'view']);
        Route::post('/{user}', [Client\Servers\SubuserController::class, 'update']);
        Route::delete('/{user}', [Client\Servers\SubuserController::class, 'delete']);
    });

    Route::group(['prefix' => '/backups'], function () {
        Route::get('/', [Client\Servers\BackupController::class, 'index']);
        Route::post('/', [Client\Servers\BackupController::class, 'store']);
        // The automatic backups of the server. These paths come before "/{backup}", which would take "auto" for a backup.
        Route::get('/auto', [Client\Servers\BackupPlanController::class, 'show']);
        Route::put('/auto', [Client\Servers\BackupPlanController::class, 'update']);
        Route::delete('/auto', [Client\Servers\BackupPlanController::class, 'destroy']);
        Route::get('/{backup}', [Client\Servers\BackupController::class, 'view']);
        Route::get('/{backup}/download', [Client\Servers\BackupController::class, 'download']);
        Route::post('/{backup}/lock', [Client\Servers\BackupController::class, 'toggleLock']);
        Route::middleware([ResourceLimit::Backup->middleware()])
            ->post('/{backup}/restore', [Client\Servers\BackupController::class, 'restore']);
        Route::delete('/{backup}', [Client\Servers\BackupController::class, 'delete']);
    });

    Route::group(['prefix' => '/startup'], function () {
        Route::get('/', [Client\Servers\StartupController::class, 'index']);
        Route::put('/variable', [Client\Servers\StartupController::class, 'update']);
    });

    Route::group(['prefix' => '/settings'], function () {
        Route::post('/rename', [Client\Servers\SettingsController::class, 'rename']);
        Route::post('/reinstall', [Client\Servers\SettingsController::class, 'reinstall']);
        Route::put('/docker-image', [Client\Servers\SettingsController::class, 'dockerImage']);
    });
});
