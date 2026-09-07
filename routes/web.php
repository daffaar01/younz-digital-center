<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashSessionController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerEmailVerificationController;
use App\Http\Controllers\CustomerOrderActionController;
use App\Http\Controllers\CustomerPasswordResetController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\CustomerProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DigiflazzWebhookController;
use App\Http\Controllers\DigitalTransactionController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FirebaseAuthProxyController;
use App\Http\Controllers\KnowledgeDocumentController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ServiceOrderController;
use App\Http\Controllers\StaffAccessController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TestimonialController;
use App\Http\Controllers\TopupController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\WhatsAppGatewayController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Middleware\RequireStaffAccessGate;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::match(['GET', 'POST'], '/__/auth/{path}', FirebaseAuthProxyController::class)
    ->where('path', '.*')
    ->middleware('throttle:firebase-helper')
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        // The helper is stateless: skip CSRF middleware's session-cookie handling too.
        ValidateCsrfToken::class,
        RequireStaffAccessGate::class,
    ])
    ->name('firebase.auth.proxy');

Route::post('/webhooks/whatsapp/messages', WhatsAppWebhookController::class)
    ->middleware('throttle:webhook')
    ->name('webhooks.whatsapp.messages');

Route::middleware('staff.host')->group(function () {
    Route::get('/akses-pegawai', [StaffAccessController::class, 'create'])->name('staff.access.show');
    Route::post('/akses-pegawai', [StaffAccessController::class, 'store'])
        ->middleware('throttle:staff-access')
        ->name('staff.access.verify');
});

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/service-worker.js', [PublicController::class, 'serviceWorker'])->name('service-worker');
Route::get('/topup', [TopupController::class, 'index'])->name('topup.index');
Route::post('/topup', [TopupController::class, 'store'])->middleware('throttle:topup-checkout')->name('topup.store');
Route::get('/topup/akses', [TopupController::class, 'access'])->name('topup.access');
Route::post('/topup/akses', [TopupController::class, 'resolveAccess'])->middleware('throttle:topup-access')->name('topup.access.resolve');
Route::get('/topup/status/{topupOrder}', [TopupController::class, 'show'])->middleware('signed:relative,order_id,status_code,transaction_status')->name('topup.show');
Route::get('/topup/status/{topupOrder}/struk', [TopupController::class, 'receipt'])->middleware(['signed:relative', 'throttle:60,1'])->name('topup.receipt');
Route::get('/topup/status/{topupOrder}/struk.pdf', [TopupController::class, 'receiptPdf'])->middleware(['signed:relative', 'throttle:60,1'])->name('topup.receipt.pdf');
Route::get('/topup/status/{topupOrder}/invoice', [TopupController::class, 'invoice'])->middleware(['signed:relative', 'throttle:60,1'])->name('topup.invoice');
Route::post('/topup/status/{topupOrder}/bayar', [TopupController::class, 'pay'])
    ->middleware(['signed:relative', 'throttle:topup-checkout'])
    ->withoutMiddleware(PreventRequestForgery::class)
    ->name('topup.pay');
