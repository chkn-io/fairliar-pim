# Shopify Order Exporter

A Laravel application that displays Shopify orders in a paginated table using GraphQL API.

## Features

- 📋 **Paginated Order Listing** - View orders with customizable pagination (10-50 per page)
- 🔍 **Advanced Search** - Filter orders using Shopify's powerful query syntax
- 👁️ **Detailed Order View** - Complete order information including line items and fulfillment status
- 📱 **Responsive Design** - Works perfectly on desktop and mobile devices
- 🚀 **Real-time Data** - Fetches live data directly from your Shopify store
- 🎨 **Modern UI** - Clean Bootstrap-based interface

## Installation

1. **Clone and Install Dependencies**
   ```bash
   cd order-exporter
   composer install
   ```

2. **Configure Environment**
   
   Update your `.env` file with your Shopify store details:
   ```env
   SHOPIFY_API_KEY=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
   SHOPIFY_STORE_DOMAIN=your-store.myshopify.com
   SHOPIFY_API_VERSION=2025-10
   ```

3. **Test Your Connection**
   ```bash
   php artisan shopify:test
   ```

4. **Start the Development Server**
   ```bash
   php artisan serve
   ```

5. **Visit Your Application**
   Open http://localhost:8000 in your browser

## Usage

### Order Listing
- Navigate to `/orders` to view all orders
- Use pagination controls to browse through orders
- Adjust items per page (10, 20, 30, or 50)

### Search and Filtering
Use Shopify's query syntax to filter orders:

- `fulfillment_status:unfulfilled` - Show unfulfilled orders
- `financial_status:paid` - Show paid orders  
- `created_at:>2025-10-01` - Orders after specific date
- `tag:urgent` - Orders with specific tags
- `customer.email:john@example.com` - Orders by customer email

### Order Details
Click "View" on any order to see:
- Complete order information
- Customer details and shipping address
- All line items with SKUs and pricing
- Fulfillment status and location assignments

## Testing Commands

```bash
# Test Shopify connection
php artisan shopify:test

# Test with more orders
php artisan shopify:test --limit=10

# Start development server
php artisan serve
```

## Configuration

### Environment Variables
- `SHOPIFY_API_KEY` - Your Shopify Admin API access token
- `SHOPIFY_STORE_DOMAIN` - Your store domain (e.g., `your-store.myshopify.com`)
- `SHOPIFY_API_VERSION` - API version (default: `2025-10`)

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
