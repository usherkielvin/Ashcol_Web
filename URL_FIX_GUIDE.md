# URL Fix Guide for XAMPP

## Problem: URLs Not Working

When using XAMPP, you need to access Laravel through the `public` directory.

## Solution Options:

### Option 1: Access via Public Directory (Easiest)
Access your application using:
```
http://localhost/ashcol_portal/public/
```

Then routes will work:
- Login: `http://localhost/ashcol_portal/public/login`
- Register: `http://localhost/ashcol_portal/public/register`
- Dashboard: `http://localhost/ashcol_portal/public/dashboard`

### Option 2: Create Virtual Host (Recommended for Development)

1. Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
2. Add this configuration:
```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/ashcol_portal/public"
    ServerName ashcol.local
    <Directory "C:/xampp/htdocs/ashcol_portal/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

3. Open `C:\Windows\System32\drivers\etc\hosts` (as Administrator)
4. Add this line:
```
127.0.0.1    ashcol.local
```

5. Restart Apache in XAMPP
6. Access via: `http://ashcol.local`

### Option 3: Enable mod_rewrite in Apache

1. Open `C:\xampp\apache\conf\httpd.conf`
2. Find this line and uncomment it (remove the #):
```apache
LoadModule rewrite_module modules/mod_rewrite.so
```
3. Restart Apache

## Quick Test

1. Test the home page:
   Visit: `http://localhost/ashcol_portal/public/`
   You should see the Laravel welcome page

3. Test login:
   Visit: `http://localhost/ashcol_portal/public/login`
   You should see the login form

## Build Assets (CSS/JS)

If styles are not loading, run in terminal:
```bash
npm run build
```

Or for development with hot reloading:
```bash
npm run dev
```

## Common Issues:

1. **404 Error**: Make sure you're accessing via `/public/` directory
2. **Styles not loading**: Run `npm run build` to compile CSS/JS
3. **mod_rewrite not working**: Enable it in Apache config (see Option 3)
4. **Permission denied**: Make sure Apache has read access to the directory