Route::post('/webhooks/midtrans', MidtransWebhookController::class)->middleware('throttle:webhook')->name('webhooks.midtrans');
Route::post('/webhooks/digiflazz', DigiflazzWebhookController::class)->middleware('throttle:webhook')->name('webhooks.digiflazz');
Route::get('/kebijakan-privasi', [PublicController::class, 'privacy'])->name('privacy');
Route::get('/syarat-layanan', [PublicController::class, 'terms'])->name('terms');
Route::get('/sitemap.xml', [PublicController::class, 'sitemap'])->name('sitemap');
Route::get('/pesan', [PublicController::class, 'order'])->name('public.order');
Route::post('/pesan', [PublicController::class, 'storeOrder'])->middleware('throttle:10,1')->name('public.order.store');
Route::get('/cek-pesanan', [PublicController::class, 'track'])->name('public.track');
Route::post('/cek-pesanan', [PublicController::class, 'track'])->middleware('throttle:20,1')->name('public.track.lookup');
Route::get('/cek-pesanan/{token}', [PublicController::class, 'showTrack'])->name('public.track.show');
Route::get('/pesanan-saya', [PublicController::class, 'myOrders'])->name('public.my-orders');
Route::post('/pesanan-saya', [PublicController::class, 'myOrders'])->middleware('throttle:20,1')->name('public.my-orders.lookup');
Route::post('/tanya-ai', [AiController::class, 'chat'])->middleware('throttle:20,1')->name('public.ai.chat');
Route::post('/tanya-ai/stream', [AiController::class, 'chatStream'])->middleware('throttle:20,1')->name('public.ai.chat.stream');
Route::delete('/tanya-ai/history', [AiController::class, 'clearHistory'])->middleware('throttle:10,1')->name('public.ai.history.clear');
Route::post('/tanya-ai/feedback', [AiController::class, 'feedback'])->middleware('throttle:30,1')->name('public.ai.feedback');
Route::post('/cek-pesanan-cepat', [AiController::class, 'quickTrack'])->middleware('throttle:20,1')->name('public.track.quick');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->middleware('staff.host')->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware(['staff.host', 'throttle:staff-login']);
    Route::get('/akun/masuk', [CustomerAuthController::class, 'create'])->name('customer.login');
    Route::post('/akun/masuk', [CustomerAuthController::class, 'store'])->middleware('throttle:customer-login')->name('customer.login.store');
    Route::get('/akun/daftar', [CustomerAuthController::class, 'showRegistration'])->name('customer.register');
    Route::post('/akun/daftar', [CustomerAuthController::class, 'register'])->middleware('throttle:registration')->name('customer.register.store');
    Route::post('/akun/firebase', [CustomerAuthController::class, 'firebase'])->middleware('throttle:firebase-login')->name('customer.firebase');
    Route::get('/akun/lupa-password', [CustomerPasswordResetController::class, 'request'])->name('password.request');
    Route::post('/akun/lupa-password', [CustomerPasswordResetController::class, 'email'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/akun/reset-password/{token}', [CustomerPasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/akun/reset-password', [CustomerPasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
    Route::get('/two-factor-challenge', [TwoFactorController::class, 'challenge'])->middleware('staff.host')->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorController::class, 'verifyChallenge'])->middleware(['staff.host', 'throttle:two-factor'])->name('two-factor.challenge.verify');
});

Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

