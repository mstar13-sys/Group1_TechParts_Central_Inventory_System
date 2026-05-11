# TechParts POS — Setup & Documentation

## 📁 Project Structure

```
techparts/
├── database.sql              ← Full schema + seed data (run this first)
├── login.php                 ← Login page
├── logout.php
├── dashboard.php             ← Overview dashboard
├── unauthorized.php
├── includes/
│   ├── config.php            ← DB connection + session helpers
│   ├── header.php            ← Sidebar nav + top bar
│   └── footer.php
├── css/
│   └── style.css             ← Industrial dark theme
├── js/
│   └── app.js                ← Shared JS (clock, modals, alerts)
└── pages/
    ├── cashier.php           ← POS / Cashier screen ⭐
    ├── transactions.php      ← Transaction history + void
    ├── products.php          ← Product CRUD
    ├── categories.php        ← Category CRUD
    ├── stock.php             ← Stock management + adjustments
    ├── suppliers.php         ← Supplier CRUD ⭐ NEW
    ├── purchase_orders.php   ← PO management + status updates
    ├── po_detail.php         ← PO detail AJAX partial
    ├── users.php             ← User management (Admin only)
    └── reports.php           ← Sales reports (Admin only)
```

---

## 🗄️ Database Setup

1. Open **phpMyAdmin** or MySQL CLI
2. Run `database.sql`
3. Done — all tables, triggers, and seed data are inserted

### Database Schema (Tables)

| Table                  | Purpose                                      |
|------------------------|----------------------------------------------|
| `User`                 | Login accounts (Admin/Cashier/User/Viewer)   |
| `Category`             | Product categories                           |
| `Product`              | Master product list                          |
| `Supplier`             | Supplier information                         |
| `Stock`                | Inventory quantities per product/supplier    |
| `PurchaseOrder`        | Purchase order headers                       |
| `PurchaseOrderItem`    | Line items inside a purchase order           |
| `Product_has_Supplier` | Many-to-many: which supplier sells what      |
| `Transaction`          | POS sale headers (cashier transactions) ⭐   |
| `SaleItem`             | Line items inside a transaction ⭐           |

### Database Triggers

| Trigger                           | When It Fires                            |
|-----------------------------------|------------------------------------------|
| `trg_deduct_stock_after_sale`     | After `SaleItem` INSERT → deducts stock  |
| `trg_restore_stock_on_void`       | After `Transaction` void → restores stock|
| `trg_add_stock_on_po_received`    | After PO set to `Received` → adds stock  |

---

## 🚀 Web Server Setup

### Using XAMPP / WAMP
1. Place the `techparts/` folder in `htdocs/` (XAMPP) or `www/` (WAMP)
2. Start Apache + MySQL
3. Import `database.sql` via phpMyAdmin
4. Edit `includes/config.php` with your DB credentials
5. Visit `http://localhost/techparts/login.php`

### Using PHP built-in server
```bash
cd techparts
php -S localhost:8000
# Visit http://localhost:8000/login.php
```

---

## 🔐 Demo Login Credentials

| Role    | Email                    | Password    |
|---------|--------------------------|-------------|
| Admin   | admin@techparts.com      | password123 |
| Cashier | maria@techparts.com      | password123 |
| Cashier | juan@techparts.com       | password123 |
| Viewer  | viewer@techparts.com     | password123 |

---

## 👤 Role Permissions

| Feature              | Admin | Cashier | User | Viewer |
|----------------------|-------|---------|------|--------|
| Dashboard            | ✅    | ✅      | ✅   | ✅     |
| POS / Cashier        | ✅    | ✅      | ❌   | ❌     |
| View Transactions    | ✅    | ✅      | ✅   | ✅     |
| Void Transactions    | ✅    | ✅      | ❌   | ❌     |
| Products (view)      | ✅    | ✅      | ✅   | ✅     |
| Products (edit)      | ✅    | ❌      | ✅   | ❌     |
| Stock (view)         | ✅    | ✅      | ✅   | ✅     |
| Stock (adjust)       | ✅    | ❌      | ✅   | ❌     |
| Suppliers            | ✅    | ✅      | ✅   | ✅     |
| Suppliers (edit)     | ✅    | ❌      | ✅   | ❌     |
| Purchase Orders      | ✅    | ✅      | ✅   | ✅     |
| Purchase Orders (edit)| ✅   | ❌      | ✅   | ❌     |
| Users                | ✅    | ❌      | ❌   | ❌     |
| Reports              | ✅    | ❌      | ❌   | ❌     |

---

## 💡 POS / Cashier Features

- **Product browser** with category tabs + live search filter
- **Stock-aware** — out-of-stock items are disabled, low-stock items are highlighted
- **Cart** with quantity controls (respects max stock)
- **Discount** percentage applied at checkout
- **Multiple payment methods**: Cash, Card, GCash, PayMaya, Other
- **Change calculator** for cash payments
- **Stock auto-deduction** via database trigger on checkout
- **Transaction voiding** restores stock automatically via trigger

---

## 📊 Reports

- Filter by custom date range, or quick-select Today / This Week / This Month
- Daily sales breakdown table
- Revenue by payment method (with visual bar)
- Top 10 products by units sold
- Cashier performance comparison

---

## 🔧 Configuration

Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'TechParts');
```

---

## 📦 Seed Data Summary

- **5 Users** (Admin, 2 Cashiers, User, Viewer)
- **10 Categories** (CPU, Motherboard, RAM, Storage, GPU, PSU, Cases, Peripherals, Networking, Cooling)
- **26 Products** (real PC parts with accurate pricing in PHP peso)
- **5 Suppliers** (Cebu-based fictional suppliers)
- **26 Stock records** with realistic quantities
- **5 Purchase Orders** (various statuses)
- **15 PO Items**
- **8 Sample Transactions** (completed POS sales)
- **12 Sale Items** across those transactions
