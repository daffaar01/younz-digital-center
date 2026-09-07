<?php

use App\Http\Controllers\AgentPpobApiController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerOrderActionController;
use App\Http\Controllers\CustomerPasswordResetController;
use App\Http\Controllers\CustomerProfileController;
use App\Http\Controllers\DigitalProductApiController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\StaffApprovalApiController;
use App\Http\Controllers\StaffContentApiController;
use App\Http\Controllers\StaffDashboardApiController;
use App\Http\Controllers\StaffDigitalApiController;
use App\Http\Controllers\StaffDirectoryApiController;
use App\Http\Controllers\StaffFinanceApiController;
use App\Http\Controllers\StaffOrderApiController;
use App\Http\Controllers\StaffProductApiController;
use App\Http\Controllers\StaffProjectReminderApiController;
use App\Http\Controllers\StaffWhatsAppApiController;
use App\Http\Controllers\ErpTransactionSyncController;
use App\Http\Controllers\TopupController;
use App\Http\Middleware\EnsureYounzErpSyncToken;
use App\Http\Middleware\EnsureYounzPpobAgentToken;
use App\Http\Middleware\EnsureYounzPpobOperatorToken;
use Illuminate\Support\Facades\Route;

Route::prefix('ppob')->middleware([EnsureYounzPpobAgentToken::class, 'throttle:120,1'])->group(function (): void {
    Route::get('/products', [AgentPpobApiController::class, 'searchProducts']);
    Route::get('/balance', [AgentPpobApiController::class, 'balance']);
    Route::post('/prepaid/prepare', [AgentPpobApiController::class, 'preparePrepaid']);
    Route::post('/postpaid/inquiry', [AgentPpobApiController::class, 'inquiryPostpaid']);
    Route::get('/transactions/{refId}', [AgentPpobApiController::class, 'transaction']);
    Route::get('/reports/daily', [AgentPpobApiController::class, 'dailyReport']);
});

