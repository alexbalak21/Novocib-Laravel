# Laravel Microservices Architecture - Integration Guide

Guide for integrating multiple mini-apps (microservices) into your Novocib Laravel application, including Inventory Management, Customer-Seller Chat, and Integrated Email System.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Microservices Module Structure](#microservices-module-structure)
3. [Service Communication](#service-communication)
4. [Authentication & Authorization](#authentication--authorization)
5. [Inventory Management System](#inventory-management-system)
6. [Customer-Seller Web Chat](#customer-seller-web-chat)
7. [Integrated Email System](#integrated-email-system)
8. [Cross-Service Events](#cross-service-events)
9. [Database Design](#database-design)
10. [Deployment Considerations](#deployment-considerations)

---

## Architecture Overview

### Microservices Approach

Instead of monolithic architecture, organize your application into loosely-coupled modules:

```
Laravel App Root
├── app/
│   ├── Modules/
│   │   ├── TaskManager/          (Already implemented)
│   │   ├── Inventory/            (New)
│   │   ├── Chat/                 (New)
│   │   └── Email/                (New)
│   ├── Models/
│   ├── Controllers/
│   └── Providers/
├── routes/
│   ├── web.php
│   ├── task-manager.php
│   ├── inventory.php
│   ├── chat.php
│   └── email.php
├── database/
│   ├── migrations/
│   └── seeders/
└── resources/
    └── views/
        ├── admin/
        │   ├── task-manager/
        │   ├── inventory/
        │   ├── chat/
        │   └── email/
        └── customer/
            ├── chat/
            └── profile/
```

### Service Registry Pattern

Track and manage all services centrally:

**`config/services.php`**:

```php
<?php

return [
    'task-manager' => [
        'enabled' => true,
        'routes' => 'task-manager',
        'namespace' => 'App\\Modules\\TaskManager',
    ],
    'inventory' => [
        'enabled' => true,
        'routes' => 'inventory',
        'namespace' => 'App\\Modules\\Inventory',
    ],
    'chat' => [
        'enabled' => true,
        'routes' => 'chat',
        'namespace' => 'App\\Modules\\Chat',
        'websocket' => true,
    ],
    'email' => [
        'enabled' => true,
        'routes' => 'email',
        'namespace' => 'App\\Modules\\Email',
    ],
];
```

---

## Microservices Module Structure

### Standard Module Layout

Each module follows this structure:

```
app/Modules/InventoryModule/
├── Models/
│   ├── Product.php
│   ├── Stock.php
│   └── StockHistory.php
├── Controllers/
│   ├── Admin/
│   │   ├── ProductController.php
│   │   └── StockController.php
│   └── Api/
│       └── InventoryApiController.php
├── Requests/
│   ├── StoreProductRequest.php
│   └── UpdateStockRequest.php
├── Services/
│   ├── InventoryService.php
│   ├── StockService.php
│   └── ReportService.php
├── Events/
│   ├── StockLow.php
│   └── ProductCreated.php
├── Listeners/
│   └── NotifyOnLowStock.php
├── Resources/
│   ├── ProductResource.php
│   └── StockResource.php
├── Migrations/
│   ├── create_products_table.php
│   └── create_stocks_table.php
└── Routes/
    └── admin.php
```

### Module Service Provider

Create a service provider for each module (`app/Modules/Inventory/InventoryServiceProvider.php`):

```php
<?php

namespace App\Modules\Inventory;

use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->registerServices();
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/Routes/admin.php');
        $this->registerListeners();
    }

    private function registerServices()
    {
        $this->app->bind('inventory.service', function ($app) {
            return new Services\InventoryService();
        });

        $this->app->bind('stock.service', function ($app) {
            return new Services\StockService();
        });
    }

    private function registerListeners()
    {
        // Register event listeners
        \Illuminate\Support\Facades\Event::listen(
            Events\StockLow::class,
            [Listeners\NotifyOnLowStock::class, 'handle']
        );
    }
}
```

Register providers in `config/app.php`:

```php
'providers' => [
    // ... existing providers
    App\Modules\TaskManager\TaskManagerServiceProvider::class,
    App\Modules\Inventory\InventoryServiceProvider::class,
    App\Modules\Chat\ChatServiceProvider::class,
    App\Modules\Email\EmailServiceProvider::class,
],
```

---

## Service Communication

### Inter-Service Communication

Services communicate through events, service calls, and API endpoints.

#### 1. Event-Based Communication

When inventory is low, notify other services:

**`app/Modules/Inventory/Events/StockLow.php`**:

```php
<?php

namespace App\Modules\Inventory\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class StockLow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $productId,
        public int $currentStock,
        public int $minimumLevel
    ) {}

    public function broadcastOn()
    {
        return new Channel('inventory');
    }
}
```

**Listener to send notification**:

```php
<?php

namespace App\Modules\Inventory\Listeners;

use App\Modules\Inventory\Events\StockLow;
use App\Modules\Email\Services\EmailService;

class NotifyOnLowStock
{
    public function handle(StockLow $event)
    {
        $emailService = app('email.service');
        
        $emailService->sendToAdmins(
            'Low Stock Alert',
            'inventory.emails.low-stock',
            ['productId' => $event->productId, 'stock' => $event->currentStock]
        );
    }
}
```

#### 2. Direct Service Injection

Access one service from another:

```php
<?php

namespace App\Modules\Chat\Services;

use App\Modules\Email\Services\EmailService;

class ChatService
{
    public function __construct(private EmailService $emailService)
    {}

    public function notifyCustomerOnMessage($customerId, $message)
    {
        // Send email notification
        $this->emailService->sendToCustomer($customerId, 'New Message', $message);
    }
}
```

#### 3. API Endpoints for Inter-Service Communication

**`app/Modules/Inventory/Routes/api.php`**:

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Inventory\Controllers\Api\InventoryApiController;

Route::middleware(['auth:api', 'internal'])->prefix('api/inventory')->group(function () {
    Route::get('products/{id}', [InventoryApiController::class, 'getProduct']);
    Route::get('stock/{id}', [InventoryApiController::class, 'getStock']);
    Route::post('check-availability', [InventoryApiController::class, 'checkAvailability']);
    Route::post('reserve-stock', [InventoryApiController::class, 'reserveStock']);
    Route::post('release-stock', [InventoryApiController::class, 'releaseStock']);
});
```

---

## Authentication & Authorization

### Multi-Guard Authentication

Configure in `config/auth.php`:

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'customer' => [
        'driver' => 'session',
        'provider' => 'customers',
    ],
    'api' => [
        'driver' => 'token',
        'provider' => 'users',
    ],
    'internal-api' => [
        'driver' => 'token',
        'provider' => 'internal-services',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
    'customers' => [
        'driver' => 'eloquent',
        'model' => App\Models\Customer::class,
    ],
    'internal-services' => [
        'driver' => 'eloquent',
        'model' => App\Models\ServiceAccount::class,
    ],
];
```

### Role-Based Access Control (RBAC)

Create roles and permissions:

```php
// Middleware for checking permissions
Route::middleware(['auth', 'role:admin,manager'])->group(function () {
    Route::resource('inventory/products', ProductController::class);
});

// In controller
if (auth()->user()->cannot('manage-inventory')) {
    abort(403);
}
```

---

## Inventory Management System

### Database Schema

**`app/Modules/Inventory/Migrations/create_products_table.php`**:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->longText('description')->nullable();
            $table->decimal('cost_price', 10, 2);
            $table->decimal('selling_price', 10, 2);
            $table->integer('quantity_available')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->integer('reorder_level')->default(10);
            $table->integer('reorder_quantity')->default(50);
            $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');
            $table->string('category')->nullable();
            $table->string('supplier')->nullable();
            $table->timestamps();
            
            $table->index('sku');
            $table->index('category');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

**`app/Modules/Inventory/Migrations/create_stock_movements_table.php`**:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->enum('type', ['in', 'out', 'adjustment', 'return', 'damage']);
            $table->integer('quantity');
            $table->text('reason')->nullable();
            $table->string('reference')->nullable(); // Order ID, etc.
            $table->integer('performed_by')->nullable();
            $table->timestamps();
            
            $table->index('product_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
```

### Models

**`app/Modules/Inventory/Models/Product.php`**:

```php
<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'description',
        'cost_price',
        'selling_price',
        'quantity_available',
        'reorder_level',
        'reorder_quantity',
        'status',
        'category',
        'supplier',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->quantity_available <= $this->reorder_level;
    }

    public function getAvailableQuantity(): int
    {
        return $this->quantity_available - $this->quantity_reserved;
    }

    public function canFulfillOrder(int $quantity): bool
    {
        return $this->getAvailableQuantity() >= $quantity;
    }

    public function recordMovement(string $type, int $quantity, string $reason = null, $reference = null)
    {
        StockMovement::create([
            'product_id' => $this->id,
            'type' => $type,
            'quantity' => $quantity,
            'reason' => $reason,
            'reference' => $reference,
            'performed_by' => auth()->id(),
        ]);

        // Update quantities
        if ($type === 'in') {
            $this->quantity_available += $quantity;
        } elseif ($type === 'out') {
            $this->quantity_available -= $quantity;
        }

        $this->save();

        // Check if low stock
        if ($this->isLowStock()) {
            event(new Events\StockLow($this->id, $this->quantity_available, $this->reorder_level));
        }
    }
}
```

### Inventory Service

**`app/Modules/Inventory/Services/InventoryService.php`**:

```php
<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class InventoryService
{
    public function getProduct(int $id): ?Product
    {
        return Product::find($id);
    }

    public function checkAvailability(int $productId, int $quantity): bool
    {
        $product = $this->getProduct($productId);
        return $product ? $product->canFulfillOrder($quantity) : false;
    }

    public function reserveStock(int $productId, int $quantity): bool
    {
        $product = $this->getProduct($productId);
        
        if (!$product || !$this->checkAvailability($productId, $quantity)) {
            return false;
        }

        $product->update([
            'quantity_reserved' => $product->quantity_reserved + $quantity,
        ]);

        return true;
    }

    public function releaseStock(int $productId, int $quantity): bool
    {
        $product = $this->getProduct($productId);
        
        if (!$product) {
            return false;
        }

        $product->update([
            'quantity_reserved' => max(0, $product->quantity_reserved - $quantity),
        ]);

        return true;
    }

    public function getLowStockProducts(): Collection
    {
        return Product::where('quantity_available', '<=', \DB::raw('reorder_level'))
                     ->where('status', 'active')
                     ->get();
    }

    public function getInventoryValue(): float
    {
        return Product::query()
            ->selectRaw('SUM(quantity_available * cost_price) as total_value')
            ->first()
            ->total_value ?? 0;
    }

    public function generateReport(string $category = null): array
    {
        $query = Product::where('status', 'active');
        
        if ($category) {
            $query->where('category', $category);
        }

        $products = $query->get();

        return [
            'total_items' => $products->sum('quantity_available'),
            'total_value' => $products->sum(fn($p) => $p->quantity_available * $p->cost_price),
            'low_stock_count' => $products->filter(fn($p) => $p->isLowStock())->count(),
            'products' => $products,
        ];
    }
}
```

---

## Customer-Seller Web Chat

### Database Schema

**`app/Modules/Chat/Migrations/create_conversations_table.php`**:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('seller_id')->nullable()->constrained('users');
            $table->string('subject')->nullable();
            $table->enum('status', ['open', 'closed', 'pending'])->default('open');
            $table->integer('unread_customer')->default(0);
            $table->integer('unread_seller')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            
            $table->index('customer_id');
            $table->index('seller_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
```

**`app/Modules/Chat/Migrations/create_messages_table.php`**:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->morphs('sender'); // Polymorphic: User or Customer
            $table->longText('body');
            $table->string('attachment_path')->nullable();
            $table->enum('type', ['text', 'image', 'file', 'system'])->default('text');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index('conversation_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
```

### Models

**`app/Modules/Chat/Models/Conversation.php`**:

```php
<?php

namespace App\Modules\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conversation extends Model
{
    protected $fillable = [
        'customer_id',
        'seller_id',
        'subject',
        'status',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'seller_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    public function addMessage($sender, string $body, ?string $attachmentPath = null): Message
    {
        $message = $this->messages()->create([
            'sender_id' => $sender->id,
            'sender_type' => $sender::class,
            'body' => $body,
            'attachment_path' => $attachmentPath,
        ]);

        // Update last message time
        $this->update(['last_message_at' => now()]);

        // Broadcast message
        broadcast(new Events\MessageSent($message, $this));

        return $message;
    }

    public function markAsRead($by = null)
    {
        if ($by instanceof \App\Models\User) {
            $this->update(['unread_seller' => 0]);
        } else {
            $this->update(['unread_customer' => 0]);
        }

        $this->messages()
             ->where('read_at', null)
             ->whereNot('sender_type', get_class($by))
             ->update(['read_at' => now()]);
    }
}
```

**`app/Modules/Chat/Models/Message.php`**:

```php
<?php

namespace App\Modules\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender_type',
        'body',
        'attachment_path',
        'type',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->morphTo();
    }

    public function markAsRead()
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }
}
```

### Chat Service

**`app/Modules/Chat/Services/ChatService.php`**:

```php
<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Models\Customer;
use App\Models\User;

class ChatService
{
    public function startConversation(Customer $customer, ?User $seller = null, string $subject = null): Conversation
    {
        return Conversation::create([
            'customer_id' => $customer->id,
            'seller_id' => $seller?->id,
            'subject' => $subject,
            'status' => 'open',
        ]);
    }

    public function getOrCreateConversation(Customer $customer, ?User $seller = null): Conversation
    {
        return Conversation::where('customer_id', $customer->id)
                          ->where('status', '!=', 'closed')
                          ->first() ?? $this->startConversation($customer, $seller);
    }

    public function sendMessage(Conversation $conversation, $sender, string $body, ?string $attachmentPath = null): Message
    {
        $message = $conversation->addMessage($sender, $body, $attachmentPath);

        // Update unread count
        if ($sender instanceof User) {
            $conversation->update(['unread_customer' => $conversation->unread_customer + 1]);
        } else {
            $conversation->update(['unread_seller' => $conversation->unread_seller + 1]);
        }

        return $message;
    }

    public function getUnreadCount($user = null): int
    {
        if ($user instanceof User) {
            return Conversation::where('status', 'open')->sum('unread_seller');
        }

        return Conversation::where('customer_id', $user->id)
                          ->where('status', 'open')
                          ->sum('unread_customer');
    }

    public function closeConversation(Conversation $conversation): void
    {
        $conversation->update(['status' => 'closed']);
    }

    public function assignSeller(Conversation $conversation, User $seller): void
    {
        $conversation->update(['seller_id' => $seller->id]);
    }
}
```

### Chat Events

**`app/Modules/Chat/Events/MessageSent.php`**:

```php
<?php

namespace App\Modules\Chat\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public $message,
        public $conversation
    ) {}

    public function broadcastOn()
    {
        return new Channel("conversation.{$this->conversation->id}");
    }

    public function broadcastAs()
    {
        return 'message-sent';
    }
}
```

---

## Integrated Email System

### Database Schema

**`app/Modules/Email/Migrations/create_emails_table.php`**:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->string('from_address');
            $table->string('to_address');
            $table->longText('body');
            $table->enum('status', ['pending', 'sent', 'failed', 'bounce'])->default('pending');
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->morphs('related'); // Link to any model
            $table->timestamps();
            
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
```

**`app/Modules/Email/Migrations/create_email_templates_table.php`**:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('subject');
            $table->longText('body');
            $table->json('variables')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
```

### Email Service

**`app/Modules/Email/Services/EmailService.php`**:

```php
<?php

namespace App\Modules\Email\Services;

use App\Modules\Email\Models\Email;
use App\Modules\Email\Models\EmailTemplate;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    public function sendEmail(
        string $to,
        string $subject,
        string $body,
        $relatedModel = null,
        array $attachments = []
    ): Email {
        $email = Email::create([
            'to_address' => $to,
            'from_address' => config('mail.from.address'),
            'subject' => $subject,
            'body' => $body,
            'related_id' => $relatedModel?->id,
            'related_type' => $relatedModel ? get_class($relatedModel) : null,
        ]);

        try {
            Mail::send('emails.generic', ['body' => $body], function ($message) use ($to, $subject, $attachments) {
                $message->to($to)->subject($subject);
                
                foreach ($attachments as $attachment) {
                    $message->attach($attachment);
                }
            });

            $email->update(['status' => 'sent']);
        } catch (\Exception $e) {
            $email->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $email;
    }

    public function sendFromTemplate(
        string $to,
        string $templateName,
        array $variables = [],
        $relatedModel = null
    ): Email {
        $template = EmailTemplate::where('name', $templateName)
                                ->where('status', 'active')
                                ->firstOrFail();

        $subject = $this->replaceVariables($template->subject, $variables);
        $body = $this->replaceVariables($template->body, $variables);

        return $this->sendEmail($to, $subject, $body, $relatedModel);
    }

    public function sendToAdmins(string $subject, string $templateName, array $variables = []): void
    {
        $admins = \App\Models\User::where('role', 'admin')->pluck('email');
        
        foreach ($admins as $admin) {
            $this->sendFromTemplate($admin, $templateName, $variables);
        }
    }

    public function sendToCustomer($customerId, string $subject, string $body): Email
    {
        $customer = \App\Models\Customer::findOrFail($customerId);
        return $this->sendEmail($customer->email, $subject, $body);
    }

    public function retryFailed(): void
    {
        Email::where('status', 'failed')
             ->where('retry_count', '<', 3)
             ->get()
             ->each(function ($email) {
                 try {
                     Mail::send('emails.generic', ['body' => $email->body], function ($message) use ($email) {
                         $message->to($email->to_address)->subject($email->subject);
                     });

                     $email->update([
                         'status' => 'sent',
                         'retry_count' => $email->retry_count + 1,
                     ]);
                 } catch (\Exception $e) {
                     $email->update([
                         'retry_count' => $email->retry_count + 1,
                         'error_message' => $e->getMessage(),
                     ]);
                 }
             });
    }

    private function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace("{{ $key }}", $value, $content);
        }

        return $content;
    }
}
```

### Email Models

**`app/Modules/Email/Models/Email.php`**:

```php
<?php

namespace App\Modules\Email\Models;

use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    protected $fillable = [
        'to_address',
        'from_address',
        'subject',
        'body',
        'status',
        'retry_count',
        'error_message',
        'related_id',
        'related_type',
    ];

    public function related()
    {
        return $this->morphTo();
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
```

---

## Cross-Service Events

### Event Flow Example

**Scenario: Customer places order → Inventory reserved → Email sent → Chat notification**

1. **Order Created** (Orders Module):
   ```php
   event(new Events\OrderCreated($order));
   ```

2. **Inventory Listener** reserves stock:
   ```php
   class ReserveInventory
   {
       public function handle(OrderCreated $event)
       {
           $inventoryService = app('inventory.service');
           $inventoryService->reserveStock($event->order->product_id, $event->order->quantity);
       }
   }
   ```

3. **Email Listener** sends confirmation:
   ```php
   class SendOrderConfirmation
   {
       public function handle(OrderCreated $event)
       {
           $emailService = app('email.service');
           $emailService->sendFromTemplate(
               $event->order->customer->email,
               'order.confirmation',
               ['order' => $event->order],
               $event->order
           );
       }
   }
   ```

4. **Chat Listener** creates conversation:
   ```php
   class CreateOrderConversation
   {
       public function handle(OrderCreated $event)
       {
           $chatService = app('chat.service');
           $chatService->startConversation(
               $event->order->customer,
               null,
               "Order #{$event->order->id} Discussion"
           );
       }
   }
   ```

---

## Database Design

### Relationships Overview

```
User (Admin/Seller)
├── Projects (Task Manager)
├── Messages (Chat)
├── Emails (Email Service)
└── StockMovements (Inventory)

Customer
├── Orders
├── Conversations (Chat)
├── Messages (Chat)
└── Email History

Product
├── Stock (Inventory)
├── StockMovements (Inventory)
└── OrderItems

Conversation (Chat)
├── Messages
├── Customer
└── Seller (User)

Email
└── Related (polymorphic)
```

### Migration Registration

In each module's service provider:

```php
public function boot()
{
    $this->loadMigrationsFrom(__DIR__ . '/Migrations');
}
```

---

## Deployment Considerations

### 1. Environment Variables

Add to `.env`:

```env
# Modules
MODULES_ENABLED=true
MODULES_INVENTORY_ENABLED=true
MODULES_CHAT_ENABLED=true
MODULES_EMAIL_ENABLED=true

# Chat WebSocket (if using Laravel Echo)
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=xxx
PUSHER_APP_KEY=xxx
PUSHER_APP_SECRET=xxx

# Email
MAIL_FROM_ADDRESS=noreply@novocib.com
MAIL_FROM_NAME=Novocib
```

### 2. Queue Jobs

For resource-intensive operations:

**`app/Modules/Email/Jobs/SendEmailJob.php`**:

```php
<?php

namespace App\Modules\Email\Jobs;

use App\Modules\Email\Models\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Email $email) {}

    public function handle()
    {
        $emailService = app('email.service');
        $emailService->retryFailed();
    }
}
```

### 3. Cron Jobs

In `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Retry failed emails every 30 minutes
    $schedule->job(new \App\Modules\Email\Jobs\SendEmailJob)
             ->everyThirtyMinutes();

    // Check low inventory daily
    $schedule->command('inventory:check-low-stock')
             ->daily();

    // Archive old chat conversations
    $schedule->command('chat:archive-old-conversations')
             ->weekly();
}
```

### 4. Commands

**`app/Modules/Inventory/Commands/CheckLowStockCommand.php`**:

```php
<?php

namespace App\Modules\Inventory\Commands;

use Illuminate\Console\Command;
use App\Modules\Inventory\Services\InventoryService;

class CheckLowStockCommand extends Command
{
    protected $signature = 'inventory:check-low-stock';
    protected $description = 'Check for low stock items and notify admins';

    public function handle()
    {
        $inventoryService = app('inventory.service');
        $lowStockProducts = $inventoryService->getLowStockProducts();

        foreach ($lowStockProducts as $product) {
            event(new \App\Modules\Inventory\Events\StockLow(
                $product->id,
                $product->quantity_available,
                $product->reorder_level
            ));
        }

        $this->info("Checked {$lowStockProducts->count()} products");
    }
}
```

### 5. Testing Multiple Modules

**`tests/Feature/CrossModuleTest.php`**:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class CrossModuleTest extends TestCase
{
    public function test_order_triggers_inventory_email_and_chat()
    {
        $customer = \App\Models\Customer::factory()->create();
        $product = \App\Modules\Inventory\Models\Product::factory()->create([
            'quantity_available' => 100,
        ]);

        $order = \App\Models\Order::factory()->create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        // Check inventory was reserved
        $this->assertEquals(90, $product->refresh()->quantity_available);

        // Check email was sent
        $this->assertTrue(\App\Modules\Email\Models\Email::where('to_address', $customer->email)->exists());

        // Check conversation was created
        $this->assertTrue(\App\Modules\Chat\Models\Conversation::where('customer_id', $customer->id)->exists());
    }
}
```

---

## Implementation Roadmap

### Phase 1: Foundation (Week 1-2)
- [ ] Set up module structure
- [ ] Create service providers
- [ ] Implement service registry

### Phase 2: Inventory (Week 2-3)
- [ ] Database migrations
- [ ] Models and repositories
- [ ] Admin controllers and views
- [ ] Inventory service

### Phase 3: Chat (Week 3-4)
- [ ] Database schema
- [ ] Chat models
- [ ] Chat controllers
- [ ] WebSocket integration

### Phase 4: Email (Week 4-5)
- [ ] Email templates
- [ ] Email service
- [ ] Queue integration
- [ ] Retry mechanism

### Phase 5: Integration (Week 5-6)
- [ ] Cross-module events
- [ ] API endpoints
- [ ] Testing
- [ ] Documentation

### Phase 6: Deployment (Week 6)
- [ ] Environment setup
- [ ] Database migrations
- [ ] Queue workers
- [ ] Cron jobs

---

## Best Practices

### ✅ Do's

- Use events for loose coupling
- Keep services focused and single-responsibility
- Version your APIs
- Log all cross-service calls
- Use queue jobs for heavy operations
- Implement rate limiting
- Cache frequently accessed data
- Write comprehensive tests

### ❌ Don'ts

- Direct database access between modules
- Circular dependencies
- Synchronous operations for non-critical tasks
- Store sensitive data in logs
- Skip error handling
- Hardcode module configurations
- Ignore backward compatibility

---

## Monitoring & Logging

### Centralized Logging

In `config/logging.php`:

```php
'channels' => [
    'modules' => [
        'driver' => 'daily',
        'path' => storage_path('logs/modules.log'),
        'level' => 'info',
        'days' => 30,
    ],
],
```

### Usage

```php
\Log::channel('modules')->info('Inventory reserved', [
    'product_id' => $productId,
    'quantity' => $quantity,
    'user_id' => auth()->id(),
]);
```

---

## Summary

Your Laravel application can now support multiple integrated mini-apps:

✅ **Modular Architecture** - Each service is independent and maintainable  
✅ **Event-Driven** - Services communicate through well-defined events  
✅ **Scalable** - Easy to add new modules without affecting existing ones  
✅ **Testable** - Each module can be tested in isolation  
✅ **Admin Ready** - Dashboard for managing all services  
✅ **Customer Facing** - Chat and order management interfaces  
✅ **Automated** - Email, inventory, and notifications handled automatically  

This architecture provides the foundation for unlimited future expansion!
