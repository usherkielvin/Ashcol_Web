# Database Setup Instructions for ashcol_portal

## Error: Table 'ashcol_portal.users' doesn't exist

This error occurs when the database exists but the tables haven't been created yet.

## Solution Options

### Option 1: Run SQL Script (Quick Fix)

1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Select the `ashcol_portal` database (or create it if it doesn't exist)
3. Click on the "SQL" tab
4. Copy and paste the contents of `database/ashcol_portal_setup.sql`
5. Click "Go" to execute the script
6. All tables will be created

### Option 2: Run Laravel Migrations (Recommended)

This is the recommended approach as it ensures migrations are tracked properly.

1. **Ensure your `.env` file is configured:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ashcol_portal
   DB_USERNAME=root
   DB_PASSWORD=
   ```

2. **Run migrations:**
   ```bash
   cd C:\xampp\htdocs\Ashcol_Web
   php artisan migrate
   ```

3. **If you want to seed default data:**
   ```bash
   php artisan migrate --seed
   ```

### Option 3: Fresh Migration (If you want to start over)

⚠️ **WARNING:** This will drop all existing tables and recreate them!

```bash
php artisan migrate:fresh --seed
```

## Verify Database Connection

After setting up, test the connection:

1. Run: `php artisan migrate:status` to check migration status
2. Or test the connection by accessing the application: `http://localhost/ashcol_portal/public/`

## Troubleshooting

### If you get MySQL Error #1813 (Tablespace exists):
This error means there's a corrupted or leftover tablespace file. The updated `ashcol_portal_setup.sql` script now automatically drops all tables first to fix this.

**Quick fix:**
1. Run the updated `ashcol_portal_setup.sql` script (it now drops tables first)
2. Or run `database/fix_tablespace_error.sql` for just the users table

### If you get "Access denied" error:
- Check MySQL is running in XAMPP
- Verify username/password in `.env` file
- Default XAMPP MySQL: username=`root`, password=`` (empty)

### If database doesn't exist:
- Create it manually in phpMyAdmin, or
- The SQL script will create it automatically

### If tables still don't exist after running migrations:
- Check Laravel logs: `storage/logs/laravel.log`
- Verify database connection in `.env`
- Make sure you're using the correct database name: `ashcol_portal`

