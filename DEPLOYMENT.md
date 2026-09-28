# KamVerify Platform - Deployment Guide

## Overview

KamVerify is a Laravel-based virtual phone number and SMS verification platform. This guide provides comprehensive instructions for deploying the platform to production.

## Prerequisites

- PHP 8.2 or higher
- Composer
- MySQL 5.7+ or MariaDB 10.3+
- Node.js 18+ and NPM
- Redis (optional, for caching and queues)
- Web server (Apache/Nginx)
- SSL certificate (recommended for production)

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd kamverify.com
```

### 2. Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
npm install --production
npm run build
```

### 3. Environment Configuration

Copy the example environment file:

```bash
cp .env.example .env
```

Configure the following environment variables:

```env
APP_NAME=KamVerify
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kamverify
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# HeroSMS Provider Configuration
HERO_SMS_MODE=production
HERO_SMS_BASE_URL=https://hero-sms.com/stubs/handler_api.php
HERO_SMS_API_KEY=your_herosms_api_key
HERO_SMS_RESELLER_ENABLED=false
HERO_SMS_RESELLER_PARAM=userId

# Payment Configuration (Future Implementation)
STRIPE_API_KEY=your_stripe_key
STRIPE_SECRET_KEY=your_stripe_secret
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_SECRET=your_paypal_secret

# Queue Configuration
QUEUE_CONNECTION=redis

# Cache Configuration
CACHE_STORE=redis

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mail_username
MAIL_PASSWORD=your_mail_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@kamverify.com
MAIL_FROM_NAME=KamVerify
```

### 4. Generate Application Key

```bash
php artisan key:generate
```

### 5. Run Database Migrations

```bash
php artisan migrate --force
```

### 6. Seed the Database

```bash
php artisan db:seed --force
```

### 7. Set Permissions

```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 8. Configure Web Server

#### Apache Configuration

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/kamverify.com/public

    <Directory /var/www/kamverify.com/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/kamverify_error.log
    CustomLog ${APACHE_LOG_DIR}/kamverify_access.log combined
</VirtualHost>
```

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/kamverify.com/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 9. SSL Configuration (Recommended)

Use Let's Encrypt for free SSL certificates:

```bash
sudo certbot --apache -d your-domain.com
# or
sudo certbot --nginx -d your-domain.com
```

## Background Services

### Queue Worker

Configure the queue worker to process background jobs:

```bash
php artisan queue:work --tries=3 --timeout=90
```

For production, use Supervisor to manage the queue worker:

```ini
[program:kamverify-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/kamverify.com/artisan queue:work --sleep=3 --tries=3 --timeout=90
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/kamverify.com/storage/logs/queue-worker.log
stopwaitsecs=3600
```

### Task Scheduling

Configure the Laravel scheduler in your crontab:

```bash
* * * * * cd /var/www/kamverify.com && php artisan schedule:run >> /dev/null 2>&1
```

## HeroSMS Integration

### API Configuration

1. Sign up for a HeroSMS account
2. Obtain your API key from the dashboard
3. Configure the following environment variables:

```env
HERO_SMS_MODE=production
HERO_SMS_BASE_URL=https://hero-sms.com/stubs/handler_api.php
HERO_SMS_API_KEY=your_api_key_here
```

### Protocol

HeroSMS uses an SMS-Activate-compatible protocol: every call is a GET to
`handler_api.php` with `api_key` + `action` + parameters. Implemented
actions:

- `getBalance` — account balance (`ACCESS_BALANCE:x`)
- `getCountries` — country catalog (numeric IDs)
- `getServicesList` — service catalog (short codes like `wa`, `tg`)
- `getPrices` — availability + cost per country/service
- `getNumberV2` — purchase (fallback `getNumber`); sends `maxPrice` ceiling
  and the reseller buyer ID when `HERO_SMS_RESELLER_ENABLED=true`
- `getStatus` — poll activation (`STATUS_WAIT_CODE` / `STATUS_OK:code`)
- `getAllSms` — full SMS list once a code arrives
- `setStatus` — `1` mark ready, `8` cancel
- `getActiveActivations` — admin diagnostics

Rate limit: 50 RPS per account (self-throttled to 40). Purchases are
never retried — a retry could double-buy.

### Country/service mappings

KamVerify countries use ISO codes; HeroSMS uses numeric country IDs.
Set `provider_country_code` to the HeroSMS numeric ID and
`provider_service_code` to the HeroSMS service code via
Admin → Providers → Edit. The "Live provider catalog" section on that
page lists the real IDs when production mode is active.

### Testing the Integration

Development uses mock mode (no real API calls):

```env
HERO_SMS_MODE=mock
```

## Payment Integration

### Stripe Integration

1. Create a Stripe account
2. Get API keys from Stripe Dashboard
3. Configure environment variables:

```env
STRIPE_API_KEY=pk_test_xxxxx
STRIPE_SECRET_KEY=sk_test_xxxxx
```

### PayPal Integration

1. Create a PayPal Developer account
2. Create a REST API app
3. Configure environment variables:

```env
PAYPAL_CLIENT_ID=your_client_id
PAYPAL_SECRET=your_secret
PAYPAL_MODE=sandbox
```

### Coinbase Integration

1. Create a Coinbase Commerce account
2. Get API credentials
3. Configure environment variables:

```env
COINBASE_API_KEY=your_api_key
COINBASE_API_SECRET=your_api_secret
```

## Security Configuration

### 1. Firewall Rules

- Allow only necessary ports (80, 443, 22)
- Restrict database access to localhost
- Use fail2ban for brute force protection

### 2. Application Security

- Ensure `APP_DEBUG=false` in production
- Use strong database passwords
- Rotate application keys regularly
- Keep dependencies updated

### 3. File Permissions

```bash
chmod 600 .env
chmod 644 storage/logs/*.log
```

## Monitoring and Logging

### Application Logs

Logs are stored in `storage/logs/laravel.log`

Monitor for:
- Application errors
- Failed jobs
- API provider errors
- Security events

### Health Checks

Configure health monitoring:

```bash
php artisan up
php artisan down
```

## Backup Strategy

### Database Backups

```bash
# Daily backup
mysqldump -u username -p kamverify > backup_$(date +%Y%m%d).sql

# Restore
mysql -u username -p kamverify < backup_20240101.sql
```

### File Backups

Backup the following directories:
- `storage/app`
- `storage/framework`
- `.env`

## Performance Optimization

### 1. Opcache

Enable PHP OPcache in `php.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
```

### 2. Redis Caching

Configure Redis for caching:

```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### 3. CDN Configuration

Configure CDN for static assets in `vite.config.js`.

## Troubleshooting

### Common Issues

1. **Permission Denied Errors**
   ```bash
   chmod -R 755 storage bootstrap/cache
   ```

2. **Database Connection Errors**
   - Check database credentials in `.env`
   - Ensure MySQL service is running
   - Verify database exists

3. **Queue Not Processing**
   - Check queue worker is running
   - Verify Redis connection
   - Check queue logs

4. **HeroSMS API Errors**
   - Verify API credentials
   - Check network connectivity
   - Review API rate limits

## Maintenance

### Regular Tasks

1. **Weekly**
   - Check application logs
   - Monitor disk space
   - Review failed jobs

2. **Monthly**
   - Update dependencies
   - Review security patches
   - Test backup restoration

3. **Quarterly**
   - Security audit
   - Performance review
   - Disaster recovery testing

## Support

For technical support:
- Email: support@kamverify.com
- Documentation: https://docs.kamverify.com
- Issue Tracker: https://github.com/kamverify/platform/issues

## License

This platform is proprietary software. All rights reserved.