Route::prefix('ppob/operator')
    ->middleware([EnsureYounzPpobOperatorToken::class, 'throttle:30,1'])
    ->group(function (): void {
        Route::post('/transactions/{refId}/approve', [AgentPpobApiController::class, 'approve']);
    });

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    // Read-only server-to-server bridge for the separate YOUNZ ERP API.
    Route::get('/staff/erp-sync/transactions', [ErpTransactionSyncController::class, 'index'])
        ->middleware([EnsureYounzErpSyncToken::class, 'throttle:60,1']);

    Route::post('/auth/login', [AuthController::class, 'apiLogin'])->middleware('throttle:api-login');
    Route::get('/staff/auth-config', [AuthController::class, 'staffAuthConfig']);
    Route::post('/staff/login', [AuthController::class, 'staffApiLogin'])->middleware('throttle:staff-login');
    Route::post('/staff/two-factor/setup', [AuthController::class, 'confirmStaffTwoFactorSetup'])->middleware('throttle:staff-two-factor-api');
    Route::post('/auth/customer-login', [CustomerAuthController::class, 'apiLogin'])->middleware('throttle:customer-login');
    Route::post('/auth/register', [CustomerAuthController::class, 'apiRegister'])->middleware('throttle:registration');
    Route::post('/auth/email-verification/resend', [CustomerAuthController::class, 'apiResendVerification'])->middleware('throttle:verification-resend');
    Route::post('/auth/forgot-password', [CustomerPasswordResetController::class, 'emailApi'])->middleware('throttle:password-reset');
    Route::post('/auth/reset-password', [CustomerPasswordResetController::class, 'updateApi'])->middleware('throttle:5,1');
    Route::get('/site', [ApiController::class, 'site']);
    Route::post('/public/orders', [ApiController::class, 'publicStoreOrder'])->middleware('throttle:10,1');
    Route::post('/public/orders/track', [ApiController::class, 'publicTrackOrder'])->middleware('throttle:20,1');
    Route::get('/public/orders/{token}', [ApiController::class, 'publicOrder'])->middleware('throttle:30,1');
    Route::get('/services', [ApiController::class, 'services']);
    Route::get('/products', [ApiController::class, 'products']);
    Route::get('/digital-products', [DigitalProductApiController::class, 'publicIndex']);
    Route::get('/digital-products/{digitalProduct}', [DigitalProductApiController::class, 'publicShow']);
    Route::get('/digital-products/{digitalProduct}/image', [DigitalProductApiController::class, 'image'])->middleware('throttle:120,1');
    Route::get('/topup/catalog', [TopupController::class, 'catalogApi']);
    Route::post('/topup/checkout', [TopupController::class, 'storeApi'])->middleware('throttle:topup-checkout');
    Route::post('/topup/access', [TopupController::class, 'resolveAccess'])->middleware('throttle:topup-access');
    Route::post('/ai/chat', [AiController::class, 'chat'])->middleware('throttle:20,1');
    Route::post('/ai/stream', [AiController::class, 'chatStream'])->middleware('throttle:20,1');
    Route::post('/ai/feedback', [AiController::class, 'feedback'])->middleware('throttle:30,1');
    Route::post('/analytics/events', [AnalyticsController::class, 'store'])->middleware('throttle:120,1');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'apiLogout']);
        Route::get('/me', [ApiController::class, 'me']);
        Route::post('/customer/digital-product-orders', [ApiController::class, 'customerStoreDigitalProductOrder'])->middleware('throttle:10,1');
        Route::get('/customer/digital-product-orders/{order}/status', [ApiController::class, 'customerDigitalProductOrderStatus'])->middleware('throttle:60,1');
        Route::post('/customer/digital-product-orders/{order}/refresh-payment', [ApiController::class, 'refreshCustomerDigitalProductOrderPayment'])->middleware('throttle:20,1');
        Route::get('/staff/dashboard', StaffDashboardApiController::class);
        Route::get('/staff/project-reminders', [StaffProjectReminderApiController::class, 'index']);
        Route::post('/staff/project-reminders', [StaffProjectReminderApiController::class, 'store']);
        Route::match(['put', 'patch'], '/staff/project-reminders/{projectReminder}', [StaffProjectReminderApiController::class, 'update']);
        Route::post('/staff/project-reminders/{projectReminder}/reveal-credentials', [StaffProjectReminderApiController::class, 'revealCredentials'])->middleware('throttle:5,1');
        Route::get('/staff/content', [StaffContentApiController::class, 'index']);
        Route::post('/staff/content/services', [StaffContentApiController::class, 'storeService']);
        Route::put('/staff/content/services/{service}', [StaffContentApiController::class, 'updateService']);
        Route::delete('/staff/content/services/{service}', [StaffContentApiController::class, 'destroyService']);
        Route::post('/staff/content/knowledge', [StaffContentApiController::class, 'storeKnowledge']);
        Route::put('/staff/content/knowledge/{document}', [StaffContentApiController::class, 'updateKnowledge']);
        Route::delete('/staff/content/knowledge/{document}', [StaffContentApiController::class, 'destroyKnowledge']);
        Route::post('/staff/content/testimonials', [StaffContentApiController::class, 'storeTestimonial']);
        Route::put('/staff/content/testimonials/{testimonial}', [StaffContentApiController::class, 'updateTestimonial']);
        Route::delete('/staff/content/testimonials/{testimonial}', [StaffContentApiController::class, 'destroyTestimonial']);
        Route::get('/staff/orders', [StaffOrderApiController::class, 'index']);
        Route::get('/staff/orders/{order}', [StaffOrderApiController::class, 'show']);
        Route::post('/staff/orders/{order}/status', [StaffOrderApiController::class, 'updateStatus']);
        Route::get('/staff/orders/{order}/files/{file}', [StaffOrderApiController::class, 'download']);
        Route::get('/staff/products', [StaffProductApiController::class, 'index']);
        Route::post('/staff/products', [StaffProductApiController::class, 'store']);
        Route::put('/staff/products/{product}', [StaffProductApiController::class, 'update']);
        Route::post('/staff/products/{product}/stock', [StaffProductApiController::class, 'requestStock']);
        Route::get('/staff/digital-products', [DigitalProductApiController::class, 'index']);
        Route::get('/staff/digital-products/{digitalProduct}/image', [DigitalProductApiController::class, 'staffImage'])->middleware('throttle:120,1');
        Route::post('/staff/digital-products', [DigitalProductApiController::class, 'store']);
        Route::patch('/staff/digital-products/{digitalProduct}', [DigitalProductApiController::class, 'update']);
        Route::delete('/staff/digital-products/{digitalProduct}', [DigitalProductApiController::class, 'destroy']);
        Route::get('/staff/approvals', [StaffApprovalApiController::class, 'index']);
        Route::get('/staff/approvals/{approval}', [StaffApprovalApiController::class, 'show']);
        Route::post('/staff/approvals/{approval}/approve', [StaffApprovalApiController::class, 'approve']);
        Route::post('/staff/approvals/{approval}/reject', [StaffApprovalApiController::class, 'reject']);
        Route::get('/staff/suppliers', [StaffDirectoryApiController::class, 'suppliers']);
        Route::post('/staff/suppliers', [StaffDirectoryApiController::class, 'storeSupplier']);
        Route::get('/staff/customers', [StaffDirectoryApiController::class, 'customers']);
        Route::post('/staff/customers', [StaffDirectoryApiController::class, 'storeCustomer']);
        Route::get('/staff/expenses', [StaffFinanceApiController::class, 'expenses']);
        Route::post('/staff/expenses', [StaffFinanceApiController::class, 'requestExpense']);
        Route::get('/staff/reports/daily', [StaffFinanceApiController::class, 'daily']);
        Route::get('/staff/digital-transactions', [StaffDigitalApiController::class, 'index']);
        Route::get('/staff/whatsapp/status', [StaffWhatsAppApiController::class, 'status']);
        Route::post('/staff/whatsapp/reconnect', [StaffWhatsAppApiController::class, 'reconnect']);
        Route::post('/staff/whatsapp/logout', [StaffWhatsAppApiController::class, 'logout']);
        Route::post('/staff/digital-transactions/offline-topups', [StaffDigitalApiController::class, 'createOfflineTopup']);
        Route::post('/staff/digital-transactions/{orderNumber}/verify-payment', [StaffDigitalApiController::class, 'verifyTopupPayment']);
        Route::post('/staff/digital-transactions', [StaffDigitalApiController::class, 'store']);
        Route::get('/customer/portal', [ApiController::class, 'customerPortal']);
        Route::patch('/customer/profile', [CustomerProfileController::class, 'updateApi']);
        Route::get('/customer/orders/{order}', [ApiController::class, 'customerOrder']);
        Route::post('/customer/orders/{order}/estimate/accept', [CustomerOrderActionController::class, 'acceptEstimateApi']);
        Route::post('/customer/orders/{order}/estimate/reject', [CustomerOrderActionController::class, 'rejectEstimateApi']);
        Route::post('/customer/orders/{order}/payment', [CustomerOrderActionController::class, 'confirmPaymentApi']);
        Route::post('/customer/orders/{order}/revision', [CustomerOrderActionController::class, 'requestRevisionApi']);
        Route::get('/customer/orders/{order}/files/{file}', [CustomerOrderActionController::class, 'downloadApi']);
        Route::get('/orders', [ApiController::class, 'orders']);
        Route::post('/orders', [ApiController::class, 'storeOrder']);
        Route::get('/orders/{order}', [ApiController::class, 'order']);
        Route::post('/ai/order-intake', [AiController::class, 'orderIntake'])->middleware('throttle:30,1');
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->middleware('role:owner,admin,cashier');
    });
});