// Verification links must also work when registration happened in the mobile
// app and the link is opened in a browser without an existing web session.
Route::get('/email/verify/{id}/{hash}', [CustomerEmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware(['auth', 'active', 'role:customer'])->group(function () {
    Route::get('/email/verify', [CustomerEmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [CustomerEmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::prefix('akun')->name('customer.')->middleware(['auth', 'active', 'role:customer', 'verified'])->group(function () {
    Route::get('/', [CustomerPortalController::class, 'index'])->name('dashboard');
    Route::get('/profil', [CustomerProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profil', [CustomerProfileController::class, 'update'])->name('profile.update');
    Route::get('/pesanan/{order}', [CustomerPortalController::class, 'show'])->name('orders.show');
    Route::post('/pesanan/{order}/estimasi/setujui', [CustomerOrderActionController::class, 'acceptEstimate'])->name('orders.estimate.accept');
    Route::post('/pesanan/{order}/estimasi/tolak', [CustomerOrderActionController::class, 'rejectEstimate'])->name('orders.estimate.reject');
    Route::post('/pesanan/{order}/pembayaran', [CustomerOrderActionController::class, 'confirmPayment'])->name('orders.payment.confirm');
    Route::post('/pesanan/{order}/revisi', [CustomerOrderActionController::class, 'requestRevision'])->name('orders.revision.request');
    Route::get('/pesanan/{order}/invoice', [CustomerOrderActionController::class, 'invoice'])->name('orders.invoice');
    Route::get('/pesanan/{order}/hasil/{file}', [CustomerOrderActionController::class, 'download'])->middleware('signed')->name('orders.results.download');
});

Route::middleware(['staff.host', 'auth', 'active', 'role:owner,admin,cashier,print_operator,designer,developer', 'staff.2fa'])->group(function () {
    Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup.show');
    Route::post('/two-factor/setup', [TwoFactorController::class, 'confirmSetup'])->middleware('throttle:two-factor')->name('two-factor.setup.confirm');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:owner,admin')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::post('/products/{product}/stock', [StockMovementController::class, 'store'])->name('products.stock');
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('/reports/daily', [ReportController::class, 'daily'])->name('reports.daily');
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::get('/approvals/{approval}', [ApprovalController::class, 'show'])->name('approvals.show');
        Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
        Route::get('/knowledge', [KnowledgeDocumentController::class, 'index'])->name('knowledge.index');
        Route::post('/knowledge', [KnowledgeDocumentController::class, 'store'])->name('knowledge.store');
        Route::put('/knowledge/{document}', [KnowledgeDocumentController::class, 'update'])->name('knowledge.update');
        Route::delete('/knowledge/{document}', [KnowledgeDocumentController::class, 'destroy'])->name('knowledge.destroy');
        Route::get('/testimonials', [TestimonialController::class, 'index'])->name('testimonials.index');
        Route::post('/testimonials', [TestimonialController::class, 'store'])->name('testimonials.store');
        Route::put('/testimonials/{testimonial}', [TestimonialController::class, 'update'])->name('testimonials.update');
        Route::delete('/testimonials/{testimonial}', [TestimonialController::class, 'destroy'])->name('testimonials.destroy');
        Route::get('/integrations/whatsapp', [WhatsAppGatewayController::class, 'index'])->name('whatsapp.index');
        Route::post('/integrations/whatsapp/reconnect', [WhatsAppGatewayController::class, 'reconnect'])->name('whatsapp.reconnect');
        Route::delete('/integrations/whatsapp/session', [WhatsAppGatewayController::class, 'logout'])->name('whatsapp.logout');
        Route::get('/employees', [EmployeeController::class, 'index'])->middleware('role:owner')->name('employees.index');
        Route::post('/employees', [EmployeeController::class, 'store'])->middleware('role:owner')->name('employees.store');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->middleware('role:owner')->name('employees.update');
    });

    Route::middleware('role:owner,admin,cashier')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('/cash-sessions', [CashSessionController::class, 'open'])->name('cash-sessions.open');
        Route::post('/cash-sessions/{cashSession}/close', [CashSessionController::class, 'close'])->name('cash-sessions.close');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/digital-transactions', [DigitalTransactionController::class, 'index'])->name('digital.index');
        Route::post('/digital-transactions', [DigitalTransactionController::class, 'store'])->name('digital.store');
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::post('/sales/{sale}/refunds', [RefundController::class, 'store'])->name('sales.refunds.store');
    });

    Route::middleware('role:owner,admin,cashier,print_operator,designer,developer')->group(function () {
        Route::get('/orders', [ServiceOrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [ServiceOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order}', [ServiceOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/status', [ServiceOrderController::class, 'updateStatus'])->name('orders.status');
        Route::patch('/orders/{order}/details', [ServiceOrderController::class, 'updateDetails'])->name('orders.details.update');
        Route::post('/orders/{order}/results', [ServiceOrderController::class, 'uploadResult'])->name('orders.results.store');
        Route::get('/orders/{order}/files/{file}', [ServiceOrderController::class, 'download'])->middleware('signed')->name('orders.files.download');
        Route::post('/ai/order-intake', [AiController::class, 'orderIntake'])->middleware('throttle:30,1')->name('ai.order-intake');
    });
});
