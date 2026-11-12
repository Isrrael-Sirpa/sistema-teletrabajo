<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentAccessGrantController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentFileController;
use App\Http\Controllers\DocumentRecipientController;
use App\Http\Controllers\DocumentReportController;
use App\Http\Controllers\DocumentReviewController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\DocumentWorkflowController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationalUnitController;
use App\Http\Controllers\TemporaryDelegationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/iniciar-sesion', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active.user'])->group(function (): void {
    Route::get('/panel', DashboardController::class)->name('dashboard');
    Route::post('/cerrar-sesion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/administracion/auditoria', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view')
        ->name('audit.index');

    Route::prefix('reportes')->name('reports.')->middleware('permission_or_delegation:reports.view_unit')->group(function (): void {
        Route::get('/documentos', [DocumentReportController::class, 'index'])->name('documents.index');
        Route::get('/documentos/exportar/csv', [DocumentReportController::class, 'csv'])
            ->middleware(['permission_or_delegation:reports.export', 'throttle:10,1'])
            ->name('documents.csv');
        Route::get('/documentos/exportar/xlsx', [DocumentReportController::class, 'xlsx'])
            ->middleware(['permission_or_delegation:reports.export', 'throttle:10,1'])
            ->name('documents.xlsx');
        Route::get('/documentos/exportar/pdf', [DocumentReportController::class, 'pdf'])
            ->middleware(['permission_or_delegation:reports.export', 'throttle:10,1'])
            ->name('documents.pdf');
    });

    Route::prefix('administracion/delegaciones')->name('delegations.')->middleware('permission:delegations.manage_global|delegations.manage_unit')->group(function (): void {
        Route::get('/', [TemporaryDelegationController::class, 'index'])->name('index');
        Route::get('/crear', [TemporaryDelegationController::class, 'create'])->name('create');
        Route::post('/', [TemporaryDelegationController::class, 'store'])->name('store');
        Route::delete('/{temporaryDelegation}', [TemporaryDelegationController::class, 'destroy'])->name('destroy');
    });

    Route::get('/notificaciones', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::patch('/notificaciones/leer-todas', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.read-all');
    Route::patch('/notificaciones/{notification}/leer', [NotificationController::class, 'markAsRead'])
        ->name('notifications.read');

    Route::get('/mis-tareas', [DocumentReviewController::class, 'index'])->name('reviews.mine');
    Route::get('/revisiones-de-mi-unidad', [DocumentReviewController::class, 'index'])->name('reviews.unit');

    Route::prefix('documentos')->name('documents.')->group(function (): void {
        Route::get('/', [DocumentController::class, 'index'])
            ->middleware('permission_or_delegation:documents.view')
            ->name('index');
        Route::get('/crear', [DocumentController::class, 'create'])
            ->middleware('permission_or_delegation:documents.create')
            ->name('create');
        Route::post('/', [DocumentController::class, 'store'])
            ->middleware('permission_or_delegation:documents.create')
            ->name('store');
        Route::get('/{document}', [DocumentController::class, 'show'])
            ->middleware('permission_or_delegation:documents.view')
            ->name('show');
        Route::get('/{document}/editar', [DocumentController::class, 'edit'])
            ->middleware('permission_or_delegation:documents.update_draft')
            ->name('edit');
        Route::put('/{document}', [DocumentController::class, 'update'])
            ->middleware('permission_or_delegation:documents.update_draft')
            ->name('update');
        Route::delete('/{document}/descartar', [DocumentController::class, 'discard'])
            ->middleware('permission_or_delegation:documents.discard_draft')
            ->name('discard');
        Route::post('/{document}/destinatarios', [DocumentRecipientController::class, 'store'])
            ->middleware('permission_or_delegation:documents.update_draft')
            ->name('recipients.store');
        Route::patch('/{document}/destinatarios', [DocumentRecipientController::class, 'update'])
            ->middleware('permission_or_delegation:documents.update_draft')
            ->name('recipients.update');
        Route::delete('/{document}/destinatarios', [DocumentRecipientController::class, 'destroy'])
            ->middleware('permission_or_delegation:documents.update_draft')
            ->name('recipients.destroy');
        Route::post('/{document}/archivos', [DocumentFileController::class, 'store'])
            ->middleware('permission_or_delegation:documents.version')
            ->name('files.store');
        Route::post('/{document}/versiones/correccion', [DocumentFileController::class, 'storeCorrection'])
            ->middleware('permission_or_delegation:documents.version')
            ->name('versions.correction.store');
        Route::get('/{document}/archivos/{documentFile}/descargar', [DocumentFileController::class, 'download'])
            ->middleware(['permission_or_delegation:documents.download', 'throttle:120,1'])
            ->name('files.download');
        Route::delete('/{document}/archivos/{documentFile}', [DocumentFileController::class, 'destroy'])
            ->middleware('permission_or_delegation:documents.version')
            ->name('files.destroy');
        Route::post('/{document}/accesos', [DocumentAccessGrantController::class, 'store'])
            ->middleware('permission_or_delegation:documents.grant_access')
            ->name('access-grants.store');
        Route::patch('/{document}/accesos/{documentAccessGrant}', [DocumentAccessGrantController::class, 'update'])
            ->middleware('permission_or_delegation:documents.grant_access')
            ->name('access-grants.update');
        Route::delete('/{document}/accesos/{documentAccessGrant}', [DocumentAccessGrantController::class, 'destroy'])
            ->middleware('permission_or_delegation:documents.grant_access')
            ->name('access-grants.destroy');
        Route::post('/{document}/enviar', [DocumentWorkflowController::class, 'send'])
            ->middleware('permission_or_delegation:documents.send')
            ->name('send');
        Route::post('/{document}/correccion/enviar', [DocumentWorkflowController::class, 'sendCorrection'])
            ->middleware('permission_or_delegation:documents.send')
            ->name('correction.send');
        Route::post('/{document}/anular', [DocumentWorkflowController::class, 'annul'])
            ->middleware('permission:documents.annul')
            ->name('annul');
        Route::patch('/{document}/destinatarios/{documentRecipient}/estado/{transition}', [DocumentWorkflowController::class, 'transition'])
            ->whereIn('transition', ['receive', 'start_review', 'approve', 'return', 'reject'])
            ->name('recipients.transition');
        Route::post('/{document}/destinatarios/{documentRecipient}/revisiones', [DocumentReviewController::class, 'store'])
            ->name('reviews.store');
        Route::patch('/{document}/revisiones/{reviewTask}', [DocumentReviewController::class, 'update'])
            ->name('reviews.update');
    });

    Route::prefix('administracion/unidades')->name('units.')->group(function (): void {
        Route::get('/', [OrganizationalUnitController::class, 'index'])
            ->middleware('permission:units.view')
            ->name('index');
        Route::get('/crear', [OrganizationalUnitController::class, 'create'])
            ->middleware('permission:units.create')
            ->name('create');
        Route::post('/', [OrganizationalUnitController::class, 'store'])
            ->middleware('permission:units.create')
            ->name('store');
        Route::get('/{unit}', [OrganizationalUnitController::class, 'show'])
            ->middleware('permission:units.view')
            ->name('show');
        Route::get('/{unit}/editar', [OrganizationalUnitController::class, 'edit'])
            ->middleware('permission:units.update')
            ->name('edit');
        Route::put('/{unit}', [OrganizationalUnitController::class, 'update'])
            ->middleware('permission:units.update')
            ->name('update');
        Route::patch('/{unit}/activar', [OrganizationalUnitController::class, 'activate'])
            ->middleware('permission:units.activate')
            ->name('activate');
        Route::patch('/{unit}/desactivar', [OrganizationalUnitController::class, 'deactivate'])
            ->middleware('permission:units.deactivate')
            ->name('deactivate');
    });

    Route::prefix('administracion/usuarios')->name('users.')->group(function (): void {
        Route::get('/', [UserController::class, 'index'])
            ->middleware('permission_or_delegation:users.view')
            ->name('index');
        Route::get('/crear', [UserController::class, 'create'])
            ->middleware('permission_or_delegation:users.create')
            ->name('create');
        Route::post('/', [UserController::class, 'store'])
            ->middleware('permission_or_delegation:users.create')
            ->name('store');
        Route::get('/{user}', [UserController::class, 'show'])
            ->middleware('permission_or_delegation:users.view')
            ->name('show');
        Route::get('/{user}/editar', [UserController::class, 'edit'])
            ->middleware('permission_or_delegation:users.update')
            ->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])
            ->middleware('permission_or_delegation:users.update')
            ->name('update');
        Route::patch('/{user}/activar', [UserController::class, 'activate'])
            ->middleware('permission_or_delegation:users.activate')
            ->name('activate');
        Route::patch('/{user}/desactivar', [UserController::class, 'deactivate'])
            ->middleware('permission_or_delegation:users.deactivate')
            ->name('deactivate');
        Route::get('/{user}/restablecer-contrasena', [UserController::class, 'editPassword'])
            ->middleware('permission_or_delegation:users.reset_password')
            ->name('password.edit');
        Route::put('/{user}/restablecer-contrasena', [UserController::class, 'updatePassword'])
            ->middleware('permission_or_delegation:users.reset_password')
            ->name('password.update');
    });

    Route::prefix('administracion/tipos-documentales')->name('document-types.')->group(function (): void {
        Route::get('/', [DocumentTypeController::class, 'index'])
            ->middleware('permission:document_types.view')
            ->name('index');
        Route::get('/crear', [DocumentTypeController::class, 'create'])
            ->middleware('permission:document_types.manage')
            ->name('create');
        Route::post('/', [DocumentTypeController::class, 'store'])
            ->middleware('permission:document_types.manage')
            ->name('store');
        Route::get('/{documentType}', [DocumentTypeController::class, 'show'])
            ->middleware('permission:document_types.view')
            ->name('show');
        Route::get('/{documentType}/editar', [DocumentTypeController::class, 'edit'])
            ->middleware('permission:document_types.manage')
            ->name('edit');
        Route::put('/{documentType}', [DocumentTypeController::class, 'update'])
            ->middleware('permission:document_types.manage')
            ->name('update');
        Route::patch('/{documentType}/activar', [DocumentTypeController::class, 'activate'])
            ->middleware('permission:document_types.manage')
            ->name('activate');
        Route::patch('/{documentType}/desactivar', [DocumentTypeController::class, 'deactivate'])
            ->middleware('permission:document_types.manage')
            ->name('deactivate');
    });
});
