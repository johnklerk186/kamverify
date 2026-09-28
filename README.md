# KamVerify Platform

A comprehensive virtual phone number and SMS verification platform built with Laravel. KamVerify allows customers to purchase temporary phone numbers from multiple countries for SMS verification workflows on popular services like WhatsApp, Facebook, Telegram, and more.

## Features

### Customer Features
- **User Authentication**: Registration, login, password reset, email verification
- **Customer Dashboard**: Real-time overview of balance, orders, and activity
- **Number Purchase Flow**: Select country → Choose service → Purchase number → Receive SMS
- **Order Management**: View active orders, order history, SMS retrieval
- **Wallet System**: Deposit funds, view transactions, automatic refunds
- **Support System**: Create and manage support tickets
- **Notifications**: Real-time alerts for deposits, orders, SMS, and refunds

### Admin Features
- **Admin Dashboard**: Platform statistics, recent orders, transactions, and users
- **User Management**: View, activate/deactivate customers, view wallet and order history
- **Order Management**: Monitor all orders, manual interventions when needed
- **Financial Overview**: Revenue tracking, profit analysis, deposit management
- **Provider Integration**: HeroSMS integration with fallback to mock provider

### Technical Features
- **Provider Abstraction**: Clean separation between application and SMS providers
- **Mock Provider**: Development mode for testing without real API calls
- **Queue System**: Background job processing for SMS checking and order expiration
- **Payment Abstraction**: Support for multiple payment providers (Stripe, PayPal, Coinbase)
- **Security**: CSRF protection, input validation, authorization policies
- **Responsive Design**: Mobile-friendly interface with Tailwind CSS

## Technology Stack

- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: Blade templates, Tailwind CSS, JavaScript
- **Database**: MySQL with Eloquent ORM
- **Queues**: Laravel Queue with Redis support
- **Cache**: Redis caching support
- **SMS Provider**: HeroSMS with mock fallback

## Installation

### Prerequisites
- PHP 8.2 or higher
- Composer
- MySQL 5.7+ or SQLite
- Node.js 18+ and NPM
- Redis (optional, recommended for production)

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd kamverify.com
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   npm run build
   ```

3. **Environment configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. **Start development server**
   ```bash
   php artisan serve
   npm run dev
   ```

6. **Start queue worker** (in separate terminal)
   ```bash
   php artisan queue:work
   ```

## Configuration

### HeroSMS Integration

To use the real HeroSMS API, configure the following environment variables:

```env
HERO_SMS_BASE_URL=https://api.herosms.com
HERO_SMS_API_KEY=your_api_key_here
HERO_SMS_USE_MOCK=false
```

For development, use the mock provider:
```env
HERO_SMS_USE_MOCK=true
```

### Payment Integration

Configure payment providers in `.env`:

```env
# Stripe
STRIPE_API_KEY=pk_test_xxxxx
STRIPE_SECRET_KEY=sk_test_xxxxx

# PayPal
PAYPAL_CLIENT_ID=your_client_id
PAYPAL_SECRET=your_secret
PAYPAL_MODE=sandbox

# Coinbase
COINBASE_API_KEY=your_api_key
COINBASE_API_SECRET=your_api_secret
```

## Default Users

After running database seeders, the following users are created:

**Admin User:**
- Email: admin@kamverify.com
- Password: password
- Role: Admin

**Test Customer:**
- Email: customer@kamverify.com
- Password: password
- Role: Customer
- Wallet Balance: $100.00

## Project Structure

```
kamverify.com/
├── app/
│   ├── Http/
│   │   ├── Controllers/       # Application controllers
│   │   ├── Middleware/        # Custom middleware
│   │   └── Requests/          # Form request validation
│   ├── Models/                # Eloquent models
│   ├── Services/              # Business logic services
│   │   ├── Payments/          # Payment provider abstractions
│   │   └── Providers/         # SMS provider abstractions
│   ├── Jobs/                  # Background jobs
│   ├── Notifications/         # Notification classes
│   └── Policies/              # Authorization policies
├── database/
│   ├── migrations/            # Database migrations
│   └── seeders/               # Database seeders
├── resources/
│   └── views/                 # Blade templates
├── routes/                    # Application routes
└── config/                    # Configuration files
```

## Key Services

### OrderService
Handles order creation, status updates, cancellation, and refunds.

### WalletService
Manages wallet operations including deposits, withdrawals, and balance checking.

### PaymentService
Abstracts payment provider integration with support for multiple payment methods.

### ProviderService
Manages SMS provider integration with HeroSMS and mock provider fallback.

### SmsService
Handles SMS message processing and OTP code extraction.

## Development

### Running Tests

```bash
php artisan test
```

### Code Style

```bash
php artisan pint
```

### Queue Worker

For development, run the queue worker in a separate terminal:

```bash
php artisan queue:work
```

### Task Scheduling

The Laravel scheduler handles order expiration checks. In production, add to crontab:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Deployment

For detailed deployment instructions, see [DEPLOYMENT.md](DEPLOYMENT.md).

## Security

- CSRF protection enabled on all forms
- Input validation using Form Requests
- Authorization policies for sensitive operations
- Secure password hashing
- Environment variable protection
- SQL injection prevention via Eloquent ORM
- XSS protection via Blade templating

## Support

For technical support and documentation:
- Email: support@kamverify.com
- Documentation: See [DEPLOYMENT.md](DEPLOYMENT.md)
- Issues: GitHub issue tracker

## License

This platform is proprietary software. All rights reserved.

## Credits

Built with Laravel 11 and modern web technologies.